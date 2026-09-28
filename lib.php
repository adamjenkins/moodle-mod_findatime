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
 * Library of interface functions and constants for mod_findatime.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Return whether the plugin supports a feature.
 *
 * @param string $feature Constant representing the feature.
 * @return mixed True if the feature is supported, null if unknown.
 */
function findatime_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_COMPLETION_HAS_RULES:
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_COLLABORATION;
        default:
            return null;
    }
}

/**
 * Add a new instance.
 *
 * @param stdClass $data Form data.
 * @param mod_findatime_mod_form|null $mform The form.
 * @return int The new instance id.
 */
function findatime_add_instance($data, $mform = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->autoconfirmdone = 0;
    $data->id = $DB->insert_record('findatime', $data);

    // The course module's instance field is not set yet, so nothing here may look the module up.
    \mod_findatime\local\calendar_sync::sync_instance($DB->get_record('findatime', ['id' => $data->id]));

    if (!empty($data->completionexpected)) {
        \core_completion\api::update_completion_date_event(
            $data->coursemodule,
            'findatime',
            $data->id,
            $data->completionexpected
        );
    }
    return $data->id;
}

/**
 * Update an instance.
 *
 * @param stdClass $data Form data.
 * @param mod_findatime_mod_form|null $mform The form.
 * @return bool
 */
function findatime_update_instance($data, $mform = null) {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();
    $old = $DB->get_record('findatime', ['id' => $data->id], '*', MUST_EXIST);
    if ((int)$old->autoconfirm !== (int)($data->autoconfirm ?? 0)) {
        // A new deadline is a new decision: run the automatic confirmation again when it passes.
        $data->autoconfirmdone = 0;
    }
    $DB->update_record('findatime', $data);

    $findatime = $DB->get_record('findatime', ['id' => $data->id], '*', MUST_EXIST);
    \mod_findatime\local\calendar_sync::sync_instance($findatime);

    \core_completion\api::update_completion_date_event(
        $data->coursemodule,
        'findatime',
        $data->id,
        $data->completionexpected ?? null
    );
    return true;
}

/**
 * Delete an instance and all its data.
 *
 * @param int $id Instance id.
 * @return bool
 */
function findatime_delete_instance($id) {
    global $DB;

    if (!$findatime = $DB->get_record('findatime', ['id' => $id])) {
        return false;
    }
    \mod_findatime\local\availability::delete_for_instance($findatime->id);
    $DB->delete_records('findatime_meetings', ['findatimeid' => $findatime->id]);
    $DB->delete_records('event', ['modulename' => 'findatime', 'instance' => $findatime->id]);
    $DB->delete_records('findatime', ['id' => $findatime->id]);
    return true;
}

/**
 * Add the custom completion rules to the course module info.
 *
 * @param stdClass $coursemodule The course module record.
 * @return cached_cm_info|false
 */
function findatime_get_coursemodule_info($coursemodule) {
    global $DB;

    $findatime = $DB->get_record(
        'findatime',
        ['id' => $coursemodule->instance],
        'id, name, intro, introformat, completionsubmit'
    );
    if (!$findatime) {
        return false;
    }
    $info = new cached_cm_info();
    $info->name = $findatime->name;
    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('findatime', $findatime, $coursemodule->id, false);
    }
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completionsubmit'] = $findatime->completionsubmit;
    }
    return $info;
}

/**
 * Descriptions of the active custom completion rules, for the activity completion UI.
 *
 * @param cm_info|stdClass $cm Course module with customdata.
 * @return array
 */
function findatime_get_completion_active_rule_descriptions($cm) {
    if (empty($cm->customdata['customcompletionrules']) || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }
    $descriptions = [];
    foreach ($cm->customdata['customcompletionrules'] as $key => $val) {
        if ($key === 'completionsubmit' && !empty($val)) {
            $descriptions[] = get_string('completionsubmit', 'findatime');
        }
    }
    return $descriptions;
}

/**
 * Mark the activity viewed and trigger the viewed event.
 *
 * @param stdClass $findatime Instance record.
 * @param stdClass $course Course record.
 * @param cm_info|stdClass $cm Course module.
 * @param context_module $context Module context.
 */
function findatime_view($findatime, $course, $cm, $context) {
    $event = \mod_findatime\event\course_module_viewed::create([
        'objectid' => $findatime->id,
        'context' => $context,
    ]);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('findatime', $findatime);
    $event->trigger();

    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Add the overview page to the activity's navigation.
 *
 * @param settings_navigation $settingsnav Settings navigation.
 * @param navigation_node $node The activity's node.
 */
function findatime_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $node) {
    $cm = $settingsnav->get_page()->cm;
    if (!$cm || !has_capability('mod/findatime:viewreports', $cm->context)) {
        return;
    }
    $node->add(
        get_string('overview', 'findatime'),
        new moodle_url('/mod/findatime/overview.php', ['id' => $cm->id]),
        navigation_node::TYPE_SETTING,
        null,
        'mod_findatime_overview'
    );
}

/**
 * Rebuild the calendar events of one, a course's or all instances from their records.
 *
 * @param int $courseid Course id (0 = all courses).
 * @param int|stdClass|null $instance Instance id or record.
 * @param stdClass|cm_info|null $cm Course module (unused).
 * @return bool
 */
function findatime_refresh_events($courseid = 0, $instance = null, $cm = null) {
    global $DB;
    if ($instance) {
        $instances = [is_object($instance) ? $instance : $DB->get_record('findatime', ['id' => $instance], '*', MUST_EXIST)];
    } else if ($courseid) {
        $instances = $DB->get_records('findatime', ['course' => $courseid]);
    } else {
        $instances = $DB->get_records('findatime');
    }
    foreach ($instances as $findatime) {
        \mod_findatime\local\calendar_sync::rebuild($findatime);
    }
    return true;
}

/**
 * The action of the "mark your availability" timeline event; hidden once the user has responded.
 *
 * @param calendar_event $event The event.
 * @param \core_calendar\action_factory $factory Action factory.
 * @param int $userid User id (0 = current user).
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_findatime_core_calendar_provide_event_action(
    calendar_event $event,
    \core_calendar\action_factory $factory,
    int $userid = 0
) {
    global $DB, $USER;
    $userid = $userid ?: (int)$USER->id;
    if ($event->eventtype !== \mod_findatime\local\calendar_sync::EVENTTYPE_DUE) {
        return null;
    }
    $cm = get_fast_modinfo($event->courseid, $userid)->instances['findatime'][$event->instance] ?? null;
    if (!$cm || !$cm->uservisible) {
        return null;
    }
    $findatime = $DB->get_record('findatime', ['id' => $event->instance]);
    if (!$findatime) {
        return null;
    }
    $access = new \mod_findatime\local\access($findatime, $cm);
    if (!$access->can_respond($userid) || \mod_findatime\local\availability::has_responded($findatime->id, $userid)) {
        return null;
    }
    return $factory->create_instance(
        get_string('markavailability', 'findatime'),
        new moodle_url('/mod/findatime/view.php', ['id' => $cm->id]),
        1,
        $event->timestart > time()
    );
}
