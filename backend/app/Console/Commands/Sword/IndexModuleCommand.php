<?php

namespace App\Console\Commands\Sword;

use App\Models\Module;
use App\Services\Sword\SwordManager;
use App\Services\Sword\SwordSearcher;
use Illuminate\Console\Command;

class IndexModuleCommand extends Command
{
    protected $signature = 'sword:index
        {module : Module key to index (e.g., KJV)}
        {--force : Rebuild index even if it already exists}';

    protected $description = 'Build an FTS5 full-text search index for a SWORD module';

    public function handle(SwordManager $manager): int
    {
        $moduleKey = $this->argument('module');
        $module = Module::whereRaw('LOWER(key) = ?', [strtolower($moduleKey)])
            ->where('is_installed', true)
            ->first();

        if (!$module) {
            $this->error("Module '{$moduleKey}' is not installed.");
            return self::FAILURE;
        }

        if (!$manager->hasDataFiles($module)) {
            $this->error("Module '{$module->key}' has no binary data files.");
            return self::FAILURE;
        }

        $searcher = new SwordSearcher($manager);

        if ($searcher->hasIndex($module) && !$this->option('force')) {
            $this->info("Index already exists for {$module->key}. Use --force to rebuild.");
            return self::SUCCESS;
        }

        $this->info("Building FTS5 index for {$module->key}...");

        $start = microtime(true);
        $count = $searcher->buildIndex($module, function (string $book, int $total) {
            $this->output->write("\r  Indexed {$book} ({$total} verses so far)");
        });
        $elapsed = round(microtime(true) - $start, 1);

        $this->newLine();
        $this->info("Indexed {$count} verses in {$elapsed}s.");

        return self::SUCCESS;
    }
}
