<?php

namespace Tests\Feature;

use App\Enums\ModuleType;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevotionalApiTest extends TestCase
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

    private function createDevotional(array $attrs = []): Module
    {
        return Module::factory()->create(array_merge([
            'type' => ModuleType::Devotional,
            'name' => 'Daily Light',
            'key' => 'DailyLight',
        ], $attrs));
    }

    // ── List ──────────────────────────────────

    public function test_list_devotional_modules(): void
    {
        $this->createDevotional(['name' => 'Daily Light']);
        $this->createDevotional(['name' => 'Spurgeon Morning', 'key' => 'SpurgeonMorn']);
        Module::factory()->create(['type' => ModuleType::Bible]); // should be excluded

        $response = $this->getJson('/api/v1/devotionals', $this->auth());

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_list_only_devotional_type(): void
    {
        Module::factory()->create(['type' => ModuleType::Bible]);
        Module::factory()->create(['type' => ModuleType::Commentary]);
        Module::factory()->create(['type' => ModuleType::Dictionary]);

        $response = $this->getJson('/api/v1/devotionals', $this->auth());

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // ── Today ─────────────────────────────────

    public function test_get_today_devotional(): void
    {
        $devo = $this->createDevotional();

        $response = $this->getJson("/api/v1/devotionals/{$devo->id}/today", $this->auth());

        $response->assertOk()
            ->assertJsonPath('data.module_key', 'DailyLight')
            ->assertJsonPath('data.date', now()->toDateString())
            ->assertJsonStructure([
                'data' => ['module_id', 'module_key', 'module_name', 'date', 'date_key', 'title', 'content'],
            ]);
    }

    public function test_get_today_by_key(): void
    {
        $devo = $this->createDevotional();

        $response = $this->getJson("/api/v1/devotionals/{$devo->key}/today", $this->auth());

        $response->assertOk()
            ->assertJsonPath('data.module_key', 'DailyLight');
    }

    public function test_today_not_found_for_non_devotional(): void
    {
        $bible = Module::factory()->create(['type' => ModuleType::Bible]);

        $response = $this->getJson("/api/v1/devotionals/{$bible->id}/today", $this->auth());

        $response->assertNotFound();
    }

    // ── Entry by Date ──────────────────────────

    public function test_get_entry_by_date_iso(): void
    {
        $devo = $this->createDevotional();

        $response = $this->getJson("/api/v1/devotionals/{$devo->id}/entry/2025-03-15", $this->auth());

        $response->assertOk()
            ->assertJsonPath('data.date_key', '03.15')
            ->assertJsonPath('data.date', '2025-03-15');
    }

    public function test_get_entry_by_date_mm_dd(): void
    {
        $devo = $this->createDevotional();

        $response = $this->getJson("/api/v1/devotionals/{$devo->id}/entry/03.15", $this->auth());

        $response->assertOk()
            ->assertJsonPath('data.date_key', '03.15');
    }

    public function test_get_entry_invalid_date_format(): void
    {
        $devo = $this->createDevotional();

        $response = $this->getJson("/api/v1/devotionals/{$devo->id}/entry/invalid", $this->auth());

        $response->assertStatus(422);
    }

    public function test_entry_returns_placeholder_content(): void
    {
        $devo = $this->createDevotional();

        $response = $this->getJson("/api/v1/devotionals/{$devo->id}/entry/03.15", $this->auth());

        $response->assertOk()
            ->assertJsonPath('data.title', 'March 15')
            ->assertJsonPath('data.has_scripture_refs', false);
    }

    // ── Auth ──────────────────────────────────

    public function test_unauthenticated_rejected(): void
    {
        $this->getJson('/api/v1/devotionals')->assertUnauthorized();
    }
}
