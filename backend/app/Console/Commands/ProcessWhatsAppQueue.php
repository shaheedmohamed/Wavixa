<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ProcessWhatsAppQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:process-queue
                            {--queue=default : The queue to listen on}
                            {--sleep=3 : Number of seconds to sleep between job attempts}
                            {--tries=3 : Number of times to attempt a job before marking it failed}
                            {--timeout=90 : Seconds a job may run before timing out}
                            {--once : Process only one job and exit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process WhatsApp message queue jobs in order with retry support.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $queue = $this->option('queue');
        $sleep = $this->option('sleep');
        $tries = $this->option('tries');
        $timeout = $this->option('timeout');
        $once = $this->option('once');

        $this->info("Starting WhatsApp queue worker on queue: {$queue}");
        $this->info("Sleep: {$sleep}s | Tries: {$tries} | Timeout: {$timeout}s");

        if ($once) {
            $this->info('Processing one job and exiting...');
        }

        $params = [
            '--queue' => $queue,
            '--sleep' => $sleep,
            '--tries' => $tries,
            '--timeout' => $timeout,
        ];

        if ($once) {
            $params['--once'] = true;
        }

        // Delegate to Laravel's built-in queue:work command
        Artisan::call('queue:work', $params);

        $this->info(Artisan::output());

        return Command::SUCCESS;
    }
}
