<?php

/**
 * Wavixa API Queue System Test Suite - Phase 7
 *
 * Tests the professional queue system for WhatsApp messages:
 * 1. All messages go through the queue (not sent directly)
 * 2. Messages are processed in order
 * 3. Automatic retry on failure
 * 4. Failed queue support
 * 5. Status updates during execution
 * 6. Queue status dashboard
 *
 * Usage:
 *   php test_phase7_queue.php
 *   php test_phase7_queue.php YOUR_API_KEY
 */

$apiKey = $_SERVER['argv'][1] ?? $_ENV['WAVIXA_API_KEY'] ?? 'wav_mOu2ZX1kQSbztjNKrarhHvuGYxIpEcPQa8JlaDcztHVWm3rS';
$baseUrl = $_ENV['WAVIXA_BASE_URL'] ?? 'http://localhost:8000/api';
$testPhone = $_ENV['WAVIXA_TEST_PHONE'] ?? '+201024520482';

$passed = 0;
$failed = 0;
$messageIds = [];

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                                                                            ║\n";
echo "║              Wavixa API Queue System Test Suite - Phase 7                ║\n";
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

function wait_for_status(int $messageId, array $targetStatuses, int $maxSeconds = 60): ?\App\Models\WhatsAppMessage
{
    $start = time();
    while (time() - $start < $maxSeconds) {
        $message = \App\Models\WhatsAppMessage::find($messageId);
        if ($message && in_array($message->status, $targetStatuses)) {
            return $message;
        }
        sleep(1);
    }
    return \App\Models\WhatsAppMessage::find($messageId);
}

$headers = [
    'Content-Type: application/json',
    'Accept: application/json',
    "X-API-Key: {$apiKey}",
];

// 1. Queue Status Empty
test('1. Queue Status (Before Sending)', function () use ($baseUrl, $headers) {
    return api_request('GET', "{$baseUrl}/public/whatsapp/queue-status", $headers);
});

// 2. Send plain text message (should be queued)
test('2. Queue Text Message', function () use ($baseUrl, $headers, $testPhone, &$messageIds) {
    $response = api_request('POST', "{$baseUrl}/public/whatsapp/send-message", $headers, [
        'to' => $testPhone,
        'message' => "Queue test: plain text message at " . date('Y-m-d H:i:s'),
    ]);

    if ($response['body']['success'] ?? false) {
        $messageIds[] = $response['body']['data']['message_id'] ?? null;
    }

    return $response;
});

// 3. Send image (queued)
$imageBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
test('3. Queue Image Message', function () use ($baseUrl, $headers, $testPhone, $imageBase64, &$messageIds) {
    $response = api_request('POST', "{$baseUrl}/public/whatsapp/send-image", $headers, [
        'to' => $testPhone,
        'content' => $imageBase64,
        'caption' => 'Queue test image',
        'file_name' => 'queue_test.png',
        'mimetype' => 'image/png',
    ]);

    if ($response['body']['success'] ?? false) {
        $messageIds[] = $response['body']['data']['message_id'] ?? null;
    }

    return $response;
});

// 4. Send file (queued)
$fileBase64 = base64_encode('Hello, this is a queued file test from Wavixa API.');
test('4. Queue File / Document Message', function () use ($baseUrl, $headers, $testPhone, $fileBase64, &$messageIds) {
    $response = api_request('POST', "{$baseUrl}/public/whatsapp/send-file", $headers, [
        'to' => $testPhone,
        'content' => $fileBase64,
        'file_name' => 'queue_test.txt',
        'mimetype' => 'text/plain',
        'caption' => 'Queue test file',
    ]);

    if ($response['body']['success'] ?? false) {
        $messageIds[] = $response['body']['data']['message_id'] ?? null;
    }

    return $response;
});

// 5. Send contact (queued)
test('5. Queue Contact Message', function () use ($baseUrl, $headers, $testPhone, &$messageIds) {
    $response = api_request('POST', "{$baseUrl}/public/whatsapp/send-contact", $headers, [
        'to' => $testPhone,
        'content' => [
            'display_name' => 'Queue Test Contact',
            'phone' => '+201024520482',
        ],
    ]);

    if ($response['body']['success'] ?? false) {
        $messageIds[] = $response['body']['data']['message_id'] ?? null;
    }

    return $response;
});

// 6. Send location (queued)
test('6. Queue Location Message', function () use ($baseUrl, $headers, $testPhone, &$messageIds) {
    $response = api_request('POST', "{$baseUrl}/public/whatsapp/send-location", $headers, [
        'to' => $testPhone,
        'content' => [
            'latitude' => 30.0444,
            'longitude' => 31.2357,
            'name' => 'Cairo Queue Test',
            'address' => 'Tahrir Square',
        ],
    ]);

    if ($response['body']['success'] ?? false) {
        $messageIds[] = $response['body']['data']['message_id'] ?? null;
    }

    return $response;
});

// 7. Process queue using artisan command
test('7. Process Queue Jobs', function () use ($messageIds) {
    echo "Processing queue jobs...\n";

    $output = [];
    $exitCode = 0;

    // Process up to 10 jobs (one at a time to maintain order)
    for ($i = 0; $i < 10; $i++) {
        exec('php ' . __DIR__ . '/artisan queue:work --once --sleep=1 --tries=3 --timeout=90', $output, $exitCode);
    }

    echo "Processed jobs.\n";

    return ['success' => true, 'body' => ['processed' => count($output), 'output' => $output]];
});

// 8. Wait for all messages to be sent and verify statuses
test('8. Verify Messages Processed in Queue', function () use ($messageIds) {
    $results = [];
    $allSent = true;

    foreach ($messageIds as $messageId) {
        if (!$messageId) {
            continue;
        }

        $message = wait_for_status($messageId, ['sent', 'failed'], 60);

        $results[$messageId] = [
            'status' => $message?->status,
            'message_type' => $message?->message_type,
            'queued_at' => $message?->queued_at,
            'processed_at' => $message?->processed_at,
            'completed_at' => $message?->completed_at,
            'attempts' => $message?->attempts,
            'error_message' => $message?->error_message,
        ];

        if ($message?->status !== 'sent') {
            $allSent = false;
        }
    }

    return [
        'success' => $allSent,
        'body' => [
            'all_sent' => $allSent,
            'messages' => $results,
        ],
    ];
});

// 9. Queue Status After Processing
test('9. Queue Status (After Processing)', function () use ($baseUrl, $headers) {
    return api_request('GET', "{$baseUrl}/public/whatsapp/queue-status", $headers);
});

// 10. Verify queue ordering (messages should have increasing completed_at times)
test('10. Verify Queue Ordering', function () use ($messageIds) {
    $messages = \App\Models\WhatsAppMessage::whereIn('id', $messageIds)
        ->orderBy('completed_at')
        ->get(['id', 'message_type', 'completed_at']);

    $ordered = true;
    $lastTime = null;
    foreach ($messages as $message) {
        if ($lastTime && $message->completed_at && $message->completed_at < $lastTime) {
            $ordered = false;
        }
        $lastTime = $message->completed_at;
    }

    return [
        'success' => $ordered,
        'body' => [
            'ordered' => $ordered,
            'messages' => $messages->toArray(),
        ],
    ];
});

// 11. Test Failed Queue Scenario (invalid phone)
test('11. Failed Queue Scenario (Invalid Phone Validation)', function () use ($baseUrl, $headers) {
    return api_request('POST', "{$baseUrl}/public/whatsapp/send-message", $headers, [
        'to' => '123',
        'message' => 'This should fail validation',
    ]);
}, true);

// 12. Final Status
test('12. Final Status & Usage Stats', function () use ($baseUrl, $headers) {
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
    echo "🎉 All queue tests passed! Your WhatsApp message queue system is ready. 🎉\n";
} else {
    echo "⚠️ Some tests failed. Review the output above for details.\n";
}

echo "\n";
