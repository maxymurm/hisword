<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    /**
     * Show the onboarding page.
     */
    public function show(): Response
    {
        $bibleModules = Module::where('type', 'bible')
            ->where('is_installed', true)
            ->select('id', 'key', 'name', 'language', 'description')
            ->orderBy('name')
            ->get();

        return Inertia::render('Onboarding', [
            'bibleModules' => $bibleModules,
        ]);
    }

    /**
     * Save onboarding preferences (module, theme, etc.).
     */
    public function complete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferred_module' => ['nullable', 'string', 'max:50'],
            'theme' => ['nullable', 'string', 'in:light,dark,system'],
            'notifications_enabled' => ['nullable', 'boolean'],
        ]);

        // Store preferences in session for guests, or user preferences for authenticated users
        if ($request->user()) {
            $user = $request->user();
            $user->preferences()->updateOrCreate(
                ['key' => 'preferred_module'],
                ['value' => ['module' => $validated['preferred_module'] ?? 'KJV']]
            );
            $user->preferences()->updateOrCreate(
                ['key' => 'theme'],
                ['value' => ['theme' => $validated['theme'] ?? 'system']]
            );
            $user->preferences()->updateOrCreate(
                ['key' => 'notifications_enabled'],
                ['value' => ['enabled' => $validated['notifications_enabled'] ?? false]]
            );
            $user->preferences()->updateOrCreate(
                ['key' => 'onboarding_completed'],
                ['value' => ['completed' => true]]
            );
        }

        // Mark onboarding as completed in session regardless
        $request->session()->put('onboarding_completed', true);

        return response()->json([
            'success' => true,
            'redirect' => route('home'),
        ]);
    }

    /**
     * Check if onboarding has been completed.
     */
    public function status(Request $request): JsonResponse
    {
        $completed = false;

        if ($request->user()) {
            $pref = $request->user()->preferences()
                ->where('key', 'onboarding_completed')
                ->first();
            $completed = $pref && ($pref->value['completed'] ?? false);
        } else {
            $completed = (bool) $request->session()->get('onboarding_completed', false);
        }

        return response()->json([
            'completed' => $completed,
        ]);
    }
}
