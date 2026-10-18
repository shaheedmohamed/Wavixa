<?php

namespace App\Modules\MetaTest\Console\Commands;

use App\Modules\MetaTest\Services\MetaTestGraphClient;
use Illuminate\Console\Command;

class MetaTestTokenCommand extends Command
{
    protected $signature = 'meta:test-token';
    protected $description = 'Validate the configured Meta access token';

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
            $this->info('Debugging access token...');
            $debug = $client->debugToken($token);

            $data = $debug['data'] ?? [];

            $this->table(
                ['Key', 'Value'],
                [
                    ['Is Valid', $data['is_valid'] ? 'Yes' : 'No'],
                    ['App ID', $data['app_id'] ?? 'N/A'],
                    ['Type', $data['type'] ?? 'N/A'],
                    ['Expires At', isset($data['expires_at']) ? date('Y-m-d H:i:s', $data['expires_at']) : 'N/A'],
                    ['Scopes', implode(', ', $data['scopes'] ?? [])],
                ]
            );

            return $data['is_valid'] ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Token validation failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
