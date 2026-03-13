<?php

namespace App\Console\Commands;

use App\Models\Verse;
use Illuminate\Console\Command;

class IndexVerses extends Command
{
    protected $signature = 'verses:index
                            {--flush : Flush the index before re-indexing}
                            {--chunk=500 : Number of records per chunk}';

    protected $description = 'Index (or re-index) all Bible verses into Meilisearch';

    public function handle(): int
    {
        $this->info('Starting verse indexing…');

        if ($this->option('flush')) {
            $this->warn('Flushing existing verse index…');
            Verse::removeAllFromSearch();
            $this->info('Index flushed.');
        }

        $chunkSize = (int) $this->option('chunk');
        $total = Verse::count();

        if ($total === 0) {
            $this->warn('No verses found in the database. Make sure Bible content is imported first.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        Verse::with('module')
            ->orderBy('id')
            ->chunk($chunkSize, function ($verses) use ($bar) {
                $verses->searchable();
                $bar->advance($verses->count());
            });

        $bar->finish();
        $this->newLine();
        $this->info("✅ Indexed {$total} verses successfully.");

        // Sync index settings (filterable, sortable, searchable attributes)
        $this->info('Syncing Meilisearch index settings…');
        $this->call('scout:sync-index-settings');

        return self::SUCCESS;
    }
}
