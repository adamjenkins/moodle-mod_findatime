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
require_once($CFG->dirroot . '/course/lib.php');

use mod_findatime\local\access;
use mod_findatime\local\availability;
use mod_findatime\local\calendar_sync;
use mod_findatime\local\meetings;
use mod_findatime\local\reminders;
use mod_findatime\local\slots;

/**
 * Tests for the RSVP date, reminders, sending meeting details and keeping past meetings in the calendar.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(reminders::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_findatime\task\send_reminders::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(availability::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(meetings::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(calendar_sync::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_findatime\local\notifier::class)]
#[\PHPUnit\Framework\Attributes\CoversFunction('findatime_delete_instance')]
final class rsvp_reminders_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    protected $course;
    /** @var \stdClass Generator course module record. */
    protected $module;
    /** @var \stdClass Instance. */
    protected $findatime;
    /** @var int[] Slot starts. */
    protected $starts;
    /** @var \stdClass Group. */
    protected $group;
    /** @var \stdClass[] Users by name. */
    protected $users = [];

    /**
     * Separate groups, one group of three students and a teacher; the activity is on 20 October 2030
     * (UTC, 09:00-11:00, 30 minute slots and meetings) with an RSVP date a day after now.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1, 'summary' => '']);
        $this->module = $gen->create_module('findatime', [
            'course' => $this->course->id,
            'name' => 'Kickoff',
            'datestart' => gmmktime(12, 0, 0, 10, 20, 2030),
            'dateend' => gmmktime(12, 0, 0, 10, 20, 2030),
            'daystartmins' => 540,
            'dayendmins' => 660,
            'duration' => 30,
            'rsvpby' => time() + DAYSECS,
            'reminders' => '10080,1440,60',
        ]);
        $this->findatime = $DB->get_record('findatime', ['id' => $this->module->id]);
        $this->starts = (new slots($this->findatime))->get_starts();
        $this->group = $gen->create_group(['courseid' => $this->course->id]);
        foreach (['a', 'b', 'c'] as $name) {
            $this->users[$name] = $gen->create_and_enrol($this->course, 'student');
            $gen->create_group_member(['groupid' => $this->group->id, 'userid' => $this->users[$name]->id]);
        }
        $this->users['teacher'] = $gen->create_and_enrol($this->course, 'editingteacher');
    }

    /**
     * Access rules for the current instance record.
     *
     * @return access
     */
    protected function access(): access {
        return new access($this->findatime, get_fast_modinfo($this->course)->get_cm($this->module->cmid));
    }

    /**
     * After the RSVP date availability can no longer be saved, and the grid becomes read-only.
     */
    public function test_rsvp_closes_responses(): void {
        global $DB, $PAGE;
        availability::save($this->findatime, $this->users['a']->id, [$this->starts[0] => 1]);
        $this->assertTrue(availability::responses_open($this->findatime));

        $this->findatime->rsvpby = time() - 1;
        $this->assertFalse(availability::responses_open($this->findatime));
        try {
            availability::save($this->findatime, $this->users['a']->id, [$this->starts[1] => 1]);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorrsvpclosed', $e->errorcode);
        }
        $this->assertSame([$this->starts[0] => 1], availability::get_user_statuses($this->findatime->id, $this->users['a']->id));

        $cm = get_fast_modinfo($this->course)->get_cm($this->module->cmid);
        $grid = new output\grid($this->findatime, $cm, (int)$this->users['a']->id);
        $grid = $grid->export_for_template($PAGE->get_renderer('core'));
        $this->assertFalse($grid['open']);

        // The timeline "mark your availability" event is due at the RSVP date.
        $DB->set_field('findatime', 'rsvpby', $this->findatime->rsvpby, ['id' => $this->findatime->id]);
        calendar_sync::sync_instance($this->findatime);
        $this->assertEquals(
            $this->findatime->rsvpby,
            $DB->get_field('event', 'timestart', ['modulename' => 'findatime', 'eventtype' => 'due'])
        );
    }

    /**
     * Reminders go to non-respondents only; several due offsets produce one reminder; nothing is
     * sent twice; a new RSVP date sends them afresh.
     */
    public function test_reminders(): void {
        global $DB;
        $this->preventResetByRollback();
        availability::save($this->findatime, $this->users['a']->id, []);
        $now = time();
        // The RSVP date is a day away: the one-week and one-day reminders are due, the one-hour one is not.
        $this->assertSame([10080, 1440], reminders::due($this->findatime, $now));

        $messages = $this->redirectMessages();
        $this->assertSame(2, reminders::process($this->access(), $now));
        $recipients = array_map(fn($m) => (int)$m->useridto, $messages->get_messages());
        $this->assertEqualsCanonicalizing([(int)$this->users['b']->id, (int)$this->users['c']->id], $recipients);
        $this->assertSame('rsvpreminder', $messages->get_messages()[0]->eventtype);
        $this->assertStringContainsString('Kickoff', $messages->get_messages()[0]->subject);

        $this->findatime = $DB->get_record('findatime', ['id' => $this->findatime->id]);
        $this->assertSame('10080,1440', $this->findatime->reminderssent);
        $this->assertSame([], reminders::due($this->findatime, $now));
        $this->assertSame([60], reminders::due($this->findatime, (int)$this->findatime->rsvpby - 30 * MINSECS));

        $data = (object)((array)$this->findatime + ['instance' => $this->findatime->id, 'coursemodule' => $this->module->cmid]);
        $data->rsvpby = $now + 2 * DAYSECS;
        findatime_update_instance($data);
        $this->assertSame('', (string)$DB->get_field('findatime', 'reminderssent', ['id' => $this->findatime->id]));
    }

    /**
     * The task waits while the activity is hidden.
     */
    public function test_reminder_task_waits_while_hidden(): void {
        global $DB;
        $this->preventResetByRollback();
        $messages = $this->redirectMessages();
        set_coursemodule_visible($this->module->cmid, 0);
        (new task\send_reminders())->execute();
        $this->assertCount(0, $messages->get_messages());
        $this->assertSame('', (string)$DB->get_field('findatime', 'reminderssent', ['id' => $this->findatime->id]));

        set_coursemodule_visible($this->module->cmid, 1);
        $this->expectOutputRegex('/3 reminders sent/');
        (new task\send_reminders())->execute();
        $this->assertCount(3, $messages->get_messages());
    }

    /**
     * Meeting details: sent with the note to the group except the sender, limited in frequency, and
     * not for a meeting that has ended. A silent confirmation sends nothing.
     */
    public function test_send_details(): void {
        global $DB;
        $this->preventResetByRollback();
        $messages = $this->redirectMessages();
        $meeting = meetings::confirm(
            $this->access(),
            $this->group->id,
            $this->starts[0],
            'Room 4',
            $this->users['teacher']->id,
            meetings::SOURCE_MANUAL,
            '',
            false
        );
        $this->assertCount(0, $messages->get_messages());
        $this->assertSame(0, (int)$DB->get_field('findatime_meetings', 'timenotified', ['id' => $meeting->id]));

        // To the group's members except the sender: students b and c (the teacher is not a member).
        $this->assertSame(2, meetings::send_details(
            $this->access(),
            $this->group->id,
            $this->users['a']->id,
            'Bring the draft.'
        ));
        $sent = $messages->get_messages();
        $this->assertCount(2, $sent);
        foreach ($sent as $message) {
            $this->assertNotEquals($this->users['a']->id, $message->useridto);
            $this->assertSame('meetingdetails', $message->eventtype);
            $this->assertStringContainsString('Bring the draft.', $message->fullmessage);
            $this->assertStringContainsString('Location: Room 4', $message->fullmessage);
        }

        try {
            meetings::send_details($this->access(), $this->group->id, $this->users['a']->id);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('errordetailsrecent', $e->errorcode);
        }

        $DB->set_field('findatime_meetings', 'timestart', time() - 2 * HOURSECS, ['id' => $meeting->id]);
        $DB->set_field('findatime_meetings', 'timenotified', 0, ['id' => $meeting->id]);
        try {
            meetings::send_details($this->access(), $this->group->id, $this->users['a']->id);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('errormeetingended', $e->errorcode);
        }
    }

    /**
     * Deleting the activity keeps the calendar events of past meetings as group events and deletes
     * the upcoming ones; restoring the meeting's event replaces a kept copy instead of duplicating it.
     */
    public function test_past_meetings_survive_deletion(): void {
        global $DB;
        $this->redirectMessages();
        $group2 = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $past = meetings::confirm($this->access(), $this->group->id, $this->starts[0], 'Room 4', 0);
        $future = meetings::confirm($this->access(), $group2->id, $this->starts[1], '', 0);
        $then = time() - DAYSECS;
        $DB->set_field('findatime_meetings', 'timestart', $then, ['id' => $past->id]);
        $DB->set_field('event', 'timestart', $then, ['id' => $past->eventid]);

        // Simulate a restore of the activity after keeping: the meeting's new event replaces the kept copy.
        calendar_sync::keep_past_meetings($this->findatime);
        $signature = calendar_sync::kept_signature($this->course->id, (int)$this->group->id, $then);
        $this->assertTrue($DB->record_exists('event', ['uuid' => $signature, 'modulename' => '']));
        calendar_sync::rebuild($this->findatime);
        $this->assertFalse($DB->record_exists('event', ['uuid' => $signature]));
        $this->assertSame(2, $DB->count_records('event', ['modulename' => 'findatime', 'eventtype' => 'meeting']));

        if (class_exists('\core_courseformat\formatactions') && method_exists('\core_courseformat\local\cmactions', 'delete')) {
            \core_courseformat\formatactions::cm($this->course->id)->delete($this->module->cmid);
        } else {
            course_delete_module($this->module->cmid);
        }

        $this->assertSame(0, $DB->count_records('event', ['modulename' => 'findatime']));
        $kept = $DB->get_records('event', ['uuid' => $signature]);
        $this->assertCount(1, $kept);
        $kept = reset($kept);
        $this->assertSame('group', $kept->eventtype);
        $this->assertSame((int)$this->group->id, (int)$kept->groupid);
        $this->assertSame((int)$this->course->id, (int)$kept->courseid);
        $this->assertSame(0, (int)$kept->instance);
        $this->assertSame('Room 4', $kept->description);
        $this->assertFalse($DB->record_exists('event', ['groupid' => $group2->id]));
    }

    /**
     * Settings arriving without the form: an RSVP date after the last slot is dropped, and the
     * automatic confirmation never comes before the RSVP date.
     */
    public function test_normalise_rsvp(): void {
        $record = (object)[
            'timezone' => 'UTC', 'slotsize' => 30, 'daystartmins' => 540, 'dayendmins' => 660, 'duration' => 30,
            'datestart' => gmmktime(0, 0, 0, 10, 20, 2030), 'dateend' => gmmktime(0, 0, 0, 10, 20, 2030),
            'autoconfirm' => gmmktime(1, 0, 0, 10, 20, 2030), 'rsvpby' => gmmktime(5, 0, 0, 10, 20, 2030),
        ];
        slots::normalise_settings($record);
        $this->assertSame(gmmktime(5, 0, 0, 10, 20, 2030), $record->autoconfirm);
        $record->rsvpby = gmmktime(12, 0, 0, 10, 20, 2030);
        slots::normalise_settings($record);
        $this->assertSame(0, $record->rsvpby);
    }

    /**
     * Course reset moves the RSVP date with the other dates and sends the reminders again.
     */
    public function test_reset_shifts_rsvp(): void {
        global $DB;
        $DB->set_field('findatime', 'reminderssent', '10080', ['id' => $this->findatime->id]);
        findatime_reset_userdata((object)['courseid' => $this->course->id, 'timeshift' => 7 * DAYSECS]);
        $after = $DB->get_record('findatime', ['id' => $this->findatime->id]);
        $this->assertEquals($this->findatime->rsvpby + 7 * DAYSECS, $after->rsvpby);
        $this->assertSame('', (string)$after->reminderssent);
    }
}
