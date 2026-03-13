<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_page_renders(): void
    {
        $this->withoutVite();

        $response = $this->get('/onboarding');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Onboarding')
                ->has('bibleModules')
        );
    }

    public function test_onboarding_page_includes_installed_bible_modules(): void
    {
        $this->withoutVite();

        Module::factory()->create([
            'key' => 'KJV',
            'name' => 'King James Version',
            'type' => 'bible',
            'is_installed' => true,
            'language' => 'English',
        ]);
        Module::factory()->create([
            'key' => 'ESV',
            'name' => 'English Standard Version',
            'type' => 'bible',
            'is_installed' => true,
            'language' => 'English',
        ]);
        Module::factory()->create([
            'key' => 'Commentary',
            'name' => 'A Commentary',
            'type' => 'commentary',
            'is_installed' => true,
            'language' => 'English',
        ]);
        Module::factory()->create([
            'key' => 'NotInstalled',
            'name' => 'Not Installed Bible',
            'type' => 'bible',
            'is_installed' => false,
            'language' => 'English',
        ]);

        $this->withoutVite();

        $response = $this->get('/onboarding');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Onboarding')
                ->has('bibleModules', 2)
        );
    }

    public function test_onboarding_complete_as_guest(): void
    {
        $response = $this->postJson('/onboarding/complete', [
            'preferred_module' => 'KJV',
            'theme' => 'dark',
            'notifications_enabled' => false,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonStructure(['redirect']);
    }

    public function test_onboarding_complete_as_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/onboarding/complete', [
            'preferred_module' => 'ESV',
            'theme' => 'light',
            'notifications_enabled' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $user->id,
            'key' => 'preferred_module',
        ]);
        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $user->id,
            'key' => 'theme',
        ]);
        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $user->id,
            'key' => 'onboarding_completed',
        ]);
    }

    public function test_onboarding_complete_validates_theme(): void
    {
        $response = $this->postJson('/onboarding/complete', [
            'theme' => 'rainbow',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('theme');
    }

    public function test_onboarding_complete_with_defaults(): void
    {
        $response = $this->postJson('/onboarding/complete', []);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_onboarding_status_for_guest_not_completed(): void
    {
        $response = $this->getJson('/onboarding/status');

        $response->assertStatus(200);
        $response->assertJson(['completed' => false]);
    }

    public function test_onboarding_status_for_guest_after_completion(): void
    {
        // Complete onboarding first
        $this->postJson('/onboarding/complete', [
            'preferred_module' => 'KJV',
            'theme' => 'system',
        ]);

        $response = $this->getJson('/onboarding/status');

        $response->assertStatus(200);
        $response->assertJson(['completed' => true]);
    }

    public function test_onboarding_status_for_authenticated_user_not_completed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/onboarding/status');

        $response->assertStatus(200);
        $response->assertJson(['completed' => false]);
    }

    public function test_onboarding_status_for_authenticated_user_after_completion(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/onboarding/complete', [
            'preferred_module' => 'KJV',
            'theme' => 'system',
        ]);

        $response = $this->actingAs($user)->getJson('/onboarding/status');

        $response->assertStatus(200);
        $response->assertJson(['completed' => true]);
    }

    public function test_onboarding_accessible_via_named_route(): void
    {
        $this->withoutVite();

        $response = $this->get(route('onboarding'));

        $response->assertStatus(200);
    }

    public function test_onboarding_complete_accessible_via_named_route(): void
    {
        $response = $this->postJson(route('onboarding.complete'), [
            'preferred_module' => 'KJV',
            'theme' => 'system',
        ]);

        $response->assertStatus(200);
    }
}
