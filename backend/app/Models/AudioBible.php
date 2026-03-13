<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AudioBible extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'module_key',
        'book_osis_id',
        'chapter_number',
        'storage_path',
        'storage_disk',
        'duration',
        'file_size',
        'format',
        'narrator',
        'language',
        'verse_timings',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'chapter_number' => 'integer',
            'duration' => 'integer',
            'file_size' => 'integer',
            'verse_timings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Generate a temporary signed URL for streaming.
     */
    public function getStreamUrl(): string
    {
        $disk = Storage::disk($this->storage_disk);

        // For S3/R2 — generate a signed (temporary) URL valid for 1 hour
        if (in_array($this->storage_disk, ['s3', 'r2'])) {
            return $disk->temporaryUrl($this->storage_path, now()->addHour());
        }

        // For local — serve from app URL
        return $disk->url($this->storage_path);
    }

    /**
     * Get formatted duration (e.g., "5:32").
     */
    public function getFormattedDurationAttribute(): string
    {
        if (!$this->duration) {
            return '0:00';
        }

        $minutes = intdiv($this->duration, 60);
        $seconds = $this->duration % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    /**
     * Scope to active audio tracks.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
