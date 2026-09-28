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

use mod_findatime\local\access;
use mod_findatime\local\meetings;
use renderer_base;

/**
 * The meeting of one group: confirmed time and place, and the confirm/cancel controls.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class meeting_panel implements \renderable, \templatable {
    /** @var access Access rules. */
    protected $access;
    /** @var int Group id. */
    protected $groupid;
    /** @var int Viewing user id. */
    protected $userid;

    /**
     * Constructor.
     *
     * @param access $access Access rules of the activity.
     * @param int $groupid Group shown.
     * @param int $userid Viewing user.
     */
    public function __construct(access $access, int $groupid, int $userid) {
        $this->access = $access;
        $this->groupid = $groupid;
        $this->userid = $userid;
    }

    /**
     * Export for the template.
     *
     * @param renderer_base $output Renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $findatime = $this->access->get_findatime();
        $context = $this->access->get_context();
        $meeting = meetings::get_current($findatime->id, $this->groupid);
        $confirmed = $meeting && (int)$meeting->status === meetings::STATUS_CONFIRMED;
        $usertz = \core_date::get_user_timezone();
        $format = get_string('strftimedaydatetime', 'langconfig');

        $data = [
            'cmid' => $this->access->get_cm()->id,
            'groupid' => $this->groupid,
            'confirmed' => $confirmed,
            'cancelled' => $meeting && !$confirmed,
            'canconfirm' => $this->access->can_confirm($this->groupid, $this->userid),
            'autoconfirmpending' => false,
        ];
        if ($confirmed) {
            $data['time'] = userdate($meeting->timestart, $format, $usertz);
            $data['endtime'] = userdate(
                $meeting->timestart + $meeting->duration * MINSECS,
                get_string('strftimetime', 'langconfig'),
                $usertz
            );
            $data['duration'] = format_time((int)$meeting->duration * MINSECS);
            $data['location'] = format_text(
                (string)$meeting->location,
                FORMAT_MOODLE,
                ['context' => $context, 'para' => false]
            );
            $data['auto'] = (int)$meeting->source === meetings::SOURCE_AUTO;
            $data['confirmedby'] = meetings::user_name((int)$meeting->usermodified, $context);
            $data['calendarurl'] = (new \moodle_url(
                '/calendar/view.php',
                ['view' => 'day', 'time' => $meeting->timestart, 'course' => $findatime->course]
            ))->out(false);
        }
        if (!$meeting && !empty($findatime->autoconfirm) && empty($findatime->autoconfirmdone)) {
            $data['autoconfirmpending'] = true;
            $data['autoconfirmtime'] = userdate($findatime->autoconfirm, $format, $usertz);
        }
        return $data;
    }
}
