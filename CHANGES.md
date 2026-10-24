# Release notes

## 0.2.0 (2026-10-24)

- "Respond by" (RSVP) date: after it the availability grid is read-only and only the answers given by then count. The date is shown in large type above the activity description.
- Reminders 1 hour, 1, 2 or 3 days or a week before the RSVP date, sent only to group members who have not answered.
- Confirming a meeting can notify the group with an optional message, and "Send meeting details" sends the time, length, location and a note again (at most once every 10 minutes).
- Necessary participants: once they have answered, the times they cannot make are left out of the best times and never chosen automatically; optionally those times are greyed out on the map and in the other members' grids.
- The group overlap map is colour-coded from red (nobody) through orange, yellow and light green to dark green (everybody), with a colour key. Each block shows its start and end time as a tooltip, and clicking a block keeps its details shown.
- Calendar entries of meetings that have already taken place stay in the course calendar when the activity is deleted.
- Colours follow the Moodle Design System tokens, so the activity follows Moodle 5.3's dark colour mode.
- A new activity icon.
- Upgrading adds database fields and a table; run the Moodle upgrade after installing.
