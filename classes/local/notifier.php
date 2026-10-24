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
 * Sends the activity's notifications through the Message API.
 *
 * Every message is composed separately for each recipient, in the recipient's language and with
 * times in the recipient's timezone. Messages are plain text; the HTML version is the escaped text.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notifier {
    /**
     * Send one message per recipient.
     *
     * @param access $access Access rules of the activity.
     * @param int[] $userids Recipients; deleted and suspended users are skipped.
     * @param string $provider Message provider name in db/messages.php.
     * @param int $fromid Sender (0 = the no-reply user).
     * @param \moodle_url $url Link to the page the message is about.
     * @param callable $compose function(\stdClass $recipient, string $timezone): array returning [subject, body],
     *     called while the recipient's language is active.
     * @return int Number of messages sent.
     */
    public static function send(
        access $access,
        array $userids,
        string $provider,
        int $fromid,
        \moodle_url $url,
        callable $compose
    ): int {
        global $CFG;
        $context = $access->get_context();
        $from = $fromid > 0 ? \core_user::get_user($fromid) : \core_user::get_noreply_user();
        $activityname = self::activity_name($access);
        $sent = 0;
        foreach (array_unique(array_map('intval', $userids)) as $userid) {
            $recipient = \core_user::get_user($userid);
            if (!$recipient || !empty($recipient->deleted) || !empty($recipient->suspended)) {
                continue;
            }
            $lang = !empty($recipient->lang) ? $recipient->lang : $CFG->lang;
            $previouslang = force_current_language($lang);
            try {
                [$subject, $body] = $compose($recipient, \core_date::get_user_timezone($recipient));
                $body .= "\n\n" . $url->out(false);

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
                $message->courseid = $access->get_cm()->course;
                if (message_send($message)) {
                    $sent++;
                }
            } finally {
                force_current_language($previouslang);
            }
        }
        return $sent;
    }

    /**
     * The activity name as plain text (the messages are plain text).
     *
     * @param access $access Access rules of the activity.
     * @return string
     */
    public static function activity_name(access $access): string {
        return format_string($access->get_findatime()->name, true, ['context' => $access->get_context(), 'escape' => false]);
    }

    /**
     * A group name for messages: " (Name)", or an empty string without a group.
     *
     * @param access $access Access rules of the activity.
     * @param int $groupid Group id (0 = none).
     * @return string
     */
    public static function group_suffix(access $access, int $groupid): string {
        if (!$groupid) {
            return '';
        }
        return ' (' . access::group_name((string)groups_get_group_name($groupid), $access->get_context()) . ')';
    }
}
