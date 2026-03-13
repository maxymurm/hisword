<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Module;
use App\Services\Bintex\BintexManager;
use App\Services\Sword\SwordManager;

/**
 * Unified factory for reading Bible content from any engine (SWORD or Bintex).
 *
 * Dispatches to the correct reader based on module.engine column.
 */
class BibleReaderFactory
{
    public function __construct(
        private SwordManager $swordManager,
        private BintexManager $bintexManager,
    ) {}

    /**
     * Read a single verse from a module.
     *
     * @return array{raw: string|null, html: string|null, plain: string|null}
     */
    public function readVerse(Module $module, string $bookRef, int $chapter, int $verse): array
    {
        return match ($module->engine ?? 'sword') {
            'bintex' => $this->readBintexVerse($module, $bookRef, $chapter, $verse),
            default  => $this->swordManager->readVerse($module, $bookRef, $chapter, $verse),
        };
    }

    /**
     * Read an entire chapter from a module.
     *
     * @return array<int, array{raw: string, html: string, plain: string}>
     */
    public function readChapter(Module $module, string $bookRef, int $chapter): array
    {
        return match ($module->engine ?? 'sword') {
            'bintex' => $this->readBintexChapter($module, $bookRef, $chapter),
            default  => $this->swordManager->readChapter($module, $bookRef, $chapter),
        };
    }

    /**
     * Check if a module's data files are accessible.
     */
    public function hasDataFiles(Module $module): bool
    {
        return match ($module->engine ?? 'sword') {
            'bintex' => $module->data_path !== null && file_exists($module->data_path),
            default  => $this->swordManager->hasDataFiles($module),
        };
    }

    /**
     * Read a verse from a Bintex YES1/YES2 module.
     *
     * @return array{raw: string|null, html: string|null, plain: string|null}
     */
    private function readBintexVerse(Module $module, string $bookRef, int $chapter, int $verse): array
    {
        $bookId = $this->resolveBookId($bookRef);
        $raw = $this->bintexManager->readVerse($module->data_path, $bookId, $chapter, $verse);

        if ($raw === null) {
            return ['raw' => null, 'html' => null, 'plain' => null];
        }

        return [
            'raw' => $raw,
            'html' => e($raw),
            'plain' => $raw,
        ];
    }

    /**
     * Read a chapter from a Bintex YES1/YES2 module.
     *
     * @return array<int, array{raw: string, html: string, plain: string}>
     */
    private function readBintexChapter(Module $module, string $bookRef, int $chapter): array
    {
        $bookId = $this->resolveBookId($bookRef);
        $rawVerses = $this->bintexManager->readChapter($module->data_path, $bookId, $chapter);
        $result = [];

        foreach ($rawVerses as $verseNum => $raw) {
            $result[$verseNum] = [
                'raw' => $raw,
                'html' => e($raw),
                'plain' => $raw,
            ];
        }

        return $result;
    }

    /**
     * Map an OSIS book ID to a numeric book ID for Bintex.
     *
     * Uses the standard Protestant 66-book ordering (Gen=1..Rev=66).
     */
    private function resolveBookId(string $osisId): int
    {
        return self::OSIS_TO_BOOK_ID[$osisId]
            ?? throw new \InvalidArgumentException("Unknown OSIS book ID: {$osisId}");
    }

    private const OSIS_TO_BOOK_ID = [
        'Gen' => 1, 'Exod' => 2, 'Lev' => 3, 'Num' => 4, 'Deut' => 5,
        'Josh' => 6, 'Judg' => 7, 'Ruth' => 8, '1Sam' => 9, '2Sam' => 10,
        '1Kgs' => 11, '2Kgs' => 12, '1Chr' => 13, '2Chr' => 14, 'Ezra' => 15,
        'Neh' => 16, 'Esth' => 17, 'Job' => 18, 'Ps' => 19, 'Prov' => 20,
        'Eccl' => 21, 'Song' => 22, 'Isa' => 23, 'Jer' => 24, 'Lam' => 25,
        'Ezek' => 26, 'Dan' => 27, 'Hos' => 28, 'Joel' => 29, 'Amos' => 30,
        'Obad' => 31, 'Jonah' => 32, 'Mic' => 33, 'Nah' => 34, 'Hab' => 35,
        'Zeph' => 36, 'Hag' => 37, 'Zech' => 38, 'Mal' => 39,
        'Matt' => 40, 'Mark' => 41, 'Luke' => 42, 'John' => 43, 'Acts' => 44,
        'Rom' => 45, '1Cor' => 46, '2Cor' => 47, 'Gal' => 48, 'Eph' => 49,
        'Phil' => 50, 'Col' => 51, '1Thess' => 52, '2Thess' => 53, '1Tim' => 54,
        '2Tim' => 55, 'Titus' => 56, 'Phlm' => 57, 'Heb' => 58, 'Jas' => 59,
        '1Pet' => 60, '2Pet' => 61, '1John' => 62, '2John' => 63, '3John' => 64,
        'Jude' => 65, 'Rev' => 66,
    ];
}
