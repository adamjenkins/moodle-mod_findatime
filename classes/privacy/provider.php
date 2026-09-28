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

namespace mod_findatime\privacy;

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadataprovider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider as userlistprovider;
use core_privacy\local\request\helper;
use core_privacy\local\request\plugin\provider as pluginprovider;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_findatime\local\slots;

/**
 * Privacy provider: users' availability, and the meetings they confirmed or cancelled.
 *
 * Availability belongs to the user and is exported and deleted. A meeting belongs to its group,
 * so deleting a user's data only removes their name from it (usermodified becomes 0).
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements metadataprovider, pluginprovider, userlistprovider {
    /**
     * Describe the personal data.
     *
     * @param collection $collection Collection to add to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('findatime_responses', [
            'userid' => 'privacy:metadata:findatime_responses:userid',
            'timecreated' => 'privacy:metadata:findatime_responses:timecreated',
            'timemodified' => 'privacy:metadata:findatime_responses:timemodified',
        ], 'privacy:metadata:findatime_responses');
        $collection->add_database_table('findatime_slots', [
            'responseid' => 'privacy:metadata:findatime_slots:responseid',
            'slotstart' => 'privacy:metadata:findatime_slots:slotstart',
            'status' => 'privacy:metadata:findatime_slots:status',
        ], 'privacy:metadata:findatime_slots');
        $collection->add_database_table('findatime_meetings', [
            'usermodified' => 'privacy:metadata:findatime_meetings:usermodified',
            'timestart' => 'privacy:metadata:findatime_meetings:timestart',
            'location' => 'privacy:metadata:findatime_meetings:location',
            'timemodified' => 'privacy:metadata:findatime_meetings:timemodified',
        ], 'privacy:metadata:findatime_meetings');
        $collection->add_subsystem_link('core_message', [], 'privacy:metadata:core_message');
        return $collection;
    }

    /**
     * Contexts holding data of a user.
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $base = "SELECT ctx.id
                   FROM {context} ctx
                   JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                   JOIN {modules} m ON m.id = cm.module AND m.name = 'findatime'
                   JOIN {findatime} f ON f.id = cm.instance";
        $params = ['contextlevel' => CONTEXT_MODULE, 'userid' => $userid];
        $contextlist->add_from_sql($base . " JOIN {findatime_responses} r ON r.findatimeid = f.id
                                            WHERE r.userid = :userid", $params);
        $contextlist->add_from_sql($base . " JOIN {findatime_meetings} fm ON fm.findatimeid = f.id
                                            WHERE fm.usermodified = :userid", $params);
        return $contextlist;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist List to fill.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }
        $params = ['cmid' => $context->instanceid, 'modname' => 'findatime'];
        $base = "FROM {course_modules} cm
                 JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                 JOIN {findatime} f ON f.id = cm.instance";
        $userlist->add_from_sql('userid', "SELECT r.userid $base
                 JOIN {findatime_responses} r ON r.findatimeid = f.id WHERE cm.id = :cmid", $params);
        $userlist->add_from_sql('usermodified', "SELECT fm.usermodified $base
                 JOIN {findatime_meetings} fm ON fm.findatimeid = f.id WHERE cm.id = :cmid AND fm.usermodified > 0", $params);
    }

    /**
     * The instance id behind a module context, or null when it is not a Find a time context.
     *
     * @param context $context Context.
     * @return int|null
     */
    protected static function instance_id(context $context): ?int {
        if (!$context instanceof context_module) {
            return null;
        }
        $cm = get_coursemodule_from_id('findatime', $context->instanceid);
        return $cm ? (int)$cm->instance : null;
    }

    /**
     * Export a user's data.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        $statusnames = [
            slots::STATUS_AVAILABLE => get_string('statusavailable', 'findatime'),
            slots::STATUS_IFNEEDBE => get_string('statusifneedbe', 'findatime'),
        ];
        foreach ($contextlist->get_contexts() as $context) {
            $findatimeid = self::instance_id($context);
            if ($findatimeid === null) {
                continue;
            }
            $data = helper::get_context_data($context, $contextlist->get_user());
            helper::export_context_files($context, $contextlist->get_user());

            $response = $DB->get_record('findatime_responses', ['findatimeid' => $findatimeid, 'userid' => $userid]);
            if ($response) {
                $availability = [];
                foreach ($DB->get_records('findatime_slots', ['responseid' => $response->id], 'slotstart') as $slot) {
                    $availability[] = [
                        'slotstart' => transform::datetime($slot->slotstart),
                        'status' => $statusnames[(int)$slot->status] ?? (string)$slot->status,
                    ];
                }
                $data->availability = [
                    'timecreated' => transform::datetime($response->timecreated),
                    'timemodified' => transform::datetime($response->timemodified),
                    'slots' => $availability,
                ];
            }

            $meetings = [];
            foreach ($DB->get_records('findatime_meetings', ['findatimeid' => $findatimeid, 'usermodified' => $userid]) as $m) {
                $meetings[] = [
                    'group' => $m->groupid ? groups_get_group_name($m->groupid) : '',
                    'timestart' => transform::datetime($m->timestart),
                    'duration' => (int)$m->duration,
                    'location' => (string)$m->location,
                    'status' => (int)$m->status === \mod_findatime\local\meetings::STATUS_CONFIRMED
                        ? get_string('privacy:confirmed', 'findatime') : get_string('privacy:cancelled', 'findatime'),
                    'timemodified' => transform::datetime($m->timemodified),
                ];
            }
            if ($meetings) {
                $data->meetingsconfirmedorcancelled = $meetings;
            }
            writer::with_context($context)->export_data([], $data);
        }
    }

    /**
     * Delete every user's data in a context.
     *
     * @param context $context Context.
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;
        $findatimeid = self::instance_id($context);
        if ($findatimeid === null) {
            return;
        }
        \mod_findatime\local\availability::delete_for_instance($findatimeid);
        $DB->set_field('findatime_meetings', 'usermodified', 0, ['findatimeid' => $findatimeid]);
    }

    /**
     * Delete one user's data in the approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $findatimeid = self::instance_id($context);
            if ($findatimeid !== null) {
                self::delete_users($findatimeid, [$userid]);
            }
        }
    }

    /**
     * Delete several users' data in one context.
     *
     * @param approved_userlist $userlist Approved users.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $findatimeid = self::instance_id($userlist->get_context());
        if ($findatimeid !== null && $userlist->get_userids()) {
            self::delete_users($findatimeid, $userlist->get_userids());
        }
    }

    /**
     * Delete availability of users and anonymise the meetings they confirmed or cancelled.
     *
     * @param int $findatimeid Instance id.
     * @param int[] $userids Users.
     */
    protected static function delete_users(int $findatimeid, array $userids): void {
        global $DB;
        foreach ($userids as $userid) {
            \mod_findatime\local\availability::delete_for_user($findatimeid, (int)$userid);
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['findatimeid'] = $findatimeid;
        $DB->set_field_select(
            'findatime_meetings',
            'usermodified',
            0,
            "findatimeid = :findatimeid AND usermodified $insql",
            $params
        );
    }
}
