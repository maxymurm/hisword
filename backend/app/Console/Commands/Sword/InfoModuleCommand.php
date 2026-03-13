<?php

namespace App\Console\Commands\Sword;

use App\Models\Module;
use Illuminate\Console\Command;

class InfoModuleCommand extends Command
{
    protected $signature = 'sword:info {module : Module key (e.g., KJV)}';

    protected $description = 'Display full SWORD module metadata from .conf';

    public function handle(): int
    {
        $moduleKey = strtoupper($this->argument('module'));
        $module = Module::where('key', $moduleKey)->first();

        if (!$module) {
            $this->error("Module '{$moduleKey}' not found.");
            return self::FAILURE;
        }

        $this->info("=== {$module->key}: {$module->name} ===");
        $this->newLine();

        $rows = [
            ['Key', $module->key],
            ['Name', $module->name],
            ['Description', mb_substr($module->description ?? '', 0, 80)],
            ['Type', $module->type->value ?? $module->type],
            ['Language', $module->language],
            ['Version', $module->version ?? 'N/A'],
            ['Driver', $module->mod_drv ?? 'N/A'],
            ['Source Type', $module->source_type_format ?? 'N/A'],
            ['Compression', $module->compress_type ?? 'N/A'],
            ['Block Type', $module->block_type ?? 'N/A'],
            ['Versification', $module->versification ?? 'N/A'],
            ['Encoding', $module->encoding ?? 'N/A'],
            ['Direction', $module->direction ?? 'N/A'],
            ['Category', $module->category ?? 'N/A'],
            ['Installed', $module->is_installed ? 'Yes' : 'No'],
            ['Bundled', $module->is_bundled ? 'Yes' : 'No'],
            ['Data Path', $module->data_path ?? 'N/A'],
            ['File Size', $module->file_size ? $this->formatSize($module->file_size) : 'N/A'],
            ['Install Size', $module->install_size ? $this->formatSize($module->install_size) : 'N/A'],
            ['Features', is_array($module->features) ? implode(', ', $module->features) : 'N/A'],
            ['Filters', is_array($module->global_option_filters) ? implode(', ', $module->global_option_filters) : 'N/A'],
            ['Cipher Key', $module->cipher_key ? '(set)' : 'N/A'],
        ];

        $this->table(['Property', 'Value'], $rows);

        // Show about text if available
        if ($module->about) {
            $this->newLine();
            $this->info('About:');
            $this->line($module->about);
        }

        // Show raw conf data if available
        if ($module->conf_data && is_array($module->conf_data)) {
            $this->newLine();
            if ($this->confirm('Show raw .conf data?', false)) {
                $confRows = [];
                foreach ($module->conf_data as $key => $value) {
                    $displayValue = is_array($value) ? implode(', ', $value) : mb_substr((string)$value, 0, 100);
                    $confRows[] = [$key, $displayValue];
                }
                $this->table(['Conf Key', 'Value'], $confRows);
            }
        }

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
