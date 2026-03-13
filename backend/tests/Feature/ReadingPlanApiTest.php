<?php

namespace Tests\Feature;

use App\Models\ReadingPlan;
use App\Models\ReadingPlanProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingPlanApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // ── List Plans ────────────────────────────

    public function test_list_plans(): void
    {
        ReadingPlan::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/reading-plans', $this->auth());

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_list_plans_with_search(): void
    {
        ReadingPlan::factory()->create(['name' => 'Bible in a Year']);
        ReadingPlan::factory()->create(['name' => 'Gospels in 30 Days']);

        $response = $this->getJson('/api/v1/reading-plans?search=Bible', $this->auth());

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bible in a Year');
    }

    public function test_list_plans_shows_subscription_status(): void
    {
        $plan = ReadingPlan::factory()->create();
        ReadingPlanProgress::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
            'completed_days' => [1, 2, 3],
        ]);

        $response = $this->getJson('/api/v1/reading-plans', $this->auth());

        $response->assertOk()
            ->assertJsonPath('data.0.is_subscribed', true)
            ->assertJsonPath('data.0.progress_percentage', fn ($v) => $v > 0);
    }

    // ── Show Plan ─────────────────────────────

    public function test_show_plan(): void
    {
        $plan = ReadingPlan::factory()->create();

        $response = $this->getJson("/api/v1/reading-plans/{$plan->id}", $this->auth());

        $response->assertOk()
            ->assertJsonPath('data.name', $plan->name)
            ->assertJsonPath('data.plan_data', fn ($v) => is_array($v));
    }

    public function test_show_plan_includes_progress(): void
    {
        $plan = ReadingPlan::factory()->create();
        ReadingPlanProgress::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
            'current_day' => 5,
            'completed_days' => [1, 2, 3, 4],
        ]);

        $response = $this->getJson("/api/v1/reading-plans/{$plan->id}", $this->auth());

        $response->assertOk()
            ->assertJsonPath('data.progress.current_day', 5)
            ->assertJsonPath('data.progress.completed_days', [1, 2, 3, 4]);
    }

    public function test_show_plan_not_found(): void
    {
        $response = $this->getJson('/api/v1/reading-plans/nonexistent-id', $this->auth());

        $response->assertNotFound();
    }

    // ── Subscribe ─────────────────────────────

    public function test_subscribe_to_plan(): void
    {
        $plan = ReadingPlan::factory()->create();

        $response = $this->postJson("/api/v1/reading-plans/{$plan->id}/subscribe", [], $this->auth());

        $response->assertCreated()
            ->assertJsonPath('data.plan_id', $plan->id)
            ->assertJsonPath('data.current_day', 1)
            ->assertJsonPath('data.completed_days', []);

        $this->assertDatabaseHas('reading_plan_progress', [
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
            'is_deleted' => false,
        ]);
    }

    public function test_cannot_subscribe_twice(): void
    {
        $plan = ReadingPlan::factory()->create();
        ReadingPlanProgress::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
        ]);

        $response = $this->postJson("/api/v1/reading-plans/{$plan->id}/subscribe", [], $this->auth());

        $response->assertStatus(422);
    }

    public function test_resubscribe_after_delete(): void
    {
        $plan = ReadingPlan::factory()->create();
        ReadingPlanProgress::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
            'is_deleted' => true,
            'completed_days' => [1, 2, 3],
            'current_day' => 4,
        ]);

        $response = $this->postJson("/api/v1/reading-plans/{$plan->id}/subscribe", [], $this->auth());

        $response->assertCreated()
            ->assertJsonPath('data.current_day', 1)
            ->assertJsonPath('data.completed_days', []);
    }

    // ── Update Progress ───────────────────────

    public function test_mark_day_complete(): void
    {
        $plan = ReadingPlan::factory()->create(['duration_days' => 30]);
        ReadingPlanProgress::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
            'completed_days' => [],
        ]);

        $response = $this->putJson("/api/v1/reading-plans/{$plan->id}/progress", [
            'day' => 1,
        ], $this->auth());

        $response->assertOk()
            ->assertJsonPath('data.completed_days', [1]);
    }

    public function test_toggle_day_off(): void
    {
        $plan = ReadingPlan::factory()->create(['duration_days' => 30]);
        ReadingPlanProgress::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
            'completed_days' => [1, 2, 3],
        ]);

        $response = $this->putJson("/api/v1/reading-plans/{$plan->id}/progress", [
            'day' => 2,
        ], $this->auth());

        $response->assertOk()
            ->assertJsonPath('data.completed_days', [1, 3]);
    }

    public function test_plan_completes_when_all_days_done(): void
    {
        $plan = ReadingPlan::factory()->create(['duration_days' => 3]);
        ReadingPlanProgress::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
            'completed_days' => [1, 2],
        ]);

        $response = $this->putJson("/api/v1/reading-plans/{$plan->id}/progress", [
            'day' => 3,
        ], $this->auth());

        $response->assertOk()
            ->assertJsonPath('data.is_completed', true)
            ->assertJsonPath('data.progress_percentage', 100);
    }

    public function test_cannot_mark_invalid_day(): void
    {
        $plan = ReadingPlan::factory()->create(['duration_days' => 30]);
        ReadingPlanProgress::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
        ]);

        $response = $this->putJson("/api/v1/reading-plans/{$plan->id}/progress", [
            'day' => 31,
        ], $this->auth());

        $response->assertUnprocessable();
    }

    public function test_progress_requires_subscription(): void
    {
        $plan = ReadingPlan::factory()->create();

        $response = $this->putJson("/api/v1/reading-plans/{$plan->id}/progress", [
            'day' => 1,
        ], $this->auth());

        $response->assertNotFound();
    }

    // ── Active Plans ──────────────────────────

    public function test_active_plans(): void
    {
        $plan1 = ReadingPlan::factory()->create();
        $plan2 = ReadingPlan::factory()->create();
        ReadingPlanProgress::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan1->id,
            'completed_days' => [1, 2],
        ]);
        ReadingPlanProgress::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan2->id,
            'completed_days' => [1],
        ]);

        $response = $this->getJson('/api/v1/reading-plans/active', $this->auth());

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_active_excludes_deleted(): void
    {
        $plan = ReadingPlan::factory()->create();
        ReadingPlanProgress::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
            'is_deleted' => true,
        ]);

        $response = $this->getJson('/api/v1/reading-plans/active', $this->auth());

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_active_excludes_other_users(): void
    {
        $plan = ReadingPlan::factory()->create();
        ReadingPlanProgress::factory()->create([
            'user_id' => User::factory()->create()->id,
            'plan_id' => $plan->id,
        ]);

        $response = $this->getJson('/api/v1/reading-plans/active', $this->auth());

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // ── Auth ──────────────────────────────────

    public function test_unauthenticated_rejected(): void
    {
        $this->getJson('/api/v1/reading-plans')->assertUnauthorized();
        $this->getJson('/api/v1/reading-plans/active')->assertUnauthorized();
    }
}
