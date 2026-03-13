<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ── Registration ──────────────────────────────

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['user' => ['id', 'name', 'email'], 'token'],
            ]);

        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    }

    public function test_registration_requires_valid_data(): void
    {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_registration_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane',
            'email' => 'taken@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_accepts_timezone_and_locale(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ahmed',
            'email' => 'ahmed@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'timezone' => 'Asia/Riyadh',
            'locale' => 'ar',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'ahmed@example.com',
            'timezone' => 'Asia/Riyadh',
            'locale' => 'ar',
        ]);
    }

    // ── Login ─────────────────────────────────────

    public function test_user_can_login(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Password123!')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['user', 'token'],
            ]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Password123!')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(422);
    }

    // ── Authenticated Routes ──────────────────────

    public function test_can_get_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/auth/user');

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_can_update_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson('/api/v1/auth/user', [
                'name' => 'Updated Name',
                'timezone' => 'America/Chicago',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.timezone', 'America/Chicago');
    }

    public function test_can_update_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('OldPassword1!')]);

        $response = $this->actingAs($user)
            ->putJson('/api/v1/auth/user', [
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ]);

        $response->assertOk();

        // Verify old password no longer works
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'OldPassword1!',
        ]);
        $loginResponse->assertStatus(422);

        // Verify new password works
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'NewPassword123!',
        ]);
        $loginResponse->assertOk();
    }

    // ── Logout ────────────────────────────────────

    public function test_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertOk()
            ->assertJsonPath('message', 'Logged out successfully');

        // Verify token was deleted from database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'test',
        ]);
    }

    public function test_can_logout_all_sessions(): void
    {
        $user = User::factory()->create();
        $user->createToken('device1');
        $user->createToken('device2');
        $token1 = $user->createToken('device3')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token1}")
            ->postJson('/api/v1/auth/logout-all')
            ->assertOk();

        // All tokens should be deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    // ── Authorization ─────────────────────────────

    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $this->getJson('/api/v1/auth/user')->assertStatus(401);
        $this->putJson('/api/v1/auth/user')->assertStatus(401);
        $this->postJson('/api/v1/auth/logout')->assertStatus(401);
    }
}
