<?php

namespace App\Services\Sword\Versification;

/**
 * Shared logic for all SWORD versification systems.
 *
 * Subclasses provide book lists, verse counts, and human-readable names.
 * This base implements flat-index computation, slot counting, and navigation.
 */
abstract class AbstractVersification implements VersificationInterface
{
    private ?array $otOffsets = null;
    private ?array $ntOffsets = null;

    /** @return array<string> OT books in canonical order */
    abstract protected function otBooks(): array;

    /** @return array<string> NT books in canonical order */
    abstract protected function ntBooks(): array;

    /** @return array<string, array<int>> Book OSIS ID → verse counts per chapter */
    abstract protected function verseCounts(): array;

    /** @return array<string, string> OSIS ID → human-readable name */
    abstract protected function bookNames(): array;

    public function getTestament(string $osisId): string
    {
        if (in_array($osisId, $this->otBooks(), true)) {
            return 'ot';
        }
        if (in_array($osisId, $this->ntBooks(), true)) {
            return 'nt';
        }
        throw new \InvalidArgumentException("Unknown book: {$osisId}");
    }

    public function getBookIndex(string $osisId): int
    {
        $testament = $this->getTestament($osisId);
        $books = $testament === 'ot' ? $this->otBooks() : $this->ntBooks();
        $index = array_search($osisId, $books, true);
        if ($index === false) {
            throw new \InvalidArgumentException("Book not found: {$osisId}");
        }
        return $index;
    }

    public function getChapterCount(string $osisId): int
    {
        $counts = $this->verseCounts();
        if (!isset($counts[$osisId])) {
            throw new \InvalidArgumentException("Unknown book: {$osisId}");
        }
        return count($counts[$osisId]);
    }

    public function getVerseCount(string $osisId, int $chapter): int
    {
        $counts = $this->verseCounts();
        if (!isset($counts[$osisId])) {
            throw new \InvalidArgumentException("Unknown book: {$osisId}");
        }
        $chapterCounts = $counts[$osisId];
        if ($chapter < 1 || $chapter > count($chapterCounts)) {
            throw new \InvalidArgumentException("Invalid chapter {$chapter} for {$osisId}");
        }
        return $chapterCounts[$chapter - 1];
    }

    public function bookSlotCount(string $osisId): int
    {
        $counts = $this->verseCounts()[$osisId];
        $total = 1; // chapter 0, verse 0 (book intro)
        foreach ($counts as $maxVerse) {
            $total += 1 + $maxVerse; // verse 0 (heading) + verses 1..maxVerse
        }
        return $total;
    }

    public function flatIndex(string $osisId, int $chapter, int $verse): int
    {
        $offsets = $this->getTestamentOffsets($osisId);
        $bookIndex = $this->getBookIndex($osisId);

        $index = $offsets[$bookIndex];

        if ($chapter === 0) {
            return $index + $verse;
        }

        $index += 1; // skip chapter 0

        $counts = $this->verseCounts()[$osisId];
        for ($c = 1; $c < $chapter; $c++) {
            $index += 1 + $counts[$c - 1];
        }

        $index += $verse;

        return $index;
    }

    public function getBooksForTestament(string $testament): array
    {
        return $testament === 'ot' ? $this->otBooks() : $this->ntBooks();
    }

    public function getAllBooks(): array
    {
        return array_merge($this->otBooks(), $this->ntBooks());
    }

    public function getBookName(string $osisId): string
    {
        return $this->bookNames()[$osisId] ?? $osisId;
    }

    public function getBookOrder(string $osisId): int
    {
        $otIndex = array_search($osisId, $this->otBooks(), true);
        if ($otIndex !== false) {
            return $otIndex + 1;
        }
        $ntIndex = array_search($osisId, $this->ntBooks(), true);
        if ($ntIndex !== false) {
            return count($this->otBooks()) + $ntIndex + 1;
        }
        throw new \InvalidArgumentException("Unknown book: {$osisId}");
    }

    public function getTotalVerses(string $osisId): int
    {
        return array_sum($this->verseCounts()[$osisId]);
    }

    private function getTestamentOffsets(string $osisId): array
    {
        $testament = $this->getTestament($osisId);

        if ($testament === 'ot') {
            return $this->otOffsets ??= $this->computeOffsets($this->otBooks());
        }
        return $this->ntOffsets ??= $this->computeOffsets($this->ntBooks());
    }

    private function computeOffsets(array $books): array
    {
        $offsets = [];
        $cumulative = 2; // SWORD reserves 2 slots at start of each testament
        foreach ($books as $osisId) {
            $offsets[] = $cumulative;
            $cumulative += $this->bookSlotCount($osisId);
        }
        return $offsets;
    }
}
