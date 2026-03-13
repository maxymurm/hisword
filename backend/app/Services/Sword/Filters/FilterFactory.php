<?php

namespace App\Services\Sword\Filters;

/**
 * Factory to create the appropriate markup filter based on SourceType.
 */
class FilterFactory
{
    /**
     * Create filter for a given SWORD SourceType.
     */
    public static function create(string $sourceType): FilterInterface
    {
        return match (strtolower($sourceType)) {
            'osis'  => new OsisFilter(),
            'thml'  => new ThmlFilter(),
            'gbf'   => new GbfFilter(),
            'tei'   => new TeiFilter(),
            default => new PlainFilter(),
        };
    }
}
