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
use mod_findatime\local\meetings;
use mod_findatime\local\overlap;
use mod_findatime\local\slots;

/**
 * Regression tests for the findings of the 2026-09-28 security and correctness audit.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(availability::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(overlap::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(meetings::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(slots::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_findatime\task\auto_confirm::class)]
#[\PHPUnit\Framework\Attributes\CoversFunction('findatime_shift_instance')]
final class audit_fixes_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    protected $course;
    /** @var \stdClass Generator course module record. */
    protected $module;
    /** @var \stdClass Instance. */
    protected $findatime;
    /** @var int[] Slot starts. */
    protected $starts;

    /**
     * An activity on 20 October 2026, 09:00-11:00 London time, without groups.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->module = $this->getDataGenerator()->create_module('findatime', [
            'course' => $this->course->id,
            'name' => 'Q&A',
            'timezone' => 'Europe/London',
            'datestart' => gmmktime(12, 0, 0, 10, 20, 2026),
            'dateend' => gmmktime(12, 0, 0, 10, 20, 2026),
            'daystartmins' => 540,
            'dayendmins' => 660,
            'duration' => 30,
        ]);
        $this->findatime = $DB->get_record('findatime', ['id' => $this->module->id]);
        $this->starts = (new slots($this->findatime))->get_starts();
    }

    /**
     * Access rules.
     *
     * @return access
     */
    protected function access(): access {
        return new access($this->findatime, get_fast_modinfo($this->course)->get_cm($this->module->cmid));
    }

    /**
     * Switching "if need be" off after answers: stored "if need be" marks stop counting, and the user
     * can still save (the grid no longer sends them back).
     */
    public function test_ifneedbe_switched_off(): void {
        $user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        availability::save($this->findatime, $user->id, [$this->starts[0] => 1, $this->starts[1] => 2]);
        $this->findatime->allowifneedbe = 0;

        $effective = availability::effective(
            $this->findatime,
            availability::get_user_statuses($this->findatime->id, $user->id)
        );
        $this->assertSame([$this->starts[0] => 1], $effective);
        availability::save($this->findatime, $user->id, $effective);

        $overlap = overlap::for_instance($this->findatime, [$user->id]);
        $this->assertSame([$this->starts[0]], array_keys($overlap->slot_summary()));
    }

    /**
     * Notifications carry plain-text names: an ampersand is not turned into an entity.
     */
    public function test_message_names_not_escaped(): void {
        $this->preventResetByRollback();
        $messages = $this->redirectMessages();
        $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        meetings::confirm($this->access(), 0, $this->starts[0], '', 0);
        $message = $messages->get_messages()[0];
        $this->assertSame('Meeting confirmed: Q&A', $message->subject);
        $this->assertStringContainsString('Q&amp;A', $message->fullmessagehtml);
        $this->assertStringNotContainsString('&amp;amp;', $message->fullmessagehtml);
    }

    /**
     * A meeting that has taken place cannot be cancelled; the automatic confirmation never replaces
     * a meeting row that appeared meanwhile.
     */
    public function test_meeting_rules(): void {
        global $DB;
        $this->redirectMessages();
        $meeting = meetings::confirm($this->access(), 0, $this->starts[0], '', 0);
        try {
            meetings::confirm($this->access(), 0, $this->starts[1], '', 0, meetings::SOURCE_AUTO);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorautoconfirmdecided', $e->errorcode);
        }
        $this->assertEquals($this->starts[0], meetings::get_current($this->findatime->id, 0)->timestart);

        $DB->set_field('findatime_meetings', 'timestart', time() - HOURSECS, ['id' => $meeting->id]);
        try {
            meetings::cancel($this->access(), 0, 0);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('errormeetingended', $e->errorcode);
        }
    }

    /**
     * The automatic confirmation waits while the activity is hidden.
     */
    public function test_auto_confirm_waits_while_hidden(): void {
        global $DB;
        $this->redirectMessages();
        $user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        availability::save($this->findatime, $user->id, [$this->starts[0] => 1]);
        $DB->set_field('findatime', 'autoconfirm', time() - 60, ['id' => $this->findatime->id]);
        set_coursemodule_visible($this->module->cmid, 0);

        $task = new \mod_findatime\task\auto_confirm();
        $task->execute();
        $this->assertNull(meetings::get_current($this->findatime->id, 0));
        $this->assertSame(0, (int)$DB->get_field('findatime', 'autoconfirmdone', ['id' => $this->findatime->id]));

        set_coursemodule_visible($this->module->cmid, 1);
        $this->expectOutputRegex('/confirmed/');
        $task->execute();
        $this->assertNotNull(meetings::get_current($this->findatime->id, 0));
    }

    /**
     * Settings arriving without the form are forced into what the form accepts.
     */
    public function test_normalise_settings(): void {
        $tz = new \DateTimeZone('Europe/London');
        $record = slots::normalise_settings((object)[
            'timezone' => 'Nowhere/Nothing',
            'slotsize' => 7,
            'daystartmins' => 1439,
            'dayendmins' => 2000,
            'duration' => 1000,
            'datestart' => slots::civil_midnight('2026-10-20', $tz),
            'dateend' => slots::civil_midnight('2026-10-10', $tz),
            'autoconfirm' => gmmktime(0, 0, 0, 1, 1, 2030),
        ]);
        $this->assertSame(30, $record->slotsize);
        $this->assertSame(1410, $record->daystartmins);
        $this->assertSame(1440, $record->dayendmins);
        $this->assertSame(30, $record->duration);
        $this->assertSame($record->datestart, $record->dateend);
        $this->assertSame(0, $record->autoconfirm);

        $long = slots::normalise_settings((object)[
            'timezone' => 'UTC', 'slotsize' => 15, 'daystartmins' => 0, 'dayendmins' => 1440, 'duration' => 60,
            'datestart' => gmmktime(0, 0, 0, 1, 1, 2027), 'dateend' => gmmktime(0, 0, 0, 12, 31, 2027), 'autoconfirm' => 0,
        ]);
        $this->assertLessThanOrEqual(slots::MAX_SLOTS, (new slots($long))->count());
        $this->assertSame(20, (new slots($long))->count() / 96, '96 slots a day: 20 days fit in 2000 slots');
    }

    /**
     * Course reset moving the dates keeps local times and moves kept availability and meetings with the grid.
     */
    public function test_reset_shift_keeps_data_on_grid(): void {
        global $DB;
        $this->redirectMessages();
        $user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        availability::save($this->findatime, $user->id, [$this->starts[0] => 1, $this->starts[1] => 1]);
        meetings::confirm($this->access(), 0, $this->starts[0], '', 0);
        $DB->set_field('findatime', 'autoconfirmdone', 1, ['id' => $this->findatime->id]);
        $DB->set_field('findatime', 'autoconfirm', gmmktime(12, 0, 0, 10, 19, 2026), ['id' => $this->findatime->id]);

        findatime_reset_userdata((object)['courseid' => $this->course->id, 'timeshift' => 14 * DAYSECS]);

        $after = $DB->get_record('findatime', ['id' => $this->findatime->id]);
        $slots = new slots($after);
        $this->assertSame(
            [gmmktime(9, 0, 0, 11, 3, 2026), gmmktime(9, 30, 0, 11, 3, 2026)],
            array_slice($slots->get_starts(), 0, 2)
        );
        foreach (array_keys(availability::get_user_statuses($after->id, $user->id)) as $slotstart) {
            $this->assertTrue($slots->contains($slotstart));
        }
        $this->assertEquals(gmmktime(9, 0, 0, 11, 3, 2026), meetings::get_current($after->id, 0)->timestart);
        $this->assertSame(0, (int)$after->autoconfirmdone);
    }
}
