<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class AuthController extends BaseApiController
{
    /**
     * Register a new user and return a Sanctum token.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'timezone' => ['sometimes', 'string', 'max:50'],
            'locale' => ['sometimes', 'string', 'max:10'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'timezone' => $validated['timezone'] ?? 'UTC',
            'locale' => $validated['locale'] ?? 'en',
        ]);

        $token = $user->createToken(
            $request->input('device_name', 'api'),
        )->plainTextToken;

        return $this->success([
            'user' => $user,
            'token' => $token,
        ], 'Registration successful', 201);
    }

    /**
     * Authenticate user and return a Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken(
            $request->input('device_name', 'api'),
        )->plainTextToken;

        return $this->success([
            'user' => $user,
            'token' => $token,
        ], 'Login successful');
    }

    /**
     * Revoke current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logged out successfully');
    }

    /**
     * Revoke all access tokens for the user.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return $this->success(null, 'All sessions revoked');
    }

    /**
     * Get the authenticated user.
     */
    public function user(Request $request): JsonResponse
    {
        return $this->success($request->user());
    }

    /**
     * Update the authenticated user's profile.
     */
    public function updateUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $request->user()->id],
            'password' => ['sometimes', 'confirmed', Rules\Password::defaults()],
            'avatar_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'timezone' => ['sometimes', 'string', 'max:50'],
            'locale' => ['sometimes', 'string', 'max:10'],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $request->user()->update($validated);

        return $this->success($request->user()->fresh(), 'Profile updated');
    }

    /**
     * Send a password reset link.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return $this->success(null, 'Password reset link sent');
    }

    /**
     * Reset the user's password.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return $this->success(null, 'Password has been reset');
    }
}
