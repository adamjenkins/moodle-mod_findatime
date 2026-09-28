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
 * Confirming, changing and cancelling a group's meeting from the activity page.
 *
 * @module     mod_findatime/meeting
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import ModalForm from 'core_form/modalform';
import Notification from 'core/notification';
import Templates from 'core/templates';
import {getString} from 'core/str';

/**
 * Replace the meeting panel with new HTML.
 *
 * @param {string} html The panel.
 */
const replacePanel = (html) => {
    const panel = document.querySelector('[data-region="mod-findatime-meeting"]');
    if (panel) {
        Templates.replaceNode(panel, html, '');
    }
};

/**
 * Open the confirmation form.
 *
 * @param {number} cmid Course module id.
 * @param {number} groupid Group id.
 * @param {number} timestart Suggested start, or 0.
 * @param {HTMLElement} trigger Element to return focus to.
 */
const openConfirm = (cmid, groupid, timestart, trigger) => {
    const form = new ModalForm({
        formClass: 'mod_findatime\\form\\confirm_meeting',
        args: {cmid: cmid, groupid: groupid, timestart: timestart},
        modalConfig: {title: getString('confirmatime', 'mod_findatime')},
        saveButtonText: getString('confirm'),
        returnFocus: trigger,
    });
    form.addEventListener(form.events.FORM_SUBMITTED, (e) => {
        replacePanel(e.detail.panelhtml);
        getString('meetingconfirmednotice', 'mod_findatime').then((text) => {
            Notification.addNotification({message: text, type: 'success'});
            return null;
        }).catch(Notification.exception);
    });
    form.show();
};

/**
 * Ask, then cancel the meeting.
 *
 * @param {number} cmid Course module id.
 * @param {number} groupid Group id.
 */
const cancelMeeting = (cmid, groupid) => {
    Notification.saveCancelPromise(
        getString('cancelmeeting', 'mod_findatime'),
        getString('cancelmeetingconfirm', 'mod_findatime'),
        getString('cancelmeeting', 'mod_findatime')
    ).then(() => Ajax.call([{
        methodname: 'mod_findatime_cancel_meeting',
        args: {cmid: cmid, groupid: groupid},
    }])[0]).then((result) => {
        replacePanel(result.panelhtml);
        return null;
    }).catch((error) => {
        // Pressing Cancel in the dialogue rejects without an error object.
        if (error) {
            Notification.exception(error);
        }
    });
};

/**
 * Wire the confirm and cancel buttons of the page (the candidate list and the panel).
 *
 * @param {number} cmid Course module id.
 * @param {number} groupid Group shown.
 */
export const init = (cmid, groupid) => {
    document.addEventListener('click', (e) => {
        const button = e.target.closest('[data-action="confirm"], [data-action="cancel"]');
        if (!button || !button.closest('.path-mod-findatime')) {
            return;
        }
        if (parseInt(button.dataset.groupid, 10) !== groupid) {
            return;
        }
        e.preventDefault();
        if (button.dataset.action === 'confirm') {
            openConfirm(cmid, groupid, parseInt(button.dataset.timestart || '0', 10), button);
        } else {
            cancelMeeting(cmid, groupid);
        }
    });
};
