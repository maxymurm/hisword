<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Centralized caching service for Bible content and app data.
 *
 * Uses tagged caches where supported (Redis, Memcached) with sensible TTLs.
 */
class CacheService
{
    /** TTL constants (seconds) */
    public const TTL_VERSE = 86400;        // 24 hours – verse content rarely changes
    public const TTL_MODULE_LIST = 3600;    // 1 hour  – module installs are infrequent
    public const TTL_BOOK_LIST = 86400;     // 24 hours – books never change per module
    public const TTL_SEARCH = 1800;         // 30 min  – search results
    public const TTL_CONFIG = 7200;         // 2 hours – config/bible data

    /**
     * Get cached verses for a chapter.
     */
    public function verses(string $moduleKey, string $bookOsis, int $chapter, \Closure $callback): mixed
    {
        $key = "verses:{$moduleKey}:{$bookOsis}:{$chapter}";

        return $this->remember('bible', $key, self::TTL_VERSE, $callback);
    }

    /**
     * Get cached books for a module.
     */
    public function booksForModule(string $moduleId, \Closure $callback): mixed
    {
        $key = "books:module:{$moduleId}";

        return $this->remember('bible', $key, self::TTL_BOOK_LIST, $callback);
    }

    /**
     * Get cached installed modules by type.
     */
    public function installedModules(string $type, \Closure $callback): mixed
    {
        $key = "modules:installed:{$type}";

        return $this->remember('modules', $key, self::TTL_MODULE_LIST, $callback);
    }

    /**
     * Cache search results.
     */
    public function searchResults(string $hash, \Closure $callback): mixed
    {
        $key = "search:{$hash}";

        return $this->remember('search', $key, self::TTL_SEARCH, $callback);
    }

    /**
     * Flush all cached data for a specific tag.
     */
    public function flushTag(string $tag): void
    {
        try {
            Cache::tags([$tag])->flush();
        } catch (\BadMethodCallException) {
            // Driver doesn't support tags (file/database) – flush all
            Cache::flush();
        }
    }

    /**
     * Flush module-related caches (after install/uninstall).
     */
    public function flushModuleCache(): void
    {
        $this->flushTag('modules');
        $this->flushTag('bible');
    }

    /**
     * Flush search caches.
     */
    public function flushSearchCache(): void
    {
        $this->flushTag('search');
    }

    /**
     * Internal remember helper with tag support fallback.
     */
    private function remember(string $tag, string $key, int $ttl, \Closure $callback): mixed
    {
        try {
            return Cache::tags([$tag])->remember($key, $ttl, $callback);
        } catch (\BadMethodCallException) {
            // Fallback for drivers without tag support
            return Cache::remember($key, $ttl, $callback);
        }
    }
}
