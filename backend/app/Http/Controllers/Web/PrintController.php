<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Verse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PrintController extends Controller
{
    /**
     * Print-optimized chapter view.
     */
    public function chapter(Request $request, string $module, string $book, int $chapter): Response
    {
        $moduleModel = Module::where('key', $module)->where('is_installed', true)->firstOrFail();

        $verses = Verse::where('module_id', $moduleModel->id)
            ->where('book_osis_id', $book)
            ->where('chapter_number', $chapter)
            ->orderBy('verse_number')
            ->get(['verse_number', 'text']);

        $bookName = config("bible.osis_to_name.{$book}", $book);
        $title = "{$bookName} {$chapter} ({$moduleModel->name})";

        $html = $this->buildPrintHtml($title, $bookName, $chapter, $verses, $request->query('style', 'classic'));

        return response($html)->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Print-optimized passage range.
     */
    public function passage(Request $request, string $module, string $book, int $chapter, int $verseStart, int $verseEnd): Response
    {
        $moduleModel = Module::where('key', $module)->where('is_installed', true)->firstOrFail();

        $verses = Verse::where('module_id', $moduleModel->id)
            ->where('book_osis_id', $book)
            ->where('chapter_number', $chapter)
            ->whereBetween('verse_number', [$verseStart, $verseEnd])
            ->orderBy('verse_number')
            ->get(['verse_number', 'text']);

        $bookName = config("bible.osis_to_name.{$book}", $book);
        $title = "{$bookName} {$chapter}:{$verseStart}-{$verseEnd} ({$moduleModel->name})";

        $html = $this->buildPrintHtml($title, $bookName, $chapter, $verses, $request->query('style', 'classic'));

        return response($html)->header('Content-Type', 'text/html; charset=utf-8');
    }

    private function buildPrintHtml(string $title, string $bookName, int $chapter, $verses, string $style): string
    {
        $styles = $this->getStyles($style);

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
        $html .= "<title>{$title}</title>";
        $html .= "<style>{$styles}</style></head><body>";
        $html .= '<div class="page">';
        $html .= "<h1>{$bookName}</h1>";
        $html .= "<h2>Chapter {$chapter}</h2>";
        $html .= '<div class="content">';

        foreach ($verses as $verse) {
            $text = strip_tags($verse->text);
            $html .= "<span class=\"verse\"><sup>{$verse->verse_number}</sup>{$text} </span>";
        }

        $html .= '</div>';
        $html .= '<footer>HisWord — ' . now()->format('F j, Y') . '</footer>';
        $html .= '</div>';
        $html .= '<script>window.onload = function() { window.print(); }</script>';
        $html .= '</body></html>';

        return $html;
    }

    private function getStyles(string $style): string
    {
        $base = 'body{margin:0;padding:0;color:#222;background:#fff}';
        $base .= '.page{max-width:700px;margin:0 auto;padding:40px 30px}';
        $base .= 'h1{margin:0 0 4px;text-align:center}h2{margin:0 0 24px;text-align:center;color:#666;font-weight:normal}';
        $base .= 'sup{color:#999;font-size:0.7em;margin-right:2px}';
        $base .= 'footer{margin-top:40px;text-align:center;color:#999;font-size:0.8em;border-top:1px solid #ddd;padding-top:12px}';
        $base .= '@media print{body{margin:0}.page{padding:20px}footer{position:fixed;bottom:20px;left:0;right:0}}';

        return match ($style) {
            'modern' => $base . 'body{font-family:Inter,system-ui,sans-serif;font-size:14px;line-height:1.8}.verse{display:block;margin-bottom:8px}h1{font-size:24px}h2{font-size:14px}',
            'large' => $base . 'body{font-family:Georgia,serif;font-size:20px;line-height:2}.verse{display:block;margin-bottom:12px}h1{font-size:32px}h2{font-size:18px}',
            'compact' => $base . 'body{font-family:system-ui,sans-serif;font-size:11px;line-height:1.5;columns:2;column-gap:24px}.content{text-align:justify}h1{font-size:16px;column-span:all}h2{font-size:11px;column-span:all}footer{column-span:all}',
            default => $base . 'body{font-family:Georgia,serif;font-size:16px;line-height:1.8}.content{text-align:justify}h1{font-size:28px}h2{font-size:16px}',
        };
    }
}
