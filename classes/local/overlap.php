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
 * Overlap of a group's availability: per-slot counts and ranked meeting times.
 *
 * Pure calculation over data passed in; storage and access checks happen elsewhere.
 * Statuses for timestamps that are not in the slot set (left over from a change of the
 * activity's timing) are ignored.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overlap {
    /** @var slots The slot set. */
    protected $slots;
    /** @var int[] Member user ids. */
    protected $memberids;
    /** @var array userid => [slotstart => status], members only. */
    protected $statuses;

    /**
     * Constructor.
     *
     * @param slots $slots The slot set.
     * @param int[] $memberids The group's members whose availability counts.
     * @param array $statuses userid => [slotstart => status]; non-members are ignored.
     */
    public function __construct(slots $slots, array $memberids, array $statuses) {
        $this->slots = $slots;
        $this->memberids = array_values(array_map('intval', $memberids));
        $this->statuses = [];
        foreach ($this->memberids as $userid) {
            if (array_key_exists($userid, $statuses)) {
                $this->statuses[$userid] = $statuses[$userid];
            }
        }
    }

    /**
     * Number of members.
     *
     * @return int
     */
    public function member_count(): int {
        return count($this->memberids);
    }

    /**
     * Members who have submitted availability.
     *
     * @return int[]
     */
    public function responded(): array {
        return array_keys($this->statuses);
    }

    /**
     * Members who have not submitted availability.
     *
     * @return int[]
     */
    public function pending(): array {
        return array_values(array_diff($this->memberids, $this->responded()));
    }

    /**
     * Who is available, and who only if need be, in each slot that anybody marked.
     *
     * @return array slotstart => ['available' => int[], 'ifneedbe' => int[]], in slot order.
     */
    public function slot_summary(): array {
        $summary = [];
        foreach ($this->statuses as $userid => $userstatuses) {
            foreach ($userstatuses as $slotstart => $status) {
                if (!$this->slots->contains((int)$slotstart)) {
                    continue;
                }
                $key = $status === slots::STATUS_AVAILABLE ? 'available' : 'ifneedbe';
                $summary[$slotstart] = $summary[$slotstart] ?? ['available' => [], 'ifneedbe' => []];
                $summary[$slotstart][$key][] = $userid;
            }
        }
        ksort($summary);
        return $summary;
    }

    /**
     * Meeting start times ranked by how many members can come.
     *
     * A member counts as available for a meeting when they are available in every slot it spans,
     * and as "if need be" when they are at least "if need be" in every spanned slot but not
     * available in all of them. Ranking: most available, then most available-or-if-need-be,
     * then earliest. Times nobody can make are left out.
     *
     * @param int $duration Meeting length in minutes.
     * @param int $after Only starts later than this timestamp (0 = no limit).
     * @param int $limit Maximum number of candidates (0 = all).
     * @return array List of ['timestart' => int, 'available' => int[], 'ifneedbe' => int[]].
     */
    public function candidates(int $duration, int $after = 0, int $limit = 5): array {
        $candidates = [];
        foreach ($this->slots->get_starts() as $start) {
            if ($start <= $after) {
                continue;
            }
            $span = $this->slots->span($start, $duration);
            if ($span === null) {
                continue;
            }
            $available = [];
            $ifneedbe = [];
            foreach ($this->statuses as $userid => $userstatuses) {
                $all = true;
                $atleast = true;
                foreach ($span as $slot) {
                    $status = $userstatuses[$slot] ?? 0;
                    if ($status !== slots::STATUS_AVAILABLE) {
                        $all = false;
                    }
                    if ($status === 0) {
                        $atleast = false;
                        break;
                    }
                }
                if ($all) {
                    $available[] = $userid;
                } else if ($atleast) {
                    $ifneedbe[] = $userid;
                }
            }
            if ($available || $ifneedbe) {
                $candidates[] = ['timestart' => $start, 'available' => $available, 'ifneedbe' => $ifneedbe];
            }
        }
        usort($candidates, function (array $a, array $b): int {
            return [count($b['available']), count($b['available']) + count($b['ifneedbe']), $a['timestart']]
                <=> [count($a['available']), count($a['available']) + count($a['ifneedbe']), $b['timestart']];
        });
        return $limit > 0 ? array_slice($candidates, 0, $limit) : $candidates;
    }

    /**
     * The most popular meeting time, as used by the automatic confirmation.
     *
     * @param int $duration Meeting length in minutes.
     * @param int $after Only starts later than this timestamp.
     * @return array|null The best candidate, or null when nobody is available for any time.
     */
    public function best(int $duration, int $after): ?array {
        $candidates = $this->candidates($duration, $after, 0);
        foreach ($candidates as $candidate) {
            if ($candidate['available']) {
                return $candidate;
            }
        }
        return null;
    }
}
