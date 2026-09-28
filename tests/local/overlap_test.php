<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_findatime\local;

/**
 * Tests for the overlap calculation.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(overlap::class)]
final class overlap_test extends \basic_testcase {
    /** @var slots 5 October 2026, 09:00-11:00 UTC, 30 minute slots: 09:00, 09:30, 10:00, 10:30. */
    protected $slots;
    /** @var int[] The four slot starts. */
    protected $s;

    /**
     * Build the slot set.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->slots = new slots((object)[
            'timezone' => 'UTC',
            'datestart' => gmmktime(0, 0, 0, 10, 5, 2026),
            'dateend' => gmmktime(0, 0, 0, 10, 5, 2026),
            'daystartmins' => 540,
            'dayendmins' => 660,
            'slotsize' => 30,
        ]);
        $this->s = $this->slots->get_starts();
    }

    /**
     * Per-slot summary counts members only and ignores times that are not slots.
     */
    public function test_slot_summary(): void {
        [$a, $b] = $this->s;
        $overlap = new overlap($this->slots, [1, 2, 3], [
            1 => [$a => 1, $b => 2],
            2 => [$a => 1, $a + 60 => 1],
            99 => [$a => 1],
        ]);
        $this->assertSame([
            $a => ['available' => [1, 2], 'ifneedbe' => []],
            $b => ['available' => [], 'ifneedbe' => [1]],
        ], $overlap->slot_summary());
        $this->assertSame(3, $overlap->member_count());
        $this->assertSame([1, 2], $overlap->responded());
        $this->assertSame([3], $overlap->pending());
    }

    /**
     * A member counts for a meeting only when free for every slot it spans.
     */
    public function test_candidates_span_per_person(): void {
        [$a, $b, $c, $d] = $this->s;
        $overlap = new overlap($this->slots, [1, 2], [
            1 => [$a => 1, $b => 1, $c => 1],
            2 => [$a => 1, $b => 2, $d => 1],
        ]);
        $candidates = $overlap->candidates(60);
        // 09:00: user 1 fully, user 2 only if need be (09:30). 09:30: user 1 fully, user 2 no (10:00 missing).
        // 10:00: user 1 no (10:30 missing), user 2 no. 10:30 cannot hold 60 minutes.
        $this->assertSame([
            ['timestart' => $a, 'available' => [1], 'ifneedbe' => [2]],
            ['timestart' => $b, 'available' => [1], 'ifneedbe' => []],
        ], $candidates);
    }

    /**
     * Ranking: most available, then most at least "if need be", then earliest.
     */
    public function test_candidates_ranking(): void {
        [$a, $b, $c, $d] = $this->s;
        $overlap = new overlap($this->slots, [1, 2, 3], [
            1 => [$a => 1, $b => 1, $c => 1, $d => 1],
            2 => [$c => 1, $d => 1, $b => 2],
            3 => [$d => 2],
        ]);
        $starts = array_column($overlap->candidates(30), 'timestart');
        // Two available at 10:00 and 10:30; 10:30 also has an "if need be", so it ranks first.
        // One available at 09:00 and 09:30; 09:30 has an "if need be".
        $this->assertSame([$d, $c, $b, $a], $starts);
        $this->assertCount(2, $overlap->candidates(30, 0, 2));
        $this->assertSame([$d], array_column($overlap->candidates(30, $c), 'timestart'));
    }

    /**
     * The automatic choice needs somebody fully available.
     */
    public function test_best(): void {
        [$a, $b] = $this->s;
        $onlyifneedbe = new overlap($this->slots, [1], [1 => [$a => 2, $b => 2]]);
        $this->assertNotEmpty($onlyifneedbe->candidates(60));
        $this->assertNull($onlyifneedbe->best(60, 0));

        $nobody = new overlap($this->slots, [1, 2], []);
        $this->assertSame([], $nobody->candidates(30));
        $this->assertNull($nobody->best(30, 0));

        $some = new overlap($this->slots, [1, 2], [1 => [$b => 1], 2 => [$a => 1, $b => 1]]);
        $this->assertSame($b, $some->best(30, 0)['timestart']);
        $this->assertNull($some->best(30, $b));
    }
}
