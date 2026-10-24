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
    /** @var int Colour levels once anybody has answered: 1 = nobody available (red) to 6 = everybody (blue). */
    public const LEVELS = 6;

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
        $overlap = overlap::for_instance($findatime, array_keys($members), $slots);

        $viewfullnames = has_capability('moodle/site:viewfullnames', $context, $this->userid);
        $requiredids = $overlap->required_ids();
        $names = [];
        $requirednames = [];
        foreach ($members as $member) {
            $name = fullname($member, $viewfullnames);
            if (in_array((int)$member->id, $requiredids, true)) {
                $requirednames[] = $name;
            }
            // Necessary participants are marked wherever their name appears.
            $names[(int)$member->id] = in_array((int)$member->id, $requiredids, true)
                ? get_string('requiredname', 'findatime', $name) : $name;
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

        $blocked = [];
        foreach ($overlap->blocked() as $slotstart => $userids) {
            $blocked[] = ['slotstart' => (int)$slotstart, 'names' => $namelist($userids)];
        }

        return [
            'groupid' => $this->groupid,
            'membercount' => $overlap->member_count(),
            'respondedcount' => count($overlap->responded()),
            'pendingnames' => $namelist($overlap->pending()),
            // Plain names here: the list is already titled "Necessary participants".
            'requirednames' => $requirednames,
            'greyrequired' => !empty($findatime->greyrequired),
            'canconfirm' => $canconfirm,
            'slots' => $summary,
            'blocked' => $blocked,
            'candidates' => $candidates,
        ];
    }

    /**
     * Heat level of a slot: 0 while nobody has answered; then 1 when nobody can come (red), LEVELS when
     * everybody is available (blue), and 2-5 for the quarters in between, by the share of members
     * available with "if need be" counting half.
     *
     * @param int $available Members available.
     * @param int $ifneedbe Members available if need be.
     * @param int $members Group size.
     * @param int $responded Members who have answered.
     * @return int
     */
    public static function level(int $available, int $ifneedbe, int $members, int $responded): int {
        if ($members <= 0 || $responded <= 0) {
            return 0;
        }
        $share = ($available + $ifneedbe / 2) / $members;
        if ($share <= 0) {
            return 1;
        }
        if ($share >= 1) {
            return self::LEVELS;
        }
        // In between: under a quarter, a quarter to a half, half to three quarters, over three quarters.
        return 2 + min(3, (int)floor($share * 4));
    }

    /**
     * The colour legend, from "nobody" to "everybody".
     *
     * @return array List of ['level' => int, 'label' => string].
     */
    public static function legend(): array {
        $legend = [];
        for ($level = 1; $level <= self::LEVELS; $level++) {
            $legend[] = ['level' => $level, 'label' => get_string('legendlevel' . $level, 'findatime')];
        }
        return $legend;
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
        $responded = $data['respondedcount'];
        $grey = $data['greyrequired'] ? array_column($data['blocked'], null, 'slotstart') : [];
        $layout = $slots->layout(\core_date::get_user_timezone_object(), function (int $start) use (
            $byslot,
            $members,
            $responded,
            $grey
        ): array {
            $available = $byslot[$start]['available'] ?? 0;
            $ifneedbe = $byslot[$start]['ifneedbe'] ?? 0;
            return [
                'available' => $available,
                'ifneedbe' => $ifneedbe,
                'level' => self::level($available, $ifneedbe, $members, $responded),
                'blocked' => isset($grey[$start]),
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
            'blockeddata' => json_encode($data['blocked']),
            'greyrequired' => $data['greyrequired'],
            'legend' => self::legend(),
            'hasrequired' => !empty($data['requirednames']),
            'requiredlist' => implode(', ', $data['requirednames']),
            'candidateslist' => self::candidates_context($data),
        ];
    }
}
