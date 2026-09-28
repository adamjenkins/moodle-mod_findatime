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

/**
 * Tests for the teacher overview.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(overview_report::class)]
final class overview_report_test extends \advanced_testcase {
    /**
     * In separate groups, a teacher without access to all groups sees only their own groups; names are
     * passed to the template unescaped (the template escapes them once).
     */
    public function test_groups_limited_to_viewer(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $module = $gen->create_module('findatime', ['course' => $course->id]);
        $own = $gen->create_group(['courseid' => $course->id, 'name' => 'R&D']);
        $other = $gen->create_group(['courseid' => $course->id, 'name' => 'Other team']);
        $teacher = $gen->create_and_enrol($course, 'teacher');
        $editor = $gen->create_and_enrol($course, 'editingteacher');
        $gen->create_group_member(['groupid' => $own->id, 'userid' => $teacher->id]);
        $findatime = $DB->get_record('findatime', ['id' => $module->id]);
        $cm = get_fast_modinfo($course)->get_cm($module->cmid);
        $access = new access($findatime, $cm);
        $this->assertFalse(has_capability('moodle/site:accessallgroups', $access->get_context(), $teacher->id));
        $output = $PAGE->get_renderer('core');

        $rows = (new overview_report($access, (int)$teacher->id))->export_for_template($output)['groups'];
        $this->assertSame(['R&D'], array_column($rows, 'groupname'));

        $rows = (new overview_report($access, (int)$editor->id))->export_for_template($output)['groups'];
        $this->assertEqualsCanonicalizing(['R&D', 'Other team'], array_column($rows, 'groupname'));
    }
}
