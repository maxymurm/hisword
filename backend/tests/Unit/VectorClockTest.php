<?php

namespace Tests\Unit;

use App\Support\VectorClock;
use PHPUnit\Framework\TestCase;

class VectorClockTest extends TestCase
{
    public function test_empty_clock(): void
    {
        $clock = new VectorClock;

        $this->assertTrue($clock->isEmpty());
        $this->assertEquals(0, $clock->sum());
        $this->assertEquals([], $clock->toArray());
    }

    public function test_increment(): void
    {
        $clock = new VectorClock;

        $clock = $clock->increment('device-1');
        $this->assertEquals(1, $clock->get('device-1'));
        $this->assertEquals(0, $clock->get('device-2'));

        $clock = $clock->increment('device-1');
        $this->assertEquals(2, $clock->get('device-1'));

        $clock = $clock->increment('device-2');
        $this->assertEquals(1, $clock->get('device-2'));
        $this->assertEquals(3, $clock->sum());
    }

    public function test_increment_is_immutable(): void
    {
        $clock1 = new VectorClock;
        $clock2 = $clock1->increment('device-1');

        $this->assertTrue($clock1->isEmpty());
        $this->assertEquals(1, $clock2->get('device-1'));
    }

    public function test_merge_clocks(): void
    {
        $a = VectorClock::fromArray(['d1' => 3, 'd2' => 1]);
        $b = VectorClock::fromArray(['d1' => 2, 'd2' => 4, 'd3' => 1]);

        $merged = $a->merge($b);

        $this->assertEquals(3, $merged->get('d1'));
        $this->assertEquals(4, $merged->get('d2'));
        $this->assertEquals(1, $merged->get('d3'));
    }

    public function test_merge_is_commutative(): void
    {
        $a = VectorClock::fromArray(['d1' => 3, 'd2' => 1]);
        $b = VectorClock::fromArray(['d1' => 2, 'd2' => 4]);

        $this->assertTrue($a->merge($b)->equals($b->merge($a)));
    }

    public function test_merge_is_idempotent(): void
    {
        $a = VectorClock::fromArray(['d1' => 3, 'd2' => 1]);

        $this->assertTrue($a->merge($a)->equals($a));
    }

    public function test_is_newer_than_by_sum(): void
    {
        $newer = VectorClock::fromArray(['d1' => 5]);
        $older = VectorClock::fromArray(['d1' => 3]);

        $this->assertTrue($newer->isNewerThan($older));
        $this->assertFalse($older->isNewerThan($newer));
    }

    public function test_is_newer_than_by_component_wins(): void
    {
        // Same sum (4) but d1 has more wins on its component
        $a = VectorClock::fromArray(['d1' => 3, 'd2' => 1]);
        $b = VectorClock::fromArray(['d1' => 1, 'd2' => 3]);

        // a wins d1, b wins d2 — tied at 1-1, neither is newer
        $this->assertFalse($a->isNewerThan($b));
    }

    public function test_is_newer_than_different_devices(): void
    {
        $a = VectorClock::fromArray(['d1' => 5]);
        $b = VectorClock::fromArray(['d2' => 3]);

        $this->assertTrue($a->isNewerThan($b));
    }

    public function test_equals(): void
    {
        $a = VectorClock::fromArray(['d1' => 3, 'd2' => 1]);
        $b = VectorClock::fromArray(['d1' => 3, 'd2' => 1]);

        $this->assertTrue($a->equals($b));
        $this->assertTrue($b->equals($a));
    }

    public function test_not_equals(): void
    {
        $a = VectorClock::fromArray(['d1' => 3, 'd2' => 1]);
        $b = VectorClock::fromArray(['d1' => 3, 'd2' => 2]);

        $this->assertFalse($a->equals($b));
    }

    public function test_happened_before(): void
    {
        $a = VectorClock::fromArray(['d1' => 1, 'd2' => 1]);
        $b = VectorClock::fromArray(['d1' => 2, 'd2' => 2]);

        $this->assertTrue($a->happenedBefore($b));
        $this->assertFalse($b->happenedBefore($a));
    }

    public function test_happened_before_partial(): void
    {
        $a = VectorClock::fromArray(['d1' => 1, 'd2' => 3]);
        $b = VectorClock::fromArray(['d1' => 2, 'd2' => 2]);

        // a.d2 > b.d2, so a did NOT happen before b
        $this->assertFalse($a->happenedBefore($b));
        $this->assertFalse($b->happenedBefore($a));
    }

    public function test_concurrent_clocks(): void
    {
        $a = VectorClock::fromArray(['d1' => 2, 'd2' => 1]);
        $b = VectorClock::fromArray(['d1' => 1, 'd2' => 2]);

        $this->assertTrue($a->isConcurrentWith($b));
        $this->assertTrue($b->isConcurrentWith($a));
    }

    public function test_not_concurrent_when_one_is_newer(): void
    {
        $a = VectorClock::fromArray(['d1' => 5]);
        $b = VectorClock::fromArray(['d1' => 3]);

        $this->assertFalse($a->isConcurrentWith($b));
    }

    public function test_from_array(): void
    {
        $data = ['device-a' => 10, 'device-b' => 5];
        $clock = VectorClock::fromArray($data);

        $this->assertEquals(10, $clock->get('device-a'));
        $this->assertEquals(5, $clock->get('device-b'));
        $this->assertEquals($data, $clock->toArray());
    }

    public function test_empty_clocks_are_equal(): void
    {
        $a = new VectorClock;
        $b = new VectorClock;

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->isConcurrentWith($b));
        $this->assertFalse($a->isNewerThan($b));
    }
}
