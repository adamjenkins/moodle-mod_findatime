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

namespace mod_findatime;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/backup/moodle2/backup_plan_builder.class.php');
require_once($CFG->dirroot . '/backup/moodle2/restore_plan_builder.class.php');
require_once($CFG->dirroot . '/mod/findatime/backup/moodle2/backup_findatime_activity_task.class.php');
require_once($CFG->dirroot . '/mod/findatime/backup/moodle2/restore_findatime_activity_task.class.php');

use mod_findatime\local\access;
use mod_findatime\local\availability;
use mod_findatime\local\meetings;
use mod_findatime\local\slots;

/**
 * Tests for backup and restore.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\backup_findatime_activity_structure_step::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_findatime_activity_structure_step::class)]
final class backup_restore_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    protected $course;
    /** @var \stdClass Instance. */
    protected $findatime;
    /** @var \stdClass[] Students. */
    protected $students = [];
    /** @var \stdClass Group. */
    protected $group;

    /**
     * A course with one activity, two students' availability and a confirmed meeting.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course(['groupmode' => SEPARATEGROUPS, 'startdate' => gmmktime(0, 0, 0, 10, 1, 2026)]);
        $module = $gen->create_module('findatime', [
            'course' => $this->course->id,
            'timezone' => 'Europe/London',
            'datestart' => gmmktime(12, 0, 0, 10, 20, 2026),
            'dateend' => gmmktime(12, 0, 0, 10, 21, 2026),
            'daystartmins' => 540,
            'dayendmins' => 660,
            'autoconfirm' => gmmktime(12, 0, 0, 10, 19, 2026),
            'memberconfirm' => 1,
        ]);
        $this->findatime = $DB->get_record('findatime', ['id' => $module->id]);
        $this->group = $gen->create_group(['courseid' => $this->course->id, 'name' => 'Team']);
        foreach (['a', 'b'] as $name) {
            $this->students[$name] = $gen->create_and_enrol($this->course, 'student');
            $gen->create_group_member(['groupid' => $this->group->id, 'userid' => $this->students[$name]->id]);
        }
        $starts = (new slots($this->findatime))->get_starts();
        availability::save($this->findatime, $this->students['a']->id, [$starts[0] => 1, $starts[1] => 2]);
        availability::save($this->findatime, $this->students['b']->id, []);
        $this->redirectMessages();
        $cm = get_fast_modinfo($this->course)->get_cm($module->cmid);
        meetings::confirm(new access($this->findatime, $cm), $this->group->id, $starts[0], 'Room 1', $this->students['a']->id);
    }

    /**
     * Back the course up and restore it into a new course.
     *
     * @param bool $users Include user data.
     * @param int $shift Seconds to move the course start date by.
     * @param callable|null $edit Optional function(string $xml): string rewriting findatime.xml before restoring.
     * @return \stdClass The restored instance.
     */
    protected function backup_and_restore(bool $users, int $shift = 0, ?callable $edit = null): \stdClass {
        global $DB, $USER;
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $this->course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value($users);
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $bc->destroy();

        $dirname = 'findatime_restore_' . (int)$users . '_' . $shift . ($edit ? '_edited' : '');
        $path = make_backup_temp_directory($dirname);
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $path);
        if ($edit) {
            $xmlfiles = glob($path . '/activities/findatime_*/findatime.xml');
            $this->assertCount(1, $xmlfiles);
            file_put_contents($xmlfiles[0], $edit(file_get_contents($xmlfiles[0])));
        }
        $newcourseid = \restore_dbops::create_new_course(
            'Restored',
            'restored' . (int)$users . $shift . ($edit ? 'e' : ''),
            $this->course->category
        );
        $rc = new \restore_controller(
            $dirname,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->get_plan()->get_setting('users')->set_value($users);
        $rc->get_plan()->get_setting('course_startdate')->set_value((int)$this->course->startdate + $shift);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
        return $DB->get_record('findatime', ['course' => $newcourseid], '*', MUST_EXIST);
    }

    /**
     * With user data: settings, availability, the meeting and exactly one event per record come back.
     */
    public function test_restore_with_users(): void {
        global $DB;
        $restored = $this->backup_and_restore(true);

        foreach (
            ['timezone', 'datestart', 'dateend', 'daystartmins', 'dayendmins', 'slotsize', 'duration',
                'memberconfirm', 'autoconfirm'] as $field
        ) {
            $this->assertEquals($this->findatime->$field, $restored->$field, $field);
        }
        $this->assertEquals(
            availability::get_statuses($this->findatime->id, [$this->students['a']->id, $this->students['b']->id]),
            availability::get_statuses($restored->id, [$this->students['a']->id, $this->students['b']->id])
        );
        $this->assertTrue(availability::has_responded($restored->id, $this->students['b']->id));

        $newgroupid = (int)$DB->get_field('groups', 'id', ['courseid' => $restored->course, 'name' => 'Team']);
        $meeting = meetings::get_current($restored->id, $newgroupid);
        $this->assertNotNull($meeting);
        $this->assertSame('Room 1', $meeting->location);
        $this->assertSame((int)$this->students['a']->id, (int)$meeting->usermodified);

        $events = $DB->get_records('event', ['modulename' => 'findatime', 'instance' => $restored->id]);
        $this->assertCount(2, $events, 'one meeting event and one deadline event, no duplicates');
        $this->assertTrue($DB->record_exists('event', ['id' => $meeting->eventid, 'groupid' => $newgroupid,
            'eventtype' => 'meeting', 'courseid' => $restored->course]));
    }

    /**
     * A crafted backup: impossible settings are forced into what the form allows, a second response
     * for the same user is skipped together with its slots, and a second meeting for the group is dropped.
     */
    public function test_restore_crafted_backup(): void {
        global $DB;
        $restored = $this->backup_and_restore(true, 0, function (string $xml): string {
            $xml = preg_replace('~<duration>\d+</duration>(\s*<allowifneedbe>)~', '<duration>1000</duration>$1', $xml, 1, $n1);
            $xml = preg_replace('~<slotsize>\d+</slotsize>~', '<slotsize>7</slotsize>', $xml, 1, $n2);
            // Append a copy of student a's response (same user, with its slots) after student b's empty one.
            // It is a duplicate, so it is skipped; unless its slots are skipped too, they land in the last
            // restored response, which is b's.
            preg_match('~<response id="(\d+)">\s*<userid>(\d+)</userid>.*?</response>~s', $xml, $a);
            $copy = str_replace('<response id="' . $a[1] . '">', '<response id="999999">', $a[0]);
            $xml = str_replace('</responses>', $copy . '</responses>', $xml, $n3);
            preg_match('~<meeting id="(\d+)">.*?</meeting>~s', $xml, $m);
            $dup = str_replace('<meeting id="' . $m[1] . '">', '<meeting id="999998">', $m[0]);
            $xml = str_replace('</meetings>', $dup . '</meetings>', $xml, $n4);
            $this->assertSame([1, 1, 1, 1], [$n1, $n2, $n3, $n4], 'every edit applied');
            return $xml;
        });
        $this->assertSame(30, (int)$restored->slotsize);
        $this->assertSame(120, (int)$restored->duration, 'clamped to the two-hour window, whole slots');
        $this->assertSame(2, $DB->count_records('findatime_responses', ['findatimeid' => $restored->id]));
        $this->assertCount(2, availability::get_user_statuses($restored->id, $this->students['a']->id));
        $this->assertSame(
            [],
            availability::get_user_statuses($restored->id, $this->students['b']->id),
            'the skipped copy\'s slots must not attach to another response'
        );
        $this->assertSame(1, $DB->count_records('findatime_meetings', ['findatimeid' => $restored->id]));
        $this->assertSame(1, $DB->count_records('event', ['modulename' => 'findatime', 'instance' => $restored->id,
            'eventtype' => 'meeting']));
    }

    /**
     * Without user data: settings only, and the automatic confirmation will run again.
     */
    public function test_restore_without_users(): void {
        global $DB;
        $DB->set_field('findatime', 'autoconfirmdone', 1, ['id' => $this->findatime->id]);
        $restored = $this->backup_and_restore(false);
        $this->assertSame(0, $DB->count_records('findatime_responses', ['findatimeid' => $restored->id]));
        $this->assertSame(0, $DB->count_records('findatime_meetings', ['findatimeid' => $restored->id]));
        $this->assertSame(0, (int)$restored->autoconfirmdone);
        $this->assertSame(0, $DB->count_records('event', ['modulename' => 'findatime', 'instance' => $restored->id,
            'eventtype' => 'meeting']));
    }

    /**
     * Moving the course start date by two weeks, across the end of British Summer Time, moves the dates,
     * availability and meetings by two weeks of wall-clock time: 09:00 stays 09:00.
     */
    public function test_restore_with_date_shift(): void {
        global $DB;
        $shift = 14 * DAYSECS;
        $restored = $this->backup_and_restore(true, $shift);
        $london = new \DateTimeZone('Europe/London');
        $this->assertEquals(slots::civil_midnight('2026-11-03', $london), $restored->datestart);
        $this->assertEquals(slots::civil_midnight('2026-11-04', $london), $restored->dateend);
        // 12:00 UTC on 19 October is 13:00 BST; it becomes 13:00 GMT (= 13:00 UTC) on 2 November.
        $this->assertEquals(gmmktime(13, 0, 0, 11, 2, 2026), $restored->autoconfirm);
        $newgroupid = (int)$DB->get_field('groups', 'id', ['courseid' => $restored->course, 'name' => 'Team']);
        $this->assertEquals(gmmktime(9, 0, 0, 11, 3, 2026), meetings::get_current($restored->id, $newgroupid)->timestart);
        $slots = new slots($restored);
        $statuses = availability::get_user_statuses($restored->id, $this->students['a']->id);
        $this->assertCount(2, $statuses);
        foreach (array_keys($statuses) as $slotstart) {
            $this->assertTrue($slots->contains($slotstart), 'restored availability stays on the grid');
        }
    }
}
