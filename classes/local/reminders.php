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
 * Reminders, before the RSVP date, to members who have not marked their availability.
 *
 * An activity stores the chosen offsets (minutes before the RSVP date) and the offsets already
 * processed. When several offsets have passed by the time the task runs (an activity created close
 * to its RSVP date, or a slow cron), only one reminder is sent for all of them.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reminders {
    /** @var int[] Offsets offered, in minutes before the RSVP date: 1 hour, 1, 2 and 3 days, 1 week. */
    public const OFFSETS = [60, 1440, 2880, 4320, 10080];

    /**
     * Options for the settings form.
     *
     * @return array minutes => label
     */
    public static function offset_options(): array {
        $options = [];
        foreach (self::OFFSETS as $minutes) {
            $options[$minutes] = get_string('reminderbefore', 'findatime', format_time($minutes * MINSECS));
        }
        return $options;
    }

    /**
     * Parse a stored list of offsets, keeping only the offered ones.
     *
     * @param string|null $value Comma-separated minutes.
     * @return int[] Largest first.
     */
    public static function parse(?string $value): array {
        $offsets = array_intersect(array_map('intval', explode(',', (string)$value)), self::OFFSETS);
        $offsets = array_values(array_unique($offsets));
        rsort($offsets);
        return $offsets;
    }

    /**
     * Store a list of offsets.
     *
     * @param array $offsets Minutes.
     * @return string Comma-separated minutes, largest first.
     */
    public static function format(array $offsets): string {
        return implode(',', self::parse(implode(',', $offsets)));
    }

    /**
     * The chosen offsets whose time has come and that have not been processed.
     *
     * @param \stdClass $findatime Instance record.
     * @param int $now Current time.
     * @return int[]
     */
    public static function due(\stdClass $findatime, int $now): array {
        $rsvpby = (int)$findatime->rsvpby;
        if (!$rsvpby || $rsvpby <= $now) {
            return [];
        }
        $sent = self::parse($findatime->reminderssent ?? '');
        $due = [];
        foreach (self::parse($findatime->reminders ?? '') as $minutes) {
            if (!in_array($minutes, $sent, true) && $rsvpby - $minutes * MINSECS <= $now) {
                $due[] = $minutes;
            }
        }
        return $due;
    }

    /**
     * The members who may respond and have not done so (in every group of the activity).
     *
     * @param access $access Access rules of the activity.
     * @return int[] User ids.
     */
    public static function non_respondents(access $access): array {
        $members = [];
        foreach (array_keys($access->all_groups()) as $groupid) {
            $members += $access->members($groupid);
        }
        if (!$members) {
            return [];
        }
        $responded = availability::get_statuses($access->get_findatime()->id, array_keys($members));
        return array_values(array_diff(array_keys($members), array_keys($responded)));
    }

    /**
     * Send the due reminder of one activity, and record the due offsets as processed.
     *
     * @param access $access Access rules of the activity.
     * @param int $now Current time.
     * @return int Number of messages sent.
     */
    public static function process(access $access, int $now): int {
        global $DB;
        $findatime = $access->get_findatime();
        $due = self::due($findatime, $now);
        if (!$due) {
            return 0;
        }
        $sent = 0;
        $recipients = self::non_respondents($access);
        if ($recipients) {
            $url = new \moodle_url('/mod/findatime/view.php', ['id' => $access->get_cm()->id]);
            $activityname = notifier::activity_name($access);
            $rsvpby = (int)$findatime->rsvpby;
            $sent = notifier::send(
                $access,
                $recipients,
                'rsvpreminder',
                0,
                $url,
                function (\stdClass $recipient, string $timezone) use ($activityname, $rsvpby, $now): array {
                    $a = (object)[
                        'name' => $activityname,
                        'time' => userdate($rsvpby, get_string('strftimedaydatetime', 'langconfig'), $timezone),
                        'left' => format_time($rsvpby - $now),
                    ];
                    return [
                        get_string('messagersvpremindersubject', 'findatime', $a),
                        get_string('messagersvpreminderbody', 'findatime', $a),
                    ];
                }
            );
        }
        $findatime->reminderssent = self::format(array_merge(self::parse($findatime->reminderssent ?? ''), $due));
        $DB->set_field('findatime', 'reminderssent', $findatime->reminderssent, ['id' => $findatime->id]);
        return $sent;
    }
}
