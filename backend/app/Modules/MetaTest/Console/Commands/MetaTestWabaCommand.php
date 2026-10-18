<?php

namespace App\Modules\MetaTest\Console\Commands;

use App\Modules\MetaTest\Services\MetaTestConnectionManager;
use App\Modules\MetaTest\Services\MetaTestGraphClient;
use Illuminate\Console\Command;

class MetaTestWabaCommand extends Command
{
    protected $signature = 'meta:test-waba';
    protected $description = 'List WhatsApp Business Accounts accessible by the configured token';

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

        $client = new MetaTestGraphClient();

        try {
            $this->info('Fetching WABAs...');
            $wabas = $client->request('GET', '/me/whatsapp_business_accounts', [
                'fields' => 'id,name,timezone,currency,status,phone_numbers,message_templates',
                'limit' => 100,
            ], $token);

            $rows = [];

            foreach ($wabas['data'] ?? [] as $waba) {
                $rows[] = [
                    $waba['id'],
                    $waba['name'] ?? 'N/A',
                    $waba['status'] ?? 'N/A',
                    $waba['timezone'] ?? 'N/A',
                    $waba['currency'] ?? 'N/A',
                ];
            }

            if (empty($rows)) {
                $this->warn('No WABAs found. The token may not have WhatsApp permissions.');
                return self::FAILURE;
            }

            $this->table(['WABA ID', 'Name', 'Status', 'Timezone', 'Currency'], $rows);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('WABA fetch failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
