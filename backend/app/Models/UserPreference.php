<?php

namespace App\Models;

use App\Models\Concerns\Syncable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
    use HasFactory, HasUuids, Syncable;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'key',
        'value',
        'is_deleted',
        'deleted_at',
        'vector_clock',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_deleted' => 'boolean',
            'vector_clock' => 'array',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
