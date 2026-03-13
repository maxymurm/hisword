<?php

declare(strict_types=1);

namespace App\Console\Commands\Bintex;

use App\Services\Bintex\BintexManager;
use Illuminate\Console\Command;

class VerifyCommand extends Command
{
    protected $signature = 'bintex:verify
        {file : Path to YES file (absolute or relative to bintex storage)}
        {--read-sample : Also read a sample verse to verify text extraction}';

    protected $description = 'Verify a YES1/YES2 file can be opened and read correctly';

    public function handle(BintexManager $manager): int
    {
        $file = $this->argument('file');

        try {
            $result = $manager->verify($file);

            if (!$result['valid']) {
                $this->error("INVALID: {$result['error']}");
                return self::FAILURE;
            }

            $info = $result['info'];
            $this->info("VALID YES{$result['format']} file");
            $this->line("  Short Name:    " . ($info['shortName'] ?? '(none)'));
            $this->line("  Long Name:     " . ($info['longName'] ?? '(none)'));
            $this->line("  Locale:        " . ($info['locale'] ?? '(none)'));
            $this->line("  Books:         {$info['book_count']}");
            $this->line("  Text Encoding: " . ($info['textEncoding'] === 1 ? 'ASCII' : 'UTF-8'));

            if ($this->option('read-sample')) {
                $this->newLine();
                $this->info('Reading sample verse (first book, chapter 1, verse 1):');

                $books = $manager->listBooks($file);
                if (!empty($books)) {
                    $firstBook = $books[0];
                    $text = $manager->readVerse($file, $firstBook['bookId'], 1, 1);
                    if ($text !== null) {
                        $this->line("  [{$firstBook['shortName']} 1:1] {$text}");
                    } else {
                        $this->warn("  Could not read sample verse.");
                    }
                }
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
