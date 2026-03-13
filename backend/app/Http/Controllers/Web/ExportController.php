<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Bookmark;
use App\Models\Highlight;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExportController extends Controller
{
    /**
     * Export notes as a text/markdown file.
     */
    public function exportNotes(Request $request): Response
    {
        $format = $request->query('format', 'md');
        $notes = Note::where('user_id', $request->user()->id)
            ->where('is_deleted', false)
            ->orderByDesc('updated_at')
            ->get();

        if ($format === 'html') {
            return $this->exportAsHtml('My Study Notes', $notes->map(fn ($n) => [
                'title' => $n->title ?? 'Untitled',
                'content' => $n->content ?? '',
                'ref' => $n->book_osis_id ? "{$n->book_osis_id} {$n->chapter_number}" . ($n->verse_start ? ":{$n->verse_start}" : '') : null,
                'date' => $n->updated_at->format('Y-m-d'),
            ])->toArray());
        }

        $content = "# My Study Notes\n\n";
        $content .= "Exported on " . now()->format('Y-m-d H:i') . "\n\n---\n\n";

        foreach ($notes as $note) {
            $content .= "## " . ($note->title ?? 'Untitled') . "\n\n";
            if ($note->book_osis_id) {
                $content .= "*{$note->book_osis_id} {$note->chapter_number}";
                if ($note->verse_start) $content .= ":{$note->verse_start}";
                $content .= "*\n\n";
            }
            $content .= ($note->content ?? '') . "\n\n---\n\n";
        }

        return response($content)
            ->header('Content-Type', 'text/markdown; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="HisWord-notes.md"');
    }

    /**
     * Export bookmarks as a text file.
     */
    public function exportBookmarks(Request $request): Response
    {
        $format = $request->query('format', 'md');
        $bookmarks = Bookmark::where('user_id', $request->user()->id)
            ->where('is_deleted', false)
            ->orderBy('book_osis_id')
            ->orderBy('chapter_number')
            ->orderBy('verse_start')
            ->get();

        if ($format === 'html') {
            return $this->exportAsHtml('My Bookmarks', $bookmarks->map(fn ($b) => [
                'title' => $b->label ?? "{$b->book_osis_id} {$b->chapter_number}:{$b->verse_start}",
                'content' => $b->description ?? '',
                'ref' => "{$b->book_osis_id} {$b->chapter_number}:{$b->verse_start}",
                'date' => $b->created_at->format('Y-m-d'),
            ])->toArray());
        }

        $content = "# My Bookmarks\n\n";
        $content .= "Exported on " . now()->format('Y-m-d H:i') . "\n\n";

        foreach ($bookmarks as $bm) {
            $ref = "{$bm->book_osis_id} {$bm->chapter_number}:{$bm->verse_start}";
            $content .= "- **{$ref}**";
            if ($bm->label) $content .= " — {$bm->label}";
            if ($bm->description) $content .= "\n  {$bm->description}";
            $content .= "\n";
        }

        return response($content)
            ->header('Content-Type', 'text/markdown; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="HisWord-bookmarks.md"');
    }

    /**
     * Export highlights as a text file.
     */
    public function exportHighlights(Request $request): Response
    {
        $format = $request->query('format', 'md');
        $highlights = Highlight::where('user_id', $request->user()->id)
            ->where('is_deleted', false)
            ->orderBy('book_osis_id')
            ->orderBy('chapter_number')
            ->orderBy('verse_number')
            ->get();

        if ($format === 'html') {
            return $this->exportAsHtml('My Highlights', $highlights->map(fn ($h) => [
                'title' => "{$h->book_osis_id} {$h->chapter_number}:{$h->verse_number}",
                'content' => "Color: {$h->color}",
                'ref' => "{$h->book_osis_id} {$h->chapter_number}:{$h->verse_number}",
                'date' => $h->created_at->format('Y-m-d'),
            ])->toArray());
        }

        $content = "# My Highlights\n\n";
        $content .= "Exported on " . now()->format('Y-m-d H:i') . "\n\n";

        foreach ($highlights as $h) {
            $content .= "- {$h->book_osis_id} {$h->chapter_number}:{$h->verse_number} [{$h->color}]\n";
        }

        return response($content)
            ->header('Content-Type', 'text/markdown; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="HisWord-highlights.md"');
    }

    /**
     * Generate a printable HTML document.
     */
    private function exportAsHtml(string $title, array $items): Response
    {
        $html = "<!DOCTYPE html><html><head><meta charset=\"utf-8\"><title>{$title}</title>";
        $html .= '<style>body{font-family:Georgia,serif;max-width:700px;margin:40px auto;padding:20px;color:#333;line-height:1.6}';
        $html .= 'h1{border-bottom:2px solid #4f46e5;padding-bottom:8px}h2{color:#4f46e5;margin-top:24px}';
        $html .= '.ref{color:#6b7280;font-style:italic;font-size:0.9em}.date{color:#9ca3af;font-size:0.8em}';
        $html .= '.item{margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid #e5e7eb}';
        $html .= '@media print{body{margin:0;padding:15px}}</style></head><body>';
        $html .= "<h1>{$title}</h1>";
        $html .= '<p class="date">Exported on ' . now()->format('F j, Y') . '</p>';

        foreach ($items as $item) {
            $html .= '<div class="item">';
            $html .= '<h2>' . htmlspecialchars($item['title']) . '</h2>';
            if (!empty($item['ref'])) $html .= '<p class="ref">' . htmlspecialchars($item['ref']) . '</p>';
            if (!empty($item['content'])) $html .= '<p>' . nl2br(htmlspecialchars($item['content'])) . '</p>';
            $html .= '</div>';
        }

        $html .= '</body></html>';

        return response($html)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="HisWord-' . str_replace(' ', '-', strtolower($title)) . '.html"');
    }
}
