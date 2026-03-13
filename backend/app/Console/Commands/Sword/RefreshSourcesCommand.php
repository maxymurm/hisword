<?php

namespace App\Console\Commands\Sword;

use App\Models\ModuleSource;
use App\Services\Sword\RepositoryBrowser;
use Illuminate\Console\Command;

class RefreshSourcesCommand extends Command
{
    protected $signature = 'sword:refresh-sources
        {--source= : Refresh only a specific source by caption}
        {--seed : Create default sources if none exist}';

    protected $description = 'Fetch module catalogs from SWORD repositories and update available modules';

    public function handle(RepositoryBrowser $browser): int
    {
        // Seed default sources if requested or if none exist
        if ($this->option('seed') || ModuleSource::count() === 0) {
            $this->seedDefaultSources();
        }

        $sourceName = $this->option('source');

        if ($sourceName) {
            $source = ModuleSource::where('caption', 'like', "%{$sourceName}%")->first();
            if (!$source) {
                $this->error("Source '{$sourceName}' not found.");
                return self::FAILURE;
            }

            $this->info("Refreshing source: {$source->caption}...");
            try {
                $count = $browser->refreshSource($source);
                $this->info("Found {$count} modules from {$source->caption}.");
            } catch (\Throwable $e) {
                $this->error("Failed: {$e->getMessage()}");
                return self::FAILURE;
            }
        } else {
            $this->info('Refreshing all active SWORD sources...');
            $result = $browser->refreshAll();

            $this->info("Refreshed {$result['refreshed']} source(s).");
            $this->info("Found {$result['modules_found']} total modules.");

            if (!empty($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    $this->warn("  Error: {$error}");
                }
            }
        }

        return self::SUCCESS;
    }

    private function seedDefaultSources(): void
    {
        $defaults = config('bible.default_sources', []);

        foreach ($defaults as $source) {
            ModuleSource::firstOrCreate(
                ['server' => $source['server'], 'directory' => $source['directory']],
                [
                    'caption' => $source['caption'],
                    'type' => $source['type'],
                    'is_active' => true,
                ]
            );
        }

        $this->info('Default sources seeded.');
    }
}
