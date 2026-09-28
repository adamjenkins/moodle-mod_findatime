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

namespace mod_findatime\output;

/**
 * Presentation helpers shared by the output classes.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * Flag the grid rows that start a new hour, so the template can separate hours visually.
     *
     * @param array $rows Rows from \mod_findatime\local\slots::layout().
     * @return array
     */
    public static function mark_hours(array $rows): array {
        foreach ($rows as $i => $row) {
            [$minutes, $occurrence] = array_map('intval', explode(':', $row['key']));
            $rows[$i]['hourstart'] = $i > 0 && $occurrence === 0 && $minutes % 60 === 0;
        }
        return $rows;
    }

    /**
     * A sentence telling the user which timezone the times are shown in.
     *
     * @param \stdClass $findatime Instance record.
     * @return string
     */
    public static function timezone_notice(\stdClass $findatime): string {
        $usertz = \core_date::get_user_timezone_object()->getName();
        $activitytz = \mod_findatime\local\slots::timezone($findatime->timezone)->getName();
        $a = ['user' => $usertz, 'activity' => $activitytz];
        if ($usertz === $activitytz) {
            return get_string('timezonenotice', 'findatime', $a);
        }
        return get_string('timezonenoticediffers', 'findatime', $a);
    }
}
