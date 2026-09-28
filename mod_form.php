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
 * Activity settings form for mod_findatime.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

use mod_findatime\local\slots;

/**
 * Activity settings form.
 *
 * The date selectors work in the editing user's timezone, while the activity stores its dates as
 * midnights in its own reference timezone; data_preprocessing() and data_postprocessing() convert
 * between the two by civil date, so "5 October" stays 5 October whichever zones are involved.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_findatime_mod_form extends moodleform_mod {
    /**
     * Form definition.
     */
    public function definition() {
        global $DB;
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $this->standard_intro_elements();

        $mform->addElement('header', 'timinghdr', get_string('timing', 'findatime'));
        $mform->setExpanded('timinghdr');

        if (
            !empty($this->_instance) &&
                $DB->record_exists('findatime_responses', ['findatimeid' => $this->_instance])
        ) {
            $mform->addElement(
                'static',
                'responseswarning',
                '',
                html_writer::div(get_string('timingchangewarning', 'findatime'), 'alert alert-warning')
            );
        }

        $timezones = core_date::get_list_of_timezones(null, false);
        $mform->addElement('select', 'timezone', get_string('activitytimezone', 'findatime'), $timezones);
        $mform->setDefault('timezone', core_date::get_user_timezone());
        $mform->addHelpButton('timezone', 'activitytimezone', 'findatime');

        $mform->addElement('date_selector', 'datestart', get_string('datestart', 'findatime'));
        $mform->setDefault('datestart', usergetmidnight(time() + DAYSECS));
        $mform->addElement('date_selector', 'dateend', get_string('dateend', 'findatime'));
        $mform->setDefault('dateend', usergetmidnight(time() + 7 * DAYSECS));

        $mform->addElement('select', 'daystartmins', get_string('daystart', 'findatime'), self::time_options(0, 1425));
        $mform->setDefault('daystartmins', 540);
        $mform->addElement('select', 'dayendmins', get_string('dayend', 'findatime'), self::time_options(15, 1440));
        $mform->setDefault('dayendmins', 1020);
        $mform->addHelpButton('daystartmins', 'daystart', 'findatime');

        $sizes = [];
        foreach (slots::SLOT_SIZES as $size) {
            $sizes[$size] = get_string('numminutes', 'moodle', $size);
        }
        $mform->addElement('select', 'slotsize', get_string('slotsize', 'findatime'), $sizes);
        $mform->setDefault('slotsize', 30);

        $durations = [];
        for ($m = 15; $m <= 480; $m += 15) {
            $durations[$m] = format_time($m * MINSECS);
        }
        $mform->addElement('select', 'duration', get_string('duration', 'findatime'), $durations);
        $mform->setDefault('duration', 60);
        $mform->addHelpButton('duration', 'duration', 'findatime');

        $mform->addElement('advcheckbox', 'allowifneedbe', get_string('allowifneedbe', 'findatime'));
        $mform->setDefault('allowifneedbe', 1);

        $mform->addElement('header', 'confirmationhdr', get_string('confirmation', 'findatime'));
        $mform->addElement('advcheckbox', 'memberconfirm', get_string('memberconfirm', 'findatime'));
        $mform->addHelpButton('memberconfirm', 'memberconfirm', 'findatime');
        $mform->addElement(
            'date_time_selector',
            'autoconfirm',
            get_string('autoconfirm', 'findatime'),
            ['optional' => true]
        );
        $mform->addHelpButton('autoconfirm', 'autoconfirm', 'findatime');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Options for a time-of-day select, in 15 minute steps.
     *
     * @param int $from First option, minutes after midnight.
     * @param int $to Last option, minutes after midnight.
     * @return array minutes => "HH:MM"
     */
    protected static function time_options(int $from, int $to): array {
        $options = [];
        for ($m = $from; $m <= $to; $m += 15) {
            $options[$m] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        }
        return $options;
    }

    /**
     * Convert stored reference-timezone dates to the editing user's timezone for the selectors.
     *
     * @param array $defaultvalues Values being loaded into the form.
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);
        if (!empty($defaultvalues['timezone']) && !empty($defaultvalues['datestart'])) {
            $reftz = slots::timezone($defaultvalues['timezone']);
            $usertz = core_date::get_user_timezone_object();
            $defaultvalues['datestart'] = slots::convert_civil_midnight((int)$defaultvalues['datestart'], $reftz, $usertz);
            $defaultvalues['dateend'] = slots::convert_civil_midnight((int)$defaultvalues['dateend'], $reftz, $usertz);
        }
    }

    /**
     * Convert the selectors' dates to midnights in the chosen reference timezone.
     *
     * @param stdClass $data Submitted data.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        $usertz = core_date::get_user_timezone_object();
        $reftz = slots::timezone($data->timezone);
        $data->datestart = slots::convert_civil_midnight((int)$data->datestart, $usertz, $reftz);
        $data->dateend = slots::convert_civil_midnight((int)$data->dateend, $usertz, $reftz);
        if (empty($data->autoconfirm)) {
            $data->autoconfirm = 0;
        }
        if (!empty($data->completionunlocked)) {
            $suffix = $this->get_suffix();
            if (empty($data->{'completionsubmit' . $suffix})) {
                $data->{'completionsubmit' . $suffix} = 0;
            }
        }
    }

    /**
     * Validate the timing settings.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!array_key_exists($data['timezone'], core_date::get_list_of_timezones(null, false))) {
            $errors['timezone'] = get_string('invalidtimezone', 'findatime');
            return $errors;
        }
        $usertz = core_date::get_user_timezone_object();
        $reftz = slots::timezone($data['timezone']);
        $start = slots::convert_civil_midnight((int)$data['datestart'], $usertz, $reftz);
        $end = slots::convert_civil_midnight((int)$data['dateend'], $usertz, $reftz);
        $days = (int)round(($end - $start) / DAYSECS) + 1;

        if ($end < $start) {
            $errors['dateend'] = get_string('errordateorder', 'findatime');
        } else if ($days > slots::MAX_DAYS) {
            $errors['dateend'] = get_string('errortoomanydays', 'findatime', slots::MAX_DAYS);
        }
        $daystart = (int)$data['daystartmins'];
        $dayend = (int)$data['dayendmins'];
        $slotsize = (int)$data['slotsize'];
        $duration = (int)$data['duration'];
        if (!in_array($slotsize, slots::SLOT_SIZES, true)) {
            $errors['slotsize'] = get_string('required');
        }
        if ($dayend <= $daystart) {
            $errors['dayendmins'] = get_string('errorwindoworder', 'findatime');
        } else if ($slotsize && ($dayend - $daystart) < $slotsize) {
            $errors['dayendmins'] = get_string('errorwindowshort', 'findatime');
        }
        if ($slotsize && $duration % $slotsize !== 0) {
            $errors['duration'] = get_string('errordurationmultiple', 'findatime');
        } else if ($duration > $dayend - $daystart) {
            $errors['duration'] = get_string('errordurationlong', 'findatime');
        }
        if ($errors) {
            return $errors;
        }

        $record = (object)[
            'timezone' => $data['timezone'],
            'datestart' => $start,
            'dateend' => $end,
            'daystartmins' => $daystart,
            'dayendmins' => $dayend,
            'slotsize' => $slotsize,
        ];
        $slots = new slots($record);
        if ($slots->count() > slots::MAX_SLOTS) {
            $errors['slotsize'] = get_string(
                'errortoomanyslots',
                'findatime',
                ['count' => $slots->count(), 'max' => slots::MAX_SLOTS]
            );
        }
        if (!empty($data['autoconfirm']) && $slots->count()) {
            $starts = $slots->get_starts();
            if ((int)$data['autoconfirm'] >= end($starts)) {
                $errors['autoconfirm'] = get_string('errorautoconfirmlate', 'findatime');
            }
        }
        return $errors;
    }

    /**
     * Add the custom completion rule.
     *
     * @return array Element names.
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        $element = 'completionsubmit' . $this->get_suffix();
        $mform->addElement('checkbox', $element, '', get_string('completionsubmit', 'findatime'));
        $mform->setDefault($element, 1);
        return [$element];
    }

    /**
     * Whether the custom completion rule is enabled.
     *
     * @param array $data Submitted data.
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return !empty($data['completionsubmit' . $this->get_suffix()]);
    }
}
