<?php

namespace App\Services\Sword\Readers;

use App\Services\Sword\Versification\VersificationInterface;

/**
 * Factory to create the appropriate binary reader for a SWORD module
 * based on its ModDrv configuration.
 */
class ReaderFactory
{
    /**
     * Create a text/commentary reader (implements ReaderInterface).
     *
     * @param string                       $modDrv        Module driver (zText, rawText, rawText4, zCom, zCom4, rawCom, rawCom4)
     * @param string                       $dataPath      Absolute path to the module data directory
     * @param string|null                  $cipherKey     Cipher key for locked modules
     * @param VersificationInterface|null  $versification Versification to use (defaults to KJV)
     * @return ReaderInterface
     */
    public static function createTextReader(
        string $modDrv,
        string $dataPath,
        ?string $cipherKey = null,
        ?VersificationInterface $versification = null,
    ): ReaderInterface {
        return match (strtolower($modDrv)) {
            'ztext'    => new ZTextReader($dataPath, $cipherKey, $versification),
            'rawtext'  => new RawTextReader($dataPath, false, $cipherKey, $versification),
            'rawtext4' => new RawTextReader($dataPath, true, $cipherKey, $versification),
            'zcom', 'zcom4' => new ZComReader($dataPath, $cipherKey, $versification),
            'rawcom'   => new RawComReader($dataPath, false, $cipherKey, $versification),
            'rawcom4'  => new RawComReader($dataPath, true, $cipherKey, $versification),
            default    => throw new \InvalidArgumentException("Unknown text driver: {$modDrv}"),
        };
    }

    /**
     * Create a dictionary/lexicon reader (implements DictionaryReaderInterface).
     *
     * @param string      $modDrv     Module driver (zLD, rawLD, rawLD4, RawGenBook)
     * @param string      $dataPath   Absolute path to the module data directory
     * @param string      $moduleName Module key name (needed for GenBook file naming)
     * @param string|null $cipherKey  Cipher key for locked modules
     * @return DictionaryReaderInterface
     */
    public static function createDictionaryReader(
        string $modDrv,
        string $dataPath,
        string $moduleName = '',
        ?string $cipherKey = null,
    ): DictionaryReaderInterface {
        return match (strtolower($modDrv)) {
            'zld'         => new ZLDReader($dataPath, $cipherKey),
            'rawld'       => new RawLDReader($dataPath, false, $cipherKey, $moduleName),
            'rawld4'      => new RawLDReader($dataPath, true, $cipherKey, $moduleName),
            'rawgenbook'  => new RawGenBookReader($dataPath, $moduleName, $cipherKey),
            default       => throw new \InvalidArgumentException("Unknown dictionary driver: {$modDrv}"),
        };
    }

    /**
     * Determine if a driver is for text-based modules (Bible/Commentary).
     */
    public static function isTextDriver(string $modDrv): bool
    {
        return in_array(strtolower($modDrv), ['ztext', 'rawtext', 'rawtext4', 'zcom', 'zcom4', 'rawcom', 'rawcom4'], true);
    }

    /**
     * Determine if a driver is for dictionary-based modules.
     */
    public static function isDictionaryDriver(string $modDrv): bool
    {
        return in_array(strtolower($modDrv), ['zld', 'rawld', 'rawld4', 'rawgenbook'], true);
    }
}
