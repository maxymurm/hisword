<?php

declare(strict_types=1);

namespace App\Console\Commands\Bintex;

use App\Services\Bintex\BintexManager;
use Illuminate\Console\Command;

class ReadCommand extends Command
{
    protected $signature = 'bintex:read
        {file : Path to YES file (absolute or relative to bintex storage)}
        {book : Book ID (0=Genesis, 65=Revelation)}
        {chapter : Chapter number (1-based)}
        {--verse= : Specific verse (1-based), omit for entire chapter}';

    protected $description = 'Read and display verse text from a YES1/YES2 Bible module file';

    public function handle(BintexManager $manager): int
    {
        $file = $this->argument('file');
        $bookId = (int) $this->argument('book');
        $chapter = (int) $this->argument('chapter');
        $verse = $this->option('verse') !== null ? (int) $this->option('verse') : null;

        try {
            if ($verse !== null) {
                $text = $manager->readVerse($file, $bookId, $chapter, $verse);
                if ($text === null) {
                    $this->warn("Verse not found.");
                    return self::FAILURE;
                }
                $this->info("Book {$bookId}, Chapter {$chapter}, Verse {$verse}:");
                $this->line($text);
            } else {
                $verses = $manager->readChapter($file, $bookId, $chapter);
                if (empty($verses)) {
                    $this->warn("No verses found.");
                    return self::FAILURE;
                }
                $this->info("Book {$bookId}, Chapter {$chapter} (" . count($verses) . " verses):");
                foreach ($verses as $num => $text) {
                    $this->line("  {$num}: {$text}");
                }
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
