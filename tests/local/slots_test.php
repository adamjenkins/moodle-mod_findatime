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

use DateTimeZone;

/**
 * Tests for slot generation, timezone conversion and viewer layout.
 *
 * All dates are fixed in 2026: British Summer Time ends on 25 October, US daylight time
 * starts on 8 March and ends on 1 November.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(slots::class)]
final class slots_test extends \basic_testcase {
    /**
     * Build an instance-like record.
     *
     * @param string $tz Reference timezone.
     * @param string $first First day, Y-m-d.
     * @param string $last Last day, Y-m-d.
     * @param int $from Window start, minutes.
     * @param int $to Window end, minutes.
     * @param int $size Slot size, minutes.
     * @return \stdClass
     */
    protected function make(string $tz, string $first, string $last, int $from, int $to, int $size): \stdClass {
        $zone = new DateTimeZone($tz);
        return (object)[
            'timezone' => $tz,
            'datestart' => slots::civil_midnight($first, $zone),
            'dateend' => slots::civil_midnight($last, $zone),
            'daystartmins' => $from,
            'dayendmins' => $to,
            'slotsize' => $size,
        ];
    }

    /**
     * Slots cover the window on every day of the range, in UTC.
     */
    public function test_generate_basic(): void {
        $slots = new slots($this->make('UTC', '2026-10-05', '2026-10-06', 540, 660, 30));
        $starts = $slots->get_starts();
        $this->assertCount(8, $starts);
        $this->assertSame(gmmktime(9, 0, 0, 10, 5, 2026), $starts[0]);
        $this->assertSame(gmmktime(10, 30, 0, 10, 5, 2026), $starts[3]);
        $this->assertSame(gmmktime(9, 0, 0, 10, 6, 2026), $starts[4]);
        $this->assertSame(1800, $slots->get_step());
        $this->assertTrue($slots->contains($starts[5]));
        $this->assertFalse($slots->contains($starts[5] + 60));
    }

    /**
     * A window that does not hold one slot produces nothing, and so does a reversed range.
     */
    public function test_generate_empty(): void {
        $this->assertSame(0, (new slots($this->make('UTC', '2026-10-05', '2026-10-05', 540, 555, 30)))->count());
        $this->assertSame(0, (new slots($this->make('UTC', '2026-10-06', '2026-10-05', 540, 600, 30)))->count());
    }

    /**
     * 09:00 London is 08:00 UTC before the end of BST and 09:00 UTC after it.
     */
    public function test_generate_across_dst_end(): void {
        $slots = new slots($this->make('Europe/London', '2026-10-24', '2026-10-26', 540, 600, 60));
        $this->assertSame([
            gmmktime(8, 0, 0, 10, 24, 2026),
            gmmktime(9, 0, 0, 10, 25, 2026),
            gmmktime(9, 0, 0, 10, 26, 2026),
        ], $slots->get_starts());
    }

    /**
     * Local times inside a spring-forward gap are skipped, never duplicated.
     */
    public function test_generate_skips_spring_forward_gap(): void {
        // 8 March 2026 in New York: 02:00 EST jumps to 03:00 EDT.
        $slots = new slots($this->make('America/New_York', '2026-03-08', '2026-03-08', 60, 240, 30));
        $this->assertSame([
            gmmktime(6, 0, 0, 3, 8, 2026), // 01:00 EST.
            gmmktime(6, 30, 0, 3, 8, 2026), // 01:30 EST.
            gmmktime(7, 0, 0, 3, 8, 2026), // 03:00 EDT.
            gmmktime(7, 30, 0, 3, 8, 2026), // 03:30 EDT.
        ], $slots->get_starts());
    }

    /**
     * An ambiguous fall-back local time yields one slot, its first occurrence.
     */
    public function test_generate_fall_back_once(): void {
        // 1 November 2026 in New York: 02:00 EDT falls back to 01:00 EST.
        $slots = new slots($this->make('America/New_York', '2026-11-01', '2026-11-01', 0, 180, 60));
        $this->assertSame([
            gmmktime(4, 0, 0, 11, 1, 2026), // 00:00 EDT.
            gmmktime(5, 0, 0, 11, 1, 2026), // 01:00 EDT (first occurrence).
            gmmktime(7, 0, 0, 11, 1, 2026), // 02:00 EST.
        ], $slots->get_starts());
    }

    /**
     * A meeting spans consecutive slots and must fit completely.
     */
    public function test_span(): void {
        $slots = new slots($this->make('UTC', '2026-10-05', '2026-10-06', 540, 660, 30));
        $nine = gmmktime(9, 0, 0, 10, 5, 2026);
        $this->assertSame([$nine, $nine + 1800], $slots->span($nine, 60));
        $this->assertSame([$nine], $slots->span($nine, 30));
        // 10:30 + 60 minutes runs past the 11:00 window end.
        $this->assertNull($slots->span($nine + 5400, 60));
        $this->assertNull($slots->span($nine + 60, 30));
        $this->assertNull($slots->span($nine, 0));
    }

    /**
     * Continuity is measured in real time, not wall-clock time: 01:30 EST plus half an hour is
     * 03:00 EDT, so a meeting across a spring-forward gap is one continuous hour.
     */
    public function test_span_across_gap_is_real_time(): void {
        $slots = new slots($this->make('America/New_York', '2026-03-08', '2026-03-08', 60, 240, 30));
        $this->assertSame(
            [gmmktime(6, 30, 0, 3, 8, 2026), gmmktime(7, 0, 0, 3, 8, 2026)],
            $slots->span(gmmktime(6, 30, 0, 3, 8, 2026), 60)
        );
        // 03:30 EDT + 60 minutes runs past the 04:00 window end.
        $this->assertNull($slots->span(gmmktime(7, 30, 0, 3, 8, 2026), 60));
    }

    /**
     * A civil date keeps its meaning when moved between zones.
     */
    public function test_convert_civil_midnight(): void {
        $tokyo = new DateTimeZone('Asia/Tokyo');
        $london = new DateTimeZone('Europe/London');
        $tokyomidnight = slots::civil_midnight('2026-10-05', $tokyo);
        $this->assertSame(gmmktime(15, 0, 0, 10, 4, 2026), $tokyomidnight);
        $londonmidnight = slots::convert_civil_midnight($tokyomidnight, $tokyo, $london);
        $this->assertSame(gmmktime(23, 0, 0, 10, 4, 2026), $londonmidnight);
        $this->assertSame('2026-10-05', slots::civil_date($londonmidnight, $london));
    }

    /**
     * A bad stored timezone falls back to a valid zone instead of failing.
     */
    public function test_timezone_fallback(): void {
        $this->assertInstanceOf(DateTimeZone::class, slots::timezone('Not/AZone'));
        $this->assertSame('Europe/London', slots::timezone('Europe/London')->getName());
    }

    /**
     * The grid is laid out in the viewer's timezone, even when that moves slots to other dates.
     */
    public function test_layout_in_viewer_timezone(): void {
        // 20:00-22:00 London on one day is 04:00-06:00 the next day in Tokyo (BST, UTC+1).
        $slots = new slots($this->make('Europe/London', '2026-10-05', '2026-10-05', 1200, 1320, 60));
        $layout = $slots->layout(new DateTimeZone('Asia/Tokyo'));
        $this->assertCount(1, $layout['days']);
        $this->assertSame('2026-10-06', $layout['days'][0]['key']);
        $this->assertCount(2, $layout['rows']);
        $this->assertSame((4 * 60) . ':0', $layout['rows'][0]['key']);
        $this->assertSame(gmmktime(19, 0, 0, 10, 5, 2026), $layout['rows'][0]['cells'][0]['slotstart']);
    }

    /**
     * Across a DST change in the reference zone only, a viewer sees the slots move row.
     */
    public function test_layout_across_reference_dst(): void {
        // 09:00 London on 24 and 26 October is 08:00 and 09:00 UTC.
        $slots = new slots($this->make('Europe/London', '2026-10-24', '2026-10-26', 540, 600, 60));
        $layout = $slots->layout(new DateTimeZone('UTC'));
        $this->assertCount(3, $layout['days']);
        $this->assertCount(2, $layout['rows']);
        // Row 08:00: only the first day. Row 09:00: the second and third days.
        $this->assertSame([true, false, false], array_column($layout['rows'][0]['cells'], 'isslot'));
        $this->assertSame([false, true, true], array_column($layout['rows'][1]['cells'], 'isslot'));
        $this->assertSame(1, $layout['rows'][1]['cells'][2]['row']);
        $this->assertSame(2, $layout['rows'][1]['cells'][2]['col']);
    }

    /**
     * Two slots with the same local date and time in the viewer's zone get separate rows.
     */
    public function test_layout_viewer_fall_back_collision(): void {
        // 05:00 and 06:00 UTC on 1 November 2026 are both 01:00 in New York (EDT, then EST).
        $slots = new slots($this->make('UTC', '2026-11-01', '2026-11-01', 300, 420, 60));
        $layout = $slots->layout(new DateTimeZone('America/New_York'));
        $this->assertCount(1, $layout['days']);
        $this->assertCount(2, $layout['rows']);
        $this->assertSame('60:0', $layout['rows'][0]['key']);
        $this->assertSame('60:1', $layout['rows'][1]['key']);
        $this->assertStringContainsString('EST', $layout['rows'][1]['label']);
        $this->assertSame(gmmktime(6, 0, 0, 11, 1, 2026), $layout['rows'][1]['cells'][0]['slotstart']);
    }

    /**
     * The cell callback adds data to every slot cell.
     */
    public function test_layout_callback(): void {
        $slots = new slots($this->make('UTC', '2026-10-05', '2026-10-05', 540, 600, 30));
        $layout = $slots->layout(new DateTimeZone('UTC'), function (int $start): array {
            return ['status' => $start % 3600 === 0 ? 1 : 0];
        });
        $this->assertSame(1, $layout['rows'][0]['cells'][0]['status']);
        $this->assertSame(0, $layout['rows'][1]['cells'][0]['status']);
    }
}
