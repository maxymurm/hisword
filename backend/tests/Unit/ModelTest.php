<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Bookmark;
use App\Models\BookmarkFolder;
use App\Models\Highlight;
use App\Models\Note;
use App\Models\Pin;
use App\Models\Device;
use App\Models\History;
use App\Models\Module;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Verse;
use App\Models\ReadingPlan;
use App\Models\ReadingPlanProgress;
use App\Models\UserPreference;
use App\Models\SyncLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_has_uuid_primary_key(): void
    {
        $this->assertIsString($this->user->id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $this->user->id
        );
    }

    public function test_user_has_extra_fields(): void
    {
        $user = User::factory()->create([
            'timezone' => 'America/New_York',
            'locale' => 'ar',
        ]);

        $this->assertEquals('America/New_York', $user->timezone);
        $this->assertEquals('ar', $user->locale);
    }

    public function test_device_belongs_to_user(): void
    {
        $device = Device::create([
            'user_id' => $this->user->id,
            'device_id' => 'test-device-123',
            'platform' => 'android',
            'name' => 'Test Phone',
        ]);

        $this->assertEquals($this->user->id, $device->user->id);
        $this->assertCount(1, $this->user->devices);
    }

    public function test_bookmark_folder_hierarchy(): void
    {
        $parent = BookmarkFolder::create([
            'user_id' => $this->user->id,
            'name' => 'Parent',
            'color' => '#FF0000',
        ]);

        $child = BookmarkFolder::create([
            'user_id' => $this->user->id,
            'parent_id' => $parent->id,
            'name' => 'Child',
            'color' => '#00FF00',
        ]);

        $this->assertEquals($parent->id, $child->parent->id);
        $this->assertCount(1, $parent->children);
    }

    public function test_bookmark_belongs_to_folder(): void
    {
        $folder = BookmarkFolder::create([
            'user_id' => $this->user->id,
            'name' => 'Favorites',
            'color' => '#FFD700',
        ]);

        $bookmark = Bookmark::create([
            'user_id' => $this->user->id,
            'folder_id' => $folder->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_start' => 1,
        ]);

        $this->assertEquals($folder->id, $bookmark->folder->id);
        $this->assertCount(1, $folder->bookmarks);
    }

    public function test_bookmark_soft_delete_for_sync(): void
    {
        $bookmark = Bookmark::create([
            'user_id' => $this->user->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_start' => 1,
        ]);

        $bookmark->syncDelete();

        // Not visible with default scope
        $this->assertCount(0, Bookmark::where('user_id', $this->user->id)->get());

        // Visible when including deleted
        $this->assertCount(1, Bookmark::withDeleted()->where('user_id', $this->user->id)->get());
    }

    public function test_highlight_creation(): void
    {
        $highlight = Highlight::create([
            'user_id' => $this->user->id,
            'book_osis_id' => 'John',
            'chapter_number' => 3,
            'verse_number' => 16,
            'color' => 'yellow',
            'module_key' => 'KJV',
        ]);

        $this->assertNotNull($highlight->id);
        $this->assertEquals('John', $highlight->book_osis_id);
    }

    public function test_note_creation(): void
    {
        $note = Note::create([
            'user_id' => $this->user->id,
            'book_osis_id' => 'Ps',
            'chapter_number' => 23,
            'verse_start' => 1,
            'content' => '# The Lord is my shepherd',
            'content_format' => 'markdown',
        ]);

        $this->assertEquals('markdown', $note->content_format);
    }

    public function test_pin_creation(): void
    {
        $pin = Pin::create([
            'user_id' => $this->user->id,
            'book_osis_id' => 'Rom',
            'chapter_number' => 8,
            'verse_number' => 28,
            'module_key' => 'KJV',
            'label' => 'All things work together',
        ]);

        $this->assertEquals('All things work together', $pin->label);
    }

    public function test_module_and_books(): void
    {
        $module = Module::create([
            'key' => 'KJV',
            'name' => 'King James Version',
            'type' => 'bible',
            'language' => 'en',
            'features' => ['strongs'],
        ]);

        $book = Book::create([
            'module_id' => $module->id,
            'osis_id' => 'Gen',
            'name' => 'Genesis',
            'testament' => 'OT',
            'book_order' => 1,
            'chapter_count' => 50,
        ]);

        $this->assertCount(1, $module->books);
        $this->assertEquals('KJV', $module->key);
    }

    public function test_reading_plan_progress(): void
    {
        $plan = ReadingPlan::create([
            'name' => 'Bible in a Year',
            'duration_days' => 365,
            'plan_data' => [['day' => 1, 'readings' => ['Gen 1-3']]],
        ]);

        $progress = ReadingPlanProgress::create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
            'start_date' => now()->toDateString(),
            'completed_days' => [1, 2, 3],
        ]);

        $this->assertCount(3, $progress->completed_days);
        $this->assertEquals('Bible in a Year', $progress->plan->name);
    }

    public function test_user_preference(): void
    {
        $pref = UserPreference::create([
            'user_id' => $this->user->id,
            'key' => 'theme',
            'value' => ['mode' => 'dark', 'amoled' => true],
        ]);

        $this->assertEquals('dark', $pref->value['mode']);
        $this->assertTrue($pref->value['amoled']);
    }

    public function test_user_relationships(): void
    {
        Bookmark::create([
            'user_id' => $this->user->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_start' => 1,
        ]);

        Highlight::create([
            'user_id' => $this->user->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_number' => 1,
            'color' => 'blue',
        ]);

        Note::create([
            'user_id' => $this->user->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_start' => 1,
            'content' => 'Test note',
        ]);

        $this->assertCount(1, $this->user->bookmarks);
        $this->assertCount(1, $this->user->highlights);
        $this->assertCount(1, $this->user->notes);
    }
}
