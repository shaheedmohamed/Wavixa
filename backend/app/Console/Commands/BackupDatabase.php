<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:database
                            {--path= : Custom backup directory}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a compressed database backup';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $db = config('database.connections.mysql');
        $date = now()->format('Y-m-d_H-i-s');
        $filename = "backup_{$db['database']}_{$date}.sql.gz";
        $path = $this->option('path') ?? storage_path('app/backups');

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $fullPath = $path . '/' . $filename;

        $command = sprintf(
            'mysqldump --no-tablespaces -h%s -u%s -p%s %s | gzip > %s',
            escapeshellarg($db['host'] ?? '127.0.0.1'),
            escapeshellarg($db['username']),
            escapeshellarg($db['password'] ?? ''),
            escapeshellarg($db['database']),
            escapeshellarg($fullPath)
        );

        $process = Process::fromShellCommandline($command);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            Log::error('Database backup failed', ['error' => $process->getErrorOutput()]);
            $this->error('Database backup failed: ' . $process->getErrorOutput());
            return self::FAILURE;
        }

        // Keep only the last 14 backups
        $backups = glob($path . '/backup_*.sql.gz');
        usort($backups, function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });

        foreach (array_slice($backups, 14) as $oldBackup) {
            unlink($oldBackup);
        }

        Log::info('Database backup created', ['path' => $fullPath]);
        $this->info("Database backup created: {$fullPath}");

        return self::SUCCESS;
    }
}
