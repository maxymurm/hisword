<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Bookmark;
use App\Models\Highlight;
use App\Models\Note;
use App\Models\Pin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DataTransferController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;
        $counts = [
            'bookmarks' => Bookmark::where('user_id', $userId)->count(),
            'highlights' => Highlight::where('user_id', $userId)->count(),
            'notes' => Note::where('user_id', $userId)->count(),
            'pins' => Pin::where('user_id', $userId)->count(),
        ];

        return Inertia::render('DataTransfer', [
            'counts' => $counts,
        ]);
    }

    public function export(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $data = [
            'version' => '1.0',
            'exported_at' => now()->toISOString(),
            'bookmarks' => Bookmark::where('user_id', $userId)->get()->toArray(),
            'highlights' => Highlight::where('user_id', $userId)->get()->toArray(),
            'notes' => Note::where('user_id', $userId)->get()->toArray(),
            'pins' => Pin::where('user_id', $userId)->get()->toArray(),
        ];

        return response()->json($data);
    }

    public function preview(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:json,txt|max:10240']);

        $content = json_decode($request->file('file')->get(), true);

        if (!$content || !is_array($content)) {
            return response()->json(['error' => 'Invalid JSON file.'], 422);
        }

        return response()->json([
            'version' => $content['version'] ?? 'unknown',
            'exported_at' => $content['exported_at'] ?? null,
            'counts' => [
                'bookmarks' => count($content['bookmarks'] ?? []),
                'highlights' => count($content['highlights'] ?? []),
                'notes' => count($content['notes'] ?? []),
                'pins' => count($content['pins'] ?? []),
            ],
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:json,txt|max:10240']);

        $content = json_decode($request->file('file')->get(), true);

        if (!$content || !is_array($content)) {
            return response()->json(['error' => 'Invalid JSON file.'], 422);
        }

        $userId = $request->user()->id;
        $imported = ['bookmarks' => 0, 'highlights' => 0, 'notes' => 0, 'pins' => 0];

        DB::transaction(function () use ($content, $userId, &$imported) {
            foreach ($content['bookmarks'] ?? [] as $item) {
                unset($item['id']);
                $item['user_id'] = $userId;
                Bookmark::create($item);
                $imported['bookmarks']++;
            }
            foreach ($content['highlights'] ?? [] as $item) {
                unset($item['id']);
                $item['user_id'] = $userId;
                Highlight::create($item);
                $imported['highlights']++;
            }
            foreach ($content['notes'] ?? [] as $item) {
                unset($item['id']);
                $item['user_id'] = $userId;
                Note::create($item);
                $imported['notes']++;
            }
            foreach ($content['pins'] ?? [] as $item) {
                unset($item['id']);
                $item['user_id'] = $userId;
                Pin::create($item);
                $imported['pins']++;
            }
        });

        return response()->json(['success' => true, 'imported' => $imported]);
    }
}
