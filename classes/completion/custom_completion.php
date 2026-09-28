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

namespace mod_findatime\completion;

use core_completion\activity_custom_completion;

/**
 * Custom completion rule: the user has submitted availability.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {
    /**
     * Completion state of a rule.
     *
     * @param string $rule The rule.
     * @return int
     */
    public function get_state(string $rule): int {
        $this->validate_rule($rule);
        return \mod_findatime\local\availability::has_responded($this->cm->instance, $this->userid)
            ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * The custom rules.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionsubmit'];
    }

    /**
     * Descriptions of the custom rules.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        return ['completionsubmit' => get_string('completiondetail:submit', 'findatime')];
    }

    /**
     * Display order of all rules.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return ['completionview', 'completionsubmit'];
    }
}
