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
use mod_findatime\local\availability;
use mod_findatime\local\slots;

/**
 * Get the slots of an activity and the current user's availability.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_grid extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
        ]);
    }

    /**
     * Get the grid.
     *
     * @param int $cmid Course module id.
     * @return array
     */
    public static function execute(int $cmid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);
        [$findatime, , , , $access] = helper::setup($params['cmid']);

        $slots = new slots($findatime);
        $statuses = availability::get_user_statuses($findatime->id, $USER->id);
        $usertz = \core_date::get_user_timezone();
        $format = get_string('strftimedaydatetime', 'langconfig');
        $result = [];
        foreach ($slots->get_starts() as $start) {
            $result[] = [
                'slotstart' => $start,
                'status' => $statuses[$start] ?? 0,
                'label' => userdate($start, $format, $usertz),
            ];
        }
        return [
            'activitytimezone' => $slots->get_timezone()->getName(),
            'usertimezone' => \core_date::get_user_timezone_object()->getName(),
            'slotsize' => (int)$findatime->slotsize,
            'duration' => (int)$findatime->duration,
            'allowifneedbe' => !empty($findatime->allowifneedbe),
            'canrespond' => $access->can_respond($USER->id),
            'responded' => availability::has_responded($findatime->id, $USER->id),
            'slots' => $result,
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'activitytimezone' => new external_value(PARAM_TEXT, 'Timezone the activity\'s times are defined in'),
            'usertimezone' => new external_value(PARAM_TEXT, 'The current user\'s timezone'),
            'slotsize' => new external_value(PARAM_INT, 'Slot length in minutes'),
            'duration' => new external_value(PARAM_INT, 'Meeting length in minutes'),
            'allowifneedbe' => new external_value(PARAM_BOOL, 'Whether "if need be" may be chosen'),
            'canrespond' => new external_value(PARAM_BOOL, 'Whether the user may mark availability'),
            'responded' => new external_value(PARAM_BOOL, 'Whether the user has submitted availability'),
            'slots' => new external_multiple_structure(new external_single_structure([
                'slotstart' => new external_value(PARAM_INT, 'Slot start timestamp'),
                'status' => new external_value(PARAM_INT, 'The user\'s status: 0, 1 or 2'),
                'label' => new external_value(PARAM_TEXT, 'Slot start in the user\'s timezone'),
            ])),
        ]);
    }
}
