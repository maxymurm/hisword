<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeepLinkController extends Controller
{
    /**
     * Android App Links: /.well-known/assetlinks.json
     *
     * Allows Android to verify that your app is the official handler
     * for URLs on this domain.
     */
    public function assetLinks(): JsonResponse
    {
        $packageName = config('app.android_package', 'com.HisWord.app');
        $fingerprint = config('app.android_sha256', 'AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99:AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99');

        return response()->json([
            [
                'relation' => ['delegate_permission/common.handle_all_urls'],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => $packageName,
                    'sha256_cert_fingerprints' => [$fingerprint],
                ],
            ],
        ]);
    }

    /**
     * iOS Universal Links: /.well-known/apple-app-site-association
     *
     * Allows iOS to verify that your app is the official handler
     * for URLs on this domain.
     */
    public function appleAppSiteAssociation(): JsonResponse
    {
        $appId = config('app.apple_app_id', 'TEAMID.com.HisWord.app');

        return response()->json([
            'applinks' => [
                'details' => [
                    [
                        'appIDs' => [$appId],
                        'components' => [
                            [
                                '/' => '/read/*',
                                'comment' => 'Bible reader deep links',
                            ],
                            [
                                '/' => '/bookmarks/*',
                                'comment' => 'Bookmark deep links',
                            ],
                            [
                                '/' => '/search*',
                                'comment' => 'Search deep links',
                            ],
                            [
                                '/' => '/plans/*',
                                'comment' => 'Reading plan deep links',
                            ],
                        ],
                    ],
                ],
            ],
            'webcredentials' => [
                'apps' => [$appId],
            ],
        ]);
    }

    /**
     * Generate a shareable deep link URL for a verse.
     */
    public function generateShareLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module' => ['required', 'string', 'max:50'],
            'book' => ['required', 'string', 'max:20'],
            'chapter' => ['required', 'integer', 'min:1'],
            'verse' => ['nullable', 'integer', 'min:1'],
            'type' => ['nullable', 'string', 'in:web,app,universal'],
        ]);

        $type = $validated['type'] ?? 'universal';
        $module = $validated['module'];
        $book = $validated['book'];
        $chapter = $validated['chapter'];
        $verse = $validated['verse'] ?? null;

        $links = [];

        // Web URL (always generated)
        $webPath = "/read/{$module}/{$book}/{$chapter}";
        if ($verse) {
            $webPath .= "?verse={$verse}";
        }
        $links['web'] = url($webPath);

        // App scheme URL
        $appPath = "HisWord://read/{$module}/{$book}/{$chapter}";
        if ($verse) {
            $appPath .= "/{$verse}";
        }
        $links['app'] = $appPath;

        // Universal link (same as web, apps intercept it)
        $links['universal'] = $links['web'];

        return response()->json([
            'links' => $links,
            'preferred' => $links[$type] ?? $links['universal'],
            'text' => $this->formatVerseReference($module, $book, $chapter, $verse),
        ]);
    }

    /**
     * Handle a deep link redirect — if the user has the app, they'll be
     * intercepted by universal links; otherwise they see the web reader.
     */
    public function resolve(Request $request, string $module, string $book, int $chapter): \Illuminate\Http\RedirectResponse
    {
        $verse = $request->query('verse');

        // Check if request is from a known mobile user-agent
        $ua = $request->userAgent() ?? '';
        $isMobile = preg_match('/Android|iPhone|iPad|iPod/i', $ua);

        if ($isMobile && ! $request->query('web')) {
            // For mobile, try app scheme first (with fallback meta tag handled by frontend)
            return redirect()->to("/read/{$module}/{$book}/{$chapter}" . ($verse ? "?verse={$verse}" : ''));
        }

        return redirect()->to("/read/{$module}/{$book}/{$chapter}" . ($verse ? "?verse={$verse}" : ''));
    }

    /**
     * Generate deep link for a bookmark folder.
     */
    public function bookmarkLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'folder_id' => ['required', 'string', 'uuid'],
        ]);

        return response()->json([
            'links' => [
                'web' => url("/bookmarks/{$validated['folder_id']}"),
                'app' => "HisWord://bookmarks/{$validated['folder_id']}",
                'universal' => url("/bookmarks/{$validated['folder_id']}"),
            ],
        ]);
    }

    /**
     * Generate deep link for search results.
     */
    public function searchLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:200'],
            'module' => ['nullable', 'string', 'max:50'],
        ]);

        $params = ['q' => $validated['query']];
        if (! empty($validated['module'])) {
            $params['module'] = $validated['module'];
        }

        $queryString = http_build_query($params);

        return response()->json([
            'links' => [
                'web' => url("/search?{$queryString}"),
                'app' => "HisWord://search?{$queryString}",
                'universal' => url("/search?{$queryString}"),
            ],
        ]);
    }

    /**
     * Format a human-readable verse reference.
     */
    private function formatVerseReference(string $module, string $book, int $chapter, ?int $verse): string
    {
        $ref = "{$book} {$chapter}";
        if ($verse) {
            $ref .= ":{$verse}";
        }

        return "{$ref} ({$module})";
    }
}
