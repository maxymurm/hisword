<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerseOfDayApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_get_today_verse(): void
    {
        $response = $this->getJson('/api/v1/verse-of-the-day', $this->auth());

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['date', 'book', 'book_name', 'chapter', 'verse', 'reference', 'text', 'translation'],
            ])
            ->assertJsonPath('data.date', now()->toDateString())
            ->assertJsonPath('data.translation', 'KJV');
    }

    public function test_get_verse_for_specific_date(): void
    {
        $response = $this->getJson('/api/v1/verse-of-the-day/2025-01-01', $this->auth());

        $response->assertOk()
            ->assertJsonPath('data.date', '2025-01-01')
            ->assertJsonPath('data.reference', 'John 3:16');
    }

    public function test_same_date_returns_same_verse(): void
    {
        $r1 = $this->getJson('/api/v1/verse-of-the-day/2025-06-15', $this->auth());
        $r2 = $this->getJson('/api/v1/verse-of-the-day/2025-06-15', $this->auth());

        $r1->assertOk();
        $r2->assertOk();

        $this->assertEquals($r1->json('data.reference'), $r2->json('data.reference'));
    }

    public function test_different_dates_can_return_different_verses(): void
    {
        $r1 = $this->getJson('/api/v1/verse-of-the-day/2025-01-01', $this->auth());
        $r2 = $this->getJson('/api/v1/verse-of-the-day/2025-01-02', $this->auth());

        $this->assertNotEquals($r1->json('data.reference'), $r2->json('data.reference'));
    }

    public function test_invalid_date_format(): void
    {
        $response = $this->getJson('/api/v1/verse-of-the-day/not-a-date', $this->auth());

        $response->assertStatus(422);
    }

    public function test_verse_includes_book_info(): void
    {
        $response = $this->getJson('/api/v1/verse-of-the-day/2025-01-01', $this->auth());

        $response->assertOk()
            ->assertJsonPath('data.book', 'John')
            ->assertJsonPath('data.book_name', 'John')
            ->assertJsonPath('data.chapter', 3)
            ->assertJsonPath('data.verse', 16);
    }

    public function test_verse_end_can_be_present(): void
    {
        // Day 4 (Jan 4) = Proverbs 3:5-6 which has verse_end
        $response = $this->getJson('/api/v1/verse-of-the-day/2025-01-04', $this->auth());

        $response->assertOk()
            ->assertJsonPath('data.verse_end', 6);
    }

    public function test_unauthenticated_rejected(): void
    {
        $this->getJson('/api/v1/verse-of-the-day')->assertUnauthorized();
    }
}
