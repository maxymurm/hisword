<?php

namespace Tests\Feature\Web;

use App\Models\Bookmark;
use App\Models\BookmarkFolder;
use App\Models\Highlight;
use App\Models\Note;
use App\Models\Pin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebAnnotationsTest extends TestCase
{
    use RefreshDatabase;

    // ── Guest Redirect ───────────────────────────────────────

    public function test_guest_is_redirected_from_bookmarks(): void
    {
        $this->withoutVite();
        $this->get('/bookmarks')->assertRedirect('/login');
    }

    public function test_guest_is_redirected_from_notes(): void
    {
        $this->withoutVite();
        $this->get('/notes')->assertRedirect('/login');
    }

    public function test_guest_is_redirected_from_highlights(): void
    {
        $this->withoutVite();
        $this->get('/highlights')->assertRedirect('/login');
    }

    public function test_guest_is_redirected_from_pins(): void
    {
        $this->withoutVite();
        $this->get('/pins')->assertRedirect('/login');
    }

    // ── Bookmarks Page ───────────────────────────────────────

    public function test_bookmarks_page_renders(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->get('/bookmarks');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Annotations/Bookmarks')
                ->has('folders')
                ->has('bookmarks')
            );
    }

    public function test_bookmarks_page_shows_user_bookmarks(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $folder = BookmarkFolder::factory()->create(['user_id' => $user->id, 'name' => 'Favorites']);
        Bookmark::factory()->count(3)->create(['user_id' => $user->id, 'folder_id' => $folder->id]);
        // Create bookmark for other user (should not appear)
        Bookmark::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->get('/bookmarks');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Annotations/Bookmarks')
                ->has('folders', 1)
                ->has('bookmarks.data', 3)
            );
    }

    public function test_bookmarks_excludes_deleted(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        Bookmark::factory()->create(['user_id' => $user->id, 'is_deleted' => false]);
        Bookmark::factory()->create(['user_id' => $user->id, 'is_deleted' => true]);

        $response = $this->actingAs($user, 'web')
            ->get('/bookmarks');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('bookmarks.data', 1)
            );
    }

    // ── Notes Page ───────────────────────────────────────────

    public function test_notes_page_renders(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->get('/notes');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Annotations/Notes')
                ->has('notes')
            );
    }

    public function test_notes_page_shows_user_notes(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        Note::factory()->count(5)->create(['user_id' => $user->id]);
        Note::factory()->create(); // other user

        $response = $this->actingAs($user, 'web')
            ->get('/notes');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('notes.data', 5)
            );
    }

    public function test_notes_excludes_deleted(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        Note::factory()->create(['user_id' => $user->id, 'is_deleted' => false]);
        Note::factory()->create(['user_id' => $user->id, 'is_deleted' => true]);

        $response = $this->actingAs($user, 'web')
            ->get('/notes');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('notes.data', 1)
            );
    }

    // ── Highlights Page ──────────────────────────────────────

    public function test_highlights_page_renders(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->get('/highlights');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Annotations/Highlights')
                ->has('highlights')
            );
    }

    public function test_highlights_page_shows_user_highlights(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        Highlight::factory()->count(4)->create(['user_id' => $user->id]);
        Highlight::factory()->create(); // other user

        $response = $this->actingAs($user, 'web')
            ->get('/highlights');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('highlights.data', 4)
            );
    }

    public function test_highlights_excludes_deleted(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        Highlight::factory()->create(['user_id' => $user->id, 'is_deleted' => false]);
        Highlight::factory()->create(['user_id' => $user->id, 'is_deleted' => true]);

        $response = $this->actingAs($user, 'web')
            ->get('/highlights');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('highlights.data', 1)
            );
    }

    // ── Pins Page ────────────────────────────────────────────

    public function test_pins_page_renders(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->get('/pins');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Annotations/Pins')
                ->has('pins')
            );
    }

    public function test_pins_page_shows_user_pins(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        Pin::factory()->count(2)->create(['user_id' => $user->id]);
        Pin::factory()->create(); // other user

        $response = $this->actingAs($user, 'web')
            ->get('/pins');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('pins.data', 2)
            );
    }

    public function test_pins_excludes_deleted(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        Pin::factory()->create(['user_id' => $user->id, 'is_deleted' => false]);
        Pin::factory()->create(['user_id' => $user->id, 'is_deleted' => true]);

        $response = $this->actingAs($user, 'web')
            ->get('/pins');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('pins.data', 1)
            );
    }
}
