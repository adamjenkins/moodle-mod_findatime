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
 * Data generator for mod_findatime.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_findatime_generator extends testing_module_generator {
    /**
     * Create an instance.
     *
     * Dates default to a week starting tomorrow, 09:00-17:00 in UTC, 30 minute slots and a
     * 60 minute meeting. Pass datestart/dateend as any instant on the first/last day.
     *
     * @param array|stdClass|null $record Instance data.
     * @param array|null $options Course module options.
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object)(array)$record;
        $defaults = [
            'timezone' => 'UTC',
            'daystartmins' => 540,
            'dayendmins' => 1020,
            'slotsize' => 30,
            'duration' => 60,
            'allowifneedbe' => 1,
            'memberconfirm' => 0,
            'autoconfirm' => 0,
            'completionsubmit' => 0,
        ];
        foreach ($defaults as $name => $value) {
            if (!isset($record->$name)) {
                $record->$name = $value;
            }
        }
        $tz = \mod_findatime\local\slots::timezone($record->timezone);
        $start = $record->datestart ?? time() + DAYSECS;
        $end = $record->dateend ?? $start + 6 * DAYSECS;
        $record->datestart = \mod_findatime\local\slots::civil_midnight(
            \mod_findatime\local\slots::civil_date((int)$start, $tz),
            $tz
        );
        $record->dateend = \mod_findatime\local\slots::civil_midnight(
            \mod_findatime\local\slots::civil_date((int)$end, $tz),
            $tz
        );
        return parent::create_instance($record, (array)$options);
    }

    /**
     * Store a user's availability.
     *
     * @param array $data With 'findatimeid' or 'cmid', 'userid' and 'slots' (slotstart => status).
     * @return stdClass The response row.
     */
    public function create_availability(array $data): stdClass {
        global $DB;
        if (!empty($data['cmid'])) {
            $cm = get_coursemodule_from_id('findatime', $data['cmid'], 0, false, MUST_EXIST);
            $data['findatimeid'] = $cm->instance;
        }
        $findatime = $DB->get_record('findatime', ['id' => $data['findatimeid']], '*', MUST_EXIST);
        return \mod_findatime\local\availability::save($findatime, (int)$data['userid'], $data['slots'] ?? []);
    }
}
