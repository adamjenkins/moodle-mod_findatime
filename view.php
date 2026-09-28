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
 * Find a time activity page: the user's availability grid, the group overlap and the meeting.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'findatime');
$findatime = $DB->get_record('findatime', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/findatime:view', $context);

$PAGE->set_url('/mod/findatime/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($findatime->name));
$PAGE->set_heading(format_string($course->fullname));

findatime_view($findatime, $course, $cm, $context);

$access = new \mod_findatime\local\access($findatime, $cm, $context);

echo $OUTPUT->header();

echo html_writer::tag('p', s(\mod_findatime\output\helper::timezone_notice($findatime)), ['class' => 'mod-findatime-tznotice']);

// Teachers and other non-respondents only see the group overlap.
if (has_capability('mod/findatime:respond', $context)) {
    echo $OUTPUT->heading(get_string('youravailability', 'findatime'), 3);
    if ($access->can_respond($USER->id)) {
        $grid = new \mod_findatime\output\grid($findatime, $cm, $USER->id);
        echo $OUTPUT->render_from_template('mod_findatime/grid', $grid->export_for_template($OUTPUT));
    } else {
        echo $OUTPUT->notification(get_string('errornogroup', 'findatime'), \core\output\notification::NOTIFY_INFO);
    }
}

echo $OUTPUT->footer();
