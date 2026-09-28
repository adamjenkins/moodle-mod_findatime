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

namespace mod_findatime\event;

/**
 * A group meeting was cancelled.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int groupid: the group (0 without group mode).
 *      - int timestart: the start of the cancelled meeting.
 * }
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class meeting_cancelled extends \core\event\base {
    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'findatime_meetings';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventmeetingcancelled', 'findatime');
    }

    /**
     * Description of the event.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' cancelled the meeting of the group with id '{$this->other['groupid']}' " .
            "in the Find a time activity with course module id '{$this->contextinstanceid}'.";
    }

    /**
     * Link to the activity, showing the group.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url(
            '/mod/findatime/view.php',
            ['id' => $this->contextinstanceid, 'group' => $this->other['groupid']]
        );
    }

    /**
     * Validate the data.
     */
    protected function validate_data() {
        parent::validate_data();
        foreach (['groupid', 'timestart'] as $key) {
            if (!isset($this->other[$key])) {
                throw new \coding_exception("The '$key' value must be set in other.");
            }
        }
    }

    /**
     * Backup/restore mapping of the object id.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'findatime_meetings', 'restore' => 'findatime_meeting'];
    }

    /**
     * Backup/restore mapping of other.
     *
     * @return array
     */
    public static function get_other_mapping() {
        return ['groupid' => ['db' => 'groups', 'restore' => 'group']];
    }
}
