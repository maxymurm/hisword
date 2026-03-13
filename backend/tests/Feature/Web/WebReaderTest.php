<?php

namespace Tests\Feature\Web;

use App\Models\Book;
use App\Models\Chapter;
use App\Models\Highlight;
use App\Models\Module;
use App\Models\Note;
use App\Models\User;
use App\Models\Verse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebReaderTest extends TestCase
{
    use RefreshDatabase;

    private Module $module;
    private Book $book;
    private Chapter $chapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->module = Module::factory()->create([
            'key'          => 'KJV',
            'name'         => 'King James Version',
            'type'         => 'bible',
            'is_installed' => true,
        ]);

        $this->book = Book::factory()->create([
            'module_id'     => $this->module->id,
            'osis_id'       => 'Gen',
            'name'          => 'Genesis',
            'abbreviation'  => 'Gen',
            'testament'     => 'OT',
            'book_order'    => 1,
            'chapter_count' => 50,
        ]);

        $this->chapter = Chapter::factory()->create([
            'book_id'        => $this->book->id,
            'chapter_number' => 1,
            'verse_count'    => 3,
        ]);

        foreach (range(1, 3) as $v) {
            Verse::factory()->create([
                'module_id'      => $this->module->id,
                'book_osis_id'   => 'Gen',
                'chapter_number' => 1,
                'verse_number'   => $v,
                'text_raw'       => "Verse {$v} text content.",
                'text_rendered'  => "Verse {$v} text content.",
            ]);
        }
    }

    public function test_reader_page_renders(): void
    {
        $response = $this->get('/read');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Reader')
            ->has('modules')
            ->has('books')
            ->has('verses')
            ->where('moduleKey', 'KJV')
        );
    }

    public function test_reader_with_module_book_chapter(): void
    {
        $response = $this->get('/read/KJV/Gen/1');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Reader')
            ->where('moduleKey', 'KJV')
            ->where('chapterNumber', 1)
            ->where('currentBook.osis_id', 'Gen')
            ->has('verses') // count varies: 3 from DB, 31 from SWORD binary
        );
    }

    public function test_reader_returns_verses_in_order(): void
    {
        $response = $this->get('/read/KJV/Gen/1');

        $response->assertInertia(fn ($page) => $page
            ->where('verses.0.number', 1)
            ->where('verses.1.number', 2)
            ->where('verses.2.number', 3)
        );
    }

    public function test_reader_with_navigation_links(): void
    {
        Chapter::factory()->create([
            'book_id'        => $this->book->id,
            'chapter_number' => 2,
            'verse_count'    => 5,
        ]);

        $response = $this->get('/read/KJV/Gen/1');

        $response->assertInertia(fn ($page) => $page
            ->where('prevLink', null)
            ->where('nextLink.url', '/read/KJV/Gen/2')
        );
    }

    public function test_reader_chapter_2_has_prev_link(): void
    {
        Chapter::factory()->create([
            'book_id'        => $this->book->id,
            'chapter_number' => 2,
            'verse_count'    => 5,
        ]);

        $response = $this->get('/read/KJV/Gen/2');

        $response->assertInertia(fn ($page) => $page
            ->where('prevLink.url', '/read/KJV/Gen/1')
        );
    }

    public function test_reader_with_highlights(): void
    {
        $user = User::factory()->create();

        Highlight::factory()->create([
            'user_id'        => $user->id,
            'book_osis_id'   => 'Gen',
            'chapter_number' => 1,
            'verse_number'   => 1,
            'color'          => 'yellow',
            'module_key'     => 'KJV',
        ]);

        $response = $this->actingAs($user)->get('/read/KJV/Gen/1');

        $response->assertInertia(fn ($page) => $page
            ->has('highlights', 1)
            ->where('highlights.0.verse', 1)
        );
    }

    public function test_reader_with_notes(): void
    {
        $user = User::factory()->create();

        Note::factory()->create([
            'user_id'        => $user->id,
            'book_osis_id'   => 'Gen',
            'chapter_number' => 1,
            'verse_start'    => 1,
            'title'          => 'My Note',
            'module_key'     => 'KJV',
        ]);

        $response = $this->actingAs($user)->get('/read/KJV/Gen/1');

        $response->assertInertia(fn ($page) => $page
            ->has('notes', 1)
        );
    }

    public function test_reader_without_auth_no_highlights(): void
    {
        $response = $this->get('/read/KJV/Gen/1');

        $response->assertInertia(fn ($page) => $page
            ->where('highlights', [])
            ->where('notes', [])
        );
    }

    public function test_reader_falls_back_to_first_module(): void
    {
        $response = $this->get('/read/UNKNOWN/Gen/1');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('moduleKey', 'KJV')
        );
    }

    public function test_reader_lists_available_modules(): void
    {
        Module::factory()->create([
            'key'          => 'ESV',
            'type'         => 'bible',
            'is_installed' => true,
        ]);

        $response = $this->get('/read');

        $response->assertInertia(fn ($page) => $page
            ->has('modules', 2)
        );
    }

    public function test_reader_includes_total_chapters(): void
    {
        $response = $this->get('/read/KJV/Gen/1');

        $response->assertInertia(fn ($page) => $page
            ->where('totalChapters', 50)
        );
    }
}
