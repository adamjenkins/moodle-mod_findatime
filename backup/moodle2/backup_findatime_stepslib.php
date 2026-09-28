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
 * Backup structure of mod_findatime.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_findatime_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $findatime = new backup_nested_element('findatime', ['id'], [
            'name', 'intro', 'introformat', 'timezone', 'datestart', 'dateend', 'daystartmins', 'dayendmins',
            'slotsize', 'duration', 'allowifneedbe', 'memberconfirm', 'autoconfirm', 'autoconfirmdone',
            'completionsubmit', 'timecreated', 'timemodified',
        ]);
        $responses = new backup_nested_element('responses');
        $response = new backup_nested_element('response', ['id'], ['userid', 'timecreated', 'timemodified']);
        $slots = new backup_nested_element('slots');
        $slot = new backup_nested_element('slot', ['id'], ['slotstart', 'status']);
        $meetings = new backup_nested_element('meetings');
        $meeting = new backup_nested_element('meeting', ['id'], [
            'groupid', 'timestart', 'duration', 'location', 'status', 'source', 'usermodified', 'timecreated',
            'timemodified',
        ]);

        $findatime->add_child($responses);
        $responses->add_child($response);
        $response->add_child($slots);
        $slots->add_child($slot);
        $findatime->add_child($meetings);
        $meetings->add_child($meeting);

        $findatime->set_source_table('findatime', ['id' => backup::VAR_ACTIVITYID]);
        if ($userinfo) {
            $response->set_source_table('findatime_responses', ['findatimeid' => backup::VAR_PARENTID], 'id ASC');
            $slot->set_source_table('findatime_slots', ['responseid' => backup::VAR_PARENTID], 'id ASC');
            $meeting->set_source_table('findatime_meetings', ['findatimeid' => backup::VAR_PARENTID], 'id ASC');
        }

        $response->annotate_ids('user', 'userid');
        $meeting->annotate_ids('user', 'usermodified');
        $meeting->annotate_ids('group', 'groupid');
        $findatime->annotate_files('mod_findatime', 'intro', null);

        return $this->prepare_activity_structure($findatime);
    }
}
