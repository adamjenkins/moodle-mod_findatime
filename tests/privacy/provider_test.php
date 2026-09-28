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

namespace mod_findatime\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_findatime\local\access;
use mod_findatime\local\availability;
use mod_findatime\local\meetings;
use mod_findatime\local\slots;

/**
 * Tests for the privacy provider.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass Instance. */
    protected $findatime;
    /** @var \context_module Context. */
    protected $context;
    /** @var \stdClass[] Users. */
    protected $users = [];
    /** @var int[] Slot starts. */
    protected $starts;

    /**
     * Two students with availability; a teacher who confirmed a meeting; a second activity.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $module = $gen->create_module('findatime', [
            'course' => $course->id,
            'datestart' => gmmktime(12, 0, 0, 10, 20, 2026),
            'dateend' => gmmktime(12, 0, 0, 10, 20, 2026),
        ]);
        $this->findatime = $DB->get_record('findatime', ['id' => $module->id]);
        $this->context = \context_module::instance($module->cmid);
        $this->starts = (new slots($this->findatime))->get_starts();
        $this->users['a'] = $gen->create_and_enrol($course, 'student');
        $this->users['b'] = $gen->create_and_enrol($course, 'student');
        $this->users['teacher'] = $gen->create_and_enrol($course, 'editingteacher');
        $this->users['none'] = $gen->create_and_enrol($course, 'student');
        availability::save($this->findatime, $this->users['a']->id, [$this->starts[0] => 1, $this->starts[1] => 2]);
        availability::save($this->findatime, $this->users['b']->id, [$this->starts[0] => 1]);
        $this->redirectMessages();
        $cm = get_fast_modinfo($course)->get_cm($module->cmid);
        meetings::confirm(new access($this->findatime, $cm), 0, $this->starts[0], 'Library', $this->users['teacher']->id);

        $other = $gen->create_module('findatime', ['course' => $course->id]);
        $otherrecord = $DB->get_record('findatime', ['id' => $other->id]);
        availability::save($otherrecord, $this->users['a']->id, [(new slots($otherrecord))->get_starts()[0] => 1]);
    }

    /**
     * Every table in the metadata.
     */
    public function test_get_metadata(): void {
        $items = provider::get_metadata(new collection('mod_findatime'))->get_collection();
        $names = array_map(function ($item) {
            return $item->get_name();
        }, $items);
        $this->assertEqualsCanonicalizing(
            ['findatime_responses', 'findatime_slots', 'findatime_meetings', 'core_message'],
            $names
        );
    }

    /**
     * Contexts and users are found through responses and through confirmed meetings.
     */
    public function test_contexts_and_users(): void {
        $this->assertCount(2, provider::get_contexts_for_userid($this->users['a']->id)->get_contextids());
        $this->assertSame(
            [$this->context->id],
            array_map('intval', provider::get_contexts_for_userid($this->users['teacher']->id)->get_contextids())
        );
        $this->assertCount(0, provider::get_contexts_for_userid($this->users['none']->id)->get_contextids());

        $userlist = new userlist($this->context, 'mod_findatime');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing(
            [$this->users['a']->id, $this->users['b']->id, $this->users['teacher']->id],
            $userlist->get_userids()
        );
    }

    /**
     * Export contains the user's availability and the meetings they confirmed.
     */
    public function test_export(): void {
        $this->export_context_data_for_user($this->users['a']->id, $this->context, 'mod_findatime');
        $data = writer::with_context($this->context)->get_data();
        $this->assertCount(2, $data->availability['slots']);
        $this->assertSame(get_string('statusifneedbe', 'findatime'), $data->availability['slots'][1]['status']);
        $this->assertObjectNotHasProperty('meetingsconfirmedorcancelled', $data);

        writer::reset();
        $this->export_context_data_for_user($this->users['teacher']->id, $this->context, 'mod_findatime');
        $data = writer::with_context($this->context)->get_data();
        $this->assertSame('Library', $data->meetingsconfirmedorcancelled[0]['location']);
        $this->assertObjectNotHasProperty('availability', $data);
    }

    /**
     * Deleting one user leaves everybody else, and anonymises the meeting.
     */
    public function test_delete_for_user(): void {
        global $DB;
        $contextlist = new approved_contextlist($this->users['a'], 'mod_findatime', [$this->context->id]);
        provider::delete_data_for_user($contextlist);
        $this->assertFalse(availability::has_responded($this->findatime->id, $this->users['a']->id));
        $this->assertTrue(availability::has_responded($this->findatime->id, $this->users['b']->id));
        // B's slot here and A's slot in the other activity remain.
        $this->assertSame(2, $DB->count_records('findatime_slots'));

        provider::delete_data_for_user(new approved_contextlist(
            $this->users['teacher'],
            'mod_findatime',
            [$this->context->id]
        ));
        $meeting = meetings::get_current($this->findatime->id, 0);
        $this->assertSame(0, (int)$meeting->usermodified);
        $this->assertSame('Library', $meeting->location, 'the meeting belongs to the group and stays');
    }

    /**
     * Deleting a list of users, and everybody in a context.
     */
    public function test_delete_for_users_and_all(): void {
        global $DB;
        provider::delete_data_for_users(new approved_userlist(
            $this->context,
            'mod_findatime',
            [$this->users['b']->id, $this->users['teacher']->id]
        ));
        $this->assertTrue(availability::has_responded($this->findatime->id, $this->users['a']->id));
        $this->assertFalse(availability::has_responded($this->findatime->id, $this->users['b']->id));
        $this->assertSame(0, (int)meetings::get_current($this->findatime->id, 0)->usermodified);

        provider::delete_data_for_all_users_in_context($this->context);
        $this->assertSame(0, $DB->count_records('findatime_responses', ['findatimeid' => $this->findatime->id]));
        $this->assertSame(1, $DB->count_records('findatime_responses'), 'the other activity is untouched');
    }
}
