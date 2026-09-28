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
 * Restore task of mod_findatime.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/findatime/backup/moodle2/restore_findatime_stepslib.php');

/**
 * Restore task of mod_findatime.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_findatime_activity_task extends restore_activity_task {
    /**
     * No specific settings.
     */
    protected function define_my_settings() {
    }

    /**
     * The structure step.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_findatime_activity_structure_step('findatime_structure', 'findatime.xml'));
    }

    /**
     * Contents to decode.
     *
     * @return array
     */
    public static function define_decode_contents() {
        return [new restore_decode_content('findatime', ['intro'], 'findatime')];
    }

    /**
     * Link decoding rules.
     *
     * @return array
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('FINDATIMEVIEWBYID', '/mod/findatime/view.php?id=$1', 'course_module'),
            new restore_decode_rule('FINDATIMEINDEX', '/mod/findatime/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Log restore rules.
     *
     * @return array
     */
    public static function define_restore_log_rules() {
        return [
            new restore_log_rule('findatime', 'view', 'view.php?id={course_module}', '{findatime}'),
        ];
    }

    /**
     * Course log restore rules.
     *
     * @return array
     */
    public static function define_restore_log_rules_for_course() {
        return [
            new restore_log_rule('findatime', 'view all', 'index.php?id={course}', null),
        ];
    }
}
