<?php

namespace App\Models;

use App\Models\Concerns\Syncable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookmarkFolder extends Model
{
    use HasFactory, HasUuids, Syncable;

    protected $fillable = [
        'user_id',
        'parent_id',
        'name',
        'color',
        'sort_order',
        'is_deleted',
        'vector_clock',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_deleted' => 'boolean',
            'vector_clock' => 'array',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class, 'folder_id');
    }
}
