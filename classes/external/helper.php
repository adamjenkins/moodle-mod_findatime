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
use mod_findatime\local\access;

/**
 * Shared set-up of the external functions.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * Resolve and validate a course module, and require the view capability.
     *
     * @param int $cmid Course module id.
     * @return array [$findatime, $course, $cm, $context, $access]
     */
    public static function setup(int $cmid): array {
        global $DB;
        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'findatime');
        $context = \context_module::instance($cm->id);
        external_api::validate_context($context);
        require_capability('mod/findatime:view', $context);
        $findatime = $DB->get_record('findatime', ['id' => $cm->instance], '*', MUST_EXIST);
        return [$findatime, $course, $cm, $context, new access($findatime, $cm, $context)];
    }
}
