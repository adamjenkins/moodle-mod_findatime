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
use mod_findatime\local\availability;
use mod_findatime\local\meetings;
use mod_findatime\local\overlap;
use mod_findatime\local\slots;

/**
 * Confirm the most popular time for every group that has no meeting once an activity's
 * automatic confirmation time has passed.
 *
 * Groups with any meeting row (confirmed or cancelled by a person) are left alone, and groups
 * where nobody is fully available for any future time are skipped. Each activity is processed
 * once; changing its automatic confirmation time makes it eligible again.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auto_confirm extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskautoconfirm', 'findatime');
    }

    /**
     * Run the task.
     */
    public function execute() {
        global $DB;
        $now = time();
        $instances = $DB->get_records_select(
            'findatime',
            'autoconfirmdone = 0 AND autoconfirm > 0 AND autoconfirm <= ?',
            [$now]
        );
        foreach ($instances as $findatime) {
            $cm = get_coursemodule_from_instance('findatime', $findatime->id, $findatime->course, false, IGNORE_MISSING);
            if ($cm && empty($cm->deletioninprogress)) {
                $this->process($findatime, $cm, $now);
            }
            $DB->set_field('findatime', 'autoconfirmdone', 1, ['id' => $findatime->id]);
        }
    }

    /**
     * Confirm the best time for each group of one activity.
     *
     * @param \stdClass $findatime Instance record.
     * @param \stdClass $cm Course module.
     * @param int $now Current time.
     */
    protected function process(\stdClass $findatime, \stdClass $cm, int $now): void {
        $access = new access($findatime, $cm);
        $slots = new slots($findatime);
        foreach (array_keys($access->all_groups()) as $groupid) {
            if (meetings::get_current($findatime->id, $groupid)) {
                continue;
            }
            $members = $access->members($groupid);
            $overlap = new overlap(
                $slots,
                array_keys($members),
                availability::get_statuses($findatime->id, array_keys($members))
            );
            $best = $overlap->best((int)$findatime->duration, $now);
            if (!$best) {
                mtrace("  findatime {$findatime->id}, group {$groupid}: nobody available, skipped");
                continue;
            }
            try {
                meetings::confirm($access, $groupid, $best['timestart'], '', 0, meetings::SOURCE_AUTO);
                mtrace("  findatime {$findatime->id}, group {$groupid}: confirmed " . userdate($best['timestart']));
            } catch (\moodle_exception $e) {
                mtrace("  findatime {$findatime->id}, group {$groupid}: " . $e->getMessage());
            }
        }
    }
}
