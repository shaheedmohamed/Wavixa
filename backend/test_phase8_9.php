<?php

/**
 * Wavixa API Phase 8 + 9 Test Suite
 *
 * Tests:
 * - Message history system (search, filter, pagination, details, resend, export)
 * - Webhook management (CRUD, test, logs, retry)
 * - Webhook events triggered on message send
 *
 * Usage:
 *   php test_phase8_9.php
 *   php test_phase8_9.php YOUR_API_KEY
 */

$apiKey = $_SERVER['argv'][1] ?? $_ENV['WAVIXA_API_KEY'] ?? 'wav_mOu2ZX1kQSbztjNKrarhHvuGYxIpEcPQa8JlaDcztHVWm3rS';
$baseUrl = $_ENV['WAVIXA_BASE_URL'] ?? 'http://localhost:8000/api';
$testPhone = $_ENV['WAVIXA_TEST_PHONE'] ?? '+201024520482';

$passed = 0;
$failed = 0;
$webhookId = null;
$messageId = null;

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                                                                            ║\n";
echo "║              Wavixa Phase 8 + 9 Test Suite (Messages + Webhooks)         ║\n";
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

// Clear webhook test receiver
test('0. Clear Webhook Test Receiver', function () use ($baseUrl) {
    return api_request('DELETE', "{$baseUrl}/webhook-test-receiver", []);
});

// 1. Send a message to create history
test('1. Send Message to Create History', function () use ($baseUrl, $headers, $testPhone, &$messageId) {
    $response = api_request('POST', "{$baseUrl}/public/whatsapp/send-message", $headers, [
        'to' => $testPhone,
        'message' => 'Phase 8/9 test message at ' . date('Y-m-d H:i:s'),
    ]);

    if ($response['body']['success'] ?? false) {
        $messageId = $response['body']['data']['message_id'] ?? null;
    }

    return $response;
});

// 2. Process queue so message is sent
test('2. Process Queue Jobs', function () {
    $output = [];
    for ($i = 0; $i < 3; $i++) {
        exec('php ' . __DIR__ . '/artisan queue:work --once --sleep=1 --tries=3 --timeout=90', $output);
    }
    return ['success' => true, 'body' => ['processed' => count($output)]];
});

// 3. Get message history
test('3. Get Message History', function () use ($baseUrl, $headers) {
    return api_request('GET', "{$baseUrl}/messages?per_page=10", $headers);
});

// 4. Search message history
test('4. Search Message History', function () use ($baseUrl, $headers, $testPhone) {
    return api_request('GET', "{$baseUrl}/messages?search=" . urlencode($testPhone) . "&direction=outgoing", $headers);
});

// 5. Get message details
test('5. Get Message Details', function () use ($baseUrl, $headers, $messageId) {
    if (!$messageId) {
        return ['success' => false, 'body' => ['message' => 'No message ID']];
    }
    return api_request('GET', "{$baseUrl}/messages/{$messageId}", $headers);
});

// 6. Get filter options
test('6. Get Message Filter Options', function () use ($baseUrl, $headers) {
    return api_request('GET', "{$baseUrl}/messages/filters", $headers);
});

// 7. Resend a message
test('7. Resend Message', function () use ($baseUrl, $headers, $messageId) {
    if (!$messageId) {
        return ['success' => false, 'body' => ['message' => 'No message ID']];
    }
    return api_request('POST', "{$baseUrl}/messages/{$messageId}/resend", $headers, []);
});

// 8. Export messages
test('8. Export Messages CSV', function () use ($baseUrl, $headers) {
    $response = api_request('GET', "{$baseUrl}/messages/export/csv?direction=outgoing", $headers);
    $hasCsv = strpos($response['raw'] ?? '', ',') !== false;
    return ['success' => $hasCsv, 'body' => ['csv_preview' => substr($response['raw'] ?? '', 0, 200)]];
});

// 9. Get webhook events list
test('9. Get Webhook Events List', function () use ($baseUrl, $headers) {
    return api_request('GET', "{$baseUrl}/webhooks/events", $headers);
});

// 10. Create a webhook
test('10. Create Webhook', function () use ($baseUrl, $headers, &$webhookId) {
    $response = api_request('POST', "{$baseUrl}/webhooks", $headers, [
        'name' => 'Test Webhook',
        'url' => "{$baseUrl}/webhook-test-receiver",
        'secret' => 'test-secret-123',
        'events' => ['message.sent', 'message.failed', 'message.received'],
        'max_retries' => 2,
    ]);

    if ($response['body']['success'] ?? false) {
        $webhookId = $response['body']['data']['id'] ?? null;
    }

    return $response;
});

// 11. List webhooks
test('11. List Webhooks', function () use ($baseUrl, $headers) {
    return api_request('GET', "{$baseUrl}/webhooks", $headers);
});

// 12. Test webhook
test('12. Test Webhook', function () use ($baseUrl, $headers, $webhookId) {
    if (!$webhookId) {
        return ['success' => false, 'body' => ['message' => 'No webhook ID']];
    }
    return api_request('POST', "{$baseUrl}/webhooks/{$webhookId}/test", $headers, []);
});

// 13. Process webhook queue
test('13. Process Webhook Queue', function () {
    $output = [];
    for ($i = 0; $i < 5; $i++) {
        exec('php ' . __DIR__ . '/artisan queue:work --once --sleep=1 --tries=1 --timeout=30', $output);
    }
    return ['success' => true, 'body' => ['processed' => count($output)]];
});

// 14. Verify webhook received test event
test('14. Verify Webhook Test Received', function () use ($baseUrl) {
    $response = api_request('GET', "{$baseUrl}/webhook-test-receiver", []);
    $received = $response['body']['data'] ?? [];
    $hasTest = false;
    foreach ($received as $item) {
        if (($item['payload']['event'] ?? '') === 'test') {
            $hasTest = true;
            break;
        }
    }
    return ['success' => $hasTest, 'body' => ['received_count' => count($received), 'has_test_event' => $hasTest]];
});

// 15. Get webhook logs
test('15. Get Webhook Logs', function () use ($baseUrl, $headers, $webhookId) {
    if (!$webhookId) {
        return ['success' => false, 'body' => ['message' => 'No webhook ID']];
    }
    return api_request('GET', "{$baseUrl}/webhooks/{$webhookId}/logs", $headers);
});

// 16. Resend a webhook log
test('16. Resend Webhook Log', function () use ($baseUrl, $headers, $webhookId) {
    if (!$webhookId) {
        return ['success' => false, 'body' => ['message' => 'No webhook ID']];
    }

    $logsResponse = api_request('GET', "{$baseUrl}/webhooks/{$webhookId}/logs", $headers);
    $logs = $logsResponse['body']['data']['data'] ?? [];

    if (empty($logs)) {
        return ['success' => false, 'body' => ['message' => 'No logs to resend']];
    }

    $logId = $logs[0]['id'];
    return api_request('POST', "{$baseUrl}/webhook-logs/{$logId}/resend", $headers, []);
});

// 17. Update webhook
test('17. Update Webhook', function () use ($baseUrl, $headers, $webhookId) {
    if (!$webhookId) {
        return ['success' => false, 'body' => ['message' => 'No webhook ID']];
    }
    return api_request('PUT', "{$baseUrl}/webhooks/{$webhookId}", $headers, [
        'name' => 'Updated Test Webhook',
        'url' => "{$baseUrl}/webhook-test-receiver",
        'secret' => 'updated-secret',
        'events' => ['message.sent'],
        'max_retries' => 3,
    ]);
});

// 18. Verify message sent webhook event
test('18. Verify Message Sent Webhook Event', function () use ($baseUrl) {
    $response = api_request('GET', "{$baseUrl}/webhook-test-receiver", []);
    $received = $response['body']['data'] ?? [];
    $hasSent = false;
    foreach ($received as $item) {
        if (($item['payload']['event'] ?? '') === 'message.sent') {
            $hasSent = true;
            break;
        }
    }
    return ['success' => $hasSent, 'body' => ['has_message_sent_event' => $hasSent]];
});

// 19. Delete webhook
test('19. Delete Webhook', function () use ($baseUrl, $headers, $webhookId) {
    if (!$webhookId) {
        return ['success' => false, 'body' => ['message' => 'No webhook ID']];
    }
    return api_request('DELETE', "{$baseUrl}/webhooks/{$webhookId}", $headers);
});

// 20. Final status check
test('20. Final Status Check', function () use ($baseUrl, $headers) {
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
    echo "🎉 All Phase 8 + 9 tests passed! Messages and Webhooks are ready. 🎉\n";
} else {
    echo "⚠️ Some tests failed. Review the output above for details.\n";
}

echo "\n";
