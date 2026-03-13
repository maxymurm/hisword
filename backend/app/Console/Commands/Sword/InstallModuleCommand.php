<?php

namespace App\Console\Commands\Sword;

use App\Services\Sword\ModuleInstaller;
use Illuminate\Console\Command;

class InstallModuleCommand extends Command
{
    protected $signature = 'sword:install
        {module : Module key to install (e.g., KJV)}
        {--force : Force re-installation}
        {--source= : Specify repository source}';

    protected $description = 'Download and install a SWORD module';

    public function handle(ModuleInstaller $installer): int
    {
        $moduleKey = strtoupper($this->argument('module'));
        $force = $this->option('force');

        $this->info("Installing SWORD module: {$moduleKey}...");

        $progressBar = $this->output->createProgressBar(100);
        $progressBar->setFormat(' %current%/%max% [%bar%] %message%');
        $progressBar->start();

        try {
            $module = $installer->install($moduleKey, $force, function (string $message, int $percent) use ($progressBar) {
                $progressBar->setProgress($percent);
                $progressBar->setMessage($message);
            });

            $progressBar->finish();
            $this->newLine(2);

            $this->info("Module {$moduleKey} installed successfully!");
            $this->table(
                ['Property', 'Value'],
                [
                    ['Key', $module->key],
                    ['Name', $module->name],
                    ['Type', $module->type->value],
                    ['Language', $module->language],
                    ['Driver', $module->mod_drv ?? 'N/A'],
                    ['Source Type', $module->source_type_format ?? 'N/A'],
                    ['Version', $module->version ?? 'N/A'],
                    ['Installed', $module->is_installed ? 'Yes' : 'No'],
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $progressBar->finish();
            $this->newLine(2);
            $this->error("Installation failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
