<?php

namespace App\Models;

use App\Models\Concerns\Syncable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    use HasFactory, HasUuids, Syncable;

    protected $fillable = [
        'user_id',
        'book_osis_id',
        'chapter_number',
        'verse_start',
        'verse_end',
        'module_key',
        'title',
        'content',
        'content_format',
        'is_public',
        'is_deleted',
        'vector_clock',
    ];

    protected function casts(): array
    {
        return [
            'chapter_number' => 'integer',
            'verse_start' => 'integer',
            'verse_end' => 'integer',
            'is_public' => 'boolean',
            'is_deleted' => 'boolean',
            'vector_clock' => 'array',
        ];
    }
}
