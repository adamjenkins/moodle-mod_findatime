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
use mod_findatime\local\slots;

/**
 * Tests for the automatic confirmation task.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(auto_confirm::class)]
final class auto_confirm_test extends \advanced_testcase {
    /**
     * The task confirms the most popular time per group, leaves decided groups and empty groups alone,
     * runs once, and runs again after the time is changed.
     */
    public function test_auto_confirm(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/findatime/lib.php');
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $messages = $this->redirectMessages();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $module = $gen->create_module('findatime', [
            'course' => $course->id,
            'groupmode' => SEPARATEGROUPS,
            'datestart' => gmmktime(12, 0, 0, 10, 20, 2026),
            'dateend' => gmmktime(12, 0, 0, 10, 20, 2026),
            'daystartmins' => 540,
            'dayendmins' => 660,
            'duration' => 30,
            'autoconfirm' => time() - 60,
        ]);
        $findatime = $DB->get_record('findatime', ['id' => $module->id]);
        $starts = (new slots($findatime))->get_starts();
        $groups = [];
        $users = [];
        foreach ([1, 2, 3] as $i) {
            $groups[$i] = $gen->create_group(['courseid' => $course->id]);
            $users[$i] = [$gen->create_and_enrol($course, 'student'), $gen->create_and_enrol($course, 'student')];
            foreach ($users[$i] as $user) {
                $gen->create_group_member(['groupid' => $groups[$i]->id, 'userid' => $user->id]);
            }
        }
        // Group 1: both members at 09:30, one at 09:00: 09:30 wins.
        availability::save($findatime, $users[1][0]->id, [$starts[0] => 1, $starts[1] => 1]);
        availability::save($findatime, $users[1][1]->id, [$starts[1] => 1]);
        // Group 2: availability, but a teacher already decided (cancelled): leave alone.
        availability::save($findatime, $users[2][0]->id, [$starts[2] => 1]);
        $cm = get_fast_modinfo($course)->get_cm($module->cmid);
        meetings::confirm(new access($findatime, $cm), $groups[2]->id, $starts[2], '', 0);
        meetings::cancel(new access($findatime, $cm), $groups[2]->id, 0);
        // Group 3: only "if need be": nobody fully available, skipped.
        availability::save($findatime, $users[3][0]->id, [$starts[3] => 2]);
        $messages->clear();

        $task = new auto_confirm();
        $this->expectOutputRegex('/group ' . $groups[3]->id . ': nobody available, skipped/');
        $task->execute();

        $meeting1 = meetings::get_current($findatime->id, $groups[1]->id);
        $this->assertSame($starts[1], (int)$meeting1->timestart);
        $this->assertSame(meetings::SOURCE_AUTO, (int)$meeting1->source);
        $this->assertSame(0, (int)$meeting1->usermodified);
        $this->assertSame(meetings::STATUS_CANCELLED, (int)meetings::get_current($findatime->id, $groups[2]->id)->status);
        $this->assertNull(meetings::get_current($findatime->id, $groups[3]->id));
        $this->assertSame(1, (int)$DB->get_field('findatime', 'autoconfirmdone', ['id' => $findatime->id]));
        $this->assertCount(2, $messages->get_messages(), 'Both members of group 1 are notified');
        $this->assertStringContainsString('The automatic confirmation confirmed', $messages->get_messages()[0]->fullmessage);

        // Runs once: deleting the meeting and running again changes nothing.
        $DB->delete_records('findatime_meetings', ['id' => $meeting1->id]);
        $task->execute();
        $this->assertNull(meetings::get_current($findatime->id, $groups[1]->id));

        // Changing the time through the settings makes it eligible again.
        $data = (object)((array)$findatime + ['instance' => $findatime->id, 'coursemodule' => $cm->id]);
        $data->autoconfirm = time() - 30;
        findatime_update_instance($data);
        $this->assertSame(0, (int)$DB->get_field('findatime', 'autoconfirmdone', ['id' => $findatime->id]));
        $task->execute();
        $this->assertSame($starts[1], (int)meetings::get_current($findatime->id, $groups[1]->id)->timestart);
    }
}
