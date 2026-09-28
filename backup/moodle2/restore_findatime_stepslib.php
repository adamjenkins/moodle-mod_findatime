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

/**
 * Restore structure of mod_findatime.
 *
 * Backup files are untrusted input: settings are clamped to what the settings form allows,
 * statuses to the known values, and rows whose user or group does not map are dropped.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_findatime_activity_structure_step extends restore_activity_structure_step {
    /** @var DateTimeZone|null The restored activity's reference timezone. */
    protected $reftz = null;
    /** @var int Whole days the course start date moved by. */
    protected $shiftdays = 0;

    /**
     * Move a timestamp by the restore's whole-day shift, keeping its wall-clock time.
     *
     * @param int $timestamp Timestamp.
     * @return int
     */
    protected function shift(int $timestamp): int {
        if (!$this->reftz || !$timestamp) {
            return $timestamp;
        }
        return \mod_findatime\local\slots::shift_civil_days($timestamp, $this->shiftdays, $this->reftz);
    }
    /**
     * Define the paths.
     *
     * @return array
     */
    protected function define_structure() {
        $paths = [new restore_path_element('findatime', '/activity/findatime')];
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('findatime_response', '/activity/findatime/responses/response');
            $paths[] = new restore_path_element('findatime_slot', '/activity/findatime/responses/response/slots/slot');
            $paths[] = new restore_path_element('findatime_meeting', '/activity/findatime/meetings/meeting');
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * The instance.
     *
     * @param array $data Data.
     */
    protected function process_findatime($data) {
        global $DB;
        $data = (object)$data;
        $data->course = $this->get_courseid();
        $data->timezone = \core_date::normalise_timezone((string)$data->timezone);
        // Core moves dates by the course start date offset in seconds. This activity moves them by whole
        // days of wall-clock time in its own timezone instead, and moves the availability and meetings the
        // same way, so that 09:00 stays 09:00 across a DST change and restored user data stays on the grid.
        // (Core's policy is not to move user data at all, MDL-9367, but here that would strand it off the grid.)
        $this->reftz = \mod_findatime\local\slots::timezone($data->timezone);
        $offset = (int)$this->apply_date_offset(1) - 1;
        $this->shiftdays = (int)round($offset / DAYSECS);
        foreach (['datestart', 'dateend'] as $field) {
            $date = \mod_findatime\local\slots::civil_date_of_midnight((int)$data->$field, $this->reftz);
            $midnight = \mod_findatime\local\slots::civil_midnight($date, $this->reftz);
            $data->$field = \mod_findatime\local\slots::shift_civil_days($midnight, $this->shiftdays, $this->reftz);
        }
        $data->autoconfirm = empty($data->autoconfirm) ? 0 : $this->shift((int)$data->autoconfirm);
        if (!in_array((int)$data->slotsize, \mod_findatime\local\slots::SLOT_SIZES, true)) {
            $data->slotsize = 30;
        }
        $data->daystartmins = max(0, min(1425, (int)$data->daystartmins));
        $data->dayendmins = max($data->daystartmins + (int)$data->slotsize, min(1440, (int)$data->dayendmins));
        $data->duration = max((int)$data->slotsize, min(480, (int)$data->duration));
        $data->dateend = min((int)$data->dateend, (int)$data->datestart + \mod_findatime\local\slots::MAX_DAYS * DAYSECS);
        $data->allowifneedbe = empty($data->allowifneedbe) ? 0 : 1;
        $data->memberconfirm = empty($data->memberconfirm) ? 0 : 1;
        $data->completionsubmit = empty($data->completionsubmit) ? 0 : 1;
        // Without user data there are no meetings, so the automatic confirmation must run again.
        $data->autoconfirmdone = ($this->get_setting_value('userinfo') && !empty($data->autoconfirmdone)) ? 1 : 0;

        $newid = $DB->insert_record('findatime', $data);
        $this->apply_activity_instance($newid);
    }

    /**
     * A user's response.
     *
     * @param array $data Data.
     */
    protected function process_findatime_response($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->findatimeid = $this->get_new_parentid('findatime');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (
            !$data->userid || $DB->record_exists(
                'findatime_responses',
                ['findatimeid' => $data->findatimeid, 'userid' => $data->userid]
            )
        ) {
            return;
        }
        $newid = $DB->insert_record('findatime_responses', $data);
        $this->set_mapping('findatime_response', $oldid, $newid);
    }

    /**
     * One slot of a response.
     *
     * @param array $data Data.
     */
    protected function process_findatime_slot($data) {
        global $DB;
        $data = (object)$data;
        $data->responseid = $this->get_new_parentid('findatime_response');
        $status = (int)$data->status;
        if (!$data->responseid || !in_array($status, [1, 2], true)) {
            return;
        }
        $data->status = $status;
        $data->slotstart = $this->shift((int)$data->slotstart);
        if ($DB->record_exists('findatime_slots', ['responseid' => $data->responseid, 'slotstart' => $data->slotstart])) {
            return;
        }
        $DB->insert_record('findatime_slots', $data);
    }

    /**
     * A group's meeting.
     *
     * @param array $data Data.
     */
    protected function process_findatime_meeting($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->findatimeid = $this->get_new_parentid('findatime');
        if (!empty($data->groupid)) {
            $data->groupid = $this->get_mappingid('group', $data->groupid);
            if (!$data->groupid) {
                return;
            }
        }
        $data->usermodified = empty($data->usermodified) ? 0 : (int)$this->get_mappingid('user', $data->usermodified);
        $data->timestart = $this->shift((int)$data->timestart);
        $data->status = (int)$data->status === 2 ? 2 : 1;
        $data->source = (int)$data->source === 2 ? 2 : 1;
        $data->duration = max(1, (int)$data->duration);
        $data->location = clean_param((string)$data->location, PARAM_TEXT);
        $data->eventid = null;
        $newid = $DB->insert_record('findatime_meetings', $data);
        $this->set_mapping('findatime_meeting', $oldid, $newid);
    }

    /**
     * Intro files.
     */
    protected function after_execute() {
        $this->add_related_files('mod_findatime', 'intro', null);
    }

    /**
     * After the whole restore (including the calendar events core restores): rebuild the events
     * from the restored records, so they match and are never duplicated.
     */
    protected function after_restore() {
        global $DB;
        $findatime = $DB->get_record('findatime', ['id' => $this->task->get_activityid()]);
        if ($findatime) {
            \mod_findatime\local\calendar_sync::rebuild($findatime);
        }
    }
}
