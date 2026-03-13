<?php

namespace App\Services\Sword\Versification;

/**
 * Maps SWORD module Versification= conf values to versification instances.
 */
class VersificationRegistry
{
    /** @var array<string, VersificationInterface> */
    private array $cache = [];

    /** @var array<string, class-string<VersificationInterface>> */
    private const MAP = [
        'KJV'      => KjvVersification::class,
        'KJVA'     => KjvaVersification::class,
        'NRSV'     => NrsvVersification::class,
        'NRSVA'    => NrsvVersification::class,
        'Synodal'  => SynodalVersification::class,
        'SynodalP' => SynodalVersification::class,
        'Catholic' => CatholicVersification::class,
        'Catholic2' => CatholicVersification::class,
        'German'   => GermanVersification::class,
        'Luther'   => GermanVersification::class,
    ];

    /**
     * Get a versification instance by name (from module conf).
     * Falls back to KJV if unknown.
     */
    public function get(string $name): VersificationInterface
    {
        $name = trim($name);

        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        $class = self::MAP[$name] ?? KjvVersification::class;
        $this->cache[$name] = new $class();

        return $this->cache[$name];
    }

    /**
     * Check if a versification name is supported.
     */
    public function has(string $name): bool
    {
        return isset(self::MAP[trim($name)]);
    }

    /**
     * Get all supported versification names.
     *
     * @return array<string>
     */
    public function supported(): array
    {
        return array_keys(self::MAP);
    }
}
