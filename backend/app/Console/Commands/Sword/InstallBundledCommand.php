<?php

namespace App\Console\Commands\Sword;

use App\Services\Sword\ModuleInstaller;
use Illuminate\Console\Command;

/**
 * Install bundled SWORD modules from the Resources/ directory.
 *
 * These are the same modules HisWord iOS bundles on first launch:
 * KJV, MHCC, Robinson, StrongsRealHebrew, StrongsRealGreek
 */
class InstallBundledCommand extends Command
{
    protected $signature = 'sword:install-bundled
        {--force : Re-install even if already installed}
        {--module= : Install a specific bundled module only}';

    protected $description = 'Install bundled SWORD modules from the Resources/ ZIP files';

    public function handle(ModuleInstaller $installer): int
    {
        $specificModule = $this->option('module');
        $force = (bool) $this->option('force');

        // Resolve the Resources directory
        $resourcesPath = $this->resolveResourcesPath();
        if (!$resourcesPath) {
            $this->error('Could not find the Resources/ directory.');
            return self::FAILURE;
        }

        $this->info("Found Resources at: {$resourcesPath}");

        // Module key => ZIP filename mapping
        $zipMap = [
            'KJV' => 'KJV.zip',
            'MHCC' => 'MHCC.zip',
            'Robinson' => 'Robinson.zip',
            'StrongsRealHebrew' => 'strongsrealhebrew.zip',
            'StrongsRealGreek' => 'strongsrealgreek.zip',
        ];

        if ($specificModule) {
            if (!isset($zipMap[$specificModule])) {
                $this->error("Unknown bundled module: {$specificModule}");
                $this->line('Available: ' . implode(', ', array_keys($zipMap)));
                return self::FAILURE;
            }
            $zipMap = [$specificModule => $zipMap[$specificModule]];
        }

        $total = count($zipMap);
        $installed = 0;
        $skipped = 0;
        $failed = 0;

        $this->newLine();

        foreach ($zipMap as $key => $zipName) {
            $zipPath = $resourcesPath . DIRECTORY_SEPARATOR . $zipName;

            if (!file_exists($zipPath)) {
                $this->warn("  [SKIP] {$key}: ZIP not found ({$zipName})");
                $skipped++;
                continue;
            }

            $this->info("Installing {$key} from {$zipName}...");

            $bar = $this->output->createProgressBar(100);
            $bar->setFormat("  %current%/%max% [%bar%] %percent:3s%% %message%");
            $bar->setMessage('Starting...');
            $bar->start();

            try {
                $installer->installFromZip($zipPath, $force, function (string $msg, int $pct) use ($bar) {
                    $bar->setProgress(min(100, max(0, $pct)));
                    // Truncate message for progress bar
                    $bar->setMessage(mb_substr($msg, 0, 50));
                });

                $bar->setMessage('Done!');
                $bar->finish();
                $this->newLine();
                $this->info("  [OK] {$key} installed successfully.");
                $installed++;
            } catch (\Throwable $e) {
                $bar->finish();
                $this->newLine();
                $this->error("  [FAIL] {$key}: {$e->getMessage()}");
                $failed++;
            }

            $this->newLine();
        }

        $this->newLine();
        $this->info("Results: {$installed} installed, {$skipped} skipped, {$failed} failed (out of {$total})");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveResourcesPath(): ?string
    {
        // Try various relative paths from the backend directory
        $candidates = [
            base_path('../Resources'),
            base_path('../../Resources'),
            dirname(base_path()) . '/Resources',
        ];

        foreach ($candidates as $path) {
            $resolved = realpath($path);
            if ($resolved && is_dir($resolved)) {
                return $resolved;
            }
        }

        return null;
    }
}
