<?php

declare(strict_types=1);

namespace App\Console\Commands\Bintex;

use App\Services\Bintex\BintexManager;
use Illuminate\Console\Command;

class InfoCommand extends Command
{
    protected $signature = 'bintex:info
        {file : Path to YES file (absolute or relative to bintex storage)}';

    protected $description = 'Show version and book info for a YES1/YES2 Bible module file';

    public function handle(BintexManager $manager): int
    {
        $file = $this->argument('file');

        try {
            $info = $manager->getModuleInfo($file);

            $this->info('Module Info:');
            $this->table(
                ['Property', 'Value'],
                [
                    ['Format', "YES{$info['format']}"],
                    ['Short Name', $info['shortName'] ?? '(none)'],
                    ['Long Name', $info['longName'] ?? '(none)'],
                    ['Description', $info['description'] ? mb_substr($info['description'], 0, 80) . '...' : '(none)'],
                    ['Locale', $info['locale'] ?? '(none)'],
                    ['Book Count', $info['book_count']],
                    ['Has Pericopes', $info['hasPericopes'] ? 'Yes' : 'No'],
                    ['Text Encoding', $info['textEncoding'] === 1 ? 'ASCII' : 'UTF-8'],
                ]
            );

            $books = $manager->listBooks($file);
            $this->newLine();
            $this->info('Books (' . count($books) . '):');
            $this->table(
                ['Book ID', 'Short Name', 'Chapters'],
                array_map(fn ($b) => [$b['bookId'], $b['shortName'] ?? '(none)', $b['chapter_count']], $books)
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
