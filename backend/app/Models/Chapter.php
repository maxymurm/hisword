<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Chapter extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = [
        'book_id',
        'chapter_number',
        'verse_count',
    ];

    protected function casts(): array
    {
        return [
            'chapter_number' => 'integer',
            'verse_count' => 'integer',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
