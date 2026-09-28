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
 * English strings for mod_findatime.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['activitytimezone'] = 'Activity timezone';
$string['activitytimezone_help'] = 'The date range and the daily time window are defined in this timezone. Each participant sees the time grid converted to their own timezone.';
$string['allowifneedbe'] = 'Allow "if need be" answers';
$string['autoconfirm'] = 'Confirm automatically at';
$string['autoconfirm_help'] = 'If enabled, at this time every group that has no meeting yet gets the most popular time confirmed automatically: the time when the most members are available for the whole meeting, then the most members available at least "if need be", then the earliest. Groups where nobody has marked any availability are skipped. A meeting that was confirmed or cancelled by hand is never changed.';
$string['brush'] = 'Status to mark';
$string['cannotrespond'] = 'You cannot mark availability in this activity.';
$string['completiondetail:submit'] = 'Submit availability';
$string['completionsubmit'] = 'Participants must submit their availability';
$string['confirmation'] = 'Confirming a meeting';
$string['dateend'] = 'Last day';
$string['datestart'] = 'First day';
$string['dayend'] = 'Daily end time';
$string['daystart'] = 'Daily start time';
$string['daystart_help'] = 'Participants can mark availability between the daily start and end times on every day of the date range. The times are in the activity timezone.';
$string['duration'] = 'Meeting length';
$string['duration_help'] = 'How long the meeting will be. It must be a whole number of slots. The overlap view suggests start times when enough consecutive slots are free.';
$string['errorautoconfirmlate'] = 'The automatic confirmation must happen before the last possible meeting slot starts.';
$string['errordateorder'] = 'The last day must not be before the first day.';
$string['errordurationlong'] = 'The meeting is longer than the daily time window.';
$string['errordurationmultiple'] = 'The meeting length must be a whole number of slots.';
$string['errornogroup'] = 'You are not a member of any group in this activity, so you cannot mark your availability.';
$string['errortoomanydays'] = 'The date range can be at most {$a} days long.';
$string['errortoomanyslots'] = 'These settings would create {$a->count} slots; the maximum is {$a->max}. Use a longer slot size, a shorter date range or a shorter daily window.';
$string['errorwindoworder'] = 'The daily end time must be after the daily start time.';
$string['errorwindowshort'] = 'The daily time window is shorter than one slot.';
$string['eventavailabilitysubmitted'] = 'Availability submitted';
$string['eventdue'] = 'Mark your availability: {$a}';
$string['eventmeeting'] = 'Meeting: {$a}';
$string['findatime:addinstance'] = 'Add a new Find a time activity';
$string['findatime:confirm'] = 'Confirm or cancel a group meeting';
$string['findatime:respond'] = 'Mark own availability';
$string['findatime:view'] = 'View Find a time';
$string['findatime:viewreports'] = 'View the overview of all groups';
$string['gridhelp'] = 'Choose a status, then click or drag across the grid to mark the times. On a touch screen, tap a time. With the keyboard, move with the arrow keys and press Space to mark the current time; hold Shift while moving to mark as you go. Your changes are saved automatically.';
$string['invalidtimezone'] = 'Choose a valid timezone.';
$string['lastsaved'] = 'Last saved: {$a}';
$string['memberconfirm'] = 'Any group member may confirm';
$string['memberconfirm_help'] = 'If enabled, any member of a group who can mark availability may also confirm or cancel the meeting time for their own group. Otherwise only people with the "Confirm or cancel a group meeting" capability, such as teachers, can.';
$string['modulename'] = 'Find a time';
$string['modulename_help'] = 'The Find a time activity helps members of a group agree on a meeting time. Each member marks when they are available on a time grid, everyone in the group sees where their availability overlaps, and a teacher or group member confirms a time. The confirmed meeting appears in the group\'s calendar and members are notified.';
$string['modulenameplural'] = 'Find a time activities';
$string['needsjs'] = 'Marking availability needs JavaScript.';
$string['noslots'] = 'There are no times to choose from. Check the activity\'s dates and daily times.';
$string['paintmode'] = 'Drag to select';
$string['pluginadministration'] = 'Find a time administration';
$string['pluginname'] = 'Find a time';
$string['saveavailability'] = 'Save availability';
$string['savestatuserror'] = 'Your availability could not be saved.';
$string['savestatussaved'] = 'Saved.';
$string['savestatussaving'] = 'Saving...';
$string['savestatusunsaved'] = 'Unsaved changes.';
$string['slotsize'] = 'Slot size';
$string['statusavailable'] = 'Available';
$string['statusifneedbe'] = 'If need be';
$string['statusunavailable'] = 'Unavailable';
$string['time'] = 'Time';
$string['timezonenotice'] = 'Times are shown in your timezone, {$a->user}.';
$string['timezonenoticediffers'] = 'Times are shown in your timezone, {$a->user}. The activity\'s times were set in {$a->activity}.';
$string['timing'] = 'Dates and times';
$string['timingchangewarning'] = 'Participants have already marked their availability. If you change the dates, daily times, slot size or timezone, availability for times that are no longer on the grid will be ignored.';
$string['youravailability'] = 'Your availability';
