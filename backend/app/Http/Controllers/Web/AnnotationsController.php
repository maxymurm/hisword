<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Bookmark;
use App\Models\BookmarkFolder;
use App\Models\Highlight;
use App\Models\Note;
use App\Models\Pin;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnnotationsController extends Controller
{
    private const PER_PAGE = 50;

    public function bookmarks(Request $request): Response
    {
        $userId = $request->user()->id;

        $folders = BookmarkFolder::where('user_id', $userId)
            ->orderBy('sort_order')
            ->get();

        $bookmarks = Bookmark::where('user_id', $userId)
            ->where('is_deleted', false)
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE);

        return Inertia::render('Annotations/Bookmarks', [
            'folders'   => $folders,
            'bookmarks' => $bookmarks,
        ]);
    }

    public function notes(Request $request): Response
    {
        $notes = Note::where('user_id', $request->user()->id)
            ->where('is_deleted', false)
            ->orderByDesc('updated_at')
            ->paginate(self::PER_PAGE);

        return Inertia::render('Annotations/Notes', [
            'notes' => $notes,
        ]);
    }

    public function highlights(Request $request): Response
    {
        $highlights = Highlight::where('user_id', $request->user()->id)
            ->where('is_deleted', false)
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE);

        return Inertia::render('Annotations/Highlights', [
            'highlights' => $highlights,
        ]);
    }

    public function pins(Request $request): Response
    {
        $pins = Pin::where('user_id', $request->user()->id)
            ->where('is_deleted', false)
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE);

        return Inertia::render('Annotations/Pins', [
            'pins' => $pins,
        ]);
    }
}
