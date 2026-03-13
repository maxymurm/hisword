<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeepLinkTest extends TestCase
{
    use RefreshDatabase;

    // ── Well-Known Endpoints ──────────────────────────────────

    public function test_assetlinks_json_returns_valid_structure(): void
    {
        $response = $this->getJson('/.well-known/assetlinks.json');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            [
                'relation',
                'target' => [
                    'namespace',
                    'package_name',
                    'sha256_cert_fingerprints',
                ],
            ],
        ]);
        $response->assertJsonPath('0.relation.0', 'delegate_permission/common.handle_all_urls');
        $response->assertJsonPath('0.target.namespace', 'android_app');
    }

    public function test_apple_app_site_association_returns_valid_structure(): void
    {
        $response = $this->getJson('/.well-known/apple-app-site-association');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'applinks' => [
                'details' => [
                    [
                        'appIDs',
                        'components',
                    ],
                ],
            ],
            'webcredentials' => [
                'apps',
            ],
        ]);
    }

    public function test_apple_app_site_association_includes_read_path(): void
    {
        $response = $this->getJson('/.well-known/apple-app-site-association');

        $data = $response->json();
        $components = $data['applinks']['details'][0]['components'];
        $paths = array_column($components, '/');

        $this->assertContains('/read/*', $paths);
    }

    // ── Share Link Generation ─────────────────────────────────

    public function test_generate_share_link_for_verse(): void
    {
        $response = $this->postJson('/deeplink/share', [
            'module' => 'KJV',
            'book' => 'Gen',
            'chapter' => 1,
            'verse' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'links' => ['web', 'app', 'universal'],
            'preferred',
            'text',
        ]);

        $this->assertStringContains('/read/KJV/Gen/1', $response->json('links.web'));
        $this->assertStringContains('verse=1', $response->json('links.web'));
        $this->assertStringContains('HisWord://read/KJV/Gen/1/1', $response->json('links.app'));
        $this->assertEquals('Gen 1:1 (KJV)', $response->json('text'));
    }

    public function test_generate_share_link_without_verse(): void
    {
        $response = $this->postJson('/deeplink/share', [
            'module' => 'ESV',
            'book' => 'John',
            'chapter' => 3,
        ]);

        $response->assertStatus(200);
        $this->assertStringContains('/read/ESV/John/3', $response->json('links.web'));
        $this->assertStringNotContains('verse=', $response->json('links.web'));
        $this->assertEquals('John 3 (ESV)', $response->json('text'));
    }

    public function test_generate_share_link_with_type_app(): void
    {
        $response = $this->postJson('/deeplink/share', [
            'module' => 'KJV',
            'book' => 'Gen',
            'chapter' => 1,
            'type' => 'app',
        ]);

        $response->assertStatus(200);
        $this->assertStringStartsWith('HisWord://', $response->json('preferred'));
    }

    public function test_share_link_validates_required_fields(): void
    {
        $response = $this->postJson('/deeplink/share', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['module', 'book', 'chapter']);
    }

    public function test_share_link_validates_type_enum(): void
    {
        $response = $this->postJson('/deeplink/share', [
            'module' => 'KJV',
            'book' => 'Gen',
            'chapter' => 1,
            'type' => 'invalid',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('type');
    }

    // ── Bookmark Deep Links ───────────────────────────────────

    public function test_bookmark_link_generation(): void
    {
        $folderId = fake()->uuid();

        $response = $this->postJson('/deeplink/bookmark', [
            'folder_id' => $folderId,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'links' => ['web', 'app', 'universal'],
        ]);
        $this->assertStringContains("/bookmarks/{$folderId}", $response->json('links.web'));
        $this->assertStringContains("HisWord://bookmarks/{$folderId}", $response->json('links.app'));
    }

    public function test_bookmark_link_validates_folder_id(): void
    {
        $response = $this->postJson('/deeplink/bookmark', [
            'folder_id' => 'not-a-uuid',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('folder_id');
    }

    // ── Search Deep Links ─────────────────────────────────────

    public function test_search_link_generation(): void
    {
        $response = $this->postJson('/deeplink/search', [
            'query' => 'love one another',
            'module' => 'KJV',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'links' => ['web', 'app', 'universal'],
        ]);
        $this->assertStringContains('q=love', $response->json('links.web'));
        $this->assertStringContains('module=KJV', $response->json('links.web'));
    }

    public function test_search_link_without_module(): void
    {
        $response = $this->postJson('/deeplink/search', [
            'query' => 'faith',
        ]);

        $response->assertStatus(200);
        $this->assertStringContains('q=faith', $response->json('links.web'));
        $this->assertStringNotContains('module=', $response->json('links.web'));
    }

    public function test_search_link_validates_query(): void
    {
        $response = $this->postJson('/deeplink/search', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('query');
    }

    // ── Deep Link Resolution ──────────────────────────────────

    public function test_resolve_deep_link_redirects_to_reader(): void
    {
        $response = $this->get('/link/KJV/Gen/1');

        $response->assertRedirect('/read/KJV/Gen/1');
    }

    public function test_resolve_deep_link_with_verse_query(): void
    {
        $response = $this->get('/link/KJV/Gen/1?verse=3');

        $response->assertRedirect('/read/KJV/Gen/1?verse=3');
    }

    // ── Helper Methods ────────────────────────────────────────

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'."
        );
    }

    private function assertStringNotContains(string $needle, string $haystack): void
    {
        $this->assertFalse(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' does not contain '{$needle}'."
        );
    }
}
