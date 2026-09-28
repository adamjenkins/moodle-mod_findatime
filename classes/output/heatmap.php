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

namespace mod_findatime\output;

use mod_findatime\local\access;
use mod_findatime\local\availability;
use mod_findatime\local\overlap;
use mod_findatime\local\slots;
use renderer_base;

/**
 * The overlap heatmap of one group, with the best meeting times.
 *
 * The same data feeds the page (export_for_template) and the get_overlap external
 * function (build_data), so the two cannot drift apart.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class heatmap implements \renderable, \templatable {
    /** @var int Number of best meeting times listed. */
    public const CANDIDATES = 5;

    /** @var access Access rules. */
    protected $access;
    /** @var int Group id. */
    protected $groupid;
    /** @var int Viewing user id. */
    protected $userid;

    /**
     * Constructor.
     *
     * @param access $access Access rules of the activity.
     * @param int $groupid Group shown.
     * @param int $userid Viewing user.
     */
    public function __construct(access $access, int $groupid, int $userid) {
        $this->access = $access;
        $this->groupid = $groupid;
        $this->userid = $userid;
    }

    /**
     * The group's overlap data, with names and labels for the viewing user.
     *
     * @return array
     */
    public function build_data(): array {
        $findatime = $this->access->get_findatime();
        $context = $this->access->get_context();
        $slots = new slots($findatime);
        $members = $this->access->members($this->groupid);
        $statuses = availability::get_statuses($findatime->id, array_keys($members));
        $overlap = new overlap($slots, array_keys($members), $statuses);

        $viewfullnames = has_capability('moodle/site:viewfullnames', $context, $this->userid);
        $names = [];
        foreach ($members as $member) {
            $names[(int)$member->id] = fullname($member, $viewfullnames);
        }
        $namelist = function (array $userids) use ($names): array {
            return array_values(array_map(function ($id) use ($names) {
                return $names[$id];
            }, $userids));
        };

        $summary = [];
        foreach ($overlap->slot_summary() as $slotstart => $who) {
            $summary[] = [
                'slotstart' => (int)$slotstart,
                'available' => count($who['available']),
                'ifneedbe' => count($who['ifneedbe']),
                'availablenames' => $namelist($who['available']),
                'ifneedbenames' => $namelist($who['ifneedbe']),
            ];
        }

        $usertz = \core_date::get_user_timezone();
        $format = get_string('strftimedaydatetime', 'langconfig');
        $canconfirm = $this->access->can_confirm($this->groupid, $this->userid);
        $candidates = [];
        foreach ($overlap->candidates((int)$findatime->duration, time(), self::CANDIDATES) as $candidate) {
            $candidates[] = [
                'timestart' => $candidate['timestart'],
                'label' => userdate($candidate['timestart'], $format, $usertz),
                'available' => count($candidate['available']),
                'ifneedbe' => count($candidate['ifneedbe']),
                'availablenames' => $namelist($candidate['available']),
                'ifneedbenames' => $namelist($candidate['ifneedbe']),
            ];
        }

        return [
            'groupid' => $this->groupid,
            'membercount' => $overlap->member_count(),
            'respondedcount' => count($overlap->responded()),
            'pendingnames' => $namelist($overlap->pending()),
            'canconfirm' => $canconfirm,
            'slots' => $summary,
            'candidates' => $candidates,
        ];
    }

    /**
     * Heat level (0-4) of a slot: the share of members available, "if need be" counting half.
     *
     * @param int $available Members available.
     * @param int $ifneedbe Members available if need be.
     * @param int $members Group size.
     * @return int
     */
    public static function level(int $available, int $ifneedbe, int $members): int {
        if ($members <= 0 || ($available + $ifneedbe) === 0) {
            return 0;
        }
        $share = ($available + $ifneedbe / 2) / $members;
        return max(1, min(4, (int)ceil($share * 4)));
    }

    /**
     * Template data for the candidate list, shared with the JavaScript refresh.
     *
     * @param array $data Output of build_data().
     * @return array
     */
    public static function candidates_context(array $data): array {
        $candidates = [];
        foreach ($data['candidates'] as $index => $candidate) {
            $candidates[] = $candidate + [
                'first' => $index === 0,
                'availablelist' => implode(', ', $candidate['availablenames']),
                'ifneedbelist' => implode(', ', $candidate['ifneedbenames']),
                'hasifneedbe' => $candidate['ifneedbe'] > 0,
            ];
        }
        return [
            'groupid' => $data['groupid'],
            'membercount' => $data['membercount'],
            'canconfirm' => $data['canconfirm'],
            'candidates' => $candidates,
            'hascandidates' => !empty($candidates),
        ];
    }

    /**
     * Export for the template.
     *
     * @param renderer_base $output Renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $data = $this->build_data();
        $slots = new slots($this->access->get_findatime());
        $byslot = array_column($data['slots'], null, 'slotstart');
        $members = $data['membercount'];
        $layout = $slots->layout(\core_date::get_user_timezone_object(), function (int $start) use ($byslot, $members): array {
            $available = $byslot[$start]['available'] ?? 0;
            $ifneedbe = $byslot[$start]['ifneedbe'] ?? 0;
            return [
                'available' => $available,
                'ifneedbe' => $ifneedbe,
                'level' => self::level($available, $ifneedbe, $members),
                'hasany' => $available > 0 || $ifneedbe > 0,
                'summary' => get_string(
                    'heatsummary',
                    'findatime',
                    ['available' => $available, 'ifneedbe' => $ifneedbe, 'members' => $members]
                ),
            ];
        });

        return [
            'uniqid' => \html_writer::random_id('mod-findatime-heatmap'),
            'cmid' => $this->access->get_cm()->id,
            'groupid' => $this->groupid,
            'membercount' => $members,
            'respondedcount' => $data['respondedcount'],
            'pendinglist' => implode(', ', $data['pendingnames']),
            'haspending' => !empty($data['pendingnames']),
            'days' => $layout['days'],
            'rows' => helper::mark_hours($layout['rows']),
            'hasslots' => $slots->count() > 0,
            'slotdata' => json_encode(array_values($data['slots'])),
            'candidateslist' => self::candidates_context($data),
        ];
    }
}
