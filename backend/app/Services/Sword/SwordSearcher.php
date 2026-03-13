<?php

namespace App\Services\Sword;

use App\Models\Module;
use PDO;

/**
 * Full-text search over SWORD binary modules via SQLite FTS5.
 *
 * Builds a per-module FTS5 index by scanning all verses from binary data,
 * then queries it for fast substring and phrase searches without needing
 * the PostgreSQL verses table.
 */
class SwordSearcher
{
    private SwordManager $manager;

    public function __construct(SwordManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Build an FTS5 search index for a module by reading all verses from binary.
     */
    public function buildIndex(Module $module, ?\Closure $progress = null): int
    {
        $reader = $this->manager->getTextReader($module);
        $filter = $this->manager->getFilter($module);
        $versification = $this->manager->getVersification($module);

        $dbPath = $this->indexPath($module);
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Remove old index
        if (file_exists($dbPath)) {
            unlink($dbPath);
        }

        $pdo = new PDO("sqlite:{$dbPath}");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('CREATE VIRTUAL TABLE verses USING fts5(osis_id, chapter, verse, text, book_name)');

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO verses (osis_id, chapter, verse, text, book_name) VALUES (?, ?, ?, ?, ?)');
        $count = 0;

        foreach ($versification->getAllBooks() as $osisId) {
            $chapterCount = $versification->getChapterCount($osisId);
            $bookName = $versification->getBookName($osisId);

            for ($ch = 1; $ch <= $chapterCount; $ch++) {
                try {
                    $chapterVerses = $reader->readChapter($osisId, $ch);
                } catch (\Throwable) {
                    continue;
                }

                foreach ($chapterVerses as $verseNum => $raw) {
                    $plain = $filter->toPlainText($raw);
                    if ($plain === '') {
                        continue;
                    }

                    $stmt->execute([$osisId, $ch, $verseNum, $plain, $bookName]);
                    $count++;
                }
            }

            if ($progress) {
                $progress($osisId, $count);
            }
        }

        $pdo->commit();

        return $count;
    }

    /**
     * Search the FTS5 index for a module.
     *
     * @return array{hits: array, total: int}
     */
    public function search(Module $module, string $query, int $limit = 25, int $offset = 0): array
    {
        if (!$this->hasIndex($module)) {
            return ['hits' => [], 'total' => 0];
        }

        $pdo = new PDO("sqlite:{$this->indexPath($module)}");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Use FTS5 MATCH for the search — wrap in quotes for exact phrase, or use as-is
        $ftsQuery = $this->buildFtsQuery($query);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM verses WHERE verses MATCH ?");
        $countStmt->execute([$ftsQuery]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT osis_id, chapter, verse, snippet(verses, 3, '<mark>', '</mark>', '...', 40) as snippet, book_name
             FROM verses
             WHERE verses MATCH ?
             ORDER BY rank
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$ftsQuery, $limit, $offset]);

        $hits = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $hits[] = [
                'book_osis_id'   => $row['osis_id'],
                'book_name'      => $row['book_name'],
                'chapter_number' => (int) $row['chapter'],
                'verse_number'   => (int) $row['verse'],
                'reference'      => $row['book_name'] . ' ' . $row['chapter'] . ':' . $row['verse'],
                'highlight'      => $row['snippet'],
            ];
        }

        return ['hits' => $hits, 'total' => $total];
    }

    /**
     * Check if an FTS5 index exists for a module.
     */
    public function hasIndex(Module $module): bool
    {
        return file_exists($this->indexPath($module));
    }

    /**
     * Delete the index for a module.
     */
    public function deleteIndex(Module $module): void
    {
        $path = $this->indexPath($module);
        foreach ([$path, "{$path}-wal", "{$path}-shm"] as $f) {
            if (file_exists($f)) {
                unlink($f);
            }
        }
    }

    /**
     * Get the SQLite database path for a module's search index.
     */
    private function indexPath(Module $module): string
    {
        return storage_path('app/sword-search/' . strtolower($module->key) . '.db');
    }

    /**
     * Build an FTS5-compatible query string.
     *
     * If the input looks like a phrase (multiple words), wrap in quotes.
     * Single words are searched as-is with implicit prefix matching.
     */
    private function buildFtsQuery(string $query): string
    {
        $query = trim($query);

        // If already quoted, use as-is
        if (str_starts_with($query, '"') && str_ends_with($query, '"')) {
            return $query;
        }

        // Multiple words → phrase search on text column
        if (str_contains($query, ' ')) {
            return 'text : "' . str_replace('"', '', $query) . '"';
        }

        // Single word — prefix match on text column
        return 'text : ' . str_replace('"', '', $query) . '*';
    }
}
