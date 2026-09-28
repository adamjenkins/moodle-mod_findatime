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

namespace mod_findatime\courseformat;

use core\output\local\properties\text_align;
use core_courseformat\local\overview\overviewitem;
use mod_findatime\local\access;
use mod_findatime\local\availability;
use mod_findatime\local\meetings;

/**
 * Activity overview (Moodle 5.0 and later; the class is simply never loaded on 4.5).
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overview extends \core_courseformat\activityoverviewbase {
    /**
     * The instance record.
     *
     * @return \stdClass
     */
    protected function get_findatime(): \stdClass {
        global $DB;
        return $DB->get_record('findatime', ['id' => $this->cm->instance], '*', MUST_EXIST);
    }

    /**
     * Link to the teacher overview.
     *
     * @return overviewitem|null
     */
    public function get_actions_overview(): ?overviewitem {
        if (!has_capability('mod/findatime:viewreports', $this->context)) {
            return null;
        }
        $content = new \action_link(
            new \moodle_url('/mod/findatime/overview.php', ['id' => $this->cm->id]),
            get_string('view'),
            null,
            ['class' => 'btn btn-outline-secondary btn-sm']
        );
        return new overviewitem(
            name: get_string('actions'),
            value: get_string('view'),
            content: $content,
            textalign: text_align::CENTER,
        );
    }

    /**
     * Response and meeting status.
     *
     * @return array
     */
    public function get_extra_overview_items(): array {
        global $USER;
        $findatime = $this->get_findatime();
        $access = new access($findatime, $this->cm, $this->context);
        $items = [];

        if (has_capability('mod/findatime:viewreports', $this->context)) {
            $members = $access->members(0);
            $responded = count(availability::get_statuses($findatime->id, array_keys($members)));
            $items['responses'] = new overviewitem(
                name: get_string('responses', 'findatime'),
                value: $responded,
                content: get_string('respondedcount', 'findatime', ['responded' => $responded, 'members' => count($members)]),
                textalign: text_align::CENTER,
            );
            $confirmed = 0;
            foreach (meetings::get_for_instance($findatime->id) as $meeting) {
                if ((int)$meeting->status === meetings::STATUS_CONFIRMED) {
                    $confirmed++;
                }
            }
            $items['meetings'] = new overviewitem(
                name: get_string('meetingsconfirmed', 'findatime'),
                value: $confirmed,
                content: get_string(
                    'meetingsconfirmedcount',
                    'findatime',
                    ['confirmed' => $confirmed, 'groups' => count($access->all_groups())]
                ),
                textalign: text_align::CENTER,
            );
            return $items;
        }

        if ($access->can_respond($USER->id)) {
            $done = availability::has_responded($findatime->id, $USER->id);
            $items['responded'] = new overviewitem(
                name: get_string('submitted', 'findatime'),
                value: $done,
                content: $done ? get_string('yes') : get_string('no'),
                textalign: text_align::CENTER,
            );
        }
        $time = '-';
        foreach ($access->user_groupids($USER->id) as $groupid) {
            $meeting = meetings::get_current($findatime->id, $groupid);
            if ($meeting && (int)$meeting->status === meetings::STATUS_CONFIRMED) {
                $time = userdate($meeting->timestart, get_string('strftimedatetimeshort', 'langconfig'));
                break;
            }
        }
        $items['meeting'] = new overviewitem(
            name: get_string('meeting', 'findatime'),
            value: $time,
            content: $time,
        );
        return $items;
    }
}
