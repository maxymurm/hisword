<?php

namespace Database\Seeders;

use App\Models\ModuleSource;
use App\Services\Sword\ModuleInstaller;
use App\Services\Sword\RepositoryBrowser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Seeds SWORD module sources and installs bundled modules.
 *
 * Bundled modules (from config): KJV, MHCC, StrongsRealHebrew, StrongsRealGreek, Robinson
 *
 * Usage:
 *   php artisan db:seed --class=SwordModuleSeeder
 *   php artisan migrate:fresh --seed  (runs via DatabaseSeeder)
 */
class SwordModuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding SWORD module sources...');

        // Create default sources
        $sources = config('bible.default_sources', []);
        foreach ($sources as $source) {
            ModuleSource::firstOrCreate(
                ['server' => $source['server'], 'directory' => $source['directory']],
                [
                    'caption' => $source['caption'],
                    'type' => $source['type'],
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Module sources created.');

        // Refresh catalog from repository
        $browser = app(RepositoryBrowser::class);
        try {
            $this->command->info('Fetching module catalog from CrossWire...');
            $result = $browser->refreshAll();
            $this->command->info("Found {$result['modules_found']} modules.");
        } catch (\Throwable $e) {
            $this->command->warn("Failed to fetch catalog: {$e->getMessage()}");
            $this->command->warn('You can retry with: php artisan sword:refresh-sources');
            return;
        }

        // Install bundled modules
        $bundled = config('bible.bundled_modules', []);
        $installer = app(ModuleInstaller::class);

        foreach ($bundled as $moduleKey) {
            try {
                $this->command->info("Installing bundled module: {$moduleKey}...");
                $installer->install($moduleKey, true, function (string $msg, int $pct) {
                    if ($pct % 20 === 0) {
                        $this->command->info("  [{$pct}%] {$msg}");
                    }
                });
                $this->command->info("  {$moduleKey} installed.");
            } catch (\Throwable $e) {
                $this->command->warn("  Failed to install {$moduleKey}: {$e->getMessage()}");
                Log::error("SwordModuleSeeder: failed to install {$moduleKey}", ['error' => $e->getMessage()]);
            }
        }

        $this->command->info('SWORD module seeding complete.');
    }
}
