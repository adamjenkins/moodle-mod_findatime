# Find a time (mod_findatime)

A Moodle activity that helps the members of a course group agree on a meeting time. Each member
marks when they are available on a time grid, everyone in the group sees where their availability
overlaps, and a teacher (or, if allowed, any group member) confirms a time. The confirmed meeting
goes into the group's calendar and the members are notified.

It is not a replacement for the Scheduler activity, where teachers offer appointment slots: here
the group's shared availability decides the time.

## Features

- **Availability grid**: members mark times as *available*, *if need be* or *unavailable*. Click and
  drag on a desktop, tap on a touch screen (or switch on "Drag to select"), or use the keyboard:
  arrow keys, Home/End, Space or Enter to mark, Shift+arrow to mark while moving. Changes are saved
  automatically.
- **Group overlap heatmap**: shows how many members are available at each time, with the names on
  hover or keyboard focus, and lists the best meeting times. A member counts for a meeting only if
  they are free for every slot the meeting spans.
- **Confirming a meeting**: a teacher, or any group member when "Any group member may confirm" is
  enabled, confirms a time and an optional location or link. This creates a group calendar event and
  sends a notification to the group's members. The meeting can be moved or cancelled; the calendar
  event follows.
- **Automatic confirmation**: optionally, at a chosen time, every group that has no meeting yet gets
  its most popular time confirmed. A meeting that someone confirmed or cancelled by hand is never
  changed.
- **Teacher overview**: all groups with their response rate and meeting status.
- Groups and groupings (separate and visible groups), activity completion ("submitted availability"),
  course reset, backup and restore, the privacy API, events and the Moodle 5 course overview page.

## Timezones

All times are stored as UTC timestamps and shown in each user's own timezone.

The date range and the daily time window are set in the **activity timezone**, which defaults to the
timezone of the teacher creating the activity. "09:00-17:00" means 09:00-17:00 in that timezone on
every day, including across a daylight saving time change. Each person sees the grid laid out in their
own timezone, so a participant elsewhere in the world may see the times on other dates or at other
hours, and around a DST change in either timezone the rows shift accordingly.

## Requirements

- Moodle 4.5 LTS to 5.3 (`$plugin->supported = [405, 503]`).
- JavaScript for marking availability.

## Installation

Copy the plugin to `mod/findatime` (on Moodle 5.1 and later: `public/mod/findatime`) and visit
*Site administration > Notifications*, or run `admin/cli/upgrade.php`.

## Settings

| Setting | Meaning |
|---|---|
| Activity timezone | Timezone of the dates and daily times |
| First day, Last day | Date range (at most 62 days) |
| Daily start time, Daily end time | Daily window, in 15 minute steps |
| Slot size | 15, 30 or 60 minutes |
| Meeting length | A whole number of slots |
| Allow "if need be" answers | Offer the third status |
| Any group member may confirm | Let members confirm or cancel for their own group |
| Confirm automatically at | When to confirm the most popular time for groups without a meeting |

A grid may hold at most 2000 slots. Changing the timing after people have answered is allowed; answers
for times that are no longer on the grid are ignored.

## Capabilities

| Capability | Default roles |
|---|---|
| `mod/findatime:addinstance` | Editing teacher, manager |
| `mod/findatime:view` | Student, teacher, editing teacher, manager |
| `mod/findatime:respond` | Student |
| `mod/findatime:confirm` | Teacher, editing teacher, manager |
| `mod/findatime:viewreports` | Teacher, editing teacher, manager |

Someone with `mod/findatime:confirm` can confirm for groups they belong to, or for any group if they
can access all groups. To designate a student as a group's coordinator, override
`mod/findatime:confirm` for them in the activity, or enable "Any group member may confirm".

## Good to know

- A confirmed meeting is a group calendar event, so it appears in the calendars of that group's
  members. Moodle does not show another group's module events to a teacher who is not in any group of
  the course, so teachers follow the meetings on the activity's overview page.
- Restoring a course with a new start date moves the activity's dates, the availability and the
  meetings by whole days, keeping their local times.

## Web services

`mod_findatime_get_grid`, `mod_findatime_save_availability`, `mod_findatime_get_overlap` and
`mod_findatime_cancel_meeting` are available to AJAX and to the Moodle app service; the Moodle app
itself is not supported yet.

## Licence

GNU GPL v3 or later. Copyright 2026 Adam Jenkins.
