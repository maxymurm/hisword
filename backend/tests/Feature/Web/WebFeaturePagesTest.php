<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebFeaturePagesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ── Public pages ────────────────────────────────────────────

    public function test_home_page_loads(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_modules_page_loads(): void
    {
        $response = $this->get('/modules');
        $response->assertStatus(200);
    }

    public function test_reader_page_loads(): void
    {
        $response = $this->get('/read');
        $response->assertStatus(200);
    }

    public function test_cross_references_page_loads(): void
    {
        $response = $this->get('/cross-references');
        $response->assertStatus(200);
    }

    public function test_verse_of_day_returns_json(): void
    {
        $response = $this->getJson('/verse-of-day');
        $response->assertStatus(200)
            ->assertJsonStructure(['verse' => ['ref', 'text'], 'date']);
    }

    public function test_devotionals_index_loads(): void
    {
        $response = $this->get('/devotionals');
        $response->assertStatus(200);
    }

    // ── Auth-required pages ─────────────────────────────────────

    public function test_history_requires_auth(): void
    {
        $response = $this->get('/history');
        $response->assertRedirect('/login');
    }

    public function test_history_loads_when_authenticated(): void
    {
        $response = $this->actingAs($this->user)->get('/history');
        $response->assertStatus(200);
    }

    public function test_data_transfer_requires_auth(): void
    {
        $response = $this->get('/data-transfer');
        $response->assertRedirect('/login');
    }

    public function test_data_transfer_loads_when_authenticated(): void
    {
        $response = $this->actingAs($this->user)->get('/data-transfer');
        $response->assertStatus(200);
    }

    public function test_settings_page_loads(): void
    {
        $response = $this->get('/settings');
        $response->assertStatus(200);
    }

    // ── Admin pages ─────────────────────────────────────────────

    public function test_admin_requires_auth(): void
    {
        $response = $this->get('/admin');
        $response->assertRedirect('/login');
    }

    public function test_admin_requires_admin_role(): void
    {
        $response = $this->actingAs($this->user)->get('/admin');
        $response->assertStatus(403);
    }

    public function test_admin_loads_for_admin_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $response = $this->actingAs($admin)->get('/admin');
        $response->assertStatus(200);
    }
}
