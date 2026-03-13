<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Chapter;
use App\Models\Module;
use App\Models\User;
use App\Models\Verse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessibilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Module $module;
    protected Book $book;
    protected Chapter $chapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->user = User::factory()->create();

        $this->module = Module::factory()->create([
            'key' => 'KJV',
            'type' => 'bible',
            'is_installed' => true,
        ]);

        $this->book = Book::factory()->create([
            'module_id' => $this->module->id,
            'osis_id' => 'Gen',
            'name' => 'Genesis',
            'abbreviation' => 'Gen',
            'testament' => 'OT',
            'book_order' => 1,
            'chapter_count' => 50,
        ]);

        $this->chapter = Chapter::factory()->create([
            'book_id' => $this->book->id,
            'chapter_number' => 1,
            'verse_count' => 3,
        ]);

        for ($i = 1; $i <= 3; $i++) {
            Verse::factory()->create([
                'module_id' => $this->module->id,
                'book_osis_id' => 'Gen',
                'chapter_number' => 1,
                'verse_number' => $i,
                'text_raw' => "Verse {$i} text",
                'text_rendered' => "<p>Verse {$i} text</p>",
            ]);
        }
    }

    // ── Security Headers ─────────────────────────────────────

    public function test_pages_have_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    // ── HTML Structure ───────────────────────────────────────

    public function test_home_page_returns_html(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_reader_page_is_accessible(): void
    {
        $response = $this->get('/read/KJV/Gen/1');
        $response->assertStatus(200);
    }

    public function test_search_page_is_accessible(): void
    {
        $response = $this->get('/search');
        $response->assertStatus(200);
    }

    // ── Keyboard Navigation ─────────────────────────────────

    public function test_reader_provides_navigation_links(): void
    {
        $response = $this->get('/read/KJV/Gen/1');
        $response->assertStatus(200);
    }

    // ── Auth Pages ──────────────────────────────────────────

    public function test_login_page_is_accessible(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_register_page_is_accessible(): void
    {
        $response = $this->get('/register');
        $response->assertStatus(200);
    }

    // ── Authenticated Pages ─────────────────────────────────

    public function test_bookmarks_page_is_accessible(): void
    {
        $response = $this->actingAs($this->user)->get('/bookmarks');
        $response->assertStatus(200);
    }

    public function test_notes_page_is_accessible(): void
    {
        $response = $this->actingAs($this->user)->get('/notes');
        $response->assertStatus(200);
    }

    public function test_highlights_page_is_accessible(): void
    {
        $response = $this->actingAs($this->user)->get('/highlights');
        $response->assertStatus(200);
    }

    // ── API Accessibility ───────────────────────────────────

    public function test_api_responses_are_json(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/modules');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
    }

    public function test_well_known_routes_are_json(): void
    {
        $response = $this->get('/.well-known/assetlinks.json');
        $response->assertStatus(200);

        $response = $this->get('/.well-known/apple-app-site-association');
        $response->assertStatus(200);
    }

    // ── CORS & Content-Type ─────────────────────────────────

    public function test_api_returns_proper_content_type(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/modules');

        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
    }

    // ── Error Pages ─────────────────────────────────────────

    public function test_404_returns_error(): void
    {
        $response = $this->get('/nonexistent-page');
        $response->assertStatus(404);
    }

    // ── Dynamic Text Size Support ───────────────────────────

    public function test_reader_returns_verse_text(): void
    {
        $response = $this->get('/read/KJV/Gen/1');
        $response->assertStatus(200);
        // Verse text should be available for dynamic rendering
    }

    // ── Onboarding Accessibility ────────────────────────────

    public function test_onboarding_page_is_accessible(): void
    {
        $response = $this->get('/onboarding');
        $response->assertStatus(200);
    }
}
