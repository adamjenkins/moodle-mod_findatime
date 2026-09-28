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
 * List of Find a time activities in a course.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course);

$coursecontext = context_course::instance($course->id);
$event = \mod_findatime\event\course_module_instance_list_viewed::create(['context' => $coursecontext]);
$event->add_record_snapshot('course', $course);
$event->trigger();

$PAGE->set_url('/mod/findatime/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->shortname) . ': ' . get_string('modulenameplural', 'findatime'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');
$PAGE->navbar->add(get_string('modulenameplural', 'findatime'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'findatime'));

$instances = get_all_instances_in_course('findatime', $course);
if (!$instances) {
    notice(
        get_string('thereareno', 'moodle', get_string('modulenameplural', 'findatime')),
        new moodle_url('/course/view.php', ['id' => $course->id])
    );
}

$table = new html_table();
$table->head = [get_string('name'), get_string('moduleintro')];
foreach ($instances as $instance) {
    $attributes = $instance->visible ? [] : ['class' => 'dimmed'];
    $link = html_writer::link(
        new moodle_url('/mod/findatime/view.php', ['id' => $instance->coursemodule]),
        format_string($instance->name, true, ['context' => context_module::instance($instance->coursemodule)]),
        $attributes
    );
    $intro = format_module_intro('findatime', $instance, $instance->coursemodule);
    $table->data[] = [$link, $intro];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
