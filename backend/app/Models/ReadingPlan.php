<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReadingPlan extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'description',
        'duration_days',
        'plan_data',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'plan_data' => 'array',
            'is_system' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function progress(): HasMany
    {
        return $this->hasMany(ReadingPlanProgress::class, 'plan_id');
    }
}
