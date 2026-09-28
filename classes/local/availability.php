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

namespace mod_findatime\local;

/**
 * Storage of users' availability.
 *
 * A response row exists once a user has submitted (even when every slot is unavailable);
 * slot rows hold only the non-default statuses.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class availability {
    /**
     * The response row of a user, if they have submitted.
     *
     * @param int $findatimeid Instance id.
     * @param int $userid User id.
     * @return \stdClass|null
     */
    public static function get_response(int $findatimeid, int $userid): ?\stdClass {
        global $DB;
        $response = $DB->get_record('findatime_responses', ['findatimeid' => $findatimeid, 'userid' => $userid]);
        return $response ?: null;
    }

    /**
     * Whether a user has submitted availability.
     *
     * @param int $findatimeid Instance id.
     * @param int $userid User id.
     * @return bool
     */
    public static function has_responded(int $findatimeid, int $userid): bool {
        global $DB;
        return $DB->record_exists('findatime_responses', ['findatimeid' => $findatimeid, 'userid' => $userid]);
    }

    /**
     * Statuses of several users.
     *
     * @param int $findatimeid Instance id.
     * @param int[] $userids User ids.
     * @return array userid => [slotstart => status]; users who have responded are present even with no slots.
     */
    public static function get_statuses(int $findatimeid, array $userids): array {
        global $DB;
        if (!$userids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_values($userids), SQL_PARAMS_NAMED);
        $params['findatimeid'] = $findatimeid;
        $result = [];
        $responses = $DB->get_records_select_menu(
            'findatime_responses',
            "findatimeid = :findatimeid AND userid $insql",
            $params,
            '',
            'id, userid'
        );
        foreach ($responses as $userid) {
            $result[(int)$userid] = [];
        }
        $sql = "SELECT s.id, r.userid, s.slotstart, s.status
                  FROM {findatime_slots} s
                  JOIN {findatime_responses} r ON r.id = s.responseid
                 WHERE r.findatimeid = :findatimeid AND r.userid $insql";
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $row) {
            $result[(int)$row->userid][(int)$row->slotstart] = (int)$row->status;
        }
        $rs->close();
        return $result;
    }

    /**
     * Statuses of one user.
     *
     * @param int $findatimeid Instance id.
     * @param int $userid User id.
     * @return array slotstart => status.
     */
    public static function get_user_statuses(int $findatimeid, int $userid): array {
        return self::get_statuses($findatimeid, [$userid])[$userid] ?? [];
    }

    /**
     * Replace a user's availability with a new set.
     *
     * Every slot start is checked against the activity's slot set and every status against
     * the activity's settings; anything invalid rejects the whole save. Stored statuses for
     * slots that are no longer part of the slot set are dropped, because this is a full replace.
     *
     * @param \stdClass $findatime Instance record.
     * @param int $userid User id.
     * @param array $statuses slotstart => status; unavailable slots are simply absent.
     * @return \stdClass The response row.
     * @throws \invalid_parameter_exception
     */
    public static function save(\stdClass $findatime, int $userid, array $statuses): \stdClass {
        global $DB;

        $slots = new slots($findatime);
        $allowed = [slots::STATUS_AVAILABLE];
        if (!empty($findatime->allowifneedbe)) {
            $allowed[] = slots::STATUS_IFNEEDBE;
        }
        $clean = [];
        foreach ($statuses as $slotstart => $status) {
            $slotstart = (int)$slotstart;
            $status = (int)$status;
            if (!$slots->contains($slotstart)) {
                throw new \invalid_parameter_exception('Not a slot of this activity: ' . $slotstart);
            }
            if (!in_array($status, $allowed, true)) {
                throw new \invalid_parameter_exception('Invalid status: ' . $status);
            }
            $clean[$slotstart] = $status;
        }

        $now = time();
        $transaction = $DB->start_delegated_transaction();
        $response = self::get_response($findatime->id, $userid);
        if ($response) {
            $response->timemodified = $now;
            $DB->set_field('findatime_responses', 'timemodified', $now, ['id' => $response->id]);
            $DB->delete_records('findatime_slots', ['responseid' => $response->id]);
        } else {
            $response = (object)[
                'findatimeid' => $findatime->id,
                'userid' => $userid,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $response->id = $DB->insert_record('findatime_responses', $response);
        }
        $rows = [];
        foreach ($clean as $slotstart => $status) {
            $rows[] = ['responseid' => $response->id, 'slotstart' => $slotstart, 'status' => $status];
        }
        if ($rows) {
            $DB->insert_records('findatime_slots', $rows);
        }
        $transaction->allow_commit();
        return $response;
    }

    /**
     * Delete one user's availability.
     *
     * @param int $findatimeid Instance id.
     * @param int $userid User id.
     */
    public static function delete_for_user(int $findatimeid, int $userid): void {
        global $DB;
        $response = self::get_response($findatimeid, $userid);
        if ($response) {
            $DB->delete_records('findatime_slots', ['responseid' => $response->id]);
            $DB->delete_records('findatime_responses', ['id' => $response->id]);
        }
    }

    /**
     * Delete everybody's availability in an instance.
     *
     * @param int $findatimeid Instance id.
     */
    public static function delete_for_instance(int $findatimeid): void {
        global $DB;
        $DB->delete_records_select(
            'findatime_slots',
            'responseid IN (SELECT id FROM {findatime_responses} WHERE findatimeid = ?)',
            [$findatimeid]
        );
        $DB->delete_records('findatime_responses', ['findatimeid' => $findatimeid]);
    }
}
