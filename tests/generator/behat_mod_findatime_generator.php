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
 * Behat data generator for mod_findatime.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_findatime_generator extends behat_generator_base {
    /**
     * Entities Behat can create.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'availabilities' => [
                'singular' => 'availability',
                'datagenerator' => 'behat_availability',
                'required' => ['activity', 'user', 'day', 'time'],
                'switchids' => ['activity' => 'cmid', 'user' => 'userid'],
            ],
            'meetings' => [
                'singular' => 'meeting',
                'datagenerator' => 'behat_meeting',
                'required' => ['activity', 'day', 'time'],
                'switchids' => ['activity' => 'cmid', 'group' => 'groupid'],
            ],
        ];
    }
}
