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

namespace mod_findatime\external;

use core_external\external_api;
use mod_findatime\local\availability;
use mod_findatime\local\slots;

/**
 * Tests for the availability external functions.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(save_availability::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(get_grid::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_findatime\local\access::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_findatime\completion\custom_completion::class)]
final class save_availability_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    protected $course;
    /** @var \stdClass Course module record from the generator. */
    protected $module;
    /** @var int[] Slot starts. */
    protected $starts;

    /**
     * Course in separate groups mode with an activity on 5 October 2026, 09:00-11:00 London time.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1,
            'enablecompletion' => 1]);
        $this->module = $this->getDataGenerator()->create_module('findatime', [
            'course' => $this->course->id,
            'timezone' => 'Europe/London',
            'datestart' => gmmktime(12, 0, 0, 10, 5, 2026),
            'dateend' => gmmktime(12, 0, 0, 10, 5, 2026),
            'daystartmins' => 540,
            'dayendmins' => 660,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionsubmit' => 1,
        ]);
        $findatime = $DB->get_record('findatime', ['id' => $this->module->id]);
        $this->starts = (new slots($findatime))->get_starts();
    }

    /**
     * A student in a group, saving via the external function.
     *
     * @return \stdClass The student.
     */
    protected function grouped_student(): \stdClass {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $student->id]);
        return $student;
    }

    /**
     * Saving stores the statuses, triggers the event and completes the activity.
     */
    public function test_save(): void {
        $student = $this->grouped_student();
        $this->setUser($student);
        $cm = get_coursemodule_from_id('findatime', $this->module->cmid);
        $completion = new \completion_info($this->course);
        $this->assertSame(COMPLETION_INCOMPLETE, (int)$completion->get_data($cm, false, $student->id)->completionstate);
        $sink = $this->redirectEvents();

        $result = save_availability::execute($this->module->cmid, [
            ['slotstart' => $this->starts[0], 'status' => 1],
            ['slotstart' => $this->starts[1], 'status' => 2],
            ['slotstart' => $this->starts[2], 'status' => 0],
        ]);
        $result = external_api::clean_returnvalue(save_availability::execute_returns(), $result);
        $this->assertSame(2, $result['count']);
        $this->assertSame(
            [$this->starts[0] => 1, $this->starts[1] => 2],
            availability::get_user_statuses($this->module->id, $student->id)
        );

        $events = array_filter($sink->get_events(), function ($e) {
            return $e instanceof \mod_findatime\event\availability_submitted;
        });
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertSame((int)$student->id, (int)$event->userid);
        $this->assertSame(2, $event->other['count']);
        $sink->close();

        $this->assertSame(COMPLETION_COMPLETE, (int)$completion->get_data($cm, false, $student->id)->completionstate);
    }

    /**
     * In a group mode, a student in no group cannot save.
     */
    public function test_save_requires_group(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($student);
        try {
            save_availability::execute($this->module->cmid, [['slotstart' => $this->starts[0], 'status' => 1]]);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('errornogroup', $e->errorcode);
        }
        $this->assertFalse(availability::has_responded($this->module->id, $student->id));
    }

    /**
     * A teacher has no respond capability.
     */
    public function test_save_requires_capability(): void {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($teacher);
        $this->expectException(\required_capability_exception::class);
        $this->expectExceptionMessage(get_string('findatime:respond', 'findatime'));
        save_availability::execute($this->module->cmid, []);
    }

    /**
     * A user who is not enrolled cannot save at all.
     */
    public function test_save_requires_enrolment(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\require_login_exception::class);
        save_availability::execute($this->module->cmid, []);
    }

    /**
     * A timestamp outside the activity is rejected.
     */
    public function test_save_rejects_foreign_slot(): void {
        $this->setUser($this->grouped_student());
        $this->expectException(\invalid_parameter_exception::class);
        save_availability::execute($this->module->cmid, [['slotstart' => $this->starts[0] + 1, 'status' => 1]]);
    }

    /**
     * The grid comes back with labels in the user's own timezone.
     */
    public function test_get_grid_in_user_timezone(): void {
        global $DB;
        $student = $this->grouped_student();
        $student->timezone = 'Asia/Tokyo';
        $this->setUser($student);
        $findatime = $DB->get_record('findatime', ['id' => $this->module->id]);
        availability::save($findatime, $student->id, [$this->starts[0] => 1]);

        $result = external_api::clean_returnvalue(get_grid::execute_returns(), get_grid::execute($this->module->cmid));
        $this->assertSame('Europe/London', $result['activitytimezone']);
        $this->assertSame('Asia/Tokyo', $result['usertimezone']);
        $this->assertTrue($result['canrespond']);
        $this->assertTrue($result['responded']);
        $this->assertCount(4, $result['slots']);
        $this->assertSame(1, $result['slots'][0]['status']);
        // 09:00 BST is 17:00 in Tokyo.
        $this->assertStringContainsString('5:00 PM', $result['slots'][0]['label']);
    }
}
