<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackupDatabase extends Command
{
    protected $signature = 'hisword:backup-db
        {--disk=local : Storage disk for backup files}
        {--keep=30 : Number of daily backups to retain}';

    protected $description = 'Create a compressed PostgreSQL backup and prune old backups';

    public function handle(): int
    {
        $database = config('database.connections.pgsql.database');
        $host = config('database.connections.pgsql.host');
        $port = config('database.connections.pgsql.port');
        $username = config('database.connections.pgsql.username');
        $password = config('database.connections.pgsql.password');

        $timestamp = now()->format('Y-m-d_His');
        $filename = "backups/{$database}_{$timestamp}.sql.gz";
        $tempPath = storage_path("app/{$filename}");
        $backupDir = dirname($tempPath);

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $this->info("Backing up {$database}...");

        // Build pg_dump command
        $env = ['PGPASSWORD' => $password];
        $cmd = sprintf(
            'pg_dump -h %s -p %s -U %s -Fc %s | gzip > %s',
            escapeshellarg($host),
            escapeshellarg((string) $port),
            escapeshellarg($username),
            escapeshellarg($database),
            escapeshellarg($tempPath),
        );

        $process = proc_open(
            $cmd,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            array_merge($_ENV, $env),
        );

        if (!is_resource($process)) {
            $this->error('Failed to start pg_dump process.');
            return self::FAILURE;
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $this->error("pg_dump failed (exit {$exitCode}): {$stderr}");
            @unlink($tempPath);
            return self::FAILURE;
        }

        if (!file_exists($tempPath) || filesize($tempPath) === 0) {
            $this->error('Backup file is empty or missing.');
            @unlink($tempPath);
            return self::FAILURE;
        }

        $size = $this->formatBytes(filesize($tempPath));
        $this->info("Backup created: {$filename} ({$size})");

        // Upload to configured disk if not local
        $disk = $this->option('disk');
        if ($disk !== 'local') {
            $this->info("Uploading to {$disk} disk...");
            Storage::disk($disk)->put($filename, file_get_contents($tempPath));
            @unlink($tempPath);
            $this->info('Upload complete.');
        }

        // Prune old backups
        $keep = (int) $this->option('keep');
        $this->pruneBackups($disk, $keep);

        $this->info('Backup complete.');
        return self::SUCCESS;
    }

    private function pruneBackups(string $disk, int $keep): void
    {
        $storage = Storage::disk($disk);
        $files = collect($storage->files('backups'))
            ->filter(fn (string $f) => str_ends_with($f, '.sql.gz'))
            ->sort()
            ->values();

        if ($files->count() <= $keep) {
            return;
        }

        $toDelete = $files->slice(0, $files->count() - $keep);
        foreach ($toDelete as $file) {
            $storage->delete($file);
            $this->line("  Pruned: {$file}");
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) return "{$bytes} B";
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}
