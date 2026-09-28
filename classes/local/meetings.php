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
 * Confirming and cancelling group meetings.
 *
 * One meeting row per group for now (a re-confirmation updates it in place); the table has no
 * unique key, so several meetings per group can be added later. Callers check permissions with
 * access::can_confirm() first; these functions only validate the data.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class meetings {
    /** @var int Meeting is confirmed. */
    public const STATUS_CONFIRMED = 1;
    /** @var int Meeting was cancelled. */
    public const STATUS_CANCELLED = 2;
    /** @var int Confirmed by a person. */
    public const SOURCE_MANUAL = 1;
    /** @var int Confirmed by the automatic confirmation task. */
    public const SOURCE_AUTO = 2;

    /**
     * The current meeting row of each group of an instance.
     *
     * @param int $findatimeid Instance id.
     * @return \stdClass[] Meeting records, the latest per group, indexed by id.
     */
    public static function get_for_instance(int $findatimeid): array {
        global $DB;
        $latest = [];
        foreach ($DB->get_records('findatime_meetings', ['findatimeid' => $findatimeid], 'id ASC') as $meeting) {
            $latest[(int)$meeting->groupid] = $meeting;
        }
        $result = [];
        foreach ($latest as $meeting) {
            $result[$meeting->id] = $meeting;
        }
        return $result;
    }

    /**
     * The current meeting row of a group, confirmed or cancelled.
     *
     * @param int $findatimeid Instance id.
     * @param int $groupid Group id.
     * @return \stdClass|null
     */
    public static function get_current(int $findatimeid, int $groupid): ?\stdClass {
        global $DB;
        $records = $DB->get_records(
            'findatime_meetings',
            ['findatimeid' => $findatimeid, 'groupid' => $groupid],
            'id DESC',
            '*',
            0,
            1
        );
        return $records ? reset($records) : null;
    }

    /**
     * Display name of a user, or an empty string for the system (0) or a deleted account.
     *
     * @param int $userid User id.
     * @param \context $context Context for the full-name capability check.
     * @return string
     */
    public static function user_name(int $userid, \context $context): string {
        if ($userid <= 0) {
            return '';
        }
        $user = \core_user::get_user($userid);
        if (!$user || !empty($user->deleted)) {
            return '';
        }
        return fullname($user, has_capability('moodle/site:viewfullnames', $context));
    }

    /**
     * Confirm (or re-confirm) the meeting of a group.
     *
     * @param access $access Access rules of the activity.
     * @param int $groupid Group id.
     * @param int $timestart Meeting start; must start a run of slots long enough for the meeting.
     * @param string $location Free-text location or URL.
     * @param int $actorid Who confirms (0 = the system).
     * @param int $source SOURCE_MANUAL or SOURCE_AUTO.
     * @return \stdClass The meeting record.
     * @throws \moodle_exception When the time is not a valid meeting start or is in the past.
     */
    public static function confirm(
        access $access,
        int $groupid,
        int $timestart,
        string $location,
        int $actorid,
        int $source = self::SOURCE_MANUAL
    ): \stdClass {
        global $DB;
        $findatime = $access->get_findatime();
        $slots = new slots($findatime);
        if ($slots->span($timestart, (int)$findatime->duration) === null) {
            throw new \moodle_exception('errornotameetingstart', 'findatime');
        }
        if ($timestart <= time()) {
            throw new \moodle_exception('errormeetingpast', 'findatime');
        }

        $now = time();
        $existing = self::get_current($findatime->id, $groupid);
        if ($existing && $source === self::SOURCE_AUTO) {
            // Someone decided while the automatic confirmation was choosing: their decision stands.
            throw new \moodle_exception('errorautoconfirmdecided', 'findatime');
        }
        $previous = ($existing && (int)$existing->status === self::STATUS_CONFIRMED) ? (int)$existing->timestart : 0;
        $meeting = $existing ?: (object)[
            'findatimeid' => $findatime->id,
            'groupid' => $groupid,
            'eventid' => null,
            'timecreated' => $now,
        ];
        $meeting->timestart = $timestart;
        $meeting->duration = (int)$findatime->duration;
        $meeting->location = trim($location);
        $meeting->status = self::STATUS_CONFIRMED;
        $meeting->source = $source;
        $meeting->usermodified = $actorid;
        $meeting->timemodified = $now;
        if (!empty($meeting->id)) {
            $DB->update_record('findatime_meetings', $meeting);
        } else {
            $meeting->id = $DB->insert_record('findatime_meetings', $meeting);
        }
        calendar_sync::sync_meeting($meeting, $findatime);

        $params = [
            'objectid' => $meeting->id,
            'context' => $access->get_context(),
            'other' => [
                'groupid' => $groupid,
                'timestart' => $timestart,
                'previoustimestart' => $previous,
                'auto' => $source === self::SOURCE_AUTO ? 1 : 0,
            ],
        ];
        $event = \mod_findatime\event\meeting_confirmed::create($params);
        $event->add_record_snapshot('findatime_meetings', $meeting);
        $event->trigger();

        self::notify($access, $meeting, 'meetingconfirmed', $actorid);
        return $meeting;
    }

    /**
     * Cancel the confirmed meeting of a group.
     *
     * @param access $access Access rules of the activity.
     * @param int $groupid Group id.
     * @param int $actorid Who cancels.
     * @return \stdClass The meeting record.
     * @throws \moodle_exception When the group has no confirmed meeting.
     */
    public static function cancel(access $access, int $groupid, int $actorid): \stdClass {
        global $DB;
        $findatime = $access->get_findatime();
        $meeting = self::get_current($findatime->id, $groupid);
        if (!$meeting || (int)$meeting->status !== self::STATUS_CONFIRMED) {
            throw new \moodle_exception('errornomeeting', 'findatime');
        }
        if (self::has_ended($meeting)) {
            throw new \moodle_exception('errormeetingended', 'findatime');
        }
        $meeting->status = self::STATUS_CANCELLED;
        $meeting->usermodified = $actorid;
        $meeting->timemodified = time();
        $DB->update_record('findatime_meetings', $meeting);
        calendar_sync::sync_meeting($meeting, $findatime);

        $event = \mod_findatime\event\meeting_cancelled::create([
            'objectid' => $meeting->id,
            'context' => $access->get_context(),
            'other' => ['groupid' => $groupid, 'timestart' => (int)$meeting->timestart],
        ]);
        $event->add_record_snapshot('findatime_meetings', $meeting);
        $event->trigger();

        self::notify($access, $meeting, 'meetingcancelled', $actorid);
        return $meeting;
    }

    /**
     * Whether a meeting is over.
     *
     * @param \stdClass $meeting Meeting record.
     * @return bool
     */
    public static function has_ended(\stdClass $meeting): bool {
        return (int)$meeting->timestart + (int)$meeting->duration * MINSECS <= time();
    }

    /**
     * Delete every meeting of a group (the group was deleted) and its calendar events.
     *
     * @param int $groupid Group id.
     */
    public static function delete_for_group(int $groupid): void {
        global $DB;
        if ($groupid <= 0) {
            return;
        }
        $meetings = $DB->get_records('findatime_meetings', ['groupid' => $groupid]);
        foreach ($meetings as $meeting) {
            if (!empty($meeting->eventid)) {
                $DB->delete_records('event', ['id' => $meeting->eventid, 'modulename' => 'findatime']);
            }
        }
        $DB->delete_records('findatime_meetings', ['groupid' => $groupid]);
    }

    /**
     * Notify the group's members (except the person who acted) through the Message API.
     *
     * Each message is written in the recipient's language, with the time in their timezone.
     *
     * @param access $access Access rules of the activity.
     * @param \stdClass $meeting Meeting record.
     * @param string $provider Message provider: meetingconfirmed or meetingcancelled.
     * @param int $actorid Who acted (0 = the system).
     */
    protected static function notify(access $access, \stdClass $meeting, string $provider, int $actorid): void {
        global $CFG;
        $findatime = $access->get_findatime();
        $cm = $access->get_cm();
        $context = $access->get_context();
        $members = $access->members((int)$meeting->groupid);
        unset($members[$actorid]);
        if (!$members) {
            return;
        }
        $from = $actorid > 0 ? \core_user::get_user($actorid) : \core_user::get_noreply_user();
        $url = new \moodle_url('/mod/findatime/view.php', ['id' => $cm->id, 'group' => $meeting->groupid]);
        // Plain text: the messages are FORMAT_PLAIN and the HTML version is escaped as a whole below.
        $groupname = $meeting->groupid ? access::group_name((string)groups_get_group_name($meeting->groupid), $context) : '';
        $activityname = format_string($findatime->name, true, ['context' => $context, 'escape' => false]);

        foreach (array_keys($members) as $userid) {
            $recipient = \core_user::get_user($userid);
            if (!$recipient || !empty($recipient->deleted) || !empty($recipient->suspended)) {
                continue;
            }
            $lang = !empty($recipient->lang) ? $recipient->lang : $CFG->lang;
            $previouslang = force_current_language($lang);
            try {
                $time = userdate(
                    $meeting->timestart,
                    get_string('strftimedaydatetime', 'langconfig'),
                    \core_date::get_user_timezone($recipient)
                );
                $a = (object)[
                    'name' => $activityname,
                    'group' => $groupname !== '' ? ' (' . $groupname . ')' : '',
                    'time' => $time,
                    'duration' => format_time((int)$meeting->duration * MINSECS),
                    'location' => (string)$meeting->location,
                    'by' => $actorid > 0 ? fullname($from) : get_string('theautomaticconfirmation', 'findatime'),
                    'url' => $url->out(false),
                ];
                $subject = get_string('message' . $provider . 'subject', 'findatime', $a);
                $body = get_string('message' . $provider . 'body', 'findatime', $a);
                if ($a->location !== '' && $provider === 'meetingconfirmed') {
                    $body .= "\n" . get_string('messagelocation', 'findatime', $a->location);
                }
                $body .= "\n\n" . $a->url;

                $message = new \core\message\message();
                $message->component = 'mod_findatime';
                $message->name = $provider;
                $message->userfrom = $from;
                $message->userto = $recipient;
                $message->subject = $subject;
                $message->fullmessage = $body;
                $message->fullmessageformat = FORMAT_PLAIN;
                $message->fullmessagehtml = text_to_html(s($body), false, false, true);
                $message->smallmessage = $subject;
                $message->notification = 1;
                $message->contexturl = $url->out(false);
                $message->contexturlname = $activityname;
                $message->courseid = $cm->course;
                message_send($message);
            } finally {
                force_current_language($previouslang);
            }
        }
    }
}
