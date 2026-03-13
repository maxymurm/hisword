<?php

namespace App\Services\Sword\Versification;

interface VersificationInterface
{
    public function getTestament(string $osisId): string;

    public function getBookIndex(string $osisId): int;

    public function getChapterCount(string $osisId): int;

    public function getVerseCount(string $osisId, int $chapter): int;

    public function bookSlotCount(string $osisId): int;

    public function flatIndex(string $osisId, int $chapter, int $verse): int;

    /** @return array<string> */
    public function getAllBooks(): array;

    public function getBookName(string $osisId): string;

    public function getBookOrder(string $osisId): int;

    public function getTotalVerses(string $osisId): int;

    /** @return array<string> */
    public function getBooksForTestament(string $testament): array;
}
