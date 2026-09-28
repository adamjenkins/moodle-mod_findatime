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

/**
 * External functions of mod_findatime.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_findatime_get_grid' => [
        'classname' => 'mod_findatime\external\get_grid',
        'description' => 'Get the slots of an activity and the current user\'s availability.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/findatime:view',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'mod_findatime_save_availability' => [
        'classname' => 'mod_findatime\external\save_availability',
        'description' => 'Replace the current user\'s availability.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/findatime:respond',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'mod_findatime_get_overlap' => [
        'classname' => 'mod_findatime\external\get_overlap',
        'description' => 'Get how many members of a group are available in each slot, and the best meeting times.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/findatime:view',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'mod_findatime_cancel_meeting' => [
        'classname' => 'mod_findatime\external\cancel_meeting',
        'description' => 'Cancel the confirmed meeting of a group.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/findatime:view',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];
