<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

class Verse extends Model
{
    use HasFactory, Searchable;

    public $timestamps = false;

    protected $fillable = [
        'module_id',
        'book_osis_id',
        'chapter_number',
        'verse_number',
        'text_raw',
        'text_rendered',
        'strongs_data',
        'morphology_data',
        'footnotes',
        'cross_refs',
    ];

    protected function casts(): array
    {
        return [
            'chapter_number' => 'integer',
            'verse_number' => 'integer',
            'strongs_data' => 'array',
            'morphology_data' => 'array',
            'footnotes' => 'array',
            'cross_refs' => 'array',
        ];
    }

    // ── Scout / Meilisearch ─────────────────────────

    /**
     * Get the indexable data array for the model.
     */
    public function toSearchableArray(): array
    {
        $book = $this->book_osis_id;
        $bookNames = config('bible.osis_to_name', []);

        return [
            'id' => $this->id,
            'text' => $this->text_raw,
            'book_osis_id' => $book,
            'book_name' => $bookNames[$book] ?? $book,
            'chapter_number' => $this->chapter_number,
            'verse_number' => $this->verse_number,
            'module_id' => $this->module_id,
            'module_key' => $this->module?->key,
            'testament' => in_array($book, config('bible.ot_books', [])) ? 'OT' : 'NT',
            'reference' => ($bookNames[$book] ?? $book) . ' ' . $this->chapter_number . ':' . $this->verse_number,
        ];
    }

    /**
     * Get the name of the index associated with the model.
     */
    public function searchableAs(): string
    {
        return 'verses';
    }

    // ── Relationships ───────────────────────────────

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
