<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ReadingPlan;
use App\Models\ReadingPlanProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReadingPlanController extends BaseApiController
{
    /**
     * List all available reading plans.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ReadingPlan::query();

        if ($request->query('search')) {
            $query->where('name', 'LIKE', '%' . $request->query('search') . '%');
        }

        $plans = $query->orderBy('name')->get()->map(function (ReadingPlan $plan) use ($request) {
            $progress = $request->user()
                ? $plan->progress()->where('user_id', $request->user()->id)->where('is_deleted', false)->first()
                : null;

            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'duration_days' => $plan->duration_days,
                'is_system' => $plan->is_system,
                'is_subscribed' => $progress !== null,
                'progress_percentage' => $progress ? round(count($progress->completed_days ?? []) / max($plan->duration_days, 1) * 100) : 0,
            ];
        });

        return $this->success($plans);
    }

    /**
     * Show plan details with full day breakdown.
     */
    public function show(Request $request, string $plan): JsonResponse
    {
        $readingPlan = ReadingPlan::findOrFail($plan);

        $progress = $request->user()
            ? $readingPlan->progress()->where('user_id', $request->user()->id)->where('is_deleted', false)->first()
            : null;

        return $this->success([
            'id' => $readingPlan->id,
            'name' => $readingPlan->name,
            'description' => $readingPlan->description,
            'duration_days' => $readingPlan->duration_days,
            'plan_data' => $readingPlan->plan_data,
            'is_system' => $readingPlan->is_system,
            'progress' => $progress ? [
                'id' => $progress->id,
                'start_date' => $progress->start_date->toDateString(),
                'current_day' => $progress->current_day,
                'completed_days' => $progress->completed_days,
                'is_completed' => $progress->is_completed,
            ] : null,
        ]);
    }

    /**
     * Subscribe to (start) a reading plan.
     */
    public function subscribe(Request $request, string $plan): JsonResponse
    {
        $readingPlan = ReadingPlan::findOrFail($plan);

        // Check if already subscribed
        $existing = ReadingPlanProgress::where('user_id', $request->user()->id)
            ->where('plan_id', $readingPlan->id)
            ->where('is_deleted', false)
            ->first();

        if ($existing) {
            return $this->error('Already subscribed to this plan', 422);
        }

        // Re-activate a previously deleted subscription if exists
        $deleted = ReadingPlanProgress::where('user_id', $request->user()->id)
            ->where('plan_id', $readingPlan->id)
            ->where('is_deleted', true)
            ->first();

        if ($deleted) {
            $deleted->update([
                'start_date' => now()->toDateString(),
                'current_day' => 1,
                'completed_days' => [],
                'is_completed' => false,
                'is_deleted' => false,
            ]);

            return $this->success([
                'id' => $deleted->id,
                'plan_id' => $readingPlan->id,
                'start_date' => $deleted->start_date->toDateString(),
                'current_day' => 1,
                'completed_days' => [],
                'is_completed' => false,
            ], 'Subscribed to reading plan', 201);
        }

        $progress = ReadingPlanProgress::create([
            'user_id' => $request->user()->id,
            'plan_id' => $readingPlan->id,
            'start_date' => now()->toDateString(),
            'current_day' => 1,
            'completed_days' => [],
            'is_completed' => false,
            'is_deleted' => false,
            'vector_clock' => [],
        ]);

        return $this->success([
            'id' => $progress->id,
            'plan_id' => $readingPlan->id,
            'start_date' => $progress->start_date->toDateString(),
            'current_day' => 1,
            'completed_days' => [],
            'is_completed' => false,
        ], 'Subscribed to reading plan', 201);
    }

    /**
     * Mark a day as complete (or toggle off).
     */
    public function updateProgress(Request $request, string $plan): JsonResponse
    {
        $readingPlan = ReadingPlan::findOrFail($plan);

        $validated = $request->validate([
            'day' => ['required', 'integer', 'min:1', 'max:' . $readingPlan->duration_days],
        ]);

        $progress = ReadingPlanProgress::where('user_id', $request->user()->id)
            ->where('plan_id', $readingPlan->id)
            ->where('is_deleted', false)
            ->firstOrFail();

        $completedDays = $progress->completed_days ?? [];
        $day = $validated['day'];

        if (in_array($day, $completedDays)) {
            // Toggle off
            $completedDays = array_values(array_diff($completedDays, [$day]));
        } else {
            // Mark complete
            $completedDays[] = $day;
            sort($completedDays);
        }

        $isCompleted = count($completedDays) >= $readingPlan->duration_days;
        $currentDay = empty($completedDays) ? 1 : max($completedDays) + 1;
        if ($currentDay > $readingPlan->duration_days) {
            $currentDay = $readingPlan->duration_days;
        }

        $progress->update([
            'completed_days' => $completedDays,
            'current_day' => $currentDay,
            'is_completed' => $isCompleted,
        ]);

        return $this->success([
            'id' => $progress->id,
            'current_day' => $progress->current_day,
            'completed_days' => $progress->completed_days,
            'is_completed' => $progress->is_completed,
            'progress_percentage' => round(count($completedDays) / max($readingPlan->duration_days, 1) * 100),
        ]);
    }

    /**
     * Get user's active (subscribed) plans with progress.
     */
    public function active(Request $request): JsonResponse
    {
        $progressItems = ReadingPlanProgress::where('user_id', $request->user()->id)
            ->where('is_deleted', false)
            ->with('plan')
            ->orderBy('start_date', 'desc')
            ->get()
            ->map(function (ReadingPlanProgress $progress) {
                $plan = $progress->plan;

                return [
                    'id' => $progress->id,
                    'plan' => [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'description' => $plan->description,
                        'duration_days' => $plan->duration_days,
                    ],
                    'start_date' => $progress->start_date->toDateString(),
                    'current_day' => $progress->current_day,
                    'completed_days' => $progress->completed_days,
                    'is_completed' => $progress->is_completed,
                    'progress_percentage' => round(count($progress->completed_days ?? []) / max($plan->duration_days, 1) * 100),
                    'days_remaining' => max(0, $plan->duration_days - count($progress->completed_days ?? [])),
                ];
            });

        return $this->success($progressItems);
    }
}
