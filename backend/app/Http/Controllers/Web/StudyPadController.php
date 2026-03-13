<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudyPadController extends Controller
{
    public function index(Request $request): Response
    {
        $notes = Note::where('user_id', $request->user()->id)
            ->where('is_deleted', false)
            ->orderByDesc('updated_at')
            ->paginate(20);

        return Inertia::render('StudyPad/Index', [
            'notes' => $notes,
        ]);
    }

    public function show(Request $request, Note $note): Response
    {
        if ($note->user_id !== $request->user()->id) {
            abort(403);
        }

        return Inertia::render('StudyPad/Editor', [
            'note' => $note,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'content' => ['sometimes', 'string', 'max:50000'],
            'book_osis_id' => ['sometimes', 'nullable', 'string', 'max:20'],
            'chapter_number' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'verse_start' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'verse_end' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'module_key' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $note = Note::create([
            'user_id' => $request->user()->id,
            'content_format' => 'markdown',
            ...$validated,
        ]);

        return response()->json($note, 201);
    }

    public function update(Request $request, Note $note): JsonResponse
    {
        if ($note->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'content' => ['sometimes', 'string', 'max:50000'],
        ]);

        $note->update($validated);

        return response()->json($note);
    }

    public function destroy(Request $request, Note $note): JsonResponse
    {
        if ($note->user_id !== $request->user()->id) {
            abort(403);
        }

        $note->update(['is_deleted' => true]);

        return response()->json(['success' => true]);
    }
}
