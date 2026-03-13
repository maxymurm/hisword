<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModuleSource extends Model
{
    use HasFactory;
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'caption',
        'type',
        'server',
        'directory',
        'is_active',
        'last_refreshed',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_refreshed' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
