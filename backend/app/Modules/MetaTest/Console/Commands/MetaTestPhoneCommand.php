<?php

namespace App\Modules\MetaTest\Console\Commands;

use App\Modules\MetaTest\Services\MetaTestGraphClient;
use Illuminate\Console\Command;

class MetaTestPhoneCommand extends Command
{
    protected $signature = 'meta:test-phone {waba?}';
    protected $description = 'List WhatsApp phone numbers for a WABA';

    public function handle(): int
    {
        if (!config('meta.enabled')) {
            $this->error('Meta Test module is disabled.');
            return self::FAILURE;
        }

        $token = config('meta.access_token');

        if (empty($token)) {
            $this->error('META_ACCESS_TOKEN is not set.');
            return self::FAILURE;
        }

        $wabaId = $this->argument('waba');

        if (empty($wabaId)) {
            $client = new MetaTestGraphClient();

            try {
                $wabas = $client->request('GET', '/me/whatsapp_business_accounts', ['limit' => 10], $token);

                $ids = collect($wabas['data'] ?? [])->pluck('id')->toArray();

                if (empty($ids)) {
                    $this->error('No WABAs found. Provide a WABA ID.');
                    return self::FAILURE;
                }

                $wabaId = $ids[0];
            } catch (\Throwable $e) {
                $this->error('WABA fetch failed: ' . $e->getMessage());
                return self::FAILURE;
            }
        }

        $client = new MetaTestGraphClient();

        try {
            $this->info("Fetching phone numbers for WABA {$wabaId}...");

            $numbers = $client->request(
                'GET',
                "/{$wabaId}/phone_numbers",
                [
                    'fields' => 'id,display_phone_number,verified_name,quality_rating,status,messaging_limit_tier',
                    'limit' => 100,
                ],
                $token
            );

            $rows = [];

            foreach ($numbers['data'] ?? [] as $phone) {
                $rows[] = [
                    $phone['id'],
                    $phone['display_phone_number'] ?? 'N/A',
                    $phone['verified_name'] ?? 'N/A',
                    $phone['quality_rating'] ?? 'N/A',
                    $phone['status'] ?? 'N/A',
                    $phone['messaging_limit_tier'] ?? 'N/A',
                ];
            }

            if (empty($rows)) {
                $this->warn('No phone numbers found.');
                return self::FAILURE;
            }

            $this->table(
                ['Phone Number ID', 'Display Number', 'Verified Name', 'Quality', 'Status', 'Messaging Tier'],
                $rows
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Phone number fetch failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
