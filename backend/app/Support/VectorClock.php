<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Vector clock implementation for CRDT-based conflict resolution.
 *
 * A vector clock is a map of device_id => counter. Each device increments
 * its own counter on every local change. Clocks are merged on sync by
 * taking the max of each component across both clocks.
 *
 * Comparison uses sum-then-win-count heuristic:
 * 1. If sum of all counters differs, higher sum wins
 * 2. If sums are equal, count wins per component; more wins = newer
 *
 * @see https://en.wikipedia.org/wiki/Vector_clock
 */
class VectorClock
{
    /**
     * @param  array<string, int>  $entries  Map of device_id => counter
     */
    public function __construct(
        private array $entries = [],
    ) {}

    /**
     * Create from array (e.g., JSON decoded from database).
     *
     * @param  array<string, int>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Increment this clock for a specific device.
     */
    public function increment(string $deviceId): self
    {
        $entries = $this->entries;
        $entries[$deviceId] = ($entries[$deviceId] ?? 0) + 1;

        return new self($entries);
    }

    /**
     * Merge two clocks by taking the maximum of each component.
     */
    public function merge(self $other): self
    {
        $merged = $this->entries;

        foreach ($other->entries as $device => $counter) {
            $merged[$device] = max($merged[$device] ?? 0, $counter);
        }

        return new self($merged);
    }

    /**
     * Check if this clock is newer than another.
     * Uses sum comparison first, then per-component win count as tiebreaker.
     */
    public function isNewerThan(self $other): bool
    {
        $sumA = array_sum($this->entries);
        $sumB = array_sum($other->entries);

        if ($sumA !== $sumB) {
            return $sumA > $sumB;
        }

        $allKeys = array_unique(array_merge(
            array_keys($this->entries),
            array_keys($other->entries),
        ));

        $aWins = 0;
        $bWins = 0;

        foreach ($allKeys as $key) {
            $va = $this->entries[$key] ?? 0;
            $vb = $other->entries[$key] ?? 0;

            if ($va > $vb) {
                $aWins++;
            }
            if ($vb > $va) {
                $bWins++;
            }
        }

        return $aWins > $bWins;
    }

    /**
     * Check if two clocks are concurrent (neither is strictly newer).
     */
    public function isConcurrentWith(self $other): bool
    {
        return ! $this->isNewerThan($other) && ! $other->isNewerThan($this) && ! $this->equals($other);
    }

    /**
     * Check if this clock equals another.
     */
    public function equals(self $other): bool
    {
        $allKeys = array_unique(array_merge(
            array_keys($this->entries),
            array_keys($other->entries),
        ));

        foreach ($allKeys as $key) {
            if (($this->entries[$key] ?? 0) !== ($other->entries[$key] ?? 0)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if this clock happened before another (all components <= other, at least one <).
     */
    public function happenedBefore(self $other): bool
    {
        $hasStrictlyLess = false;
        $allKeys = array_unique(array_merge(
            array_keys($this->entries),
            array_keys($other->entries),
        ));

        foreach ($allKeys as $key) {
            $va = $this->entries[$key] ?? 0;
            $vb = $other->entries[$key] ?? 0;

            if ($va > $vb) {
                return false;
            }
            if ($va < $vb) {
                $hasStrictlyLess = true;
            }
        }

        return $hasStrictlyLess;
    }

    /**
     * Get the counter for a specific device.
     */
    public function get(string $deviceId): int
    {
        return $this->entries[$deviceId] ?? 0;
    }

    /**
     * Convert to array for JSON storage.
     *
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return $this->entries;
    }

    /**
     * Check if the clock is empty (no counters).
     */
    public function isEmpty(): bool
    {
        return empty($this->entries);
    }

    /**
     * Get the total sum of all counters.
     */
    public function sum(): int
    {
        return (int) array_sum($this->entries);
    }
}
