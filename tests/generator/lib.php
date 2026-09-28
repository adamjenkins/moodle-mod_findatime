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

    /**
     * Resolve an activity from its course module id.
     *
     * @param int $cmid Course module id.
     * @return array [instance record, course module]
     */
    protected function resolve_cm(int $cmid): array {
        global $DB;
        $cm = get_coursemodule_from_id('findatime', $cmid, 0, false, MUST_EXIST);
        return [$DB->get_record('findatime', ['id' => $cm->instance], '*', MUST_EXIST), $cm];
    }

    /**
     * The timestamp of a local time on a day of the activity's date range, in the activity timezone.
     *
     * @param stdClass $findatime Instance record.
     * @param int $day Day of the range, 0 = the first day.
     * @param string $time Local time, HH:MM.
     * @return int
     */
    public function local_time(stdClass $findatime, int $day, string $time): int {
        $tz = \mod_findatime\local\slots::timezone($findatime->timezone);
        $date = \mod_findatime\local\slots::civil_date_of_midnight((int)$findatime->datestart, $tz);
        [$hour, $minute] = array_map('intval', explode(':', $time));
        return (new \DateTimeImmutable($date . ' 00:00:00', $tz))->modify('+' . $day . ' days')->setTime($hour, $minute)
            ->getTimestamp();
    }

    /**
     * Behat: mark one slot of a user (keeping the user's other slots).
     *
     * @param array $data With cmid, userid, day, time (HH:MM, activity timezone) and status (available or ifneedbe).
     * @return stdClass The response row.
     */
    public function create_behat_availability(array $data): stdClass {
        [$findatime] = $this->resolve_cm((int)$data['cmid']);
        $statuses = \mod_findatime\local\availability::get_user_statuses($findatime->id, (int)$data['userid']);
        $slotstart = $this->local_time($findatime, (int)$data['day'], $data['time']);
        $statuses[$slotstart] = ($data['status'] ?? 'available') === 'ifneedbe' ? 2 : 1;
        return \mod_findatime\local\availability::save($findatime, (int)$data['userid'], $statuses);
    }

    /**
     * Behat: confirm the meeting of a group.
     *
     * @param array $data With cmid, groupid (optional), day, time (HH:MM, activity timezone) and location (optional).
     * @return stdClass The meeting record.
     */
    public function create_behat_meeting(array $data): stdClass {
        [$findatime, $cm] = $this->resolve_cm((int)$data['cmid']);
        $access = new \mod_findatime\local\access($findatime, $cm);
        return \mod_findatime\local\meetings::confirm(
            $access,
            (int)($data['groupid'] ?? 0),
            $this->local_time($findatime, (int)$data['day'], $data['time']),
            (string)($data['location'] ?? ''),
            0
        );
    }
}
