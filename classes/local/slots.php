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

use DateTimeImmutable;
use DateTimeZone;

/**
 * The slot set of an activity, and all timezone arithmetic.
 *
 * Slots are never stored: they are derived from the activity's date range, daily window
 * and slot size, evaluated in the activity's reference timezone. So "09:00-17:00" means
 * 09:00 in that zone on every day, and across a DST change the UTC offset of that day's
 * slots moves with it. Every slot is identified by its UTC start timestamp.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class slots {
    /** @var int Status: available. */
    public const STATUS_AVAILABLE = 1;
    /** @var int Status: available if need be. */
    public const STATUS_IFNEEDBE = 2;

    /** @var int Longest date range, in days. */
    public const MAX_DAYS = 62;
    /** @var int Largest slot set an activity may define. */
    public const MAX_SLOTS = 2000;
    /** @var int[] Slot sizes offered, in minutes. */
    public const SLOT_SIZES = [15, 30, 60];

    /** @var DateTimeZone The activity's reference timezone. */
    protected $timezone;
    /** @var int Slot length in seconds. */
    protected $step;
    /** @var int[] Sorted slot start timestamps. */
    protected $starts;
    /** @var array Slot start => true, for lookups. */
    protected $index;

    /**
     * Build the slot set of an activity.
     *
     * @param \stdClass $findatime The activity record.
     */
    public function __construct(\stdClass $findatime) {
        $this->timezone = self::timezone((string)$findatime->timezone);
        $this->step = (int)$findatime->slotsize * MINSECS;
        $this->starts = self::generate(
            $this->timezone,
            (int)$findatime->datestart,
            (int)$findatime->dateend,
            (int)$findatime->daystartmins,
            (int)$findatime->dayendmins,
            (int)$findatime->slotsize
        );
        $this->index = array_fill_keys($this->starts, true);
    }

    /**
     * Resolve a stored timezone name, falling back to a valid zone for legacy or bad values.
     *
     * @param string $name Timezone name.
     * @return DateTimeZone
     */
    public static function timezone(string $name): DateTimeZone {
        return new DateTimeZone(\core_date::normalise_timezone($name));
    }

    /**
     * Generate slot start timestamps.
     *
     * Local times that do not exist (inside a spring-forward gap) are skipped; an ambiguous
     * local time (fall-back) yields its first occurrence.
     *
     * @param DateTimeZone $tz Reference timezone.
     * @param int $datestart Any instant on the first day.
     * @param int $dateend Any instant on the last day (inclusive).
     * @param int $daystartmins Window start, minutes after local midnight.
     * @param int $dayendmins Window end, minutes after local midnight.
     * @param int $slotsize Slot length in minutes.
     * @return int[] Sorted UTC timestamps.
     */
    public static function generate(
        DateTimeZone $tz,
        int $datestart,
        int $dateend,
        int $daystartmins,
        int $dayendmins,
        int $slotsize
    ): array {
        if ($slotsize <= 0 || $dayendmins <= $daystartmins) {
            return [];
        }
        $day = (new DateTimeImmutable('@' . $datestart))->setTimezone($tz)->setTime(0, 0);
        $last = (new DateTimeImmutable('@' . $dateend))->setTimezone($tz)->format('Y-m-d');
        $starts = [];
        for ($i = 0; $i <= self::MAX_DAYS && $day->format('Y-m-d') <= $last; $i++) {
            for ($m = $daystartmins; $m + $slotsize <= $dayendmins; $m += $slotsize) {
                $hour = intdiv($m, 60);
                $minute = $m % 60;
                $local = $day->setTime($hour, $minute);
                if ((int)$local->format('G') !== $hour || (int)$local->format('i') !== $minute) {
                    // This local time does not exist on this day (DST gap).
                    continue;
                }
                $starts[$local->getTimestamp()] = true;
            }
            $day = $day->modify('+1 day')->setTime(0, 0);
        }
        $starts = array_keys($starts);
        sort($starts);
        return $starts;
    }

    /**
     * The civil date (Y-m-d) of an instant in a timezone.
     *
     * @param int $timestamp UTC timestamp.
     * @param DateTimeZone $tz Timezone.
     * @return string
     */
    public static function civil_date(int $timestamp, DateTimeZone $tz): string {
        return (new DateTimeImmutable('@' . $timestamp))->setTimezone($tz)->format('Y-m-d');
    }

    /**
     * The timestamp of the start of a civil date in a timezone.
     *
     * @param string $ymd Date as Y-m-d.
     * @param DateTimeZone $tz Timezone.
     * @return int
     */
    public static function civil_midnight(string $ymd, DateTimeZone $tz): int {
        return (new DateTimeImmutable($ymd . ' 00:00:00', $tz))->getTimestamp();
    }

    /**
     * Move a timestamp that means "midnight of a date in one zone" to midnight of the same date in another.
     *
     * The form's date selectors work in the editing user's timezone; the activity stores its
     * dates in its reference timezone.
     *
     * @param int $timestamp Timestamp.
     * @param DateTimeZone $from Zone the timestamp is a midnight in.
     * @param DateTimeZone $to Zone to express the same civil date in.
     * @return int
     */
    public static function convert_civil_midnight(int $timestamp, DateTimeZone $from, DateTimeZone $to): int {
        return self::civil_midnight(self::civil_date($timestamp, $from), $to);
    }

    /**
     * The reference timezone.
     *
     * @return DateTimeZone
     */
    public function get_timezone(): DateTimeZone {
        return $this->timezone;
    }

    /**
     * Slot length in seconds.
     *
     * @return int
     */
    public function get_step(): int {
        return $this->step;
    }

    /**
     * All slot starts, sorted.
     *
     * @return int[]
     */
    public function get_starts(): array {
        return $this->starts;
    }

    /**
     * Number of slots.
     *
     * @return int
     */
    public function count(): int {
        return count($this->starts);
    }

    /**
     * Whether a timestamp is the start of a slot of this activity.
     *
     * @param int $timestamp Timestamp.
     * @return bool
     */
    public function contains(int $timestamp): bool {
        return isset($this->index[$timestamp]);
    }

    /**
     * The consecutive slots a meeting starting at $start and lasting $duration minutes occupies.
     *
     * @param int $start Proposed meeting start.
     * @param int $duration Meeting length in minutes.
     * @return int[]|null Slot starts, or null when the meeting does not fit inside the slot set.
     */
    public function span(int $start, int $duration): ?array {
        if ($duration <= 0 || !$this->contains($start)) {
            return null;
        }
        $count = (int)ceil($duration * MINSECS / $this->step);
        $span = [];
        for ($i = 0; $i < $count; $i++) {
            $slot = $start + $i * $this->step;
            if (!$this->contains($slot)) {
                return null;
            }
            $span[] = $slot;
        }
        return $span;
    }

    /**
     * Lay the slots out as a grid in a viewer's timezone.
     *
     * Columns are the viewer's local dates, rows the viewer's local times of day. When two slots
     * share a local date and time (a fall-back hour in the viewer's zone) the second goes to an
     * extra row labelled with its zone abbreviation.
     *
     * @param DateTimeZone $viewertz The viewer's timezone.
     * @param callable|null $cellcallback Optional function(int $slotstart): array adding data to each cell.
     * @return array With 'days' (list of ['key', 'label']) and 'rows' (list of ['key', 'label', 'cells']).
     */
    public function layout(DateTimeZone $viewertz, ?callable $cellcallback = null): array {
        $dayformat = get_string('strftimedaydate', 'langconfig');
        $timeformat = get_string('strftimetime', 'langconfig');
        $fullformat = get_string('strftimedaydatetime', 'langconfig');
        $tzname = $viewertz->getName();

        $days = [];
        $rows = [];
        $matrix = [];
        foreach ($this->starts as $start) {
            $local = (new DateTimeImmutable('@' . $start))->setTimezone($viewertz);
            $daykey = $local->format('Y-m-d');
            $minutes = (int)$local->format('G') * 60 + (int)$local->format('i');
            if (!isset($days[$daykey])) {
                $days[$daykey] = userdate($start, $dayformat, $tzname);
            }
            $occurrence = 0;
            while (isset($matrix[$minutes . ':' . $occurrence][$daykey])) {
                $occurrence++;
            }
            $rowkey = $minutes . ':' . $occurrence;
            if (!isset($rows[$rowkey])) {
                $label = userdate($start, $timeformat, $tzname);
                if ($occurrence > 0) {
                    $label .= ' (' . $local->format('T') . ')';
                }
                $rows[$rowkey] = ['minutes' => $minutes, 'occurrence' => $occurrence, 'label' => $label];
            }
            $cell = [
                'slotstart' => $start,
                'label' => userdate($start, $fullformat, $tzname),
            ];
            if ($cellcallback) {
                $cell = array_merge($cell, $cellcallback($start));
            }
            $matrix[$rowkey][$daykey] = $cell;
        }

        ksort($days);
        uasort($rows, function (array $a, array $b): int {
            return [$a['minutes'], $a['occurrence']] <=> [$b['minutes'], $b['occurrence']];
        });

        $outdays = [];
        foreach ($days as $key => $label) {
            $outdays[] = ['key' => $key, 'label' => $label];
        }
        $outrows = [];
        $rowindex = 0;
        foreach ($rows as $rowkey => $row) {
            $cells = [];
            $colindex = 0;
            foreach (array_keys($days) as $daykey) {
                $cell = $matrix[$rowkey][$daykey] ?? null;
                $cells[] = $cell ? array_merge($cell, ['isslot' => true, 'row' => $rowindex, 'col' => $colindex])
                    : ['isslot' => false, 'row' => $rowindex, 'col' => $colindex];
                $colindex++;
            }
            $outrows[] = ['key' => $rowkey, 'label' => $row['label'], 'cells' => $cells];
            $rowindex++;
        }
        return ['days' => $outdays, 'rows' => $outrows];
    }
}
