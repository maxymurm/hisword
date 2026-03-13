<?php

namespace App\Console\Commands\Sword;

use App\Services\Sword\ModuleInstaller;
use Illuminate\Console\Command;

class RemoveModuleCommand extends Command
{
    protected $signature = 'sword:remove
        {module : Module key to remove (e.g., KJV)}
        {--keep-data : Keep database records (books, chapters, verses)}';

    protected $description = 'Remove an installed SWORD module';

    public function handle(ModuleInstaller $installer): int
    {
        $moduleKey = strtoupper($this->argument('module'));

        if (!$this->confirm("Are you sure you want to remove {$moduleKey}?")) {
            $this->info('Cancelled.');
            return self::SUCCESS;
        }

        try {
            $installer->remove($moduleKey, $this->option('keep-data'));
            $this->info("Module {$moduleKey} removed successfully.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
