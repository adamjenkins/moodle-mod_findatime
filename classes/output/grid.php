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

use mod_findatime\local\availability;
use mod_findatime\local\slots;
use renderer_base;

/**
 * The current user's availability grid.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grid implements \renderable, \templatable {
    /** @var \stdClass Instance record. */
    protected $findatime;
    /** @var \cm_info Course module. */
    protected $cm;
    /** @var int User id. */
    protected $userid;

    /**
     * Constructor.
     *
     * @param \stdClass $findatime Instance record.
     * @param \cm_info $cm Course module.
     * @param int $userid The user whose grid this is.
     */
    public function __construct(\stdClass $findatime, \cm_info $cm, int $userid) {
        $this->findatime = $findatime;
        $this->cm = $cm;
        $this->userid = $userid;
    }

    /**
     * Status names for display.
     *
     * @return array status => label
     */
    public static function status_labels(): array {
        return [
            0 => get_string('statusunavailable', 'findatime'),
            slots::STATUS_AVAILABLE => get_string('statusavailable', 'findatime'),
            slots::STATUS_IFNEEDBE => get_string('statusifneedbe', 'findatime'),
        ];
    }

    /**
     * Export for the template.
     *
     * @param renderer_base $output Renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $slots = new slots($this->findatime);
        $statuses = availability::get_user_statuses($this->findatime->id, $this->userid);
        $labels = self::status_labels();
        $layout = $slots->layout(\core_date::get_user_timezone_object(), function (int $start) use ($statuses, $labels): array {
            $status = $statuses[$start] ?? 0;
            return ['status' => $status, 'statuslabel' => $labels[$status]];
        });
        $response = availability::get_response($this->findatime->id, $this->userid);
        return [
            'uniqid' => \html_writer::random_id('mod-findatime-grid'),
            'cmid' => $this->cm->id,
            'days' => $layout['days'],
            'rows' => helper::mark_hours($layout['rows']),
            'hasslots' => $slots->count() > 0,
            'allowifneedbe' => !empty($this->findatime->allowifneedbe),
            'lastsaved' => $response ? userdate($response->timemodified) : '',
        ];
    }
}
