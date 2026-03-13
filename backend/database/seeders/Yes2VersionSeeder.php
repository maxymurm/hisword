<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

/**
 * Seeds the modules table with known YES2/Bintex Bible versions.
 *
 * These are well-known androidbible YES format translations that can be
 * made available in the catalog for download.
 *
 * Usage:
 *   php artisan db:seed --class=Yes2VersionSeeder
 */
class Yes2VersionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding YES2 Bible versions...');

        $count = 0;
        foreach (self::VERSIONS as $version) {
            Module::firstOrCreate(
                ['key' => $version['key']],
                array_merge($version, [
                    'type' => 'bible',
                    'engine' => 'bintex',
                    'is_installed' => false,
                    'is_bundled' => false,
                ]),
            );
            $count++;
        }

        $this->command->info("Seeded {$count} YES2 Bible versions.");
    }

    /**
     * Known YES2 Bible versions from the androidbible ecosystem.
     *
     * Each entry represents a Bible translation available in YES format.
     * The data_path and source_url should be populated when files are actually available.
     */
    private const VERSIONS = [
        [
            'key' => 'ab-niv',
            'name' => 'New International Version',
            'language' => 'en',
            'driver' => 'yes2',
            'description' => 'NIV Bible in YES2 format',
        ],
        [
            'key' => 'ab-kjv',
            'name' => 'King James Version (YES)',
            'language' => 'en',
            'driver' => 'yes2',
            'description' => 'KJV Bible in YES2 format',
        ],
        [
            'key' => 'ab-asv',
            'name' => 'American Standard Version (YES)',
            'language' => 'en',
            'driver' => 'yes2',
            'description' => 'ASV 1901 in YES2 format',
        ],
        [
            'key' => 'ab-web',
            'name' => 'World English Bible (YES)',
            'language' => 'en',
            'driver' => 'yes2',
            'description' => 'WEB Bible in YES2 format',
        ],
        [
            'key' => 'ab-tbi',
            'name' => 'Terjemahan Baru',
            'language' => 'id',
            'driver' => 'yes2',
            'description' => 'Indonesian New Translation in YES2 format',
        ],
        [
            'key' => 'ab-ayt',
            'name' => 'Alkitab Yang Terbuka',
            'language' => 'id',
            'driver' => 'yes2',
            'description' => 'Indonesian Open Bible in YES2 format',
        ],
        [
            'key' => 'ab-bis',
            'name' => 'Bahasa Indonesia Sehari-hari',
            'language' => 'id',
            'driver' => 'yes2',
            'description' => 'Indonesian Everyday Language in YES2 format',
        ],
        [
            'key' => 'ab-rvr60',
            'name' => 'Reina-Valera 1960 (YES)',
            'language' => 'es',
            'driver' => 'yes2',
            'description' => 'Spanish Reina-Valera 1960 in YES2 format',
        ],
        [
            'key' => 'ab-lsg',
            'name' => 'Louis Segond 1910 (YES)',
            'language' => 'fr',
            'driver' => 'yes2',
            'description' => 'French Louis Segond in YES2 format',
        ],
        [
            'key' => 'ab-lut',
            'name' => 'Luther Bibel 1912 (YES)',
            'language' => 'de',
            'driver' => 'yes2',
            'description' => 'German Luther Bible in YES2 format',
        ],
        [
            'key' => 'ab-arc',
            'name' => 'Almeida Revista e Corrigida (YES)',
            'language' => 'pt',
            'driver' => 'yes2',
            'description' => 'Portuguese Almeida in YES2 format',
        ],
        [
            'key' => 'ab-synodal',
            'name' => 'Synodal Translation (YES)',
            'language' => 'ru',
            'driver' => 'yes2',
            'description' => 'Russian Synodal Bible in YES2 format',
        ],
        [
            'key' => 'ab-cunp',
            'name' => 'Chinese Union New Punctuation (YES)',
            'language' => 'zh',
            'driver' => 'yes2',
            'description' => 'Chinese Union Version in YES2 format',
        ],
        [
            'key' => 'ab-jkb',
            'name' => 'Japanese Kougo-yaku (YES)',
            'language' => 'ja',
            'driver' => 'yes2',
            'description' => 'Japanese Colloquial Bible in YES2 format',
        ],
        [
            'key' => 'ab-krv',
            'name' => 'Korean Revised Version (YES)',
            'language' => 'ko',
            'driver' => 'yes2',
            'description' => 'Korean Revised Version in YES2 format',
        ],
    ];
}
