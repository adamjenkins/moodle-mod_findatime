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
 * Replace the current user's availability.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_availability extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'slots' => new external_multiple_structure(
                new external_single_structure([
                    'slotstart' => new external_value(PARAM_INT, 'Slot start timestamp'),
                    'status' => new external_value(PARAM_INT, 'Status: 1 = available, 2 = if need be, 0 = unavailable'),
                ]),
                'The complete set of the user\'s non-default statuses; slots not listed become unavailable',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Save.
     *
     * @param int $cmid Course module id.
     * @param array $slots List of slotstart/status pairs.
     * @return array
     */
    public static function execute(int $cmid, array $slots = []): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid, 'slots' => $slots]);
        [$findatime, $course, $cm, $context, $access] = helper::setup($params['cmid']);
        require_capability('mod/findatime:respond', $context);
        if (!$access->can_respond($USER->id)) {
            throw new \moodle_exception('errornogroup', 'findatime');
        }
        if (count($params['slots']) > slots::MAX_SLOTS) {
            throw new \invalid_parameter_exception('Too many slots');
        }

        $statuses = [];
        foreach ($params['slots'] as $slot) {
            if ((int)$slot['status'] !== 0) {
                $statuses[(int)$slot['slotstart']] = (int)$slot['status'];
            }
        }
        $response = availability::save($findatime, (int)$USER->id, $statuses);

        \mod_findatime\event\availability_submitted::create([
            'objectid' => $response->id,
            'context' => $context,
            'other' => ['count' => count($statuses)],
        ])->trigger();

        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm) && !empty($findatime->completionsubmit)) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        }

        return [
            'timemodified' => (int)$response->timemodified,
            'count' => count($statuses),
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'timemodified' => new external_value(PARAM_INT, 'When the availability was saved'),
            'count' => new external_value(PARAM_INT, 'Number of slots saved as available or if need be'),
        ]);
    }
}
