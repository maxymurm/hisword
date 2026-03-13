<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\ModuleSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ── Module Listing ──────────────────────────────

    public function test_list_modules(): void
    {
        Module::factory()->count(5)->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/modules');

        $response->assertOk()
            ->assertJsonPath('meta.total', 5);
    }

    public function test_filter_modules_by_type(): void
    {
        Module::factory()->bible()->count(3)->create();
        Module::factory()->commentary()->count(2)->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/modules?type=bible');

        $response->assertOk()
            ->assertJsonPath('meta.total', 3);
    }

    public function test_filter_modules_by_language(): void
    {
        Module::factory()->create(['language' => 'en']);
        Module::factory()->create(['language' => 'es']);
        Module::factory()->create(['language' => 'en']);

        $response = $this->actingAs($this->user)->getJson('/api/v1/modules?language=en');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_filter_modules_by_installed(): void
    {
        Module::factory()->installed()->count(2)->create();
        Module::factory()->count(3)->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/modules?installed=true');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_search_modules_by_name(): void
    {
        Module::factory()->create(['name' => 'King James Version', 'key' => 'KJV']);
        Module::factory()->create(['name' => 'English Standard Version', 'key' => 'ESV']);
        Module::factory()->create(['name' => 'Reina-Valera', 'key' => 'RVR']);

        $response = $this->actingAs($this->user)->getJson('/api/v1/modules?search=King');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    // ── Module Show ─────────────────────────────────

    public function test_show_module_by_key(): void
    {
        $module = Module::factory()->create(['key' => 'KJV']);

        $response = $this->actingAs($this->user)->getJson('/api/v1/modules/KJV');

        $response->assertOk()
            ->assertJsonPath('data.key', 'KJV');
    }

    public function test_show_module_by_id(): void
    {
        $module = Module::factory()->create();

        $response = $this->actingAs($this->user)->getJson("/api/v1/modules/{$module->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $module->id);
    }

    // ── Available Modules ───────────────────────────

    public function test_list_available_modules(): void
    {
        Module::factory()->installed()->count(2)->create();
        Module::factory()->count(3)->create(); // not installed

        $response = $this->actingAs($this->user)->getJson('/api/v1/modules/available');

        $response->assertOk()
            ->assertJsonPath('meta.total', 3);
    }

    // ── Install / Uninstall ─────────────────────────

    public function test_install_module(): void
    {
        $module = Module::factory()->create(['is_installed' => false]);

        $response = $this->actingAs($this->user)->postJson("/api/v1/modules/{$module->id}/install");

        $response->assertStatus(201)
            ->assertJsonPath('data.is_installed', true);

        $this->assertDatabaseHas('modules', ['id' => $module->id, 'is_installed' => true]);
    }

    public function test_install_already_installed_module(): void
    {
        $module = Module::factory()->installed()->create();

        $response = $this->actingAs($this->user)->postJson("/api/v1/modules/{$module->id}/install");

        $response->assertOk()
            ->assertJsonPath('message', 'Module already installed');
    }

    public function test_uninstall_module(): void
    {
        $module = Module::factory()->installed()->create();

        $response = $this->actingAs($this->user)->postJson("/api/v1/modules/{$module->id}/uninstall");

        $response->assertOk()
            ->assertJsonPath('data.is_installed', false);
    }

    // ── Module Sources ──────────────────────────────

    public function test_list_sources(): void
    {
        ModuleSource::factory()->count(3)->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/module-sources');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_add_source(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/module-sources', [
            'caption' => 'CrossWire',
            'type' => 'FTP',
            'server' => 'ftp.crosswire.org',
            'directory' => '/pub/sword/packages',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.caption', 'CrossWire');

        $this->assertDatabaseHas('module_sources', ['caption' => 'CrossWire']);
    }

    public function test_add_source_validates_type(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/module-sources', [
            'caption' => 'Test',
            'type' => 'SFTP',
            'server' => 'example.com',
            'directory' => '/mods',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_update_source(): void
    {
        $source = ModuleSource::factory()->create(['caption' => 'Old Name']);

        $response = $this->actingAs($this->user)->putJson("/api/v1/module-sources/{$source->id}", [
            'caption' => 'New Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.caption', 'New Name');
    }

    public function test_delete_source(): void
    {
        $source = ModuleSource::factory()->create();

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/module-sources/{$source->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('module_sources', ['id' => $source->id]);
    }

    // ── Auth ────────────────────────────────────────

    public function test_unauthenticated_access_rejected(): void
    {
        $this->getJson('/api/v1/modules')->assertUnauthorized();
        $this->getJson('/api/v1/module-sources')->assertUnauthorized();
    }
}
