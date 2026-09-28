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
require_once($CFG->dirroot . '/mod/findatime/lib.php');

use mod_findatime\local\access;
use mod_findatime\local\availability;
use mod_findatime\local\meetings;
use mod_findatime\local\slots;

/**
 * Tests for the lib.php callbacks: instance deletion, course reset and the timeline action.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('findatime_delete_instance')]
#[\PHPUnit\Framework\Attributes\CoversFunction('findatime_reset_userdata')]
#[\PHPUnit\Framework\Attributes\CoversFunction('mod_findatime_core_calendar_provide_event_action')]
final class lib_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    protected $course;
    /** @var \stdClass Course module record from the generator. */
    protected $module;
    /** @var \stdClass Instance. */
    protected $findatime;
    /** @var \stdClass Student. */
    protected $student;

    /**
     * One activity with an automatic confirmation time, a student's availability and a meeting.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->module = $gen->create_module('findatime', [
            'course' => $this->course->id,
            'datestart' => gmmktime(12, 0, 0, 10, 20, 2026),
            'dateend' => gmmktime(12, 0, 0, 10, 20, 2026),
            'autoconfirm' => gmmktime(12, 0, 0, 10, 19, 2026),
        ]);
        $this->findatime = $DB->get_record('findatime', ['id' => $this->module->id]);
        $this->student = $gen->create_and_enrol($this->course, 'student');
        $starts = (new slots($this->findatime))->get_starts();
        availability::save($this->findatime, $this->student->id, [$starts[0] => 1]);
        $this->redirectMessages();
        $cm = get_fast_modinfo($this->course)->get_cm($this->module->cmid);
        meetings::confirm(new access($this->findatime, $cm), 0, $starts[0], '', 0);
    }

    /**
     * Deleting the instance removes all its data and events.
     */
    public function test_delete_instance(): void {
        global $DB;
        $this->assertTrue(findatime_delete_instance($this->findatime->id));
        foreach (['findatime', 'findatime_responses', 'findatime_slots', 'findatime_meetings'] as $table) {
            $this->assertSame(0, $DB->count_records($table), $table);
        }
        $this->assertSame(0, $DB->count_records('event', ['modulename' => 'findatime']));
    }

    /**
     * Course reset deletes availability and meetings, shifts dates and rebuilds the events.
     */
    public function test_reset(): void {
        global $DB;
        $DB->set_field('findatime', 'autoconfirmdone', 1, ['id' => $this->findatime->id]);
        $status = findatime_reset_userdata((object)[
            'courseid' => $this->course->id,
            'reset_findatime_responses' => 1,
            'reset_findatime_meetings' => 1,
            'timeshift' => WEEKSECS,
        ]);
        $this->assertCount(3, $status);
        $this->assertSame(0, $DB->count_records('findatime_responses'));
        $this->assertSame(0, $DB->count_records('findatime_meetings'));
        $after = $DB->get_record('findatime', ['id' => $this->findatime->id]);
        $this->assertEquals($this->findatime->datestart + WEEKSECS, $after->datestart);
        $this->assertEquals($this->findatime->autoconfirm + WEEKSECS, $after->autoconfirm);
        $this->assertSame(0, (int)$after->autoconfirmdone);
        $this->assertSame(0, $DB->count_records('event', ['modulename' => 'findatime', 'eventtype' => 'meeting']));
        $this->assertEquals(
            $after->autoconfirm,
            $DB->get_field('event', 'timestart', ['modulename' => 'findatime', 'eventtype' => 'due'])
        );
    }

    /**
     * The timeline action is offered to students who have not answered, and hidden once they have.
     */
    public function test_event_action(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');
        $newstudent = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $event = \calendar_event::load($DB->get_field('event', 'id', ['modulename' => 'findatime', 'eventtype' => 'due']));
        $factory = new \core_calendar\action_factory();

        $action = mod_findatime_core_calendar_provide_event_action($event, $factory, $newstudent->id);
        $this->assertNotNull($action);
        $this->assertTrue($action->is_actionable());
        $this->assertSame(get_string('markavailability', 'findatime'), $action->get_name());

        $this->assertNull(mod_findatime_core_calendar_provide_event_action($event, $factory, $this->student->id));
        $this->assertNull(mod_findatime_core_calendar_provide_event_action($event, $factory, $teacher->id));
    }
}
