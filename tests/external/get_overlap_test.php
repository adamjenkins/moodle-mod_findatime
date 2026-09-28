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
 * Tests for get_overlap and cancel_meeting.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(get_overlap::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(cancel_meeting::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_findatime\output\heatmap::class)]
final class get_overlap_test extends \advanced_testcase {
    /** @var \stdClass Course module record from the generator. */
    protected $module;
    /** @var \stdClass Instance record. */
    protected $findatime;
    /** @var \stdClass[] Users. */
    protected $users = [];
    /** @var int[] Group ids. */
    protected $groups = [];
    /** @var int[] Slot starts. */
    protected $starts;

    /**
     * Two groups; A and B in group 1 with overlapping availability, C in group 2.
     *
     * @param int $groupmode Group mode.
     */
    protected function scenario(int $groupmode): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $this->module = $gen->create_module('findatime', [
            'course' => $course->id,
            'groupmode' => $groupmode,
            'datestart' => gmmktime(12, 0, 0, 10, 20, 2026),
            'dateend' => gmmktime(12, 0, 0, 10, 20, 2026),
            'daystartmins' => 540,
            'dayendmins' => 660,
        ]);
        $this->findatime = $DB->get_record('findatime', ['id' => $this->module->id]);
        $this->starts = (new slots($this->findatime))->get_starts();
        foreach (['a', 'b', 'c'] as $name) {
            $this->users[$name] = $gen->create_and_enrol($course, 'student', ['firstname' => strtoupper($name),
                'lastname' => 'Student']);
        }
        $this->users['teacher'] = $gen->create_and_enrol($course, 'editingteacher');
        $this->groups[1] = (int)$gen->create_group(['courseid' => $course->id])->id;
        $this->groups[2] = (int)$gen->create_group(['courseid' => $course->id])->id;
        $gen->create_group_member(['groupid' => $this->groups[1], 'userid' => $this->users['a']->id]);
        $gen->create_group_member(['groupid' => $this->groups[1], 'userid' => $this->users['b']->id]);
        $gen->create_group_member(['groupid' => $this->groups[2], 'userid' => $this->users['c']->id]);
        [$s0, $s1, $s2] = $this->starts;
        availability::save($this->findatime, $this->users['a']->id, [$s0 => 1, $s1 => 1, $s2 => 1]);
        availability::save($this->findatime, $this->users['b']->id, [$s0 => 1, $s1 => 2]);
        availability::save($this->findatime, $this->users['c']->id, [$s2 => 1]);
    }

    /**
     * Call get_overlap and clean the result.
     *
     * @param int $groupid Group.
     * @return array
     */
    protected function overlap(int $groupid): array {
        return external_api::clean_returnvalue(
            get_overlap::execute_returns(),
            get_overlap::execute($this->module->cmid, $groupid)
        );
    }

    /**
     * Members see their group's counts, names and best times; other groups' availability is not mixed in.
     */
    public function test_own_group(): void {
        $this->scenario(SEPARATEGROUPS);
        $this->setUser($this->users['a']);
        $result = $this->overlap($this->groups[1]);
        $this->assertSame(2, $result['membercount']);
        $this->assertSame(2, $result['respondedcount']);
        $this->assertSame([], $result['pendingnames']);
        $this->assertFalse($result['canconfirm']);
        $byslot = array_column($result['slots'], null, 'slotstart');
        $this->assertSame(2, $byslot[$this->starts[0]]['available']);
        $this->assertSame(['A Student', 'B Student'], $byslot[$this->starts[0]]['availablenames']);
        $this->assertSame(['B Student'], $byslot[$this->starts[1]]['ifneedbenames']);
        $this->assertSame(1, $byslot[$this->starts[2]]['available'], 'C in group 2 must not count');
        // A 60 minute meeting at 09:00 spans 09:30 too, where B is only "if need be".
        $this->assertSame($this->starts[0], $result['candidates'][0]['timestart']);
        $this->assertSame(['A Student'], $result['candidates'][0]['availablenames']);
        $this->assertSame(['B Student'], $result['candidates'][0]['ifneedbenames']);
    }

    /**
     * Separate groups: another group's availability is refused.
     */
    public function test_separate_groups_refuses_other_group(): void {
        $this->scenario(SEPARATEGROUPS);
        $this->setUser($this->users['c']);
        try {
            $this->overlap($this->groups[1]);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorgroupnotvisible', $e->errorcode);
        }
    }

    /**
     * Visible groups: another group can be looked at, but not confirmed for.
     */
    public function test_visible_groups_allows_other_group(): void {
        $this->scenario(VISIBLEGROUPS);
        $this->setUser($this->users['c']);
        $result = $this->overlap($this->groups[1]);
        $this->assertSame(2, $result['respondedcount']);
        $this->assertFalse($result['canconfirm']);
    }

    /**
     * Teachers see every group and may confirm; members who have not answered are listed.
     */
    public function test_teacher(): void {
        $this->scenario(SEPARATEGROUPS);
        availability::delete_for_user($this->findatime->id, $this->users['b']->id);
        $this->setUser($this->users['teacher']);
        $result = $this->overlap($this->groups[1]);
        $this->assertTrue($result['canconfirm']);
        $this->assertSame(1, $result['respondedcount']);
        $this->assertSame(['B Student'], $result['pendingnames']);
    }

    /**
     * Cancelling needs the right to confirm for that group.
     */
    public function test_cancel_meeting(): void {
        global $DB;
        $this->scenario(SEPARATEGROUPS);
        $this->redirectMessages();
        $cm = get_fast_modinfo($this->module->course)->get_cm($this->module->cmid);
        \mod_findatime\local\meetings::confirm(
            new \mod_findatime\local\access($this->findatime, $cm),
            $this->groups[1],
            $this->starts[0],
            '',
            0
        );

        $this->setUser($this->users['a']);
        try {
            cancel_meeting::execute($this->module->cmid, $this->groups[1]);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorcannotconfirm', $e->errorcode);
        }

        $this->setUser($this->users['teacher']);
        $result = external_api::clean_returnvalue(
            cancel_meeting::execute_returns(),
            cancel_meeting::execute($this->module->cmid, $this->groups[1])
        );
        $this->assertSame(\mod_findatime\local\meetings::STATUS_CANCELLED, $result['status']);
        $this->assertStringContainsString('data-region="mod-findatime-meeting"', $result['panelhtml']);
        $this->assertFalse($DB->record_exists('event', ['modulename' => 'findatime', 'eventtype' => 'meeting']));
    }
}
