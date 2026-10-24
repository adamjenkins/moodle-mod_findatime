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
 * Calendar events of an activity.
 *
 * Events are derived data: the instance and meeting records are authoritative and these
 * functions create, update or delete the events to match them.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class calendar_sync {
    /** @var string Event type of the "mark your availability" deadline. */
    public const EVENTTYPE_DUE = 'due';
    /** @var string Event type of a confirmed meeting. */
    public const EVENTTYPE_MEETING = 'meeting';

    /**
     * Load the calendar library.
     */
    protected static function require_lib(): void {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');
    }

    /**
     * Create, update or delete the instance's deadline event (the RSVP date, else the automatic confirmation).
     *
     * @param \stdClass $findatime Instance record.
     */
    public static function sync_instance(\stdClass $findatime): void {
        global $DB;
        self::require_lib();

        $existing = $DB->get_record('event', ['modulename' => 'findatime', 'instance' => $findatime->id,
            'eventtype' => self::EVENTTYPE_DUE], 'id', IGNORE_MULTIPLE);
        // Availability is due at the RSVP date; without one, before the automatic confirmation.
        $due = (int)($findatime->rsvpby ?? 0) ?: (int)$findatime->autoconfirm;
        if (!$due) {
            if ($existing) {
                \calendar_event::load($existing->id)->delete();
            }
            return;
        }
        $data = (object)[
            'name' => get_string('eventdue', 'findatime', $findatime->name),
            'description' => '',
            'format' => FORMAT_HTML,
            'courseid' => $findatime->course,
            'groupid' => 0,
            'userid' => 0,
            'modulename' => 'findatime',
            'instance' => $findatime->id,
            'eventtype' => self::EVENTTYPE_DUE,
            'type' => CALENDAR_EVENT_TYPE_ACTION,
            'timestart' => $due,
            'timesort' => $due,
            'timeduration' => 0,
            'visible' => instance_is_visible('findatime', $findatime),
        ];
        if ($existing) {
            \calendar_event::load($existing->id)->update($data, false);
        } else {
            \calendar_event::create($data, false);
        }
    }

    /**
     * Create, update or delete the calendar event of a meeting, and store its id on the meeting.
     *
     * @param \stdClass $meeting Meeting record (updated in place with the event id).
     * @param \stdClass $findatime Instance record.
     */
    public static function sync_meeting(\stdClass $meeting, \stdClass $findatime): void {
        global $DB;
        self::require_lib();

        $event = null;
        if (!empty($meeting->eventid) && $DB->record_exists('event', ['id' => $meeting->eventid])) {
            $event = \calendar_event::load($meeting->eventid);
        }
        if ((int)$meeting->status !== meetings::STATUS_CONFIRMED) {
            if ($event) {
                $event->delete();
            }
            if (!empty($meeting->eventid)) {
                $meeting->eventid = null;
                $DB->set_field('findatime_meetings', 'eventid', null, ['id' => $meeting->id]);
            }
            return;
        }
        $data = (object)[
            'name' => get_string('eventmeeting', 'findatime', $findatime->name),
            'description' => (string)$meeting->location,
            'format' => FORMAT_MOODLE,
            'courseid' => $findatime->course,
            'groupid' => (int)$meeting->groupid,
            'userid' => 0,
            'modulename' => 'findatime',
            'instance' => $findatime->id,
            'eventtype' => self::EVENTTYPE_MEETING,
            'type' => CALENDAR_EVENT_TYPE_STANDARD,
            'timestart' => $meeting->timestart,
            'timesort' => $meeting->timestart,
            'timeduration' => (int)$meeting->duration * MINSECS,
            'visible' => instance_is_visible('findatime', $findatime),
        ];
        if ($event) {
            $event->update($data, false);
        } else {
            // A copy kept when the activity was deleted comes back with a restored activity: replace it.
            $DB->delete_records('event', ['uuid' => self::kept_signature(
                $findatime->course,
                (int)$meeting->groupid,
                (int)$meeting->timestart
            ), 'modulename' => '']);
            $event = \calendar_event::create($data, false);
            $meeting->eventid = $event->id;
            $DB->set_field('findatime_meetings', 'eventid', $event->id, ['id' => $meeting->id]);
        }
    }

    /**
     * Keep the calendar events of meetings that have taken place when the activity is deleted.
     *
     * They become ordinary group events (course events without groups) that no longer belong to the
     * module, so core's deletion of the module's events leaves them alone. Each is tagged with a
     * signature in its uuid, so that restoring the activity (for example from the recycle bin)
     * replaces the kept copy instead of duplicating it.
     *
     * @param \stdClass $findatime Instance record.
     * @return int Number of events kept.
     */
    public static function keep_past_meetings(\stdClass $findatime): int {
        global $DB;
        $kept = 0;
        foreach ($DB->get_records('findatime_meetings', ['findatimeid' => $findatime->id]) as $meeting) {
            if ((int)$meeting->status !== meetings::STATUS_CONFIRMED || !meetings::has_ended($meeting)) {
                continue;
            }
            $event = !empty($meeting->eventid) ? $DB->get_record('event', ['id' => $meeting->eventid]) : null;
            if (!$event || $event->modulename !== 'findatime' || (int)$event->instance !== (int)$findatime->id) {
                continue;
            }
            $DB->update_record('event', (object)[
                'id' => $event->id,
                'modulename' => '',
                'instance' => 0,
                'component' => null,
                'eventtype' => $meeting->groupid ? 'group' : 'course',
                'uuid' => self::kept_signature($findatime->course, (int)$meeting->groupid, (int)$meeting->timestart),
                'timemodified' => time(),
            ]);
            $kept++;
        }
        return $kept;
    }

    /**
     * The uuid marking a kept meeting event.
     *
     * @param int $courseid Course id.
     * @param int $groupid Group id (0 = none).
     * @param int $timestart Meeting start.
     * @return string
     */
    public static function kept_signature(int $courseid, int $groupid, int $timestart): string {
        return 'mod_findatime-meeting-' . $courseid . '-' . $groupid . '-' . $timestart;
    }

    /**
     * Rebuild every event of an instance from its records.
     *
     * Deletes all of the instance's events first, so events carried over by a restore or left
     * behind by earlier code cannot be duplicated.
     *
     * @param \stdClass $findatime Instance record.
     */
    public static function rebuild(\stdClass $findatime): void {
        global $DB;
        self::require_lib();

        $DB->delete_records('event', ['modulename' => 'findatime', 'instance' => $findatime->id]);
        $DB->set_field('findatime_meetings', 'eventid', null, ['findatimeid' => $findatime->id]);
        self::sync_instance($findatime);
        foreach ($DB->get_records('findatime_meetings', ['findatimeid' => $findatime->id]) as $meeting) {
            $meeting->eventid = null;
            self::sync_meeting($meeting, $findatime);
        }
    }
}
