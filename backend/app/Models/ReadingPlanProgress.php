<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingPlanProgress extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'reading_plan_progress';

    protected $fillable = [
        'user_id',
        'plan_id',
        'start_date',
        'current_day',
        'completed_days',
        'is_completed',
        'is_deleted',
        'vector_clock',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'current_day' => 'integer',
            'completed_days' => 'array',
            'is_completed' => 'boolean',
            'is_deleted' => 'boolean',
            'vector_clock' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ReadingPlan::class, 'plan_id');
    }
}
