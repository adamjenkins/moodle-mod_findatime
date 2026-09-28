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
$string['autoconfirmnone'] = 'Not confirmed: nobody was available for any remaining time when the automatic confirmation ran.';
$string['autoconfirmran'] = 'The automatic confirmation ran on {$a}.';
$string['autoconfirmwill'] = 'If no time is confirmed before {$a}, the most popular time will be confirmed automatically.';
$string['besttimes'] = 'Best times';
$string['brush'] = 'Status to mark';
$string['cancelmeeting'] = 'Cancel meeting';
$string['cancelmeetingconfirm'] = 'Cancel this meeting? Group members will be notified and the calendar event will be removed.';
$string['candidateavailable'] = '{$a->available} of {$a->members} available';
$string['candidateifneedbe'] = '{$a} more if need be';
$string['cannotrespond'] = 'You cannot mark availability in this activity.';
$string['changetime'] = 'Change time';
$string['choosegroup'] = 'Choose a group to see its availability.';
$string['completiondetail:submit'] = 'Submit availability';
$string['completionsubmit'] = 'Participants must submit their availability';
$string['confirmatime'] = 'Confirm a time';
$string['confirmation'] = 'Confirming a meeting';
$string['confirmedautomatically'] = 'Confirmed automatically as the most popular time.';
$string['confirmedby'] = 'Confirmed by {$a}.';
$string['confirmthistime'] = 'Confirm this time';
$string['dateend'] = 'Last day';
$string['datestart'] = 'First day';
$string['dayend'] = 'Daily end time';
$string['daystart'] = 'Daily start time';
$string['daystart_help'] = 'Participants can mark availability between the daily start and end times on every day of the date range. The times are in the activity timezone.';
$string['duration'] = 'Meeting length';
$string['duration_help'] = 'How long the meeting will be. It must be a whole number of slots. The overlap view suggests start times when enough consecutive slots are free.';
$string['errorautoconfirmdecided'] = 'A meeting time was decided for this group while the automatic confirmation was running.';
$string['errorautoconfirmlate'] = 'The automatic confirmation must happen before the last possible meeting slot starts.';
$string['errorcannotconfirm'] = 'You cannot confirm or cancel the meeting of this group.';
$string['errordateorder'] = 'The last day must not be before the first day.';
$string['errordurationlong'] = 'The meeting is longer than the daily time window.';
$string['errordurationmultiple'] = 'The meeting length must be a whole number of slots.';
$string['errorgroupnotvisible'] = 'You cannot see the availability of this group.';
$string['errormeetingended'] = 'This meeting has already taken place.';
$string['errormeetingpast'] = 'This time has already passed.';
$string['errornogroup'] = 'You are not a member of any group in this activity, so you cannot mark your availability.';
$string['errornomeeting'] = 'This group has no confirmed meeting.';
$string['errornotameetingstart'] = 'A meeting of this length cannot start at this time.';
$string['errortoomanydays'] = 'The date range can be at most {$a} days long.';
$string['errortoomanyslots'] = 'These settings would create {$a->count} slots; the maximum is {$a->max}. Use a longer slot size, a shorter date range or a shorter daily window.';
$string['errorwindoworder'] = 'The daily end time must be after the daily start time.';
$string['errorwindowshort'] = 'The daily time window is shorter than one slot.';
$string['eventavailabilitysubmitted'] = 'Availability submitted';
$string['eventdue'] = 'Mark your availability: {$a}';
$string['eventmeeting'] = 'Meeting: {$a}';
$string['eventmeetingcancelled'] = 'Meeting cancelled';
$string['eventmeetingconfirmed'] = 'Meeting confirmed';
$string['findatime:addinstance'] = 'Add a new Find a time activity';
$string['findatime:confirm'] = 'Confirm or cancel a group meeting';
$string['findatime:respond'] = 'Mark own availability';
$string['findatime:view'] = 'View Find a time';
$string['findatime:viewreports'] = 'View the overview of all groups';
$string['gridhelp'] = 'Choose a status, then click or drag across the grid to mark the times. On a touch screen, tap a time. With the keyboard, move with the arrow keys and press Space to mark the current time; hold Shift while moving to mark as you go. Your changes are saved automatically.';
$string['groupoverlap'] = 'Group overlap';
$string['groupoverlapof'] = 'Group overlap: {$a}';
$string['heatdetailempty'] = 'Point at or move to a time to see who is available.';
$string['heathelp'] = 'Darker times suit more members. The number is how many members are available; "+n" counts members available only if need be. Point at a time, or move with the arrow keys, to see names.';
$string['heatnobody'] = 'Nobody is available at this time.';
$string['heatsummary'] = '{$a->available} of {$a->members} available, {$a->ifneedbe} if need be';
$string['invalidtimezone'] = 'Choose a valid timezone.';
$string['lastsaved'] = 'Last saved: {$a}';
$string['location'] = 'Location or link';
$string['location_help'] = 'Where the meeting takes place: a room, an address or a link to an online meeting. It is shown to group members and in the calendar event.';
$string['markavailability'] = 'Mark availability';
$string['meeting'] = 'Meeting';
$string['meetingcancelled'] = 'The meeting was cancelled.';
$string['meetingconfirmednotice'] = 'The meeting time has been confirmed and group members have been notified.';
$string['meetingended'] = 'This meeting has taken place.';
$string['meetingsconfirmed'] = 'Meetings';
$string['meetingsconfirmedcount'] = '{$a->confirmed} of {$a->groups} confirmed';
$string['meetingtime'] = 'Meeting time';
$string['memberconfirm'] = 'Any group member may confirm';
$string['memberconfirm_help'] = 'If enabled, any member of a group who can mark availability may also confirm or cancel the meeting time for their own group. Otherwise only people with the "Confirm or cancel a group meeting" capability, such as teachers, can.';
$string['messagelocation'] = 'Location: {$a}';
$string['messagemeetingcancelledbody'] = '{$a->by} cancelled the meeting of {$a->name}{$a->group} that was planned for {$a->time}.';
$string['messagemeetingcancelledsubject'] = 'Meeting cancelled: {$a->name}';
$string['messagemeetingconfirmedbody'] = '{$a->by} confirmed the meeting of {$a->name}{$a->group}: {$a->time} ({$a->duration}).';
$string['messagemeetingconfirmedsubject'] = 'Meeting confirmed: {$a->name}';
$string['messageprovider:meetingcancelled'] = 'Group meeting cancelled';
$string['messageprovider:meetingconfirmed'] = 'Group meeting confirmed';
$string['modulename'] = 'Find a time';
$string['modulename_help'] = 'The Find a time activity helps members of a group agree on a meeting time. Each member marks when they are available on a time grid, everyone in the group sees where their availability overlaps, and a teacher or group member confirms a time. The confirmed meeting appears in the group\'s calendar and members are notified.';
$string['modulenameplural'] = 'Find a time activities';
$string['needsjs'] = 'Marking availability needs JavaScript.';
$string['nocandidates'] = 'No time suits anybody yet.';
$string['nofuturetimes'] = 'There are no future times left in this activity.';
$string['nogroups'] = 'This activity has no groups.';
$string['nogroupsvisible'] = 'You are not in any group of this activity.';
$string['noslots'] = 'There are no times to choose from. Check the activity\'s dates and daily times.';
$string['notconfirmed'] = 'No meeting time has been confirmed yet.';
$string['overview'] = 'Overview';
$string['paintmode'] = 'Drag to select';
$string['pendinglist'] = 'Not answered yet: {$a}';
$string['pluginadministration'] = 'Find a time administration';
$string['pluginname'] = 'Find a time';
$string['privacy:cancelled'] = 'Cancelled';
$string['privacy:confirmed'] = 'Confirmed';
$string['privacy:metadata:core_message'] = 'The activity sends notifications to group members when a meeting is confirmed or cancelled.';
$string['privacy:metadata:findatime_meetings'] = 'Meetings confirmed for groups; records who last confirmed or cancelled each one.';
$string['privacy:metadata:findatime_meetings:location'] = 'The location or link entered for the meeting.';
$string['privacy:metadata:findatime_meetings:timemodified'] = 'When the meeting was last confirmed or cancelled.';
$string['privacy:metadata:findatime_meetings:timestart'] = 'When the meeting starts.';
$string['privacy:metadata:findatime_meetings:usermodified'] = 'The user who last confirmed or cancelled the meeting.';
$string['privacy:metadata:findatime_responses'] = 'Which users have submitted their availability.';
$string['privacy:metadata:findatime_responses:timecreated'] = 'When the user first submitted their availability.';
$string['privacy:metadata:findatime_responses:timemodified'] = 'When the user last changed their availability.';
$string['privacy:metadata:findatime_responses:userid'] = 'The user who submitted availability.';
$string['privacy:metadata:findatime_slots'] = 'The times a user marked as available or available if need be.';
$string['privacy:metadata:findatime_slots:responseid'] = 'The response the time belongs to.';
$string['privacy:metadata:findatime_slots:slotstart'] = 'The start of the time slot.';
$string['privacy:metadata:findatime_slots:status'] = 'Whether the user is available, or available if need be.';
$string['resetdatesshifted'] = 'Dates shifted';
$string['resetmeetings'] = 'Delete confirmed meetings and their calendar events';
$string['resetresponses'] = 'Delete all availability';
$string['respondedcount'] = '{$a->responded} of {$a->members} have answered';
$string['responserate'] = 'Response rate';
$string['responses'] = 'Responses';
$string['saveavailability'] = 'Save availability';
$string['savestatuserror'] = 'Your availability could not be saved.';
$string['savestatussaved'] = 'Saved.';
$string['savestatussaving'] = 'Saving...';
$string['savestatusunsaved'] = 'Unsaved changes.';
$string['slotsize'] = 'Slot size';
$string['statusavailable'] = 'Available';
$string['statusifneedbe'] = 'If need be';
$string['statusunavailable'] = 'Unavailable';
$string['submitted'] = 'Availability submitted';
$string['taskautoconfirm'] = 'Confirm the most popular meeting times';
$string['theautomaticconfirmation'] = 'The automatic confirmation';
$string['time'] = 'Time';
$string['timeoption'] = '{$a->time}: {$a->available} of {$a->members} available, {$a->ifneedbe} if need be';
$string['timezonenotice'] = 'Times are shown in your timezone, {$a->user}.';
$string['timezonenoticediffers'] = 'Times are shown in your timezone, {$a->user}. The activity\'s times were set in {$a->activity}.';
$string['timing'] = 'Dates and times';
$string['timingchangewarning'] = 'Participants have already marked their availability. If you change the dates, daily times, slot size or timezone, availability for times that are no longer on the grid will be ignored.';
$string['ungroupedwarning'] = '{$a} participants are not in any group of this activity, so they cannot mark their availability.';
$string['viewgroup'] = 'View';
$string['viewincalendar'] = 'View in calendar';
$string['youravailability'] = 'Your availability';
