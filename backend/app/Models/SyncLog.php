<?php

namespace App\Models;

use App\Enums\SyncOperation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'device_id',
        'entity_type',
        'entity_id',
        'operation',
        'payload',
        'vector_clock',
    ];

    protected function casts(): array
    {
        return [
            'operation' => SyncOperation::class,
            'payload' => 'array',
            'vector_clock' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
