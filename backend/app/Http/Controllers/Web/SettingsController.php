<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Settings', [
            'user'         => $request->user(),
            'appVersion'   => config('app.version', '1.0.0'),
            'phpVersion'   => PHP_VERSION,
            'laravelVersion' => app()->version(),
        ]);
    }
}
