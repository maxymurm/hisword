<?php

namespace App\Services\Sword\Readers;

/**
 * Interface for reading dictionary/lexicon SWORD modules.
 */
interface DictionaryReaderInterface
{
    /**
     * Read a dictionary entry by key.
     *
     * @param string $key Entry key (e.g., "G2316" for Strong's)
     * @return string|null Raw markup text, or null if not found
     */
    public function readEntry(string $key): ?string;

    /**
     * Get all available keys in the dictionary.
     *
     * @return array<string>
     */
    public function getKeys(): array;

    /**
     * Get the module driver name.
     */
    public function getDriverName(): string;
}
