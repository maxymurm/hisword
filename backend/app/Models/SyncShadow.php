<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncShadow extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'device_id',
        'entity_type',
        'entity_id',
        'shadow_data',
        'shadow_at',
    ];

    protected function casts(): array
    {
        return [
            'shadow_data' => 'array',
            'shadow_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
