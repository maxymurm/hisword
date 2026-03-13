<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\User;
use App\Models\Bookmark;
use App\Models\Highlight;
use App\Models\Note;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        if (!$request->user()?->is_admin) {
            abort(403, 'Admin access required.');
        }
    }

    public function dashboard(Request $request): Response
    {
        $this->authorizeAdmin($request);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'users' => User::count(),
                'modules_installed' => Module::where('is_installed', true)->count(),
                'modules_available' => Module::where('is_installed', false)->count(),
                'bookmarks' => Bookmark::count(),
                'highlights' => Highlight::count(),
                'notes' => Note::count(),
            ],
            'recent_users' => User::orderByDesc('created_at')->take(10)->get(['id', 'name', 'email', 'created_at']),
        ]);
    }

    public function users(Request $request): Response
    {
        $this->authorizeAdmin($request);

        $users = User::query()
            ->when($request->query('search'), function ($q, $search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->withCount(['bookmarks', 'highlights', 'notes'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'search' => $request->query('search', ''),
        ]);
    }

    public function modules(Request $request): Response
    {
        $this->authorizeAdmin($request);

        $modules = Module::orderByDesc('is_installed')
            ->orderBy('name')
            ->get(['key', 'name', 'type', 'language', 'is_installed', 'is_bundled', 'size']);

        return Inertia::render('Admin/Modules', [
            'modules' => $modules,
        ]);
    }
}
