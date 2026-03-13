<?php

namespace App\Console\Commands\Sword;

use App\Models\Module;
use App\Services\Sword\SwordManager;
use Illuminate\Console\Command;

class ReadModuleCommand extends Command
{
    protected $signature = 'sword:read
        {module : Module key (e.g., KJV)}
        {reference : Reference (e.g., Gen.1.1, "John 3:16", or dict key)}';

    protected $description = 'Read and display a passage or entry from an installed SWORD module (debug command)';

    public function handle(SwordManager $manager): int
    {
        $moduleKey = $this->argument('module');
        $reference = $this->argument('reference');

        $module = Module::whereRaw('LOWER(key) = ?', [strtolower($moduleKey)])->where('is_installed', true)->first();

        if (!$module) {
            $this->error("Module '{$moduleKey}' is not installed.");
            return self::FAILURE;
        }

        if ($manager->hasDataFiles($module)) {
            return $this->readFromBinary($module, $reference, $manager);
        }

        // Fallback: read from database
        return $this->readFromDatabase($module, $reference);
    }

    private function readFromBinary(Module $module, string $reference, SwordManager $manager): int
    {
        $modDrv = $module->mod_drv ?? '';

        // Dictionary modules
        if (\App\Services\Sword\Readers\ReaderFactory::isDictionaryDriver($modDrv)) {
            $result = $manager->readDictionaryEntry($module, $reference);

            if ($result['raw'] === null) {
                $this->warn("Entry '{$reference}' not found in {$module->key}.");
                return self::FAILURE;
            }

            $this->info("=== {$module->key}: {$reference} ===");
            $this->line('');
            $this->line("Raw:");
            $this->line($result['raw']);
            $this->line('');
            $this->line("HTML:");
            $this->line($result['html']);

            return self::SUCCESS;
        }

        // Text modules (Bible/Commentary)
        $parsed = $this->parseReference($reference);
        if (!$parsed) {
            $this->error("Cannot parse reference: {$reference}");
            return self::FAILURE;
        }

        [$osisId, $chapter, $verse] = $parsed;

        if ($verse !== null) {
            $result = $manager->readVerse($module, $osisId, $chapter, $verse);

            if ($result['raw'] === null) {
                $this->warn("Verse not found: {$osisId} {$chapter}:{$verse}");
                return self::FAILURE;
            }

            $this->info("=== {$module->key}: {$osisId} {$chapter}:{$verse} ===");
            $this->line('');
            $this->line("Raw: {$result['raw']}");
            $this->line('');
            $this->line("HTML: {$result['html']}");
            $this->line('');
            $this->line("Plain: {$result['plain']}");
        } else {
            $verses = $manager->readChapter($module, $osisId, $chapter);

            if (empty($verses)) {
                $this->warn("Chapter not found: {$osisId} {$chapter}");
                return self::FAILURE;
            }

            $this->info("=== {$module->key}: {$osisId} {$chapter} ===");
            foreach ($verses as $v => $data) {
                $this->line("{$v}: {$data['plain']}");
            }
        }

        return self::SUCCESS;
    }

    private function readFromDatabase(Module $module, string $reference): int
    {
        $parsed = $this->parseReference($reference);
        if (!$parsed) {
            $this->error("Cannot parse reference: {$reference}");
            return self::FAILURE;
        }

        [$osisId, $chapter, $verse] = $parsed;

        $query = \App\Models\Verse::where('module_id', $module->id)
            ->where('book_osis_id', $osisId)
            ->where('chapter_number', $chapter);

        if ($verse !== null) {
            $query->where('verse_number', $verse);
        }

        $verses = $query->orderBy('verse_number')->get();

        if ($verses->isEmpty()) {
            $this->warn("No verses found for {$osisId} {$chapter}" . ($verse ? ":{$verse}" : ''));
            return self::FAILURE;
        }

        $this->info("=== {$module->key}: {$osisId} {$chapter}" . ($verse ? ":{$verse}" : '') . ' ===');
        foreach ($verses as $v) {
            $this->line("{$v->verse_number}: {$v->text_raw}");
        }

        return self::SUCCESS;
    }

    /**
     * Parse a reference string into [osisId, chapter, verse].
     *
     * Supports:
     * - "Gen.1.1" → [Gen, 1, 1]
     * - "Gen.1"   → [Gen, 1, null]
     * - "John 3:16" → [John, 3, 16]
     * - "1Cor 13"   → [1Cor, 13, null]
     */
    private function parseReference(string $ref): ?array
    {
        // OSIS format: Book.Chapter.Verse
        if (preg_match('/^(\w+)\.(\d+)\.(\d+)$/', $ref, $m)) {
            return [$m[1], (int)$m[2], (int)$m[3]];
        }
        if (preg_match('/^(\w+)\.(\d+)$/', $ref, $m)) {
            return [$m[1], (int)$m[2], null];
        }

        // Human format: "Book Chapter:Verse"
        if (preg_match('/^(\d?\s?\w+)\s+(\d+):(\d+)$/i', $ref, $m)) {
            $book = $this->resolveBookName(trim($m[1]));
            return $book ? [$book, (int)$m[2], (int)$m[3]] : null;
        }
        if (preg_match('/^(\d?\s?\w+)\s+(\d+)$/i', $ref, $m)) {
            $book = $this->resolveBookName(trim($m[1]));
            return $book ? [$book, (int)$m[2], null] : null;
        }

        return null;
    }

    /**
     * Resolve a book name or abbreviation to OSIS ID.
     */
    private function resolveBookName(string $name): ?string
    {
        $nameMap = config('bible.osis_to_name', []);

        // Direct OSIS ID match
        if (isset($nameMap[$name])) {
            return $name;
        }

        // Reverse lookup by full name
        $flipped = array_flip($nameMap);
        if (isset($flipped[$name])) {
            return $flipped[$name];
        }

        // Case-insensitive search
        foreach ($nameMap as $osisId => $fullName) {
            if (strcasecmp($osisId, $name) === 0 || strcasecmp($fullName, $name) === 0) {
                return $osisId;
            }
        }

        // Remove spaces (e.g., "1 Cor" → "1Cor")
        $compact = str_replace(' ', '', $name);
        foreach ($nameMap as $osisId => $fullName) {
            if (strcasecmp($osisId, $compact) === 0) {
                return $osisId;
            }
        }

        return $name; // Return as-is, might be a valid OSIS ID
    }
}
