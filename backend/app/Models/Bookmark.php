<?php

namespace App\Models;

use App\Models\Concerns\Syncable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bookmark extends Model
{
    use HasFactory, HasUuids, Syncable;

    protected $fillable = [
        'user_id',
        'folder_id',
        'book_osis_id',
        'chapter_number',
        'verse_start',
        'verse_end',
        'module_key',
        'label',
        'description',
        'sort_order',
        'is_deleted',
        'vector_clock',
    ];

    protected function casts(): array
    {
        return [
            'chapter_number' => 'integer',
            'verse_start' => 'integer',
            'verse_end' => 'integer',
            'sort_order' => 'integer',
            'is_deleted' => 'boolean',
            'vector_clock' => 'array',
        ];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(BookmarkFolder::class, 'folder_id');
    }
}
