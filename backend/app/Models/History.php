<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class History extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'history';

    protected $fillable = [
        'user_id',
        'book_osis_id',
        'chapter_number',
        'verse_number',
        'module_key',
        'scroll_position',
        'duration_seconds',
        'is_deleted',
        'vector_clock',
    ];

    protected function casts(): array
    {
        return [
            'chapter_number' => 'integer',
            'verse_number' => 'integer',
            'scroll_position' => 'float',
            'duration_seconds' => 'integer',
            'is_deleted' => 'boolean',
            'vector_clock' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
