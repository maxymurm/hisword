<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebAuthTest extends TestCase
{
    use RefreshDatabase;

    // ── Login Page ──────────────────────────────────────────────

    public function test_login_page_renders(): void
    {
        $this->withoutVite();

        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/Login'));
    }

    public function test_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_invalid_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_with_remember_me(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
            'remember' => true,
        ]);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_authenticated_user_redirected_from_login(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/login');
        $response->assertRedirect('/');
    }

    // ── Register Page ───────────────────────────────────────────

    public function test_register_page_renders(): void
    {
        $this->withoutVite();

        $response = $this->get('/register');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/Register'));
    }

    public function test_register_creates_user(): void
    {
        $response = $this->post('/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'name' => 'John Doe',
        ]);
    }

    public function test_register_validates_required_fields(): void
    {
        $response = $this->post('/register', []);

        $response->assertSessionHasErrors(['name', 'email', 'password']);
    }

    public function test_register_validates_unique_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->post('/register', [
            'name' => 'Another User',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_register_validates_password_confirmation(): void
    {
        $response = $this->post('/register', [
            'name' => 'John',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
        ]);

        $response->assertSessionHasErrors('password');
    }

    // ── Logout ──────────────────────────────────────────────────

    public function test_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    // ── Forgot Password ─────────────────────────────────────────

    public function test_forgot_password_page_renders(): void
    {
        $this->withoutVite();

        $response = $this->get('/forgot-password');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword'));
    }

    public function test_forgot_password_sends_reset_link(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertSessionHas('status');
    }

    public function test_forgot_password_validates_email(): void
    {
        $response = $this->post('/forgot-password', [
            'email' => '',
        ]);

        $response->assertSessionHasErrors('email');
    }

    // ── Reset Password ──────────────────────────────────────────

    public function test_reset_password_page_renders(): void
    {
        $this->withoutVite();

        $response = $this->get('/reset-password/test-token?email=test@example.com');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Auth/ResetPassword')
            ->where('token', 'test-token')
            ->where('email', 'test@example.com')
        );
    }

    // ── Profile ─────────────────────────────────────────────────

    public function test_profile_page_requires_auth(): void
    {
        $response = $this->get('/profile');
        $response->assertRedirect('/login');
    }

    public function test_profile_page_renders_when_authenticated(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/Profile'));
    }

    public function test_profile_update(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/profile', [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
    }

    public function test_profile_update_clears_verification_on_email_change(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'email' => 'new-email@example.com',
        ]);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_password_update(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertRedirect();
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_password_update_requires_current_password(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertSessionHasErrors('current_password');
    }

    public function test_account_deletion(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = $this->actingAs($user)->delete('/profile', [
            'password' => 'password123',
        ]);

        $response->assertRedirect('/');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_account_deletion_requires_password(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->delete('/profile', [
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    // ── Email Verification ──────────────────────────────────────

    public function test_verify_email_page_renders(): void
    {
        $this->withoutVite();

        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($user)->get('/verify-email');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/VerifyEmail'));
    }

    public function test_resend_verification_email(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($user)->post('/email/verification-notification');
        $response->assertRedirect();
        $response->assertSessionHas('status', 'verification-link-sent');
    }

    // ── Guest Middleware ────────────────────────────────────────

    public function test_authenticated_user_cannot_access_register(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/register');
        $response->assertRedirect('/');
    }

    public function test_authenticated_user_cannot_access_forgot_password(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/forgot-password');
        $response->assertRedirect('/');
    }
}
