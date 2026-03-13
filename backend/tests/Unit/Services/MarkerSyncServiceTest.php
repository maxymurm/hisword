<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\SyncOperation;
use App\Events\MarkerChanged;
use App\Models\Bookmark;
use App\Models\Highlight;
use App\Models\Note;
use App\Models\SyncLog;
use App\Models\SyncShadow;
use App\Models\User;
use App\Services\MarkerSyncService;
use PHPUnit\Framework\TestCase;

class MarkerSyncServiceTest extends TestCase
{
    // ── Kind mapping ─────────────────────────────

    public function test_kind_0_maps_to_bookmark(): void
    {
        $service = new MarkerSyncService();
        $reflection = new \ReflectionClass($service);
        $kindMap = $reflection->getConstant('KIND_MAP');

        $this->assertSame('bookmark', $kindMap[0]['entity_type']);
        $this->assertSame(Bookmark::class, $kindMap[0]['model']);
    }

    public function test_kind_1_maps_to_note(): void
    {
        $service = new MarkerSyncService();
        $reflection = new \ReflectionClass($service);
        $kindMap = $reflection->getConstant('KIND_MAP');

        $this->assertSame('note', $kindMap[1]['entity_type']);
        $this->assertSame(Note::class, $kindMap[1]['model']);
    }

    public function test_kind_2_maps_to_highlight(): void
    {
        $service = new MarkerSyncService();
        $reflection = new \ReflectionClass($service);
        $kindMap = $reflection->getConstant('KIND_MAP');

        $this->assertSame('highlight', $kindMap[2]['entity_type']);
        $this->assertSame(Highlight::class, $kindMap[2]['model']);
    }

    // ── Marker to entity mapping ─────────────────

    public function test_map_bookmark_marker_to_entity(): void
    {
        $service = new MarkerSyncService();
        $method = new \ReflectionMethod($service, 'mapMarkerToEntity');

        $result = $method->invoke($service, 0, [
            'book_osis_id' => 'Gen',
            'chapter' => 1,
            'verse' => 1,
            'caption' => 'Test Bookmark',
            'module_key' => 'KJV',
        ]);

        $this->assertSame('Gen', $result['book_osis_id']);
        $this->assertSame(1, $result['chapter_number']);
        $this->assertSame(1, $result['verse_start']);
        $this->assertSame('Test Bookmark', $result['label']);
        $this->assertSame('KJV', $result['module_key']);
    }

    public function test_map_note_marker_to_entity(): void
    {
        $service = new MarkerSyncService();
        $method = new \ReflectionMethod($service, 'mapMarkerToEntity');

        $result = $method->invoke($service, 1, [
            'book_osis_id' => 'John',
            'chapter' => 3,
            'verse' => 16,
            'caption' => 'Famous verse',
            'content' => 'For God so loved the world...',
        ]);

        $this->assertSame('John', $result['book_osis_id']);
        $this->assertSame(3, $result['chapter_number']);
        $this->assertSame(16, $result['verse_start']);
        $this->assertSame('Famous verse', $result['title']);
        $this->assertSame('For God so loved the world...', $result['content']);
    }

    public function test_map_highlight_marker_to_entity(): void
    {
        $service = new MarkerSyncService();
        $method = new \ReflectionMethod($service, 'mapMarkerToEntity');

        $result = $method->invoke($service, 2, [
            'book_osis_id' => 'Ps',
            'chapter' => 23,
            'verse' => 1,
            'color' => 'green',
        ]);

        $this->assertSame('Ps', $result['book_osis_id']);
        $this->assertSame(23, $result['chapter_number']);
        $this->assertSame(1, $result['verse_number']);
        $this->assertSame('green', $result['color']);
    }

    // ── Entity to marker mapping ─────────────────

    public function test_map_bookmark_entity_to_marker(): void
    {
        $service = new MarkerSyncService();
        $method = new \ReflectionMethod($service, 'mapEntityToMarker');

        $result = $method->invoke($service, 0, [
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_start' => 1,
            'verse_end' => 3,
            'label' => 'Creation',
            'module_key' => 'KJV',
        ]);

        $this->assertSame('Gen', $result['book_osis_id']);
        $this->assertSame(1, $result['chapter']);
        $this->assertSame(1, $result['verse']);
        $this->assertSame(3, $result['verse_end']);
        $this->assertSame('Creation', $result['caption']);
    }

    public function test_map_note_entity_to_marker(): void
    {
        $service = new MarkerSyncService();
        $method = new \ReflectionMethod($service, 'mapEntityToMarker');

        $result = $method->invoke($service, 1, [
            'book_osis_id' => 'Rom',
            'chapter_number' => 8,
            'verse_start' => 28,
            'title' => 'Promise',
            'content' => 'All things work together for good',
        ]);

        $this->assertSame(28, $result['verse']);
        $this->assertSame('Promise', $result['caption']);
        $this->assertSame('All things work together for good', $result['content']);
    }

    public function test_map_highlight_entity_to_marker(): void
    {
        $service = new MarkerSyncService();
        $method = new \ReflectionMethod($service, 'mapEntityToMarker');

        $result = $method->invoke($service, 2, [
            'book_osis_id' => 'Isa',
            'chapter_number' => 40,
            'verse_number' => 31,
            'color' => 'yellow',
        ]);

        $this->assertSame(31, $result['verse']);
        $this->assertSame('yellow', $result['color']);
    }

    // ── Entity type to kind ──────────────────────

    public function test_entity_type_to_kind_bookmark(): void
    {
        $service = new MarkerSyncService();
        $method = new \ReflectionMethod($service, 'entityTypeToKind');

        $this->assertSame(0, $method->invoke($service, 'bookmark'));
    }

    public function test_entity_type_to_kind_note(): void
    {
        $service = new MarkerSyncService();
        $method = new \ReflectionMethod($service, 'entityTypeToKind');

        $this->assertSame(1, $method->invoke($service, 'note'));
    }

    public function test_entity_type_to_kind_highlight(): void
    {
        $service = new MarkerSyncService();
        $method = new \ReflectionMethod($service, 'entityTypeToKind');

        $this->assertSame(2, $method->invoke($service, 'highlight'));
    }

    public function test_entity_type_to_kind_unknown_throws(): void
    {
        $service = new MarkerSyncService();
        $method = new \ReflectionMethod($service, 'entityTypeToKind');

        $this->expectException(\InvalidArgumentException::class);
        $method->invoke($service, 'unknown_type');
    }

    // ── Alternative field name mappings ───────────

    public function test_map_marker_accepts_camel_case_fields(): void
    {
        $service = new MarkerSyncService();
        $method = new \ReflectionMethod($service, 'mapMarkerToEntity');

        $result = $method->invoke($service, 0, [
            'bookOsisId' => 'Matt',
            'chapter_number' => 5,
            'verse_start' => 3,
            'moduleKey' => 'ESV',
            'label' => 'Beatitudes',
        ]);

        $this->assertSame('Matt', $result['book_osis_id']);
        $this->assertSame('ESV', $result['module_key']);
    }

    public function test_map_marker_defaults_for_missing_fields(): void
    {
        $service = new MarkerSyncService();
        $method = new \ReflectionMethod($service, 'mapMarkerToEntity');

        $result = $method->invoke($service, 2, []);

        $this->assertNull($result['book_osis_id']);
        $this->assertNull($result['chapter_number']);
        $this->assertSame(1, $result['verse_number']);
        $this->assertSame('yellow', $result['color']);
    }
}
