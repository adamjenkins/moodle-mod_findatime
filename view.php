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
 * Find a time activity page.
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

findatime_view($findatime, $course, $cm, $context);

$PAGE->set_url('/mod/findatime/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($findatime->name));
$PAGE->set_heading(format_string($course->fullname));

$slots = new \mod_findatime\local\slots($findatime);

echo $OUTPUT->header();
echo html_writer::tag('p', s($slots->count() . ' slots, ' . $findatime->timezone));
echo $OUTPUT->footer();
