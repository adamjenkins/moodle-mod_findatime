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
 * Necessary participants: members whose availability a meeting time must respect.
 *
 * A necessary participant constrains the times of their own group(s) only, and only once they
 * have answered: before that nothing is known about them. A time is then ruled out when they
 * marked it unavailable; "if need be" is acceptable.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class required {
    /**
     * The necessary participants of an activity.
     *
     * @param int $findatimeid Instance id.
     * @return int[] User ids.
     */
    public static function get_userids(int $findatimeid): array {
        global $DB;
        return array_map('intval', $DB->get_fieldset_select(
            'findatime_required',
            'userid',
            'findatimeid = ?',
            [$findatimeid]
        ));
    }

    /**
     * Replace the necessary participants of an activity.
     *
     * @param int $findatimeid Instance id.
     * @param int[] $userids User ids.
     */
    public static function set_userids(int $findatimeid, array $userids): void {
        global $DB;
        $userids = array_values(array_unique(array_filter(array_map('intval', $userids))));
        $current = self::get_userids($findatimeid);
        foreach (array_diff($current, $userids) as $userid) {
            $DB->delete_records('findatime_required', ['findatimeid' => $findatimeid, 'userid' => $userid]);
        }
        foreach (array_diff($userids, $current) as $userid) {
            $DB->insert_record('findatime_required', (object)['findatimeid' => $findatimeid, 'userid' => $userid]);
        }
    }

    /**
     * The users who may be chosen as necessary participants: enrolled users able to mark availability.
     *
     * @param \context $context Course or module context.
     * @return array userid => full name
     */
    public static function candidates(\context $context): array {
        $fields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $users = get_enrolled_users(
            $context,
            'mod/findatime:respond',
            0,
            'u.id, ' . $fields,
            'u.lastname, u.firstname, u.id',
            0,
            0,
            true
        );
        $options = [];
        foreach ($users as $user) {
            $options[(int)$user->id] = fullname($user);
        }
        return $options;
    }

    /**
     * Slots ruled out because a necessary participant who has answered marked them unavailable.
     *
     * @param slots $slots The slot set.
     * @param int[] $requiredids Necessary participants to consider.
     * @param array $statuses userid => [slotstart => status], as counted (see availability::effective()).
     * @return array slotstart => int[] the necessary participants who are unavailable then.
     */
    public static function blocked(slots $slots, array $requiredids, array $statuses): array {
        $blocked = [];
        foreach ($requiredids as $userid) {
            if (!array_key_exists($userid, $statuses)) {
                continue;
            }
            foreach ($slots->get_starts() as $start) {
                if (empty($statuses[$userid][$start])) {
                    $blocked[$start][] = (int)$userid;
                }
            }
        }
        return $blocked;
    }

    /**
     * The slots greyed out in a user's own grid: ruled out by the necessary participants of the
     * user's groups (other than the user), when the activity greys them out.
     *
     * @param access $access Access rules of the activity.
     * @param int $userid The user whose grid it is.
     * @return int[] Slot starts.
     */
    public static function blocked_for_user(access $access, int $userid): array {
        $findatime = $access->get_findatime();
        if (empty($findatime->greyrequired)) {
            return [];
        }
        $requiredids = self::get_userids($findatime->id);
        if (!$requiredids) {
            return [];
        }
        $members = [];
        foreach ($access->user_groupids($userid) as $groupid) {
            $members += $access->members($groupid);
        }
        $relevant = array_values(array_diff(array_intersect($requiredids, array_keys($members)), [$userid]));
        if (!$relevant) {
            return [];
        }
        $statuses = availability::get_statuses($findatime->id, $relevant);
        foreach ($statuses as $id => $userstatuses) {
            $statuses[$id] = availability::effective($findatime, $userstatuses);
        }
        return array_keys(self::blocked(new slots($findatime), $relevant, $statuses));
    }

    /**
     * Delete the necessary participants of an instance.
     *
     * @param int $findatimeid Instance id.
     */
    public static function delete_for_instance(int $findatimeid): void {
        global $DB;
        $DB->delete_records('findatime_required', ['findatimeid' => $findatimeid]);
    }
}
