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

namespace mod_findatime\local;

/**
 * Tests for confirming and cancelling meetings, their calendar events and notifications.
 *
 * The activity runs on 20 October 2026, 09:00-11:00 London time (BST, UTC+1), so the
 * 09:00 slot is 08:00 UTC, 09:00 in London and 04:00 in New York.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(meetings::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(calendar_sync::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_findatime\observer::class)]
final class meetings_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    protected $course;
    /** @var \stdClass Instance record. */
    protected $findatime;
    /** @var \cm_info Course module. */
    protected $cm;
    /** @var \stdClass Group with the two students. */
    protected $group;
    /** @var \stdClass Other group. */
    protected $group2;
    /** @var \stdClass[] Users by role name. */
    protected $users = [];

    /**
     * Course in separate groups mode with two groups.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $module = $gen->create_module('findatime', [
            'course' => $this->course->id,
            'name' => 'Kickoff',
            'timezone' => 'Europe/London',
            'datestart' => gmmktime(12, 0, 0, 10, 20, 2026),
            'dateend' => gmmktime(12, 0, 0, 10, 20, 2026),
            'daystartmins' => 540,
            'dayendmins' => 660,
            'duration' => 60,
        ]);
        $this->findatime = $DB->get_record('findatime', ['id' => $module->id]);
        $this->cm = get_fast_modinfo($this->course)->get_cm($module->cmid);
        $this->users['teacher'] = $gen->create_and_enrol($this->course, 'editingteacher', ['timezone' => 'Europe/London']);
        $this->users['a'] = $gen->create_and_enrol($this->course, 'student', ['timezone' => 'Europe/London']);
        $this->users['b'] = $gen->create_and_enrol($this->course, 'student', ['timezone' => 'America/New_York']);
        $this->users['c'] = $gen->create_and_enrol($this->course, 'student', ['timezone' => 'Asia/Tokyo']);
        $this->group = $gen->create_group(['courseid' => $this->course->id, 'name' => 'G1']);
        $this->group2 = $gen->create_group(['courseid' => $this->course->id, 'name' => 'G2']);
        $gen->create_group_member(['groupid' => $this->group->id, 'userid' => $this->users['a']->id]);
        $gen->create_group_member(['groupid' => $this->group->id, 'userid' => $this->users['b']->id]);
        $gen->create_group_member(['groupid' => $this->group2->id, 'userid' => $this->users['c']->id]);
    }

    /**
     * The first event of a class.
     *
     * @param array $events Events.
     * @param string $class Event class.
     * @return \core\event\base|null
     */
    protected function first_event(array $events, string $class): ?\core\event\base {
        foreach ($events as $event) {
            if ($event instanceof $class) {
                return $event;
            }
        }
        return null;
    }

    /**
     * Access rules for the activity.
     *
     * @return access
     */
    protected function access(): access {
        return new access($this->findatime, $this->cm);
    }

    /**
     * Confirming creates the meeting, a group calendar event, an event and notifications in each
     * recipient's timezone; the person who confirmed is not notified.
     */
    public function test_confirm(): void {
        global $DB;
        $this->preventResetByRollback();
        $start = gmmktime(8, 0, 0, 10, 20, 2026);
        $messages = $this->redirectMessages();
        $events = $this->redirectEvents();

        $meeting = meetings::confirm($this->access(), $this->group->id, $start, 'Room 101', $this->users['teacher']->id);

        $this->assertSame(meetings::STATUS_CONFIRMED, (int)$meeting->status);
        $event = $DB->get_record('event', ['id' => $meeting->eventid], '*', MUST_EXIST);
        $this->assertSame('findatime', $event->modulename);
        $this->assertSame((int)$this->findatime->id, (int)$event->instance);
        $this->assertSame((int)$this->group->id, (int)$event->groupid);
        $this->assertSame((int)$this->course->id, (int)$event->courseid);
        $this->assertSame(calendar_sync::EVENTTYPE_MEETING, $event->eventtype);
        $this->assertSame($start, (int)$event->timestart);
        $this->assertSame(3600, (int)$event->timeduration);
        $this->assertSame('Room 101', $event->description);

        $confirmed = array_values(array_filter($events->get_events(), function ($e) {
            return $e instanceof \mod_findatime\event\meeting_confirmed;
        }));
        $this->assertCount(1, $confirmed);
        $this->assertSame(0, $confirmed[0]->other['previoustimestart']);
        $this->assertSame(0, $confirmed[0]->other['auto']);

        $sent = $messages->get_messages();
        $this->assertCount(2, $sent);
        $byuser = [];
        foreach ($sent as $message) {
            $byuser[(int)$message->useridto] = $message;
            $this->assertSame('meetingconfirmed', $message->eventtype);
            $this->assertStringContainsString('Meeting confirmed: Kickoff', $message->subject);
            $this->assertStringContainsString('Location: Room 101', $message->fullmessage);
        }
        $this->assertArrayNotHasKey((int)$this->users['teacher']->id, $byuser);
        $this->assertStringContainsString('9:00 AM', $byuser[(int)$this->users['a']->id]->fullmessage);
        $this->assertStringContainsString('4:00 AM', $byuser[(int)$this->users['b']->id]->fullmessage);
        $this->assertStringContainsString('(G1)', $byuser[(int)$this->users['a']->id]->fullmessage);
    }

    /**
     * Re-confirming moves the same meeting and calendar event; cancelling removes the event.
     */
    public function test_reconfirm_and_cancel(): void {
        global $DB;
        $this->preventResetByRollback();
        $this->redirectMessages();
        $first = meetings::confirm($this->access(), $this->group->id, gmmktime(8, 0, 0, 10, 20, 2026), '', 0);
        $events = $this->redirectEvents();
        $second = meetings::confirm(
            $this->access(),
            $this->group->id,
            gmmktime(9, 0, 0, 10, 20, 2026),
            'Online',
            $this->users['teacher']->id
        );

        $this->assertSame((int)$first->id, (int)$second->id);
        $this->assertSame((int)$first->eventid, (int)$second->eventid);
        $this->assertSame(1, $DB->count_records('findatime_meetings'));
        $this->assertSame(1, $DB->count_records('event', ['modulename' => 'findatime', 'eventtype' => 'meeting']));
        $this->assertSame(gmmktime(9, 0, 0, 10, 20, 2026), (int)$DB->get_field('event', 'timestart', ['id' => $second->eventid]));
        $confirmed = $this->first_event($events->get_events(), \mod_findatime\event\meeting_confirmed::class);
        $this->assertSame(gmmktime(8, 0, 0, 10, 20, 2026), $confirmed->other['previoustimestart']);
        $events->clear();

        $cancelled = meetings::cancel($this->access(), $this->group->id, $this->users['teacher']->id);
        $this->assertSame(meetings::STATUS_CANCELLED, (int)$cancelled->status);
        $this->assertNull($cancelled->eventid);
        $this->assertFalse($DB->record_exists('event', ['modulename' => 'findatime', 'eventtype' => 'meeting']));
        $this->assertNotNull($this->first_event($events->get_events(), \mod_findatime\event\meeting_cancelled::class));

        $this->expectException(\moodle_exception::class);
        meetings::cancel($this->access(), $this->group->id, $this->users['teacher']->id);
    }

    /**
     * Only a slot start with room for the whole meeting, in the future, can be confirmed.
     */
    public function test_confirm_validation(): void {
        try {
            // 10:30 London + 60 minutes runs past the 11:00 window end.
            meetings::confirm($this->access(), $this->group->id, gmmktime(9, 30, 0, 10, 20, 2026), '', 0);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('errornotameetingstart', $e->errorcode);
        }
        $this->setCurrentTimeStart();
        $past = clone $this->findatime;
        $past->datestart = gmmktime(0, 0, 0, 1, 5, 2021);
        $past->dateend = $past->datestart;
        try {
            meetings::confirm(new access($past, $this->cm), $this->group->id, gmmktime(9, 0, 0, 1, 5, 2021), '', 0);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('errormeetingpast', $e->errorcode);
        }
    }

    /**
     * Deleting a group deletes its meetings; other groups keep theirs.
     */
    public function test_group_deleted(): void {
        global $DB;
        $this->preventResetByRollback();
        $this->redirectMessages();
        meetings::confirm($this->access(), $this->group->id, gmmktime(8, 0, 0, 10, 20, 2026), '', 0);
        meetings::confirm($this->access(), $this->group2->id, gmmktime(9, 0, 0, 10, 20, 2026), '', 0);
        groups_delete_group($this->group->id);
        $this->assertFalse($DB->record_exists('findatime_meetings', ['groupid' => $this->group->id]));
        $this->assertTrue($DB->record_exists('findatime_meetings', ['groupid' => $this->group2->id]));
        $this->assertSame(1, $DB->count_records('event', ['modulename' => 'findatime', 'eventtype' => 'meeting']));
    }

    /**
     * Rebuilding the events recreates them from the records without duplicates.
     */
    public function test_refresh_events(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/findatime/lib.php');
        $this->preventResetByRollback();
        $this->redirectMessages();
        $DB->set_field('findatime', 'autoconfirm', gmmktime(0, 0, 0, 10, 19, 2026), ['id' => $this->findatime->id]);
        meetings::confirm($this->access(), $this->group->id, gmmktime(8, 0, 0, 10, 20, 2026), '', 0);
        // Simulate a restore carrying a stale copy of the meeting event.
        $stale = $DB->get_record('event', ['modulename' => 'findatime', 'eventtype' => 'meeting']);
        unset($stale->id);
        $DB->insert_record('event', $stale);

        findatime_refresh_events($this->course->id);

        $this->assertSame(1, $DB->count_records('event', ['modulename' => 'findatime', 'eventtype' => 'meeting']));
        $this->assertSame(1, $DB->count_records('event', ['modulename' => 'findatime', 'eventtype' => 'due']));
        $meeting = $DB->get_record('findatime_meetings', ['groupid' => $this->group->id]);
        $this->assertTrue($DB->record_exists('event', ['id' => $meeting->eventid, 'groupid' => $this->group->id]));
    }
}
