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

namespace mod_findatime\task;

use mod_findatime\local\access;
use mod_findatime\local\reminders;

/**
 * Remind members who have not marked their availability that the RSVP date is coming.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_reminders extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('tasksendreminders', 'findatime');
    }

    /**
     * Run the task. Hidden activities wait until they are shown; a reminder whose time passed
     * while hidden is still sent then, as long as the RSVP date has not passed.
     */
    public function execute() {
        global $DB;
        $now = time();
        // The earliest possible reminder is the largest offset before the RSVP date.
        $horizon = $now + max(reminders::OFFSETS) * MINSECS;
        $select = "rsvpby > :now AND rsvpby <= :horizon AND reminders IS NOT NULL AND reminders <> ''";
        $instances = $DB->get_records_select('findatime', $select, ['now' => $now, 'horizon' => $horizon]);
        foreach ($instances as $findatime) {
            if (!reminders::due($findatime, $now)) {
                continue;
            }
            $cm = get_coursemodule_from_instance('findatime', $findatime->id, $findatime->course, false, IGNORE_MISSING);
            if (!$cm || !empty($cm->deletioninprogress) || empty($cm->visible)) {
                continue;
            }
            $sent = reminders::process(new access($findatime, $cm), $now);
            mtrace("  findatime {$findatime->id}: {$sent} reminders sent");
        }
    }
}
