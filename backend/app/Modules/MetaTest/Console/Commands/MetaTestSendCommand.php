<?php

namespace App\Modules\MetaTest\Console\Commands;

use App\Modules\MetaTest\Models\MetaTestPhoneNumber;
use App\Modules\MetaTest\Services\MetaTestMessagingService;
use Illuminate\Console\Command;

class MetaTestSendCommand extends Command
{
    protected $signature = 'meta:test-send {phone_number_id} {to} {message}';
    protected $description = 'Send a test message via the official WhatsApp Cloud API';

    public function handle(): int
    {
        if (!config('meta.enabled')) {
            $this->error('Meta Test module is disabled.');
            return self::FAILURE;
        }

        $phone = MetaTestPhoneNumber::where('meta_phone_number_id', $this->argument('phone_number_id'))
            ->orWhere('id', $this->argument('phone_number_id'))
            ->first();

        if (!$phone) {
            $this->error('Phone number not found in meta_test_phone_numbers.');
            return self::FAILURE;
        }

        $service = new MetaTestMessagingService($phone->user_id);

        try {
            $message = $service->sendTextMessage(
                $phone,
                $this->argument('to'),
                $this->argument('message')
            );

            $this->info('Message sent.');
            $this->table(
                ['Key', 'Value'],
                [
                    ['Message ID', $message->meta_message_id ?? 'N/A'],
                    ['To', $message->to_phone],
                    ['Status', $message->status],
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Send failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
