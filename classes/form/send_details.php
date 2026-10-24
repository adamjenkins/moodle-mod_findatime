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
use mod_findatime\local\meetings;
use moodle_url;

/**
 * Modal form sending the details of a confirmed meeting to the group, with an optional note.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_details extends \core_form\dynamic_form {
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
     * The group's confirmed meeting that has not ended yet.
     *
     * @return \stdClass|null
     */
    protected function get_meeting(): ?\stdClass {
        $meeting = meetings::get_current($this->get_access()->get_findatime()->id, $this->get_groupid());
        if (!$meeting || (int)$meeting->status !== meetings::STATUS_CONFIRMED || meetings::has_ended($meeting)) {
            return null;
        }
        return $meeting;
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
     * The same people who may confirm the group's meeting.
     */
    protected function check_access_for_dynamic_submission(): void {
        global $USER;
        require_capability('mod/findatime:view', $this->get_context_for_dynamic_submission());
        if (!$this->get_access()->can_confirm($this->get_groupid(), $USER->id)) {
            throw new \moodle_exception('errorcannotconfirm', 'findatime');
        }
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

        $meeting = $this->get_meeting();
        if (!$meeting) {
            $mform->addElement('static', 'nomeeting', '', get_string('errornomeeting', 'findatime'));
            return;
        }
        $mform->addElement(
            'static',
            'meetingtime',
            get_string('meetingtime', 'findatime'),
            s(userdate($meeting->timestart, get_string('strftimedaydatetime', 'langconfig')))
        );
        if ((string)$meeting->location !== '') {
            $mform->addElement('static', 'meetinglocation', get_string('location', 'findatime'), s($meeting->location));
        }
        $mform->addElement('textarea', 'note', get_string('messagetomembers', 'findatime'), ['rows' => 4, 'cols' => 50]);
        $mform->setType('note', PARAM_TEXT);
        $mform->addHelpButton('note', 'messagetomembers', 'findatime');
    }

    /**
     * Refuse when there is no upcoming meeting or the details were sent moments ago.
     *
     * @param array $data Submitted data.
     * @param array $files Files.
     * @return array Errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $meeting = $this->get_meeting();
        if (!$meeting) {
            $errors['note'] = get_string('errornomeeting', 'findatime');
        } else if ((int)$meeting->timenotified > time() - meetings::RESEND_INTERVAL) {
            $errors['note'] = get_string('errordetailsrecent', 'findatime', format_time(meetings::RESEND_INTERVAL));
        }
        if (\core_text::strlen((string)($data['note'] ?? '')) > 2000) {
            $errors['note'] = get_string('maximumchars', '', 2000);
        }
        return $errors;
    }

    /**
     * Send the details.
     *
     * @return array
     */
    public function process_dynamic_submission() {
        global $USER;
        $data = $this->get_data();
        $sent = meetings::send_details($this->get_access(), $this->get_groupid(), (int)$USER->id, (string)($data->note ?? ''));
        return ['sent' => $sent, 'notice' => get_string('detailssent', 'findatime', $sent)];
    }

    /**
     * Load the form.
     */
    public function set_data_for_dynamic_submission(): void {
        $this->set_data(['cmid' => $this->get_access()->get_cm()->id, 'groupid' => $this->get_groupid()]);
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
