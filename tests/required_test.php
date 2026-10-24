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

use core_external\external_api;
use mod_findatime\local\access;
use mod_findatime\local\availability;
use mod_findatime\local\meetings;
use mod_findatime\local\required;
use mod_findatime\local\slots;
use mod_findatime\output\heatmap;

/**
 * Tests for necessary participants, greying out, and the heat colour levels.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(required::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(heatmap::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_findatime\external\get_overlap::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_findatime\task\auto_confirm::class)]
#[\PHPUnit\Framework\Attributes\CoversFunction('findatime_save_required')]
final class required_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    protected $course;
    /** @var \stdClass Generator course module record. */
    protected $module;
    /** @var \stdClass Instance. */
    protected $findatime;
    /** @var int[] Slot starts: 09:00, 09:30, 10:00, 10:30 UTC on 20 October 2030. */
    protected $starts;
    /** @var \stdClass Group of the three students. */
    protected $group;
    /** @var \stdClass[] Users by name. */
    protected $users = [];

    /**
     * Separate groups; students a, b and boss in one group, boss is necessary; outsider in no group.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1, 'summary' => '']);
        $this->module = $gen->create_module('findatime', [
            'course' => $this->course->id,
            'datestart' => gmmktime(12, 0, 0, 10, 20, 2030),
            'dateend' => gmmktime(12, 0, 0, 10, 20, 2030),
            'daystartmins' => 540,
            'dayendmins' => 660,
            'duration' => 30,
            'greyrequired' => 1,
        ]);
        $this->findatime = $DB->get_record('findatime', ['id' => $this->module->id]);
        $this->starts = (new slots($this->findatime))->get_starts();
        $this->group = $gen->create_group(['courseid' => $this->course->id]);
        foreach (['a', 'b', 'boss'] as $name) {
            $this->users[$name] = $gen->create_and_enrol($this->course, 'student', ['firstname' => ucfirst($name),
                'lastname' => 'Student']);
            $gen->create_group_member(['groupid' => $this->group->id, 'userid' => $this->users[$name]->id]);
        }
        $this->users['teacher'] = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->users['stranger'] = $gen->create_user();
        required::set_userids($this->findatime->id, [$this->users['boss']->id]);
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
     * Colour levels: neutral before any answer, red for nobody, green for everybody, quarters between.
     */
    public function test_levels(): void {
        $this->assertSame(0, heatmap::level(0, 0, 4, 0));
        $this->assertSame(1, heatmap::level(0, 0, 4, 2));
        $this->assertSame(2, heatmap::level(0, 1, 4, 2), 'one "if need be" of four is an eighth');
        $this->assertSame(3, heatmap::level(1, 0, 4, 2));
        $this->assertSame(4, heatmap::level(2, 0, 4, 2));
        $this->assertSame(5, heatmap::level(3, 1, 4, 4));
        $this->assertSame(6, heatmap::level(4, 0, 4, 4));
        $this->assertCount(heatmap::LEVELS, heatmap::legend());
    }

    /**
     * The settings form can only store enrolled users who may mark availability.
     */
    public function test_save_keeps_eligible_users_only(): void {
        findatime_save_required((object)['id' => $this->findatime->id, 'course' => $this->course->id,
            'requiredusers' => [$this->users['a']->id, $this->users['teacher']->id, $this->users['stranger']->id]]);
        $this->assertSame([(int)$this->users['a']->id], required::get_userids($this->findatime->id));
        findatime_save_required((object)['id' => $this->findatime->id, 'course' => $this->course->id, 'requiredusers' => []]);
        $this->assertSame([], required::get_userids($this->findatime->id));
    }

    /**
     * Once the necessary participant has answered, the others' grids grey out the times they cannot make
     * (but never the participant's own grid); the overlap reports them; nothing before they answer.
     */
    public function test_greying_and_overlap(): void {
        $this->assertSame([], required::blocked_for_user($this->access(), $this->users['a']->id));
        availability::save($this->findatime, $this->users['boss']->id, [$this->starts[2] => 1, $this->starts[3] => 2]);
        $this->assertSame(
            [$this->starts[0], $this->starts[1]],
            required::blocked_for_user($this->access(), $this->users['a']->id)
        );
        $this->assertSame([], required::blocked_for_user($this->access(), $this->users['boss']->id));

        $this->setUser($this->users['a']);
        $result = external_api::clean_returnvalue(
            \mod_findatime\external\get_overlap::execute_returns(),
            \mod_findatime\external\get_overlap::execute($this->module->cmid, $this->group->id)
        );
        $this->assertTrue($result['greyrequired']);
        $this->assertSame(['Boss Student'], $result['requirednames']);
        $this->assertSame([$this->starts[0], $this->starts[1]], array_column($result['blocked'], 'slotstart'));
        $this->assertSame(['Boss Student (necessary)'], $result['blocked'][0]['names']);

        // Switched off, nothing is greyed (scheduling still respects the necessary participant).
        $this->findatime->greyrequired = 0;
        $this->assertSame([], required::blocked_for_user($this->access(), $this->users['a']->id));
    }

    /**
     * The automatic confirmation never chooses a time the necessary participant cannot make, even
     * when everybody else prefers it.
     */
    public function test_auto_confirm_respects_required(): void {
        global $DB;
        $this->redirectMessages();
        [$s0, , $s2] = $this->starts;
        availability::save($this->findatime, $this->users['a']->id, [$s0 => 1, $s2 => 1]);
        availability::save($this->findatime, $this->users['b']->id, [$s0 => 1]);
        availability::save($this->findatime, $this->users['boss']->id, [$s2 => 2]);
        $DB->set_field('findatime', 'autoconfirm', time() - 60, ['id' => $this->findatime->id]);
        $this->expectOutputRegex('/confirmed/');
        (new task\auto_confirm())->execute();
        $this->assertEquals($s2, meetings::get_current($this->findatime->id, $this->group->id)->timestart);
    }

    /**
     * Privacy: the mark is exported and deleted with the user's data.
     */
    public function test_privacy(): void {
        $context = \context_module::instance($this->module->cmid);
        $this->assertContains($context->id, array_map(
            'intval',
            privacy\provider::get_contexts_for_userid($this->users['boss']->id)->get_contextids()
        ));
        privacy\provider::delete_data_for_user(new \core_privacy\local\request\approved_contextlist(
            $this->users['boss'],
            'mod_findatime',
            [$context->id]
        ));
        $this->assertSame([], required::get_userids($this->findatime->id));
    }
}
