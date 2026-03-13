<?php

namespace App\Services\Sword;

/**
 * Parses SWORD module .conf files (INI-like format with multi-line values).
 *
 * SWORD .conf files follow a format similar to INI but with these differences:
 * - The module name is in brackets [ModuleName]
 * - Multi-line values use a continuation line starting with whitespace
 * - Values can contain = signs (only the first = splits key/value)
 * - RTF-encoded text in About= fields
 * - Some keys can appear multiple times (GlobalOptionFilter, Feature, etc.)
 */
class ConfParser
{
    /**
     * Keys that can have multiple values (appear multiple times in .conf).
     */
    private const MULTI_VALUE_KEYS = [
        'GlobalOptionFilter',
        'Feature',
        'LocalStripFilter',
    ];

    /**
     * Parse a .conf file string into an associative array.
     *
     * @return array{module_name: string, config: array<string, string|array>}
     */
    public function parse(string $content): array
    {
        // Strip BOM
        $content = ltrim($content, "\xEF\xBB\xBF");

        // Normalize line endings
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        $lines = explode("\n", $content);
        $moduleName = '';
        $config = [];
        $currentKey = null;

        foreach ($lines as $line) {
            // Skip empty lines and comments
            if (trim($line) === '' || str_starts_with(trim($line), '#')) {
                continue;
            }

            // Module name in brackets: [ModuleName]
            if (preg_match('/^\[(.+)\]$/', trim($line), $matches)) {
                $moduleName = $matches[1];
                continue;
            }

            // Continuation line (starts with whitespace or tab)
            if ($currentKey !== null && preg_match('/^[\t ]+/', $line)) {
                $continuation = ltrim($line);
                if (is_array($config[$currentKey]) && !$this->isMultiValueKey($currentKey)) {
                    // Shouldn't happen for non-multi-value, but handle gracefully
                    $config[$currentKey][] = $continuation;
                } elseif (is_string($config[$currentKey])) {
                    $config[$currentKey] .= "\n" . $continuation;
                }
                continue;
            }

            // Key=Value pair (only split on first =)
            $eqPos = strpos($line, '=');
            if ($eqPos !== false) {
                $key = trim(substr($line, 0, $eqPos));
                $value = trim(substr($line, $eqPos + 1));

                if ($this->isMultiValueKey($key)) {
                    if (!isset($config[$key])) {
                        $config[$key] = [];
                    }
                    $config[$key][] = $value;
                } else {
                    $config[$key] = $value;
                }

                $currentKey = $key;
            }
        }

        return [
            'module_name' => $moduleName,
            'config' => $config,
        ];
    }

    /**
     * Parse a .conf file from a file path.
     */
    public function parseFile(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Cannot read conf file: {$path}");
        }

        return $this->parse($content);
    }

    /**
     * Extract normalized module metadata from parsed config.
     */
    public function extractMetadata(array $parsed): array
    {
        $config = $parsed['config'];

        return [
            'key' => $parsed['module_name'],
            'name' => $config['Description'] ?? $parsed['module_name'],
            'description' => $this->decodeRtf($config['About'] ?? ''),
            'type' => $this->resolveModuleType($config),
            'language' => $config['Lang'] ?? 'en',
            'version' => $config['Version'] ?? '1.0',
            'mod_drv' => $config['ModDrv'] ?? '',
            'data_path' => $config['DataPath'] ?? '',
            'source_type' => $config['SourceType'] ?? 'Plain',
            'compress_type' => $config['CompressType'] ?? null,
            'block_type' => $config['BlockType'] ?? null,
            'versification' => $config['Versification'] ?? 'KJV',
            'encoding' => $config['Encoding'] ?? 'UTF-8',
            'direction' => $config['Direction'] ?? 'LtoR',
            'category' => $config['Category'] ?? null,
            'lcsh' => $config['LCSH'] ?? null,
            'minimum_version' => $config['MinimumVersion'] ?? null,
            'install_size' => isset($config['InstallSize']) ? (int) $config['InstallSize'] : null,
            'sword_version_date' => $config['SwordVersionDate'] ?? null,
            'features' => $config['Feature'] ?? [],
            'global_option_filters' => $config['GlobalOptionFilter'] ?? [],
            'cipher_key' => $config['CipherKey'] ?? null,
            'conf_data' => $config,
        ];
    }

    /**
     * Determine the module type from the ModDrv and Category fields.
     */
    private function resolveModuleType(array $config): string
    {
        $modDrv = strtolower($config['ModDrv'] ?? '');
        $category = $config['Category'] ?? '';

        // Check category first for special types
        if (stripos($category, 'Daily Devotional') !== false) {
            return 'devotional';
        }

        // Determine type from driver (case-insensitive)
        return match (true) {
            in_array($modDrv, ['ztext', 'rawtext', 'rawtext4']) => 'bible',
            in_array($modDrv, ['zcom', 'zcom4', 'rawcom', 'rawcom4']) => 'commentary',
            in_array($modDrv, ['zld', 'rawld', 'rawld4']) => 'dictionary',
            $modDrv === 'rawgenbook' => 'genbook',
            default => 'bible',
        };
    }

    /**
     * Decode RTF-encoded About text.
     * SWORD conf files encode About text with \par for paragraphs, etc.
     */
    private function decodeRtf(string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // Replace SWORD RTF-like escapes
        $text = str_replace('\\par ', "\n", $text);
        $text = str_replace('\\par', "\n", $text);
        $text = str_replace('\\pard', '', $text);
        $text = str_replace('\\qc', '', $text);

        // Handle Unicode escapes like \u1234?
        $text = preg_replace_callback('/\\\\u(\d+)\?/', function ($m) {
            return mb_chr((int) $m[1], 'UTF-8');
        }, $text);

        // Remove remaining RTF control words
        $text = preg_replace('/\\\\[a-z]+\d*\s?/', '', $text);

        return trim($text);
    }

    /**
     * Check if a key supports multiple values.
     */
    private function isMultiValueKey(string $key): bool
    {
        return in_array($key, self::MULTI_VALUE_KEYS, true);
    }
}
