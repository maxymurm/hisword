<?php

declare(strict_types=1);

namespace App\Services\Bintex;

use RuntimeException;

/**
 * PHP port of yuku.alkitab.yes1.Yes1Reader — reads YES1 (legacy) Bible files.
 *
 * YES1 header: 0x98 0x58 0x0D 0x0A 0x00 0x5D 0xE0 0x01
 * Sequential sections with 12-byte padded names (underscore-padded).
 * Section format: [12-byte name][4-byte big-endian size][data...]
 * Verse text: newline (0x0a) separated within chapter blocks.
 * No xrefs or footnotes support.
 */
class Yes1Reader
{
    private const HEADER = "\x98\x58\x0D\x0A\x00\x5D\xE0\x01";
    private const HEADER_LENGTH = 8;
    private const SECTION_NAME_LENGTH = 12;

    private string $data;
    private int $length;

    // Parsed info
    private ?array $versionInfo = null;
    private ?array $booksInfo = null;
    private ?int $textBaseOffset = null;

    public function __construct(string $data)
    {
        $this->data = $data;
        $this->length = strlen($data);
        $this->parseHeader();
    }

    public static function fromFile(string $path): self
    {
        $data = file_get_contents($path);
        if ($data === false) {
            throw new RuntimeException("Cannot read file: {$path}");
        }
        return new self($data);
    }

    private function parseHeader(): void
    {
        if ($this->length < self::HEADER_LENGTH) {
            throw new RuntimeException('YES1: File too small for header');
        }
        $header = substr($this->data, 0, self::HEADER_LENGTH);
        if ($header !== self::HEADER) {
            throw new RuntimeException('YES1: Invalid header');
        }
    }

    /**
     * Find a section by name. Scans sequentially from offset 8.
     *
     * @return array{offset: int, size: int}|null
     */
    private function findSection(string $sectionName): ?array
    {
        // Pad section name to 12 bytes with underscores
        $paddedName = str_pad($sectionName, self::SECTION_NAME_LENGTH, '_');
        $pos = self::HEADER_LENGTH;

        while ($pos + self::SECTION_NAME_LENGTH + 4 <= $this->length) {
            $name = substr($this->data, $pos, self::SECTION_NAME_LENGTH);
            $pos += self::SECTION_NAME_LENGTH;

            // Read 4-byte big-endian size
            $size = unpack('N', substr($this->data, $pos, 4))[1];
            if ($size >= 0x80000000) {
                $size -= 0x100000000;
            }
            $pos += 4;

            if ($name === '____________') {
                // End marker
                return null;
            }

            if ($name === $paddedName) {
                return ['offset' => $pos, 'size' => $size];
            }

            // Skip this section's data
            $pos += $size;
        }

        return null;
    }

    // -- Version Info --

    public function getVersionInfo(): array
    {
        if ($this->versionInfo !== null) {
            return $this->versionInfo;
        }

        $section = $this->findSection('infoEdisi');
        if ($section === null) {
            throw new RuntimeException('YES1: infoEdisi section not found');
        }

        $sectionData = substr($this->data, $section['offset'], $section['size']);
        $br = new BintexReader($sectionData);

        $info = [
            'shortName' => null,
            'longName' => null,
            'description' => null,
            'locale' => null,
            'book_count' => 0,
            'hasPericopes' => 0,
            'textEncoding' => 1,
        ];

        while (true) {
            $key = $br->readShortString();

            switch ($key) {
                case 'versi':
                case 'format':
                    $br->readInt();
                    break;
                case 'nama':
                    $br->readShortString(); // internal name, not used
                    break;
                case 'shortName':
                case 'shortTitle':
                    $info['shortName'] = $br->readShortString();
                    break;
                case 'judul':
                    $info['longName'] = $br->readShortString();
                    break;
                case 'keterangan':
                    $info['description'] = $br->readLongString();
                    break;
                case 'nkitab':
                    $info['book_count'] = $br->readInt();
                    break;
                case 'perikopAda':
                    $info['hasPericopes'] = $br->readInt();
                    break;
                case 'encoding':
                    $info['textEncoding'] = $br->readInt();
                    break;
                case 'locale':
                    $info['locale'] = $br->readShortString();
                    break;
                case 'end':
                    break 2;
                default:
                    // Unknown key — skip. In original Java this throws; we're more lenient.
                    break 2;
            }
        }

        $this->versionInfo = $info;
        return $this->versionInfo;
    }

    // -- Books Info --

    /**
     * @return array<int, array{bookId: int, shortName: ?string, offset: int, chapter_count: int, verse_counts: int[], chapter_offsets: int[]}>
     */
    public function getBooksInfo(): array
    {
        if ($this->booksInfo !== null) {
            return $this->booksInfo;
        }

        $versionInfo = $this->getVersionInfo();
        $bookCount = $versionInfo['book_count'];

        $section = $this->findSection('infoKitab');
        if ($section === null) {
            throw new RuntimeException('YES1: infoKitab section not found');
        }

        $sectionData = substr($this->data, $section['offset'], $section['size']);
        $br = new BintexReader($sectionData);

        $books = [];
        for ($bookIndex = 0; $bookIndex < $bookCount; $bookIndex++) {
            $book = [
                'bookId' => -1,
                'shortName' => null,
                'abbreviation' => null,
                'offset' => 0,
                'chapter_count' => 0,
                'verse_counts' => [],
                'chapter_offsets' => [],
            ];
            $empty = false;

            for ($keyIndex = 0; ; $keyIndex++) {
                $key = $br->readShortString();

                switch ($key) {
                    case 'versi':
                        $br->readInt();
                        break;
                    case 'pos':
                        $book['bookId'] = $br->readInt();
                        break;
                    case 'nama':
                    case 'judul':
                        $book['shortName'] = $br->readShortString();
                        break;
                    case 'npasal':
                        $book['chapter_count'] = $br->readInt();
                        break;
                    case 'nayat':
                        $verseCounts = [];
                        for ($i = 0; $i < $book['chapter_count']; $i++) {
                            $verseCounts[] = $br->readUint8();
                        }
                        $book['verse_counts'] = $verseCounts;
                        break;
                    case 'ayatLoncat':
                    case 'pdbBookNumber':
                        $br->readInt(); // ignored
                        break;
                    case 'pasal_offset':
                        // YES1 has chapter_count + 1 offsets
                        $offsets = [];
                        for ($i = 0; $i <= $book['chapter_count']; $i++) {
                            $offsets[] = $br->readInt();
                        }
                        $book['chapter_offsets'] = $offsets;
                        break;
                    case 'encoding':
                        $br->readInt(); // ignored, deprecated
                        break;
                    case 'offset':
                        $book['offset'] = $br->readInt();
                        break;
                    case 'end':
                        if ($keyIndex === 0) {
                            $empty = true;
                        }
                        break 2;
                    default:
                        break 2;
                }
            }

            if (!$empty && $book['bookId'] >= 0) {
                $books[$book['bookId']] = $book;
            }
        }

        $this->booksInfo = $books;
        return $this->booksInfo;
    }

    // -- Text --

    private function getTextBaseOffset(): int
    {
        if ($this->textBaseOffset !== null) {
            return $this->textBaseOffset;
        }

        $section = $this->findSection('teks');
        if ($section === null) {
            throw new RuntimeException('YES1: teks section not found');
        }

        $this->textBaseOffset = $section['offset'];
        return $this->textBaseOffset;
    }

    /**
     * Load verse texts for a specific book and chapter.
     * YES1 stores chapter text as newline-separated (0x0a) verse blocks.
     *
     * @return string[]
     */
    public function loadVerseText(int $bookId, int $chapter1): array
    {
        $books = $this->getBooksInfo();
        if (!isset($books[$bookId])) {
            throw new RuntimeException("YES1: Book ID {$bookId} not found");
        }

        $book = $books[$bookId];
        if ($chapter1 <= 0 || $chapter1 > $book['chapter_count']) {
            throw new RuntimeException("YES1: Chapter {$chapter1} out of range for book {$bookId}");
        }

        $versionInfo = $this->getVersionInfo();
        $textBaseOffset = $this->getTextBaseOffset();

        $seekTo = $textBaseOffset + $book['offset'] + $book['chapter_offsets'][$chapter1 - 1];
        $chapterLength = $book['chapter_offsets'][$chapter1] - $book['chapter_offsets'][$chapter1 - 1];

        if ($chapterLength <= 0) {
            return [];
        }

        $raw = substr($this->data, $seekTo, $chapterLength);

        // Split by newline (0x0a)
        $parts = explode("\n", $raw);

        // Remove trailing empty string if any
        if (count($parts) > 0 && $parts[count($parts) - 1] === '') {
            array_pop($parts);
        }

        // Convert encoding if needed
        if ($versionInfo['textEncoding'] === 1) {
            // ASCII / Latin-1
            return array_map(fn ($v) => mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1'), $parts);
        }

        // UTF-8 (encoding == 2)
        return $parts;
    }

    // -- Pericopes --

    /**
     * Load pericope data from perikopIndex and perikopBlok sections.
     *
     * @return array<int, array{ari: int, title: ?string, parallels: string[]}>
     */
    public function loadPericopes(): array
    {
        $versionInfo = $this->getVersionInfo();
        if (($versionInfo['hasPericopes'] ?? 0) === 0) {
            return [];
        }

        $indexSection = $this->findSection('perikopIndex');
        if ($indexSection === null) {
            return [];
        }

        $indexData = substr($this->data, $indexSection['offset'], $indexSection['size']);
        $br = new BintexReader($indexData);

        // Read pericope index
        $version = $br->readUint8();
        $entryCount = $br->readInt();

        $aris = [];
        $offsets = [];
        for ($i = 0; $i < $entryCount; $i++) {
            $aris[] = $br->readInt();
            $offsets[] = $br->readInt();
        }

        // Read pericope blocks
        $blockSection = $this->findSection('perikopBlok');
        if ($blockSection === null) {
            return [];
        }

        $blockData = substr($this->data, $blockSection['offset'], $blockSection['size']);
        $result = [];

        for ($i = 0; $i < $entryCount; $i++) {
            $bbr = new BintexReader($blockData, $offsets[$i]);

            $blockVersion = $bbr->readUint8();
            $title = $bbr->readValueString();
            $parallelCount = $bbr->readUint8();
            $parallels = [];
            for ($j = 0; $j < $parallelCount; $j++) {
                $parallels[] = $bbr->readValueString();
            }

            $result[] = [
                'ari' => $aris[$i],
                'title' => $title,
                'parallels' => $parallels,
            ];
        }

        return $result;
    }

    /**
     * Get pericopes for a specific chapter.
     *
     * @return array<int, array{ari: int, title: ?string, parallels: string[]}>
     */
    public function getPericopesForChapter(int $bookId, int $chapter1): array
    {
        $allPericopes = $this->loadPericopes();
        $result = [];

        foreach ($allPericopes as $pericope) {
            $ari = $pericope['ari'];
            $periBookId = ($ari >> 16) & 0xff;
            $periChapter = ($ari >> 8) & 0xff;

            if ($periBookId === $bookId && $periChapter === $chapter1) {
                $result[] = $pericope;
            }
        }

        return $result;
    }

    // YES1 does not support xrefs or footnotes
    public function getXrefEntry(int $arif): ?string
    {
        return null;
    }

    public function getFootnoteEntry(int $arif): ?string
    {
        return null;
    }
}
