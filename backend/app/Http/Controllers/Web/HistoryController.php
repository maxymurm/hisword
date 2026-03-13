<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\History;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $history = History::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(30);

        return Inertia::render('History', [
            'history' => $history,
        ]);
    }

    public function destroy(Request $request)
    {
        History::where('user_id', $request->user()->id)->delete();

        return back()->with('flash', ['message' => 'Reading history cleared.']);
    }
}
