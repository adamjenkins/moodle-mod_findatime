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
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_findatime\output\heatmap;

/**
 * How many members of a group are available in each slot, and the best meeting times.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_overlap extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'groupid' => new external_value(PARAM_INT, 'Group id; 0 when the activity is not in a group mode'),
        ]);
    }

    /**
     * Get the overlap.
     *
     * @param int $cmid Course module id.
     * @param int $groupid Group id.
     * @return array
     */
    public static function execute(int $cmid, int $groupid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid, 'groupid' => $groupid]);
        [, , , , $access] = helper::setup($params['cmid']);
        if (!$access->can_view_group($params['groupid'], $USER->id)) {
            throw new \moodle_exception('errorgroupnotvisible', 'findatime');
        }
        return (new heatmap($access, $params['groupid'], $USER->id))->build_data();
    }

    /**
     * Names list structure.
     *
     * @param string $desc Description.
     * @return external_multiple_structure
     */
    protected static function names(string $desc): external_multiple_structure {
        return new external_multiple_structure(new external_value(PARAM_TEXT, 'Full name'), $desc);
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'groupid' => new external_value(PARAM_INT, 'Group id'),
            'membercount' => new external_value(PARAM_INT, 'Members whose availability counts'),
            'respondedcount' => new external_value(PARAM_INT, 'Members who have submitted availability'),
            'pendingnames' => self::names('Members who have not submitted availability'),
            'canconfirm' => new external_value(PARAM_BOOL, 'Whether the user may confirm a time for this group'),
            'slots' => new external_multiple_structure(new external_single_structure([
                'slotstart' => new external_value(PARAM_INT, 'Slot start timestamp'),
                'available' => new external_value(PARAM_INT, 'Members available'),
                'ifneedbe' => new external_value(PARAM_INT, 'Members available if need be'),
                'availablenames' => self::names('Members available'),
                'ifneedbenames' => self::names('Members available if need be'),
            ]), 'Slots where anybody is available; the others have nobody'),
            'candidates' => new external_multiple_structure(new external_single_structure([
                'timestart' => new external_value(PARAM_INT, 'Meeting start timestamp'),
                'label' => new external_value(PARAM_TEXT, 'Start in the user\'s timezone'),
                'available' => new external_value(PARAM_INT, 'Members available for the whole meeting'),
                'ifneedbe' => new external_value(PARAM_INT, 'Further members available at least if need be'),
                'availablenames' => self::names('Members available for the whole meeting'),
                'ifneedbenames' => self::names('Further members available at least if need be'),
            ]), 'Best future meeting times, best first'),
        ]);
    }
}
