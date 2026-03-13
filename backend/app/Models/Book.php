<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = [
        'module_id',
        'osis_id',
        'name',
        'abbreviation',
        'testament',
        'book_order',
        'chapter_count',
    ];

    protected function casts(): array
    {
        return [
            'book_order' => 'integer',
            'chapter_count' => 'integer',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class);
    }
}
