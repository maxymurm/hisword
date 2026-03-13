<?php

namespace App\Console\Commands\Sword;

use App\Models\Module;
use Illuminate\Console\Command;

class ListModulesCommand extends Command
{
    protected $signature = 'sword:list
        {--installed : Show only installed modules}
        {--available : Show only available (not installed) modules}
        {--type= : Filter by type (bible, commentary, dictionary, devotional, genbook)}
        {--language= : Filter by language code (e.g., en, de, el)}';

    protected $description = 'List all SWORD modules (installed and available)';

    public function handle(): int
    {
        $query = Module::query();

        if ($this->option('installed')) {
            $query->where('is_installed', true);
        }
        if ($this->option('available')) {
            $query->where('is_installed', false);
        }
        if ($type = $this->option('type')) {
            $query->where('type', $type);
        }
        if ($language = $this->option('language')) {
            $query->where('language', $language);
        }

        $modules = $query->orderBy('type')->orderBy('name')->get();

        if ($modules->isEmpty()) {
            $this->warn('No modules found. Run `sword:refresh-sources` to fetch available modules.');
            return self::SUCCESS;
        }

        $rows = $modules->map(fn (Module $m) => [
            $m->key,
            mb_substr($m->name, 0, 40),
            $m->type->value ?? $m->type,
            $m->language,
            $m->version ?? 'N/A',
            $m->is_installed ? 'Yes' : 'No',
            $m->mod_drv ?? 'N/A',
            $m->install_size ? $this->formatSize($m->install_size) : 'N/A',
        ]);

        $this->table(
            ['Key', 'Name', 'Type', 'Lang', 'Version', 'Installed', 'Driver', 'Size'],
            $rows
        );

        $installed = $modules->where('is_installed', true)->count();
        $total = $modules->count();
        $this->info("Showing {$total} modules ({$installed} installed).");

        return self::SUCCESS;
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}
