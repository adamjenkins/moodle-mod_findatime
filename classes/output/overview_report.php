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
use mod_findatime\local\meetings;
use renderer_base;

/**
 * The teacher overview: every group's response rate and meeting status.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overview_report implements \renderable, \templatable {
    /** @var access Access rules. */
    protected $access;

    /**
     * Constructor.
     *
     * @param access $access Access rules of the activity.
     */
    public function __construct(access $access) {
        $this->access = $access;
    }

    /**
     * Export for the template.
     *
     * @param renderer_base $output Renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $DB;
        $findatime = $this->access->get_findatime();
        $cm = $this->access->get_cm();
        $format = get_string('strftimedaydatetime', 'langconfig');
        $usertz = \core_date::get_user_timezone();

        $current = [];
        foreach (meetings::get_for_instance($findatime->id) as $meeting) {
            $current[(int)$meeting->groupid] = $meeting;
        }

        $rows = [];
        $totalmembers = 0;
        $totalresponded = 0;
        foreach ($this->access->all_groups() as $groupid => $name) {
            $members = $this->access->members($groupid);
            $responded = count(availability::get_statuses($findatime->id, array_keys($members)));
            $totalmembers += count($members);
            $totalresponded += $responded;
            $meeting = $current[$groupid] ?? null;
            $row = [
                'groupname' => $name,
                'members' => count($members),
                'responded' => $responded,
                'rate' => count($members) ? (int)round(100 * $responded / count($members)) : 0,
                'viewurl' => (new \moodle_url('/mod/findatime/view.php', ['id' => $cm->id, 'group' => $groupid]))->out(false),
                'confirmed' => false,
                'cancelled' => false,
                'noavailability' => false,
                'pending' => false,
            ];
            if ($meeting && (int)$meeting->status === meetings::STATUS_CONFIRMED) {
                $row['confirmed'] = true;
                $row['meetingtime'] = userdate($meeting->timestart, $format, $usertz);
                $row['location'] = format_text(
                    (string)$meeting->location,
                    FORMAT_MOODLE,
                    ['context' => $this->access->get_context(), 'para' => false]
                );
                $row['auto'] = (int)$meeting->source === meetings::SOURCE_AUTO;
                $row['confirmedby'] = $row['auto'] ? '' : meetings::user_name(
                    (int)$meeting->usermodified,
                    $this->access->get_context()
                );
            } else if ($meeting) {
                $row['cancelled'] = true;
            } else if (!empty($findatime->autoconfirmdone)) {
                $row['noavailability'] = true;
            } else {
                $row['pending'] = true;
            }
            $rows[] = $row;
        }

        $ungrouped = 0;
        if ($this->access->uses_groups()) {
            foreach ($this->access->members(0) as $user) {
                if (!$this->access->user_groupids((int)$user->id)) {
                    $ungrouped++;
                }
            }
        }

        return [
            'groups' => $rows,
            'hasgroups' => !empty($rows),
            'usesgroups' => $this->access->uses_groups(),
            'totalmembers' => $totalmembers,
            'totalresponded' => $totalresponded,
            'ungrouped' => $ungrouped,
            'hasungrouped' => $ungrouped > 0,
            'autoconfirm' => empty($findatime->autoconfirm) ? '' : userdate($findatime->autoconfirm, $format, $usertz),
            'autoconfirmdone' => !empty($findatime->autoconfirmdone),
        ];
    }
}
