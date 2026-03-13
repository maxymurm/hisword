<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'user_id',
        'device_id',
        'platform',
        'name',
        'push_token',
        'last_sync_at',
        'app_version',
    ];

    protected function casts(): array
    {
        return [
            'last_sync_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
