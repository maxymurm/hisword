<?php

namespace App\Models;

use App\Models\Concerns\Syncable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tag extends Model
{
    use HasFactory, HasUuids, Syncable;

    protected $fillable = [
        'user_id',
        'name',
        'color',
        'description',
        'is_deleted',
        'vector_clock',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'vector_clock' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all verses tagged with this tag via the taggables pivot.
     */
    public function bookmarks()
    {
        return $this->morphedByMany(Bookmark::class, 'taggable');
    }

    public function notes()
    {
        return $this->morphedByMany(Note::class, 'taggable');
    }

    public function highlights()
    {
        return $this->morphedByMany(Highlight::class, 'taggable');
    }
}
