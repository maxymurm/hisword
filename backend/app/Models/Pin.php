<?php

namespace App\Models;

use App\Models\Concerns\Syncable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pin extends Model
{
    use HasFactory, HasUuids, Syncable;

    protected $fillable = [
        'user_id',
        'book_osis_id',
        'chapter_number',
        'verse_number',
        'module_key',
        'label',
        'sort_order',
        'is_deleted',
        'vector_clock',
    ];

    protected function casts(): array
    {
        return [
            'chapter_number' => 'integer',
            'verse_number' => 'integer',
            'sort_order' => 'integer',
            'is_deleted' => 'boolean',
            'vector_clock' => 'array',
        ];
    }
}
