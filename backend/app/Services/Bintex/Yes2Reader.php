<?php

declare(strict_types=1);

namespace App\Services\Bintex;

use RuntimeException;

/**
 * PHP port of yuku.alkitab.yes2.Yes2Reader — reads YES2 Bible files.
 *
 * YES2 header: 0x98 0x58 0x0D 0x0A 0x00 0x5D 0xE0 0x02
 * Section index starts at offset 12.
 * Sections: versionInfo, booksInfo, text, pericopes, xrefs, footnotes.
 */
class Yes2Reader
{
    private const HEADER = "\x98\x58\x0D\x0A\x00\x5D\xE0\x02";
    private const HEADER_LENGTH = 8;
    private const SECTION_INDEX_OFFSET = 12;

    private string $data;

    /** @var array<string, array{name: string, offset: int, attributes_size: int, content_size: int}> */
    private array $sectionEntries = [];

    private int $sectionDataStartOffset = 0;

    // Cached section data
    private ?array $versionInfo = null;
    /** @var array<int, array<string, mixed>>|null */
    private ?array $booksInfo = null;

    public function __construct(string $data)
    {
        $this->data = $data;
        $this->parseHeader();
        $this->parseSectionIndex();
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
        if (strlen($this->data) < self::HEADER_LENGTH) {
            throw new RuntimeException('YES2: File too small for header');
        }
        $header = substr($this->data, 0, self::HEADER_LENGTH);
        if ($header !== self::HEADER) {
            throw new RuntimeException('YES2: Invalid header');
        }
    }

    private function parseSectionIndex(): void
    {
        $br = new BintexReader($this->data, self::SECTION_INDEX_OFFSET);

        $version = $br->readUint8();
        if ($version !== 1) {
            throw new RuntimeException("YES2: Unsupported section index version: {$version}");
        }

        $sectionCount = $br->readInt();

        for ($i = 0; $i < $sectionCount; $i++) {
            $nameLen = $br->readUint8();
            $nameRaw = $br->readRaw($nameLen);
            $name = mb_convert_encoding($nameRaw, 'UTF-8', 'ISO-8859-1');

            $offset = $br->readInt();
            $attributesSize = $br->readInt();
            $contentSize = $br->readInt();
            $br->skip(4); // reserved

            $this->sectionEntries[$name] = [
                'name' => $name,
                'offset' => $offset,
                'attributes_size' => $attributesSize,
                'content_size' => $contentSize,
            ];
        }

        $this->sectionDataStartOffset = $br->getPos();
    }

    /**
     * Get absolute offset for a section's attributes.
     */
    private function getSectionAttributesOffset(string $name): ?int
    {
        $entry = $this->sectionEntries[$name] ?? null;
        if ($entry === null) {
            return null;
        }
        return $this->sectionDataStartOffset + $entry['offset'];
    }

    /**
     * Get absolute offset for a section's content (after attributes).
     */
    private function getSectionContentOffset(string $name): ?int
    {
        $entry = $this->sectionEntries[$name] ?? null;
        if ($entry === null) {
            return null;
        }
        return $this->sectionDataStartOffset + $entry['offset'] + $entry['attributes_size'];
    }

    /**
     * Read section attributes as a SimpleMap.
     *
     * @return array<string, mixed>|null
     */
    private function readSectionAttributes(string $name): ?array
    {
        $offset = $this->getSectionAttributesOffset($name);
        if ($offset === null) {
            return null;
        }
        $br = new BintexReader($this->data, $offset);
        return $br->readValueSimpleMap();
    }

    /**
     * Get a list of all section names in this file.
     *
     * @return string[]
     */
    public function getSectionNames(): array
    {
        return array_keys($this->sectionEntries);
    }

    // -- Version Info --

    /**
     * @return array{shortName: ?string, longName: ?string, description: ?string, locale: ?string, book_count: int, hasPericopes: int, textEncoding: int}
     */
    public function getVersionInfo(): array
    {
        if ($this->versionInfo !== null) {
            return $this->versionInfo;
        }

        $offset = $this->getSectionContentOffset('versionInfo');
        if ($offset === null) {
            throw new RuntimeException('YES2: versionInfo section not found');
        }

        $br = new BintexReader($this->data, $offset);
        $map = $br->readValueSimpleMap();

        $this->versionInfo = [
            'shortName' => $map['shortName'] ?? null,
            'longName' => $map['longName'] ?? null,
            'description' => $map['description'] ?? null,
            'locale' => $map['locale'] ?? null,
            'book_count' => (int) ($map['book_count'] ?? 0),
            'hasPericopes' => (int) ($map['hasPericopes'] ?? 0),
            'textEncoding' => (int) ($map['textEncoding'] ?? 2),
        ];

        return $this->versionInfo;
    }

    // -- Books Info --

    /**
     * @return array<int, array{bookId: int, shortName: ?string, abbreviation: ?string, offset: int, chapter_count: int, verse_counts: int[], chapter_offsets: int[]}>
     */
    public function getBooksInfo(): array
    {
        if ($this->booksInfo !== null) {
            return $this->booksInfo;
        }

        $offset = $this->getSectionContentOffset('booksInfo');
        if ($offset === null) {
            throw new RuntimeException('YES2: booksInfo section not found');
        }

        $br = new BintexReader($this->data, $offset);
        $bookCount = $br->readInt();

        $books = [];
        for ($i = 0; $i < $bookCount; $i++) {
            $map = $br->readValueSimpleMap();
            $books[] = [
                'bookId' => (int) ($map['bookId'] ?? -1),
                'shortName' => $map['shortName'] ?? null,
                'abbreviation' => $map['abbreviation'] ?? null,
                'offset' => (int) ($map['offset'] ?? 0),
                'chapter_count' => (int) ($map['chapter_count'] ?? 0),
                'verse_counts' => $map['verse_counts'] ?? [],
                'chapter_offsets' => $map['chapter_offsets'] ?? [],
            ];
        }

        $this->booksInfo = $books;
        return $this->booksInfo;
    }

    // -- Text section --

    /**
     * Load verse texts for a specific book and chapter.
     *
     * @return string[] Array of verse strings
     */
    public function loadVerseText(int $bookIndex, int $chapter1): array
    {
        $books = $this->getBooksInfo();
        if (!isset($books[$bookIndex])) {
            throw new RuntimeException("YES2: Book index {$bookIndex} not found");
        }

        $book = $books[$bookIndex];
        if ($chapter1 <= 0 || $chapter1 > $book['chapter_count']) {
            throw new RuntimeException("YES2: Chapter {$chapter1} out of range for book {$bookIndex}");
        }

        $versionInfo = $this->getVersionInfo();
        $textEncoding = $versionInfo['textEncoding'];

        // Section attributes for text section
        $sectionAttributes = $this->readSectionAttributes('text');
        $sectionContentOffset = $this->getSectionContentOffset('text');
        if ($sectionContentOffset === null) {
            throw new RuntimeException('YES2: text section not found');
        }

        // Compute content offset for this chapter within the text section
        $contentOffset = $book['offset'] + $book['chapter_offsets'][$chapter1 - 1];
        $verseCount = $book['verse_counts'][$chapter1 - 1];

        // Determine if compression is used
        $textData = $this->getTextSectionData($sectionAttributes, $sectionContentOffset);

        // Read verses from the decompressed text data
        $br = new BintexReader($textData, $contentOffset);

        return $this->readVerses($br, $verseCount, $textEncoding);
    }

    /**
     * Get the decompressed text section data.
     * If compressed with snappy-blocks, decompress all blocks.
     */
    private function getTextSectionData(?array $sectionAttributes, int $sectionContentOffset): string
    {
        if ($sectionAttributes === null) {
            // No attributes — uncompressed, return raw content
            $entry = $this->sectionEntries['text'];
            return substr($this->data, $sectionContentOffset, $entry['content_size']);
        }

        $compressionName = $sectionAttributes['compression.name'] ?? null;
        if ($compressionName === null) {
            $entry = $this->sectionEntries['text'];
            return substr($this->data, $sectionContentOffset, $entry['content_size']);
        }

        if ($compressionName !== 'snappy-blocks') {
            throw new RuntimeException("YES2: Unsupported compression: {$compressionName}");
        }

        $compressionInfo = $sectionAttributes['compression.info'] ?? [];
        $blockSize = (int) ($compressionInfo['block_size'] ?? 0);
        $compressedBlockSizes = $compressionInfo['compressed_block_sizes'] ?? [];

        if ($blockSize <= 0 || empty($compressedBlockSizes)) {
            throw new RuntimeException('YES2: Invalid snappy-blocks compression info');
        }

        // Compute offsets for each compressed block
        $offsets = [];
        $cumulative = 0;
        foreach ($compressedBlockSizes as $size) {
            $offsets[] = $cumulative;
            $cumulative += $size;
        }

        // Decompress all blocks
        $decompressed = '';
        foreach ($compressedBlockSizes as $i => $compSize) {
            $blockData = substr($this->data, $sectionContentOffset + $offsets[$i], $compSize);
            $decompressed .= SnappyDecompressor::decompress($blockData);
        }

        return $decompressed;
    }

    /**
     * Read verse texts from the bintex stream.
     *
     * @return string[]
     */
    private function readVerses(BintexReader $br, int $verseCount, int $textEncoding): array
    {
        $verses = [];
        for ($i = 0; $i < $verseCount; $i++) {
            $verseLen = $br->readVarUint();
            $raw = $br->readRaw($verseLen);

            if ($textEncoding === 1) {
                // ASCII / Latin-1
                $verses[] = mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
            } else {
                // UTF-8 (textEncoding == 2, default)
                $verses[] = $raw;
            }
        }
        return $verses;
    }

    // -- Pericopes --

    /**
     * Load pericope (section heading) data.
     *
     * @return array<int, array{ari: int, title: ?string, parallels: string[]}>
     */
    public function loadPericopes(): array
    {
        $versionInfo = $this->getVersionInfo();
        if (($versionInfo['hasPericopes'] ?? 0) === 0) {
            return [];
        }

        $sectionAttributes = $this->readSectionAttributes('pericopes');
        $contentOffset = $this->getSectionContentOffset('pericopes');
        if ($contentOffset === null) {
            return [];
        }

        // Handle compression
        $data = $this->getSectionDecompressedData('pericopes', $sectionAttributes, $contentOffset);
        $br = new BintexReader($data);

        $version = $br->readUint8();
        if ($version !== 2 && $version !== 3) {
            throw new RuntimeException("YES2: Unsupported pericope version: {$version}");
        }

        /* index_size = */ $br->readInt();
        $entryCount = $br->readInt();

        $aris = [];
        $offsets = [];

        if ($version === 2) {
            for ($i = 0; $i < $entryCount; $i++) {
                $aris[] = $br->readInt();
                $offsets[] = $br->readInt();
            }
        } else {
            // version 3 — delta-encoded aris and offsets
            $lastAri = 0;
            $lastOffset = 0;
            for ($i = 0; $i < $entryCount; $i++) {
                $dataAri = $br->readUint16();
                if (($dataAri & 0x8000) === 0) {
                    // absolute — 4 bytes total
                    $ari = ($dataAri << 16) | $br->readUint16();
                } else {
                    // relative delta
                    $ari = $lastAri + ($dataAri & 0x7fff);
                }
                $aris[] = $ari;
                $lastAri = $ari;

                $dataOffset = $br->readUint16();
                $offset = $lastOffset + $dataOffset;
                $offsets[] = $offset;
                $lastOffset = $offset;
            }
        }

        // Data begins after the index
        $dataStartPos = $br->getPos();
        $result = [];

        for ($i = 0; $i < $entryCount; $i++) {
            $br->setPos($dataStartPos + $offsets[$i]);

            $blockVersion = $br->readUint8();
            $title = $br->readValueString();
            $parallelCount = $br->readUint8();
            $parallels = [];
            for ($j = 0; $j < $parallelCount; $j++) {
                $parallels[] = $br->readValueString();
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

    // -- Xrefs --

    /**
     * Load cross-reference entries.
     *
     * @return array<int, array{arif: int, content: ?string}>
     */
    public function loadXrefs(): array
    {
        return $this->loadArifSection('xrefs');
    }

    /**
     * Get a specific cross-reference by ARIF.
     */
    public function getXrefEntry(int $arif): ?string
    {
        $xrefs = $this->loadXrefs();
        foreach ($xrefs as $entry) {
            if ($entry['arif'] === $arif) {
                return $entry['content'];
            }
        }
        return null;
    }

    // -- Footnotes --

    /**
     * Load footnote entries.
     *
     * @return array<int, array{arif: int, content: ?string}>
     */
    public function loadFootnotes(): array
    {
        return $this->loadArifSection('footnotes');
    }

    /**
     * Get a specific footnote by ARIF.
     */
    public function getFootnoteEntry(int $arif): ?string
    {
        $footnotes = $this->loadFootnotes();
        foreach ($footnotes as $entry) {
            if ($entry['arif'] === $arif) {
                return $entry['content'];
            }
        }
        return null;
    }

    // -- Generic ARIF section reader (shared by xrefs & footnotes) --

    /**
     * @return array<int, array{arif: int, content: ?string}>
     */
    private function loadArifSection(string $sectionName): array
    {
        $sectionAttributes = $this->readSectionAttributes($sectionName);
        $contentOffset = $this->getSectionContentOffset($sectionName);
        if ($contentOffset === null) {
            return [];
        }

        $data = $this->getSectionDecompressedData($sectionName, $sectionAttributes, $contentOffset);
        $br = new BintexReader($data);

        $version = $br->readUint8();
        if ($version !== 1) {
            throw new RuntimeException("YES2: Unsupported {$sectionName} version: {$version}");
        }

        $entryCount = $br->readInt();

        $arifs = [];
        for ($i = 0; $i < $entryCount; $i++) {
            $arifs[] = $br->readInt();
        }

        $offsets = [];
        for ($i = 0; $i < $entryCount; $i++) {
            $offsets[] = $br->readInt();
        }

        $contentStartPos = $br->getPos();
        $result = [];

        for ($i = 0; $i < $entryCount; $i++) {
            $br->setPos($contentStartPos + $offsets[$i]);
            $content = $br->readValueString();
            $result[] = [
                'arif' => $arifs[$i],
                'content' => $content,
            ];
        }

        return $result;
    }

    /**
     * Decompress section data if it has snappy-blocks compression, otherwise return raw.
     */
    private function getSectionDecompressedData(string $sectionName, ?array $sectionAttributes, int $contentOffset): string
    {
        $entry = $this->sectionEntries[$sectionName] ?? null;
        if ($entry === null) {
            throw new RuntimeException("YES2: Section '{$sectionName}' not found");
        }

        if ($sectionAttributes === null) {
            return substr($this->data, $contentOffset, $entry['content_size']);
        }

        $compressionName = $sectionAttributes['compression.name'] ?? null;
        if ($compressionName === null) {
            return substr($this->data, $contentOffset, $entry['content_size']);
        }

        if ($compressionName !== 'snappy-blocks') {
            throw new RuntimeException("YES2: Unsupported compression: {$compressionName}");
        }

        $compressionInfo = $sectionAttributes['compression.info'] ?? [];
        $blockSize = (int) ($compressionInfo['block_size'] ?? 0);
        $compressedBlockSizes = $compressionInfo['compressed_block_sizes'] ?? [];

        if ($blockSize <= 0 || empty($compressedBlockSizes)) {
            throw new RuntimeException("YES2: Invalid snappy-blocks compression info for section '{$sectionName}'");
        }

        $offsets = [];
        $cumulative = 0;
        foreach ($compressedBlockSizes as $size) {
            $offsets[] = $cumulative;
            $cumulative += $size;
        }

        $decompressed = '';
        foreach ($compressedBlockSizes as $i => $compSize) {
            $blockData = substr($this->data, $contentOffset + $offsets[$i], $compSize);
            $decompressed .= SnappyDecompressor::decompress($blockData);
        }

        return $decompressed;
    }

    // -- ARI helpers --

    /**
     * Encode an ARI from book ID, chapter, and verse.
     */
    public static function encodeAri(int $bookId, int $chapter, int $verse): int
    {
        return (($bookId & 0xff) << 16) | (($chapter & 0xff) << 8) | ($verse & 0xff);
    }

    /**
     * Decode an ARI into [bookId, chapter, verse].
     *
     * @return array{bookId: int, chapter: int, verse: int}
     */
    public static function decodeAri(int $ari): array
    {
        return [
            'bookId' => ($ari >> 16) & 0xff,
            'chapter' => ($ari >> 8) & 0xff,
            'verse' => $ari & 0xff,
        ];
    }

    /**
     * Decode an ARIF into [ari, index].
     *
     * @return array{ari: int, index: int}
     */
    public static function decodeArif(int $arif): array
    {
        return [
            'ari' => ($arif >> 8) & 0xffffff,
            'index' => $arif & 0xff,
        ];
    }
}
