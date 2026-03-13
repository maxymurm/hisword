<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function index(Request $request): Response
    {
        $tags = Tag::where('user_id', $request->user()->id)
            ->where('is_deleted', false)
            ->withCount(['bookmarks', 'notes', 'highlights'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Tags/Index', [
            'tags' => $tags,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['sometimes', 'string', 'max:20'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $tag = Tag::create([
            'user_id' => $request->user()->id,
            ...$validated,
        ]);

        return response()->json($tag, 201);
    }

    public function update(Request $request, Tag $tag): JsonResponse
    {
        if ($tag->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'color' => ['sometimes', 'string', 'max:20'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $tag->update($validated);

        return response()->json($tag);
    }

    public function destroy(Request $request, Tag $tag): JsonResponse
    {
        if ($tag->user_id !== $request->user()->id) {
            abort(403);
        }

        $tag->update(['is_deleted' => true]);

        return response()->json(['success' => true]);
    }

    public function show(Request $request, Tag $tag): Response
    {
        if ($tag->user_id !== $request->user()->id) {
            abort(403);
        }

        $tag->loadCount(['bookmarks', 'notes', 'highlights']);

        $bookmarks = $tag->bookmarks()->latest()->limit(20)->get();
        $notes = $tag->notes()->latest()->limit(20)->get();
        $highlights = $tag->highlights()->latest()->limit(20)->get();

        return Inertia::render('Tags/Show', [
            'tag' => $tag,
            'bookmarks' => $bookmarks,
            'notes' => $notes,
            'highlights' => $highlights,
        ]);
    }
}
