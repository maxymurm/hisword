<?php

namespace App\Jobs;

use App\Services\Sword\ModuleInstaller;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Background job to download, extract, and index a SWORD module.
 *
 * Progress is reported via cache keys that the SSE endpoint polls.
 * Mirrors HisWord's serial FIFO download queue — one module at a time.
 */
class InstallModuleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600; // 10 minutes max

    public function __construct(
        public string $moduleKey,
        public bool $force = false,
        public ?string $zipPath = null,
    ) {
        $this->onQueue('sword');
    }

    public function handle(ModuleInstaller $installer): void
    {
        $progressCallback = function (string $message, int $percent) {
            cache()->put("sword_progress_{$this->moduleKey}", [
                'module' => $this->moduleKey,
                'message' => $message,
                'percent' => $percent,
                'status' => $percent >= 100 ? 'completed' : 'in_progress',
                'timestamp' => now()->toISOString(),
            ], 300);
        };

        try {
            if ($this->zipPath) {
                $installer->installFromZip($this->zipPath, $this->force, $progressCallback);
            } else {
                $installer->install($this->moduleKey, $this->force, $progressCallback);
            }

            cache()->put("sword_progress_{$this->moduleKey}", [
                'module' => $this->moduleKey,
                'message' => "{$this->moduleKey} installed successfully.",
                'percent' => 100,
                'status' => 'completed',
                'timestamp' => now()->toISOString(),
            ], 300);
        } catch (\Throwable $e) {
            Log::error("InstallModuleJob failed for {$this->moduleKey}: {$e->getMessage()}");

            cache()->put("sword_progress_{$this->moduleKey}", [
                'module' => $this->moduleKey,
                'message' => "Failed: {$e->getMessage()}",
                'percent' => -1,
                'status' => 'failed',
                'timestamp' => now()->toISOString(),
            ], 300);

            throw $e;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        cache()->put("sword_progress_{$this->moduleKey}", [
            'module' => $this->moduleKey,
            'message' => 'Installation failed: ' . ($exception?->getMessage() ?? 'Unknown error'),
            'percent' => -1,
            'status' => 'failed',
            'timestamp' => now()->toISOString(),
        ], 300);
    }
}
