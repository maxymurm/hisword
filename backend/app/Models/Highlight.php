<?php

namespace App\Models;

use App\Enums\HighlightColor;
use App\Models\Concerns\Syncable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Highlight extends Model
{
    use HasFactory, HasUuids, Syncable;

    protected $fillable = [
        'user_id',
        'book_osis_id',
        'chapter_number',
        'verse_number',
        'color',
        'module_key',
        'text_range_start',
        'text_range_end',
        'is_deleted',
        'vector_clock',
    ];

    protected function casts(): array
    {
        return [
            'chapter_number' => 'integer',
            'verse_number' => 'integer',
            'color' => HighlightColor::class,
            'text_range_start' => 'integer',
            'text_range_end' => 'integer',
            'is_deleted' => 'boolean',
            'vector_clock' => 'array',
        ];
    }
}
