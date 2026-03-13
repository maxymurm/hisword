<?php

namespace Tests\Feature;

use App\Models\AudioBible;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AudioBibleTest extends TestCase
{
    use RefreshDatabase;

    // ── API Tests ───────────────────────────────────────────────

    public function test_api_stream_requires_auth(): void
    {
        $this->getJson('/api/v1/audio/KJV/Gen/1')
            ->assertUnauthorized();
    }

    public function test_api_stream_returns_audio_data(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();

        $audio = AudioBible::factory()->create([
            'module_key' => 'KJV',
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'storage_path' => 'audio/KJV/Gen/1.mp3',
            'storage_disk' => 'public',
            'duration' => 300,
            'narrator' => 'Test Narrator',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/audio/KJV/Gen/1');

        $response->assertOk()
            ->assertJsonPath('data.module_key', 'KJV')
            ->assertJsonPath('data.book_osis_id', 'Gen')
            ->assertJsonPath('data.chapter_number', 1)
            ->assertJsonPath('data.duration', 300)
            ->assertJsonPath('data.narrator', 'Test Narrator')
            ->assertJsonStructure(['data' => [
                'id', 'module_key', 'book_osis_id', 'chapter_number',
                'stream_url', 'duration', 'formatted_duration', 'format',
                'narrator', 'verse_timings',
            ]]);
    }

    public function test_api_stream_returns_404_when_not_found(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/audio/KJV/Gen/999')
            ->assertNotFound()
            ->assertJsonPath('data', null);
    }

    public function test_api_stream_excludes_inactive_audio(): void
    {
        $user = User::factory()->create();

        AudioBible::factory()->inactive()->create([
            'module_key' => 'KJV',
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/audio/KJV/Gen/1')
            ->assertNotFound();
    }

    public function test_api_available_lists_module_audio(): void
    {
        $user = User::factory()->create();

        AudioBible::factory()->create(['module_key' => 'KJV', 'book_osis_id' => 'Gen', 'chapter_number' => 1]);
        AudioBible::factory()->create(['module_key' => 'KJV', 'book_osis_id' => 'Gen', 'chapter_number' => 2]);
        AudioBible::factory()->create(['module_key' => 'ESV', 'book_osis_id' => 'Gen', 'chapter_number' => 1]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/audio/KJV/available');

        $response->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_api_book_chapters_lists_audio(): void
    {
        $user = User::factory()->create();

        AudioBible::factory()->create(['module_key' => 'KJV', 'book_osis_id' => 'Gen', 'chapter_number' => 1]);
        AudioBible::factory()->create(['module_key' => 'KJV', 'book_osis_id' => 'Gen', 'chapter_number' => 2]);
        AudioBible::factory()->create(['module_key' => 'KJV', 'book_osis_id' => 'Exod', 'chapter_number' => 1]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/audio/KJV/Gen');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_api_next_chapter_returns_next(): void
    {
        $user = User::factory()->create();

        AudioBible::factory()->create(['module_key' => 'KJV', 'book_osis_id' => 'Gen', 'chapter_number' => 2, 'storage_disk' => 'public']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/audio/next?module=KJV&book=Gen&chapter=1');

        $response->assertOk()
            ->assertJsonPath('data.chapter_number', 2);
    }

    public function test_api_next_chapter_advances_to_next_book(): void
    {
        $user = User::factory()->create();

        AudioBible::factory()->create(['module_key' => 'KJV', 'book_osis_id' => 'Exod', 'chapter_number' => 1, 'storage_disk' => 'public']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/audio/next?module=KJV&book=Gen&chapter=50');

        $response->assertOk()
            ->assertJsonPath('data.book_osis_id', 'Exod')
            ->assertJsonPath('data.chapter_number', 1);
    }

    public function test_api_next_chapter_returns_404_at_end(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/audio/next?module=KJV&book=Rev&chapter=22')
            ->assertNotFound();
    }

    public function test_verse_timings_stored_and_returned(): void
    {
        $user = User::factory()->create();

        $timings = [
            ['verse' => 1, 'start' => 0.0, 'end' => 5.2],
            ['verse' => 2, 'start' => 5.2, 'end' => 10.8],
        ];

        AudioBible::factory()->create([
            'module_key' => 'KJV',
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'storage_disk' => 'public',
            'verse_timings' => $timings,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/audio/KJV/Gen/1');

        $response->assertOk()
            ->assertJsonCount(2, 'data.verse_timings')
            ->assertJsonPath('data.verse_timings.0.verse', 1)
            ->assertJsonPath('data.verse_timings.1.start', 5.2)
            ->assertJsonPath('data.verse_timings.0.end', 5.2);
    }

    // ── Web Tests ───────────────────────────────────────────────

    public function test_web_check_returns_availability(): void
    {
        AudioBible::factory()->create([
            'module_key' => 'KJV',
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
        ]);

        $this->getJson('/audio/check/KJV/Gen/1')
            ->assertOk()
            ->assertJsonPath('available', true);

        $this->getJson('/audio/check/KJV/Gen/99')
            ->assertOk()
            ->assertJsonPath('available', false);
    }

    public function test_web_stream_returns_audio_data(): void
    {
        AudioBible::factory()->create([
            'module_key' => 'KJV',
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'storage_disk' => 'public',
            'duration' => 300,
        ]);

        $this->getJson('/audio/KJV/Gen/1')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'id', 'stream_url', 'duration', 'formatted_duration',
                'format', 'narrator', 'verse_timings',
            ]]);
    }

    public function test_web_stream_returns_404_when_missing(): void
    {
        $this->getJson('/audio/KJV/Gen/99')
            ->assertNotFound();
    }

    // ── Model Tests ─────────────────────────────────────────────

    public function test_formatted_duration_attribute(): void
    {
        $audio = new AudioBible(['duration' => 332]);
        $this->assertEquals('5:32', $audio->formatted_duration);

        $audio2 = new AudioBible(['duration' => 60]);
        $this->assertEquals('1:00', $audio2->formatted_duration);

        $audio3 = new AudioBible(['duration' => null]);
        $this->assertEquals('0:00', $audio3->formatted_duration);
    }

    public function test_active_scope(): void
    {
        AudioBible::factory()->create(['is_active' => true, 'module_key' => 'KJV', 'book_osis_id' => 'Gen', 'chapter_number' => 1]);
        AudioBible::factory()->create(['is_active' => false, 'module_key' => 'KJV', 'book_osis_id' => 'Gen', 'chapter_number' => 2]);

        $this->assertCount(1, AudioBible::active()->get());
    }

    public function test_factory_with_verse_timings(): void
    {
        $audio = AudioBible::factory()->withVerseTimings(5)->create([
            'module_key' => 'KJV',
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
        ]);

        $this->assertNotNull($audio->verse_timings);
        $this->assertCount(5, $audio->verse_timings);
        $this->assertEquals(1, $audio->verse_timings[0]['verse']);
    }
}
