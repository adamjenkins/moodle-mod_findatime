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
use core_external\external_single_structure;
use core_external\external_value;
use mod_findatime\local\meetings;

/**
 * Cancel the confirmed meeting of a group.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cancel_meeting extends external_api {
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
     * Cancel.
     *
     * @param int $cmid Course module id.
     * @param int $groupid Group id.
     * @return array
     */
    public static function execute(int $cmid, int $groupid): array {
        global $USER, $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid, 'groupid' => $groupid]);
        [, , , , $access] = helper::setup($params['cmid']);
        if (!$access->can_confirm($params['groupid'], $USER->id)) {
            throw new \moodle_exception('errorcannotconfirm', 'findatime');
        }
        $meeting = meetings::cancel($access, $params['groupid'], (int)$USER->id);

        $panel = new \mod_findatime\output\meeting_panel($access, $params['groupid'], (int)$USER->id);
        $output = $PAGE->get_renderer('core');
        return [
            'status' => (int)$meeting->status,
            'panelhtml' => $output->render_from_template('mod_findatime/meeting_panel', $panel->export_for_template($output)),
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_INT, 'New meeting status (2 = cancelled)'),
            'panelhtml' => new external_value(PARAM_RAW, 'The refreshed meeting panel'),
        ]);
    }
}
