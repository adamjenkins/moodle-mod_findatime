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

namespace mod_findatime\local;

/**
 * Who may do what, for which group, in one activity.
 *
 * Every page, external function and task goes through this class, so the group rules are
 * stated once. Group 0 stands for "all participants" and is only meaningful when the activity
 * is not in a group mode.
 *
 * @package    mod_findatime
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access {
    /** @var \stdClass Instance record. */
    protected $findatime;
    /** @var \cm_info Course module. */
    protected $cm;
    /** @var \context_module Module context. */
    protected $context;

    /**
     * Constructor.
     *
     * @param \stdClass $findatime Instance record.
     * @param \cm_info|\stdClass $cm Course module.
     * @param \context_module|null $context Module context (derived from $cm when null).
     */
    public function __construct(\stdClass $findatime, $cm, ?\context_module $context = null) {
        $this->findatime = $findatime;
        if (!$cm instanceof \cm_info) {
            $cm = \cm_info::create($cm);
        }
        $this->cm = $cm;
        $this->context = $context ?? \context_module::instance($cm->id);
    }

    /**
     * The activity's effective group mode.
     *
     * @return int NOGROUPS, SEPARATEGROUPS or VISIBLEGROUPS.
     */
    public function groupmode(): int {
        return (int)groups_get_activity_groupmode($this->cm);
    }

    /**
     * Whether the activity works per group.
     *
     * @return bool
     */
    public function uses_groups(): bool {
        return $this->groupmode() !== NOGROUPS;
    }

    /**
     * The groups (within the activity's grouping) a user belongs to.
     *
     * @param int $userid User id.
     * @return int[] Group ids.
     */
    public function user_groupids(int $userid): array {
        if (!$this->uses_groups()) {
            return [0];
        }
        $groups = groups_get_all_groups($this->cm->course, $userid, $this->cm->groupingid, 'g.id');
        return array_map('intval', array_keys($groups));
    }

    /**
     * The groups a user may look at (heatmap and meeting).
     *
     * @param int $userid User id.
     * @return array groupid => group name (group 0 = all participants, only without group mode).
     */
    public function viewable_groups(int $userid): array {
        if (!has_capability('mod/findatime:view', $this->context, $userid)) {
            return [];
        }
        if (!$this->uses_groups()) {
            return [0 => get_string('allparticipants')];
        }
        $groups = groups_get_activity_allowed_groups($this->cm, $userid);
        $result = [];
        foreach ($groups as $group) {
            $result[(int)$group->id] = format_string($group->name, true, ['context' => $this->context]);
        }
        return $result;
    }

    /**
     * Whether a user may look at a group's availability and meeting.
     *
     * @param int $groupid Group id.
     * @param int $userid User id.
     * @return bool
     */
    public function can_view_group(int $groupid, int $userid): bool {
        return array_key_exists($groupid, $this->viewable_groups($userid));
    }

    /**
     * Whether a user may mark their own availability.
     *
     * In a group mode they must belong to a group of the activity's grouping.
     *
     * @param int $userid User id.
     * @return bool
     */
    public function can_respond(int $userid): bool {
        if (!has_capability('mod/findatime:respond', $this->context, $userid)) {
            return false;
        }
        return !$this->uses_groups() || count($this->user_groupids($userid)) > 0;
    }

    /**
     * Whether a user may confirm or cancel the meeting of a group.
     *
     * Either through the capability (for groups they belong to, or any group with
     * accessallgroups), or through the activity's "any group member may confirm" setting
     * (for their own groups only).
     *
     * @param int $groupid Group id.
     * @param int $userid User id.
     * @return bool
     */
    public function can_confirm(int $groupid, int $userid): bool {
        if ($this->uses_groups() && $groupid <= 0) {
            return false;
        }
        if (!$this->uses_groups() && $groupid !== 0) {
            return false;
        }
        if ($this->uses_groups() && !groups_group_exists($groupid)) {
            return false;
        }
        $member = in_array($groupid, $this->user_groupids($userid), true);
        if (has_capability('mod/findatime:confirm', $this->context, $userid)) {
            if ($member || has_capability('moodle/site:accessallgroups', $this->context, $userid)) {
                return $this->group_in_grouping($groupid);
            }
        }
        if (
            !empty($this->findatime->memberconfirm) && $member &&
                has_capability('mod/findatime:respond', $this->context, $userid)
        ) {
            return true;
        }
        return false;
    }

    /**
     * Whether a group belongs to the activity's grouping (always true without a grouping or groups).
     *
     * @param int $groupid Group id.
     * @return bool
     */
    protected function group_in_grouping(int $groupid): bool {
        if (!$this->uses_groups()) {
            return $groupid === 0;
        }
        if (empty($this->cm->groupingid)) {
            $group = groups_get_group($groupid);
            return $group && (int)$group->courseid === (int)$this->cm->course;
        }
        return array_key_exists($groupid, groups_get_all_groups($this->cm->course, 0, $this->cm->groupingid, 'g.id'));
    }

    /**
     * The groups the activity works with (all groups of the grouping, or [0]).
     *
     * @return array groupid => name.
     */
    public function all_groups(): array {
        if (!$this->uses_groups()) {
            return [0 => get_string('allparticipants')];
        }
        $result = [];
        foreach (groups_get_all_groups($this->cm->course, 0, $this->cm->groupingid) as $group) {
            $result[(int)$group->id] = format_string($group->name, true, ['context' => $this->context]);
        }
        return $result;
    }

    /**
     * The members of a group whose availability counts: active participants who may respond.
     *
     * @param int $groupid Group id (0 = everybody, without group mode).
     * @return \stdClass[] userid => user with name fields.
     */
    public function members(int $groupid): array {
        $fields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        return get_enrolled_users(
            $this->context,
            'mod/findatime:respond',
            $groupid,
            'u.id, ' . $fields,
            'u.lastname, u.firstname, u.id',
            0,
            0,
            true
        );
    }

    /**
     * The course module.
     *
     * @return \cm_info
     */
    public function get_cm(): \cm_info {
        return $this->cm;
    }

    /**
     * The module context.
     *
     * @return \context_module
     */
    public function get_context(): \context_module {
        return $this->context;
    }

    /**
     * The instance record.
     *
     * @return \stdClass
     */
    public function get_findatime(): \stdClass {
        return $this->findatime;
    }
}
