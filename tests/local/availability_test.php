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
 * Tests for availability storage.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(availability::class)]
final class availability_test extends \advanced_testcase {
    /** @var \stdClass Instance record. */
    protected $findatime;
    /** @var int[] Slot starts. */
    protected $starts;

    /**
     * Create an activity with a fixed slot set: 5 October 2026, 09:00-11:00 UTC, 30 minute slots.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('findatime', [
            'course' => $course->id,
            'datestart' => gmmktime(12, 0, 0, 10, 5, 2026),
            'dateend' => gmmktime(12, 0, 0, 10, 5, 2026),
            'daystartmins' => 540,
            'dayendmins' => 660,
        ]);
        $this->findatime = $DB->get_record('findatime', ['id' => $module->id]);
        $this->starts = (new slots($this->findatime))->get_starts();
    }

    /**
     * Saving stores the statuses and a response row, and a second save replaces them.
     */
    public function test_save_and_replace(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        [$a, $b, $c] = $this->starts;

        availability::save($this->findatime, $user->id, [$a => slots::STATUS_AVAILABLE, $b => slots::STATUS_IFNEEDBE]);
        $this->assertSame([$a => 1, $b => 2], availability::get_user_statuses($this->findatime->id, $user->id));

        availability::save($this->findatime, $user->id, [$c => slots::STATUS_AVAILABLE]);
        $this->assertSame([$c => 1], availability::get_user_statuses($this->findatime->id, $user->id));
        $this->assertSame(1, $DB->count_records('findatime_responses', ['findatimeid' => $this->findatime->id]));
        $this->assertSame(1, $DB->count_records('findatime_slots'));
    }

    /**
     * An empty save still counts as having responded.
     */
    public function test_empty_save_is_a_response(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->assertFalse(availability::has_responded($this->findatime->id, $user->id));
        availability::save($this->findatime, $user->id, []);
        $this->assertTrue(availability::has_responded($this->findatime->id, $user->id));
        $this->assertSame([$user->id => []], availability::get_statuses($this->findatime->id, [$user->id]));
    }

    /**
     * A timestamp that is not a slot start rejects the whole save and leaves stored data alone.
     */
    public function test_save_rejects_foreign_slot(): void {
        $user = $this->getDataGenerator()->create_user();
        availability::save($this->findatime, $user->id, [$this->starts[0] => 1]);
        try {
            availability::save($this->findatime, $user->id, [$this->starts[1] => 1, $this->starts[1] + 60 => 1]);
            $this->fail('Expected an exception');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('Not a slot of this activity', $e->debuginfo);
        }
        $this->assertSame([$this->starts[0] => 1], availability::get_user_statuses($this->findatime->id, $user->id));
    }

    /**
     * "If need be" is rejected when the activity does not allow it, and unknown statuses always are.
     */
    public function test_save_rejects_status(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->findatime->allowifneedbe = 0;
        try {
            availability::save($this->findatime, $user->id, [$this->starts[0] => slots::STATUS_IFNEEDBE]);
            $this->fail('Expected an exception');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('Invalid status: 2', $e->debuginfo);
        }
        $this->findatime->allowifneedbe = 1;
        $this->expectException(\invalid_parameter_exception::class);
        availability::save($this->findatime, $user->id, [$this->starts[0] => 3]);
    }

    /**
     * Statuses of several users come back per user; deleting one user leaves the others.
     */
    public function test_get_statuses_and_delete(): void {
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $u3 = $this->getDataGenerator()->create_user();
        availability::save($this->findatime, $u1->id, [$this->starts[0] => 1]);
        availability::save($this->findatime, $u2->id, [$this->starts[0] => 2, $this->starts[3] => 1]);

        $all = availability::get_statuses($this->findatime->id, [$u1->id, $u2->id, $u3->id]);
        $this->assertSame([$this->starts[0] => 1], $all[$u1->id]);
        $this->assertSame([$this->starts[0] => 2, $this->starts[3] => 1], $all[$u2->id]);
        $this->assertArrayNotHasKey($u3->id, $all);

        availability::delete_for_user($this->findatime->id, $u1->id);
        $this->assertFalse(availability::has_responded($this->findatime->id, $u1->id));
        $this->assertTrue(availability::has_responded($this->findatime->id, $u2->id));

        availability::delete_for_instance($this->findatime->id);
        $this->assertSame([], availability::get_statuses($this->findatime->id, [$u1->id, $u2->id]));
    }
}
