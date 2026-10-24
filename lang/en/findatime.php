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
$string['detailssent'] = 'The meeting details were sent to {$a} group members.';
$string['duration'] = 'Meeting length';
$string['duration_help'] = 'How long the meeting will be. It must be a whole number of slots. The overlap view suggests start times when enough consecutive slots are free.';
$string['errorautoconfirmbeforersvp'] = 'The automatic confirmation cannot happen before the RSVP date.';
$string['errorautoconfirmdecided'] = 'A meeting time was decided for this group while the automatic confirmation was running.';
$string['errorautoconfirmlate'] = 'The automatic confirmation must happen before the last possible meeting slot starts.';
$string['errorcannotconfirm'] = 'You cannot confirm or cancel the meeting of this group.';
$string['errordateorder'] = 'The last day must not be before the first day.';
$string['errordetailsrecent'] = 'The meeting details were sent less than {$a} ago. Please wait before sending them again.';
$string['errordurationlong'] = 'The meeting is longer than the daily time window.';
$string['errordurationmultiple'] = 'The meeting length must be a whole number of slots.';
$string['errorgroupnotvisible'] = 'You cannot see the availability of this group.';
$string['errormeetingended'] = 'This meeting has already taken place.';
$string['errormeetingpast'] = 'This time has already passed.';
$string['errornogroup'] = 'You are not a member of any group in this activity, so you cannot mark your availability.';
$string['errornomeeting'] = 'This group has no confirmed meeting.';
$string['errornotameetingstart'] = 'A meeting of this length cannot start at this time.';
$string['errorrsvpclosed'] = 'The RSVP date has passed, so availability can no longer be changed.';
$string['errorrsvplate'] = 'The RSVP date must be before the last possible meeting slot starts.';
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
$string['greyrequired'] = 'Grey out times that necessary participants cannot make';
$string['greyrequired_help'] = 'If enabled, once a necessary participant has marked their availability, the times they marked as unavailable are greyed out for the rest of their group: on the overlap map, and in the members\' own grids, where they can no longer be marked.';
$string['gridhelp'] = 'Choose a status, then click or drag across the grid to mark the times. On a touch screen, tap a time. With the keyboard, move with the arrow keys and press Space to mark the current time; hold Shift while moving to mark as you go. Your changes are saved automatically.';
$string['groupoverlap'] = 'Group overlap';
$string['groupoverlapof'] = 'Group overlap: {$a}';
$string['heatdetailempty'] = 'Point at or move to a time to see who is available.';
$string['heathelp'] = 'Red times suit nobody, blue times suit everybody. The number is how many members are available; "+n" counts members available only if need be. Point at a time, or move with the arrow keys, to see names; click a time (or press Enter) to keep its details shown.';
$string['heatnobody'] = 'Nobody is available at this time.';
$string['heatpin'] = 'Click this time to keep its details shown.';
$string['heatsummary'] = '{$a->available} of {$a->members} available, {$a->ifneedbe} if need be';
$string['heatunpin'] = 'Kept shown. Click this time again to release it.';
$string['invalidtimezone'] = 'Choose a valid timezone.';
$string['lastsaved'] = 'Last saved: {$a}';
$string['legend'] = 'Colour key';
$string['legendblocked'] = 'A necessary participant cannot come';
$string['legendlevel1'] = 'Nobody';
$string['legendlevel2'] = 'Under a quarter';
$string['legendlevel3'] = 'A quarter to half';
$string['legendlevel4'] = 'Half to three quarters';
$string['legendlevel5'] = 'Over three quarters';
$string['legendlevel6'] = 'Everybody';
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
$string['messagemeetingdetailsbody'] = '{$a->by} sent the details of the meeting of {$a->name}{$a->group}: {$a->time} ({$a->duration}).';
$string['messagemeetingdetailssubject'] = 'Meeting details: {$a->name}';
$string['messagenote'] = 'Message from {$a}:';
$string['messageprovider:meetingcancelled'] = 'Group meeting cancelled';
$string['messageprovider:meetingconfirmed'] = 'Group meeting confirmed';
$string['messageprovider:meetingdetails'] = 'Group meeting details';
$string['messageprovider:rsvpreminder'] = 'Reminder to mark availability before the RSVP date';
$string['messagersvpreminderbody'] = 'Please mark when you are available for {$a->name} by {$a->time} ({$a->left} from now). After that, your availability can no longer be changed and the meeting time will be set without it.';
$string['messagersvpremindersubject'] = 'Reminder: mark your availability for {$a->name}';
$string['messagetomembers'] = 'Message to group members';
$string['messagetomembers_help'] = 'An optional note sent with the meeting details, for example what to prepare or how to join.';
$string['modulename'] = 'Find a time';
$string['modulename_help'] = 'The Find a time activity helps people agree on a meeting time. Everyone taking part marks when they are available on a time grid and sees where their availability overlaps, and a teacher (or, if allowed, a participant) confirms a time. The confirmed meeting appears in the calendar and everyone is notified.

Use it for a whole course, for group work (with separate groups, each group finds its own time and sees only its own members), or for teachers planning together: in a staff course, or by allowing the teacher roles the "Mark own availability" capability in the activity.';
$string['modulenameplural'] = 'Find a time activities';
$string['needsjs'] = 'Marking availability needs JavaScript.';
$string['nocandidates'] = 'No time suits anybody yet.';
$string['nofuturetimes'] = 'There are no future times left in this activity.';
$string['nogroups'] = 'This activity has no groups.';
$string['nogroupsvisible'] = 'You are not in any group of this activity.';
$string['noreminders'] = 'No reminders';
$string['norequiredusers'] = 'No necessary participants';
$string['noslots'] = 'There are no times to choose from. Check the activity\'s dates and daily times.';
$string['notconfirmed'] = 'No meeting time has been confirmed yet.';
$string['notifymembers'] = 'Notify group members';
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
$string['privacy:metadata:findatime_required'] = 'The necessary participants of an activity, whose availability a meeting time must respect.';
$string['privacy:metadata:findatime_required:userid'] = 'The user marked as a necessary participant.';
$string['privacy:metadata:findatime_responses'] = 'Which users have submitted their availability.';
$string['privacy:metadata:findatime_responses:timecreated'] = 'When the user first submitted their availability.';
$string['privacy:metadata:findatime_responses:timemodified'] = 'When the user last changed their availability.';
$string['privacy:metadata:findatime_responses:userid'] = 'The user who submitted availability.';
$string['privacy:metadata:findatime_slots'] = 'The times a user marked as available or available if need be.';
$string['privacy:metadata:findatime_slots:responseid'] = 'The response the time belongs to.';
$string['privacy:metadata:findatime_slots:slotstart'] = 'The start of the time slot.';
$string['privacy:metadata:findatime_slots:status'] = 'Whether the user is available, or available if need be.';
$string['reminderbefore'] = '{$a} before';
$string['reminders'] = 'Reminders';
$string['reminders_help'] = 'Send a reminder to the members who have not marked their availability yet, at these times before the RSVP date. If several of these times have already passed when the reminders are checked, only one reminder is sent.';
$string['requiredblockedshort'] = 'a necessary participant cannot make this time';
$string['requiredlist'] = 'Necessary participants: {$a}';
$string['requiredname'] = '{$a} (necessary)';
$string['requiredunavailable'] = 'Necessary participant unavailable';
$string['requiredusers'] = 'Necessary participants';
$string['requiredusers_help'] = 'The meeting time must suit these participants. Once a necessary participant has marked their availability, the times they marked as unavailable are left out of the best times, and the automatic confirmation never chooses them. Someone who confirms by hand can still choose such a time; it is flagged in the list. Each necessary participant only affects the groups they belong to.';
$string['resetdatesshifted'] = 'Dates shifted';
$string['resetmeetings'] = 'Delete confirmed meetings and their calendar events';
$string['resetresponses'] = 'Delete all availability';
$string['respondedcount'] = '{$a->responded} of {$a->members} have answered';
$string['responserate'] = 'Response rate';
$string['responses'] = 'Responses';
$string['responsessettings'] = 'Responses';
$string['rsvpbanner'] = 'Respond by: {$a}';
$string['rsvpbannerclosed'] = 'Responses closed: {$a}';
$string['rsvpby'] = 'Respond by';
$string['rsvpby_help'] = 'Members must mark their availability by this time. After it, availability can no longer be changed, and only the answers given by then count when the meeting time is set, whether by hand or automatically.';
$string['rsvpbynotice'] = 'Please mark your availability by {$a}.';
$string['rsvpbyoverview'] = 'Responses close on {$a}.';
$string['rsvpclosednotice'] = 'Responses closed on {$a}. Your availability can no longer be changed.';
$string['rsvpclosedoverview'] = 'Responses closed on {$a}. Only the answers given by then count.';
$string['saveavailability'] = 'Save availability';
$string['savestatuserror'] = 'Your availability could not be saved.';
$string['savestatussaved'] = 'Saved.';
$string['savestatussaving'] = 'Saving...';
$string['savestatusunsaved'] = 'Unsaved changes.';
$string['send'] = 'Send';
$string['senddetails'] = 'Send meeting details';
$string['slotrange'] = '{$a->start} – {$a->end}';
$string['slotsize'] = 'Slot size';
$string['statusavailable'] = 'Available';
$string['statusifneedbe'] = 'If need be';
$string['statusunavailable'] = 'Unavailable';
$string['submitted'] = 'Availability submitted';
$string['taskautoconfirm'] = 'Confirm the most popular meeting times';
$string['tasksendreminders'] = 'Send reminders before RSVP dates';
$string['theautomaticconfirmation'] = 'The automatic confirmation';
$string['time'] = 'Time';
$string['timeoption'] = '{$a->time}: {$a->available} of {$a->members} available, {$a->ifneedbe} if need be';
$string['timeoptionblocked'] = '{$a} (a necessary participant cannot come)';
$string['timezonenotice'] = 'Times are shown in your timezone, {$a->user}.';
$string['timezonenoticediffers'] = 'Times are shown in your timezone, {$a->user}. The activity\'s times were set in {$a->activity}.';
$string['timing'] = 'Dates and times';
$string['timingchangewarning'] = 'Participants have already marked their availability. If you change the dates, daily times, slot size or timezone, availability for times that are no longer on the grid will be ignored.';
$string['ungroupedwarning'] = '{$a} participants are not in any group of this activity, so they cannot mark their availability.';
$string['viewgroup'] = 'View';
$string['viewincalendar'] = 'View in calendar';
$string['youravailability'] = 'Your availability';
