<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared functionality for models that participate in cross-device sync.
 *
 * Requirements for the model:
 * - UUID primary key
 * - user_id foreign key
 * - is_deleted, vector_clock, deleted_at columns
 */
trait Syncable
{
    /**
     * Boot the trait: apply a global scope to hide soft-deleted records.
     */
    public static function bootSyncable(): void
    {
        static::addGlobalScope('not_deleted', function ($query) {
            $query->where('is_deleted', false);
        });
    }

    /**
     * Scope to include soft-deleted records (for sync operations).
     */
    public function scopeWithDeleted($query)
    {
        return $query->withoutGlobalScope('not_deleted');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Soft-delete the model for sync (sets is_deleted + deleted_at).
     */
    public function syncDelete(): bool
    {
        $this->is_deleted = true;
        $this->deleted_at = now();
        return $this->save();
    }
}
