<?php

/**
 * Wavixa API Integration Test Suite - Phase 6 + 7 (Queue-aware)
 *
 * This script tests the full public API integration flow for any external website.
 * All outgoing messages are queued. The script runs the queue worker before verifying
 * OTPs so the code is actually sent and stored in the database.
 *
 * 1. Connection status check
 * 2. Send a plain text message
 * 3. Send an image message
 * 4. Send a file / document message
 * 5. Send a video message
 * 6. Send a voice / audio message
 * 7. Send a contact message
 * 8. Send a location message
 * 9. Send an OTP
 * 10. Process queue jobs
 * 11. Verify the OTP
 * 12. Send a templated message
 * 13. Send a custom OTP
 * 14-16. Negative / error cases
 * 17. Final status & usage stats
 *
 * Usage:
 *   php test_api_integration.php
 *   php test_api_integration.php YOUR_API_KEY
 */

// Configuration: pass API key as first argument, or set WAVIXA_API_KEY env variable
$apiKey = $_SERVER['argv'][1] ?? $_ENV['WAVIXA_API_KEY'] ?? getenv('WAVIXA_API_KEY') ?: null;
if (!$apiKey) {
    echo "Usage: php test_api_integration.php <api_key>   (or set WAVIXA_API_KEY)\n";
    exit(1);
}
$baseUrl = $_ENV['WAVIXA_BASE_URL'] ?? 'http://localhost:8000/api';
$testPhone = $_ENV['WAVIXA_TEST_PHONE'] ?? '+201024520482'; // WhatsApp connected number

$passed = 0;
$failed = 0;
$otpCode = null;
$otpId = null;

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                                                                            ║\n";
echo "║              Wavixa Public API Integration Test Suite                      ║\n";
echo "║                                                                            ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "API Key:    {$apiKey}\n";
echo "Base URL:   {$baseUrl}\n";
echo "Test Phone: {$testPhone}\n";
echo "\n";

function api_request(string $method, string $url, array $headers = [], array $body = []): array
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if (!empty($body)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['http_code' => 0, 'error' => $error, 'body' => null];
    }

    curl_close($ch);

    return [
        'http_code' => $httpCode,
        'body' => json_decode($response, true),
        'raw' => $response,
    ];
}

function test(string $name, callable $callback, bool $expectFailure = false): void
{
    global $passed, $failed;

    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "TEST: {$name}" . ($expectFailure ? " (Expected failure)" : "") . "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    try {
        $result = $callback();

        $body = $result['body'] ?? null;
        $success = $result['success'] ?? $body['success'] ?? false;

        if ($expectFailure) {
            if (!$success) {
                echo "✅ PASSED (failure as expected)\n";
                $passed++;
            } else {
                echo "❌ FAILED (expected failure but got success)\n";
                $failed++;
            }
        } else {
            if ($success) {
                echo "✅ PASSED\n";
                $passed++;
            } else {
                echo "❌ FAILED\n";
                $failed++;
            }
        }

        echo "Response:\n";
        print_r($body ?? $result['raw'] ?? $result);
    } catch (Exception $e) {
        if ($expectFailure) {
            echo "✅ PASSED (exception as expected)\n";
            $passed++;
        } else {
            echo "❌ ERROR: " . $e->getMessage() . "\n";
            $failed++;
        }
    }

    echo "\n";
}

$headers = [
    'Content-Type: application/json',
    'Accept: application/json',
    "X-API-Key: {$apiKey}",
];

// 1. Connection status
test('1. API Key Authentication & Status Check', function () use ($baseUrl, $headers) {
    return api_request('GET', "{$baseUrl}/public/status", $headers);
});

// 2. WhatsApp connection status
test('2. WhatsApp Connection Status', function () use ($baseUrl, $headers) {
    return api_request('GET', "{$baseUrl}/public/whatsapp/status", $headers);
});

// 3. Send a plain message
test('3. Send Plain Text Message', function () use ($baseUrl, $headers, $testPhone) {
    return api_request('POST', "{$baseUrl}/public/whatsapp/send-message", $headers, [
        'to' => $testPhone,
        'message' => "Hello from Wavixa API test! This is a plain text message sent at " . date('Y-m-d H:i:s') . ".",
    ]);
});

// Load media fixtures for media tests
$imageBase64 = file_exists(__DIR__ . '/test_image_base64.txt')
    ? file_get_contents(__DIR__ . '/test_image_base64.txt')
    : 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
$fileBase64 = file_exists(__DIR__ . '/test_file_base64.txt')
    ? file_get_contents(__DIR__ . '/test_file_base64.txt')
    : base64_encode('Hello, this is a test file from Wavixa API.');

// 4. Send an image
$imageCaption = 'Wavixa API image test';
test('4. Send Image Message', function () use ($baseUrl, $headers, $testPhone, $imageBase64, $imageCaption) {
    return api_request('POST', "{$baseUrl}/public/whatsapp/send-image", $headers, [
        'to' => $testPhone,
        'content' => $imageBase64,
        'caption' => $imageCaption,
        'file_name' => 'test_image.png',
        'mimetype' => 'image/png',
    ]);
});

// 5. Send a file / document
test('5. Send File / Document Message', function () use ($baseUrl, $headers, $testPhone, $fileBase64) {
    return api_request('POST', "{$baseUrl}/public/whatsapp/send-file", $headers, [
        'to' => $testPhone,
        'content' => $fileBase64,
        'file_name' => 'test_file.txt',
        'mimetype' => 'text/plain',
        'caption' => 'Wavixa API file test',
    ]);
});

// 6. Send a video (using the same tiny image base64 as a placeholder for API testing)
test('6. Send Video Message', function () use ($baseUrl, $headers, $testPhone, $imageBase64) {
    return api_request('POST', "{$baseUrl}/public/whatsapp/send-video", $headers, [
        'to' => $testPhone,
        'content' => $imageBase64,
        'caption' => 'Wavixa API video test',
        'file_name' => 'test_video.mp4',
        'mimetype' => 'video/mp4',
    ]);
});

// 7. Send a voice / audio message
$voiceBase64 = base64_encode('WAVIXA_VOICE_TEST');
test('7. Send Voice / Audio Message', function () use ($baseUrl, $headers, $testPhone, $voiceBase64) {
    return api_request('POST', "{$baseUrl}/public/whatsapp/send-voice", $headers, [
        'to' => $testPhone,
        'content' => $voiceBase64,
        'mimetype' => 'audio/mp4',
    ]);
});

// 8. Send a contact
test('8. Send Contact Message', function () use ($baseUrl, $headers, $testPhone) {
    return api_request('POST', "{$baseUrl}/public/whatsapp/send-contact", $headers, [
        'to' => $testPhone,
        'content' => [
            'display_name' => 'Wavixa Support',
            'phone' => '+201024520482',
        ],
    ]);
});

// 9. Send a location
test('9. Send Location Message', function () use ($baseUrl, $headers, $testPhone) {
    return api_request('POST', "{$baseUrl}/public/whatsapp/send-location", $headers, [
        'to' => $testPhone,
        'content' => [
            'latitude' => 30.0444,
            'longitude' => 31.2357,
            'name' => 'Cairo',
            'address' => 'Tahrir Square, Cairo, Egypt',
        ],
    ]);
});

// 10. Send an OTP
test('10. Send OTP Code', function () use ($baseUrl, $headers, $testPhone, &$otpCode, &$otpId) {
    $response = api_request('POST', "{$baseUrl}/public/whatsapp/send-otp", $headers, [
        'to' => $testPhone,
        'purpose' => 'login',
        'length' => 6,
        'expires_in_minutes' => 10,
        'template' => 'Your Wavixa verification code is: {code}. Valid for {expires_in_minutes} minutes.',
    ]);

    if ($response['body']['success'] ?? false) {
        $otpId = $response['body']['data']['otp_id'] ?? null;
    }

    return $response;
});

// 11. Process the queue so OTPs and messages are actually sent before verification
test('11. Process Queue Jobs', function () {
    echo "Processing queue jobs...\n";
    $output = [];
    $exitCode = 0;
    for ($i = 0; $i < 15; $i++) {
        exec('php ' . __DIR__ . '/artisan queue:work --once --sleep=1 --tries=3 --timeout=90', $output, $exitCode);
    }
    echo "Processed " . count($output) . " worker outputs.\n";
    return ['success' => true, 'body' => ['processed_outputs' => count($output)]];
});

// Bootstrap Laravel once for DB access (used to retrieve OTP codes for verification)
$laravelApp = null;
function getLaravelApp() {
    global $laravelApp;
    if ($laravelApp === null) {
        require_once __DIR__ . '/vendor/autoload.php';
        $laravelApp = require_once __DIR__ . '/bootstrap/app.php';
        $laravelApp->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    }
    return $laravelApp;
}

// 11. Verify OTP (auto-retrieve the latest code from DB, then call verify-otp API)
test('12. Verify OTP Code (Auto-Retrieve from DB)', function () use ($baseUrl, $headers, $testPhone) {
    getLaravelApp();

    $latestOtp = \App\Models\OtpCode::where('phone_number', $testPhone)
        ->whereNull('verified_at')
        ->where('expires_at', '>', now())
        ->latest()
        ->first();

    if (!$latestOtp) {
        return ['success' => false, 'body' => ['message' => 'No active OTP found in database']];
    }

    echo "Retrieved OTP code from DB: {$latestOtp->code}\n";

    return api_request('POST', "{$baseUrl}/public/whatsapp/verify-otp", $headers, [
        'phone' => $testPhone,
        'code' => $latestOtp->code,
        'purpose' => 'login',
    ]);
});

// 13. Send a template message
test('13. Send Templated Message (order_confirmation)', function () use ($baseUrl, $headers, $testPhone) {
    return api_request('POST', "{$baseUrl}/public/whatsapp/send-template", $headers, [
        'to' => $testPhone,
        'template' => 'order_confirmation',
        'variables' => [
            'name' => 'Ahmed',
            'order_id' => 'ORD-12345',
            'total' => '$250.00',
        ],
    ]);
});

// 14. Send an OTP with custom template
test('14. Send Custom Template OTP', function () use ($baseUrl, $headers, $testPhone) {
    return api_request('POST', "{$baseUrl}/public/whatsapp/send-otp", $headers, [
        'to' => $testPhone,
        'purpose' => 'payment',
        'length' => 4,
        'expires_in_minutes' => 5,
        'template' => 'Hi {purpose}, your Wavixa payment code is {code}. Expires in {expires_in_minutes} minutes.',
    ]);
});

// 15. Verify OTP with wrong code (should fail)
test('15. Verify OTP With Wrong Code (Expect Failure)', function () use ($baseUrl, $headers, $testPhone) {
    return api_request('POST', "{$baseUrl}/public/whatsapp/verify-otp", $headers, [
        'phone' => $testPhone,
        'code' => '000000',
        'purpose' => 'login',
    ]);
}, true);

// 16. Send message with invalid phone (should fail)
test('16. Send Message With Invalid Phone (Expect Validation Error)', function () use ($baseUrl, $headers) {
    return api_request('POST', "{$baseUrl}/public/whatsapp/send-message", $headers, [
        'to' => '123',
        'message' => 'This should fail',
    ]);
}, true);

// 17. Send message without API key (should fail)
test('17. Send Message Without API Key (Expect 401)', function () use ($baseUrl, $testPhone) {
    return api_request('POST', "{$baseUrl}/public/whatsapp/send-message", [
        'Content-Type: application/json',
        'Accept: application/json',
    ], [
        'to' => $testPhone,
        'message' => 'This should fail',
    ]);
}, true);

// 18. Final status check to see usage stats
test('18. Final Status & Usage Stats', function () use ($baseUrl, $headers) {
    return api_request('GET', "{$baseUrl}/public/status", $headers);
});

echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "                         TEST RESULTS SUMMARY                                   \n";
echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "\n";
echo "✅ Passed: {$passed}\n";
echo "❌ Failed: {$failed}\n";
echo "\n";

if ($failed === 0) {
    echo "🎉 All integration tests passed! Your API is ready for external websites. 🎉\n";
} else {
    echo "⚠️ Some tests failed. Review the output above for details.\n";
}

echo "\n";
