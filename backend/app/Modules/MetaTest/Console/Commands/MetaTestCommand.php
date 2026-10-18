<?php

namespace App\Modules\MetaTest\Console\Commands;

use App\Modules\MetaTest\Services\MetaTestSetupService;
use Illuminate\Console\Command;

class MetaTestCommand extends Command
{
    protected $signature = 'meta:test {--check= : Run a specific check (token, graph, whatsapp)}';
    protected $description = 'Run the Meta Official integration test checklist';

    public function handle(): int
    {
        if (!config('meta.enabled')) {
            $this->error('Meta Test module is disabled. Set META_OFFICIAL_TEST_ENABLED=true in .env.');
            return self::FAILURE;
        }

        $service = new MetaTestSetupService();
        $check = $this->option('check');

        if ($check) {
            $this->info("Running check: {$check}");
        }

        $checklist = $service->checklist();

        $this->info('Meta Official Integration Test Checklist');
        $this->info('========================================');

        foreach ($checklist['items'] as $item) {
            $status = $item['status'];
            $icon = in_array($status, ['ok', 'valid', 'configured'], true) ? '✅' : '⚠️';
            $this->line("{$icon} {$item['name']}: {$status} — {$item['message']}");
        }

        if (!$checklist['all_configured']) {
            $this->warn('Some items require configuration.');
            foreach ($checklist['requires_action'] as $item) {
                $this->warn("  - {$item['name']}: {$item['message']}");
            }
            return self::FAILURE;
        }

        $this->info('All checks passed.');
        return self::SUCCESS;
    }
}
