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

namespace mod_findatime\form;

use context;
use mod_findatime\local\access;
use mod_findatime\local\availability;
use mod_findatime\local\meetings;
use mod_findatime\local\overlap;
use mod_findatime\local\slots;
use moodle_url;

/**
 * Modal form confirming (or changing) the meeting time of a group.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class confirm_meeting extends \core_form\dynamic_form {
    /** @var access|null Access rules, resolved from the submitted course module. */
    protected $access = null;

    /**
     * Resolve the activity from the form's cmid.
     *
     * @return access
     */
    protected function get_access(): access {
        global $DB;
        if ($this->access === null) {
            $cmid = $this->optional_param('cmid', 0, PARAM_INT);
            [, $cm] = get_course_and_cm_from_cmid($cmid, 'findatime');
            $findatime = $DB->get_record('findatime', ['id' => $cm->instance], '*', MUST_EXIST);
            $this->access = new access($findatime, $cm);
        }
        return $this->access;
    }

    /**
     * The group the form is for.
     *
     * @return int
     */
    protected function get_groupid(): int {
        return $this->optional_param('groupid', 0, PARAM_INT);
    }

    /**
     * Context of the submission.
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        return $this->get_access()->get_context();
    }

    /**
     * Only people who may confirm for this group.
     */
    protected function check_access_for_dynamic_submission(): void {
        global $USER;
        $context = $this->get_context_for_dynamic_submission();
        require_capability('mod/findatime:view', $context);
        if (!$this->get_access()->can_confirm($this->get_groupid(), $USER->id)) {
            throw new \moodle_exception('errorcannotconfirm', 'findatime');
        }
    }

    /**
     * Every future meeting start, labelled with how many members can come.
     *
     * @return array timestamp => label
     */
    protected function time_options(): array {
        $access = $this->get_access();
        $findatime = $access->get_findatime();
        $slots = new slots($findatime);
        $members = $access->members($this->get_groupid());
        $overlap = new overlap($slots, array_keys($members), availability::get_statuses($findatime->id, array_keys($members)));
        $counts = [];
        foreach ($overlap->candidates((int)$findatime->duration, 0, 0) as $candidate) {
            $counts[$candidate['timestart']] = $candidate;
        }
        $format = get_string('strftimedaydatetime', 'langconfig');
        $options = [];
        $now = time();
        foreach ($slots->get_starts() as $start) {
            if ($start <= $now || $slots->span($start, (int)$findatime->duration) === null) {
                continue;
            }
            $a = [
                'time' => userdate($start, $format),
                'available' => count($counts[$start]['available'] ?? []),
                'ifneedbe' => count($counts[$start]['ifneedbe'] ?? []),
                'members' => count($members),
            ];
            $options[$start] = get_string('timeoption', 'findatime', $a);
        }
        return $options;
    }

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
        $mform->addElement('hidden', 'groupid');
        $mform->setType('groupid', PARAM_INT);

        $options = $this->time_options();
        if (!$options) {
            $mform->addElement('static', 'notimes', '', get_string('nofuturetimes', 'findatime'));
            return;
        }
        $mform->addElement('select', 'timestart', get_string('meetingtime', 'findatime'), $options);
        $mform->setType('timestart', PARAM_INT);
        $mform->addElement(
            'static',
            'durationinfo',
            get_string('duration', 'findatime'),
            format_time((int)$this->get_access()->get_findatime()->duration * MINSECS)
        );
        $mform->addElement('text', 'location', get_string('location', 'findatime'), ['size' => 50, 'maxlength' => 1333]);
        $mform->setType('location', PARAM_TEXT);
        $mform->addHelpButton('location', 'location', 'findatime');
        $mform->addRule('location', get_string('maximumchars', '', 1333), 'maxlength', 1333, 'client');
    }

    /**
     * Validate the chosen time against the slot set.
     *
     * @param array $data Submitted data.
     * @param array $files Files.
     * @return array Errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $findatime = $this->get_access()->get_findatime();
        $timestart = (int)($data['timestart'] ?? 0);
        if ((new slots($findatime))->span($timestart, (int)$findatime->duration) === null) {
            $errors['timestart'] = get_string('errornotameetingstart', 'findatime');
        } else if ($timestart <= time()) {
            $errors['timestart'] = get_string('errormeetingpast', 'findatime');
        }
        if (\core_text::strlen((string)($data['location'] ?? '')) > 1333) {
            $errors['location'] = get_string('maximumchars', '', 1333);
        }
        return $errors;
    }

    /**
     * Confirm the meeting and return the refreshed meeting panel.
     *
     * @return array
     */
    public function process_dynamic_submission() {
        global $USER, $PAGE;
        $data = $this->get_data();
        $access = $this->get_access();
        meetings::confirm($access, $this->get_groupid(), (int)$data->timestart, (string)$data->location, (int)$USER->id);
        $panel = new \mod_findatime\output\meeting_panel($access, $this->get_groupid(), (int)$USER->id);
        $output = $PAGE->get_renderer('core');
        return ['panelhtml' => $output->render_from_template(
            'mod_findatime/meeting_panel',
            $panel->export_for_template($output)
        )];
    }

    /**
     * Load the form: group, suggested time and the current location.
     */
    public function set_data_for_dynamic_submission(): void {
        $access = $this->get_access();
        $groupid = $this->get_groupid();
        $data = ['cmid' => $access->get_cm()->id, 'groupid' => $groupid];
        $meeting = meetings::get_current($access->get_findatime()->id, $groupid);
        $timestart = $this->optional_param('timestart', 0, PARAM_INT);
        if (!$timestart && $meeting && (int)$meeting->status === meetings::STATUS_CONFIRMED) {
            $timestart = (int)$meeting->timestart;
        }
        if ($timestart) {
            $data['timestart'] = $timestart;
        }
        if ($meeting) {
            $data['location'] = $meeting->location;
        }
        $this->set_data($data);
    }

    /**
     * Page the form belongs to.
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/mod/findatime/view.php', ['id' => $this->get_access()->get_cm()->id,
            'group' => $this->get_groupid()]);
    }
}
