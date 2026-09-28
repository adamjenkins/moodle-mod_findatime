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
 * Tests for the access rules: who may respond, see a group and confirm.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(access::class)]
final class access_test extends \advanced_testcase {
    /**
     * A course with two groups and an activity in a given group mode.
     *
     * @param int $groupmode Activity group mode.
     * @param int $memberconfirm Activity setting "any group member may confirm".
     * @return array [access, users by name, group 1 id, group 2 id]
     */
    protected function scenario(int $groupmode, int $memberconfirm = 0): array {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $module = $gen->create_module('findatime', ['course' => $course->id, 'groupmode' => $groupmode,
            'memberconfirm' => $memberconfirm]);
        $users = [
            'editingteacher' => $gen->create_and_enrol($course, 'editingteacher'),
            'teacher' => $gen->create_and_enrol($course, 'teacher'),
            'a' => $gen->create_and_enrol($course, 'student'),
            'c' => $gen->create_and_enrol($course, 'student'),
            'lonely' => $gen->create_and_enrol($course, 'student'),
        ];
        $g1 = $gen->create_group(['courseid' => $course->id]);
        $g2 = $gen->create_group(['courseid' => $course->id]);
        $gen->create_group_member(['groupid' => $g1->id, 'userid' => $users['a']->id]);
        $gen->create_group_member(['groupid' => $g2->id, 'userid' => $users['c']->id]);
        $gen->create_group_member(['groupid' => $g1->id, 'userid' => $users['teacher']->id]);
        $findatime = $DB->get_record('findatime', ['id' => $module->id]);
        $cm = get_fast_modinfo($course)->get_cm($module->cmid);
        return [new access($findatime, $cm), $users, (int)$g1->id, (int)$g2->id];
    }

    /**
     * Separate groups: students see and respond in their own group only.
     */
    public function test_separate_groups(): void {
        [$access, $u, $g1, $g2] = $this->scenario(SEPARATEGROUPS);
        $this->assertTrue($access->uses_groups());
        $this->assertSame([$g1], array_keys($access->viewable_groups($u['a']->id)));
        $this->assertTrue($access->can_view_group($g1, $u['a']->id));
        $this->assertFalse($access->can_view_group($g2, $u['a']->id));
        $this->assertTrue($access->can_respond($u['a']->id));
        $this->assertFalse($access->can_respond($u['lonely']->id));
        $this->assertFalse($access->can_respond($u['editingteacher']->id));
        $this->assertEqualsCanonicalizing([$g1, $g2], array_keys($access->viewable_groups($u['editingteacher']->id)));
        $this->assertSame([(int)$u['a']->id], array_keys($access->members($g1)));
    }

    /**
     * Visible groups: students see every group but still respond as themselves.
     */
    public function test_visible_groups(): void {
        [$access, $u, $g1, $g2] = $this->scenario(VISIBLEGROUPS);
        $this->assertEqualsCanonicalizing([$g1, $g2], array_keys($access->viewable_groups($u['a']->id)));
        $this->assertFalse($access->can_confirm($g2, $u['a']->id));
    }

    /**
     * Without groups there is one pool, group 0.
     */
    public function test_no_groups(): void {
        [$access, $u] = $this->scenario(NOGROUPS);
        $this->assertFalse($access->uses_groups());
        $this->assertSame([0], array_keys($access->viewable_groups($u['a']->id)));
        $this->assertTrue($access->can_respond($u['lonely']->id));
        $this->assertCount(3, $access->members(0));
        $this->assertTrue($access->can_confirm(0, $u['editingteacher']->id));
        $this->assertFalse($access->can_confirm(0, $u['a']->id));
        $this->assertFalse($access->can_confirm(5, $u['editingteacher']->id));
    }

    /**
     * The confirm capability works for own groups, or any group with access to all groups.
     */
    public function test_can_confirm_capability(): void {
        [$access, $u, $g1, $g2] = $this->scenario(SEPARATEGROUPS);
        $this->assertTrue($access->can_confirm($g1, $u['editingteacher']->id));
        $this->assertTrue($access->can_confirm($g2, $u['editingteacher']->id));
        $this->assertFalse($access->can_confirm(0, $u['editingteacher']->id));
        $this->assertFalse($access->can_confirm($g1, $u['a']->id));
        $this->assertFalse($access->can_confirm(99999, $u['editingteacher']->id));

        // A non-editing teacher who lost access to all groups keeps their own group only.
        $roleid = (int)$GLOBALS['DB']->get_field('role', 'id', ['shortname' => 'teacher']);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $roleid, $access->get_context()->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(has_capability('moodle/site:accessallgroups', $access->get_context(), $u['teacher']->id));
        $this->assertTrue($access->can_confirm($g1, $u['teacher']->id));
        $this->assertFalse($access->can_confirm($g2, $u['teacher']->id));
    }

    /**
     * "Any group member may confirm" lets members confirm for their own group only.
     */
    public function test_can_confirm_member_setting(): void {
        [$access, $u, $g1, $g2] = $this->scenario(SEPARATEGROUPS, 1);
        $this->assertTrue($access->can_confirm($g1, $u['a']->id));
        $this->assertFalse($access->can_confirm($g2, $u['a']->id));
        $this->assertFalse($access->can_confirm($g1, $u['lonely']->id));
    }

    /**
     * A grouping limits the groups the activity works with.
     */
    public function test_grouping(): void {
        global $DB;
        [$access, $u, $g1, $g2] = $this->scenario(SEPARATEGROUPS);
        $cm = $access->get_cm();
        $grouping = $this->getDataGenerator()->create_grouping(['courseid' => $cm->course]);
        $this->getDataGenerator()->create_grouping_group(['groupingid' => $grouping->id, 'groupid' => $g1]);
        $DB->set_field('course_modules', 'groupingid', $grouping->id, ['id' => $cm->id]);
        rebuild_course_cache($cm->course, true);
        $cm = get_fast_modinfo($cm->course)->get_cm($cm->id);
        $access = new access($access->get_findatime(), $cm);

        $this->assertSame([$g1], array_keys($access->all_groups()));
        $this->assertFalse($access->can_respond($u['c']->id));
        $this->assertTrue($access->can_confirm($g1, $u['editingteacher']->id));
        $this->assertFalse($access->can_confirm($g2, $u['editingteacher']->id));
    }
}
