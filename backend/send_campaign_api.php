<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Contact;
use App\Models\User;
use App\Services\PhoneNormalizer;
use Illuminate\Support\Facades\Http;

$apiKey = $_SERVER['argv'][1] ?? $_ENV['WAVIXA_API_KEY'] ?? getenv('WAVIXA_API_KEY') ?: null;
if (!$apiKey) {
    echo "Usage: php send_campaign_api.php <api_key>   (or set WAVIXA_API_KEY)\n";
    exit(1);
}
$apiUrl = 'https://wavixa.net/api/public/whatsapp/send-message';

// Persist sent phones so a restart skips already-delivered contacts.
$sentLogFile = __DIR__ . '/campaign_sent_phones.txt';
$sentPhones = [];
if (file_exists($sentLogFile)) {
    foreach (file($sentLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $sentPhones[trim($line)] = true;
    }
}

$user = User::where('email', 'shaheedmhmed@gmail.com')->first();
if (!$user) {
    echo "User not found\n";
    exit(1);
}

$contacts = Contact::where('user_id', $user->id)->get();
echo "Total contacts found: " . $contacts->count() . "\n";
echo "Already sent (resumed): " . count($sentPhones) . "\n";

$templates = [
    0 => "أهلاً بيك، منصة Wavixa. بنساعد الفرق والأعمال في تنظيم تواصلهم عبر الواتساب بشكل آمن ومنظم. لو مهتم تعرف أكتر، رد عليا وأنا هتواصل معاك.",
    1 => "السلام عليكم Wavixa. منصتنا بتسهّل التواصل اليومي للأعمال على الواتساب. لو حابب تستفسر عن أي حاجة، أنا تحت أمرك.",
    2 => "أهلاً، أنا شاهد من فريق Wavixa. بنقدم حل بسيط يساعد الشركات والفرق في إدارة محادثات الواتساب. لو عايز تفاصيل أكتر رد عليا.",
];

$queuedCount = 0;
$failedCount = 0;
$skippedCount = 0;
$alreadySentCount = 0;

$seenPhones = [];
$index = 0;

$sentFileHandle = fopen($sentLogFile, 'a');

foreach ($contacts as $contact) {
    $phone = PhoneNormalizer::normalize($contact->phone);
    if (empty($phone) || strlen($phone) < 10) {
        $skippedCount++;
        continue;
    }

    if (isset($seenPhones[$phone])) {
        $skippedCount++;
        continue;
    }
    $seenPhones[$phone] = true;

    // Skip contacts already sent to in a previous run
    if (isset($sentPhones[$phone])) {
        $alreadySentCount++;
        $index++;
        continue;
    }

    $rawName = trim($contact->name ?? '');
    $displayName = $rawName !== '' ? $rawName : '-';

    $template = $templates[$index % 3];
    $messageText = $template;

    // Retry loop: handles both the whatsapp-service 30-min/5-min cooldown
    // (429 with cooldown=true) and the Laravel API rate limiter (429 "Too many requests").
    $maxRetries = 30;
    $success = false;
    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
        $response = Http::timeout(15)
            ->withHeaders([
                'X-API-Key' => $apiKey,
                'Accept' => 'application/json',
            ])
            ->post($apiUrl, [
                'to' => $phone,
                'message' => $messageText,
            ]);

        $data = $response->json();

        // Session cooldown rest period -> wait and retry
        if ($response->status() === 429 && ($data['cooldown'] ?? false)) {
            $retryAfter = max(1, (int) ($data['retry_after'] ?? 300));
            echo "[" . ($index + 1) . "/" . $contacts->count() . "] COOLDOWN -> {$phone}: resting {$retryAfter}s...\n";
            sleep($retryAfter + 2);
            continue;
        }

        // Laravel rate limiter -> wait 60s and retry
        if ($response->status() === 429) {
            echo "[" . ($index + 1) . "/" . $contacts->count() . "] RATE LIMITED -> {$phone}: waiting 60s...\n";
            sleep(60);
            continue;
        }

        break;
    }

    if ($response->successful() && ($data['success'] ?? false)) {
        $queuedCount++;
        $messageId = $data['data']['message_id'] ?? 'N/A';
        echo "[" . ($index + 1) . "/" . $contacts->count() . "] OK -> {$phone} ({$displayName}) [Template " . (($index % 3) + 1) . "] Message ID: {$messageId}\n";
        // Persist sent phone for resume-on-restart
        fwrite($sentFileHandle, $phone . "\n");
        $sentPhones[$phone] = true;
        $success = true;
    } else {
        $failedCount++;
        $errMsg = $data['message'] ?? $response->body();
        echo "[" . ($index + 1) . "/" . $contacts->count() . "] FAILED -> {$phone}: {$errMsg}\n";
    }

    $index++;

    // Wait 1.5 seconds between requests (~40/min, well within the 60/min API limit)
    usleep(1500000);
}

fclose($sentFileHandle);

echo "\n--- Summary ---\n";
echo "Total processed: {$index}\n";
echo "Successfully queued: {$queuedCount}\n";
echo "Failed: {$failedCount}\n";
echo "Skipped (invalid/duplicate): {$skippedCount}\n";
echo "Already sent (resumed): {$alreadySentCount}\n";
