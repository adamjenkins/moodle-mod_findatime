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
 * Group overlap heatmap: names on hover or focus, keyboard movement, refresh after the user saves.
 *
 * @module     mod_findatime/heatmap
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Templates from 'core/templates';
import {getString, getStrings} from 'core/str';
import {SAVED_EVENT} from 'mod_findatime/grid';

/**
 * Heat level (0-4), the same formula as \mod_findatime\output\heatmap::level().
 *
 * @param {number} available Members available.
 * @param {number} ifneedbe Members available if need be.
 * @param {number} members Group size.
 * @returns {number}
 */
const level = (available, ifneedbe, members) => {
    if (members <= 0 || available + ifneedbe === 0) {
        return 0;
    }
    return Math.max(1, Math.min(4, Math.ceil(((available + ifneedbe / 2) / members) * 4)));
};

/**
 * One heatmap.
 */
class Heatmap {
    /**
     * Constructor.
     *
     * @param {HTMLElement} root Container.
     * @param {Object} strings Loaded strings.
     */
    constructor(root, strings) {
        this.root = root;
        this.strings = strings;
        this.cmid = parseInt(root.dataset.cmid, 10);
        this.groupid = parseInt(root.dataset.groupid, 10);
        this.members = parseInt(root.dataset.membercount, 10);
        this.detail = root.querySelector('[data-region="heatdetail"]');
        this.cells = Array.from(root.querySelectorAll('.mod-findatime-heat'));
        this.positions = new Map(this.cells.map((c) => [c.dataset.row + ':' + c.dataset.col, c]));
        this.setSlotData(JSON.parse(root.dataset.slotdata || '[]'));
        if (this.cells.length) {
            this.cells[0].tabIndex = 0;
        }
        this.bind();
    }

    /**
     * Index per-slot data by slot start.
     *
     * @param {Array} slots List from get_overlap.
     */
    setSlotData(slots) {
        this.slotdata = new Map(slots.map((s) => [String(s.slotstart), s]));
    }

    /**
     * Attach listeners.
     */
    bind() {
        const table = this.root.querySelector('table');
        if (!table) {
            return;
        }
        table.addEventListener('pointerover', (e) => {
            const cell = e.target.closest('.mod-findatime-heat');
            if (cell) {
                this.showDetail(cell);
            }
        });
        table.addEventListener('focusin', (e) => {
            const cell = e.target.closest('.mod-findatime-heat');
            if (cell) {
                this.cells.forEach((c) => {
                    c.tabIndex = c === cell ? 0 : -1;
                });
                this.showDetail(cell);
            }
        });
        table.addEventListener('keydown', (e) => this.keyDown(e));
        document.addEventListener(SAVED_EVENT, (e) => {
            if (e.detail && e.detail.cmid === this.cmid) {
                this.refresh();
            }
        });
    }

    /**
     * Arrow key movement between slot cells.
     *
     * @param {KeyboardEvent} e The event.
     */
    keyDown(e) {
        const moves = {ArrowUp: [-1, 0], ArrowDown: [1, 0], ArrowLeft: [0, -1], ArrowRight: [0, 1]};
        const cell = e.target.closest('.mod-findatime-heat');
        if (!cell || !moves[e.key]) {
            return;
        }
        e.preventDefault();
        let row = parseInt(cell.dataset.row, 10);
        let col = parseInt(cell.dataset.col, 10);
        for (let i = 0; i < this.cells.length; i++) {
            row += moves[e.key][0];
            col += moves[e.key][1];
            if (row < 0 || col < 0) {
                return;
            }
            const next = this.positions.get(row + ':' + col);
            if (next) {
                next.focus();
                return;
            }
        }
    }

    /**
     * Show who is available at a cell's time.
     *
     * @param {HTMLElement} cell The cell.
     */
    showDetail(cell) {
        const data = this.slotdata.get(cell.dataset.slot) || {availablenames: [], ifneedbenames: []};
        this.detail.textContent = '';
        const heading = document.createElement('p');
        const strong = document.createElement('strong');
        strong.textContent = cell.dataset.label;
        heading.appendChild(strong);
        this.detail.appendChild(heading);
        const addLine = (label, names) => {
            const line = document.createElement('p');
            line.className = 'mb-1';
            line.textContent = label + ' (' + names.length + '): ' + names.join(', ');
            this.detail.appendChild(line);
        };
        if (data.availablenames.length) {
            addLine(this.strings.available, data.availablenames);
        }
        if (data.ifneedbenames.length) {
            addLine(this.strings.ifneedbe, data.ifneedbenames);
        }
        if (!data.availablenames.length && !data.ifneedbenames.length) {
            const line = document.createElement('p');
            line.className = 'mb-1';
            line.textContent = this.strings.nobody;
            this.detail.appendChild(line);
        }
    }

    /**
     * Reload the group's overlap and redraw.
     *
     * @returns {Promise}
     */
    refresh() {
        // Refreshes can overlap when saves follow each other; only the newest may draw.
        this.sequence = (this.sequence || 0) + 1;
        const sequence = this.sequence;
        return Promise.resolve(Ajax.call([{
            methodname: 'mod_findatime_get_overlap',
            args: {cmid: this.cmid, groupid: this.groupid},
        }])[0]).then((data) => (sequence === this.sequence ? this.redraw(data) : null)).catch(Notification.exception);
    }

    /**
     * Redraw cells, counts and the candidate list from get_overlap data.
     *
     * @param {Object} data The get_overlap result.
     * @returns {Promise}
     */
    redraw(data) {
        this.members = data.membercount;
        this.setSlotData(data.slots);
        const summaries = this.cells.map((cell) => {
            const s = this.slotdata.get(cell.dataset.slot) || {available: 0, ifneedbe: 0};
            cell.dataset.level = String(level(s.available, s.ifneedbe, this.members));
            cell.textContent = '';
            if (s.available || s.ifneedbe) {
                cell.appendChild(document.createTextNode(String(s.available)));
                if (s.ifneedbe) {
                    const plus = document.createElement('span');
                    plus.className = 'mod-findatime-plus';
                    plus.textContent = '+' + s.ifneedbe;
                    cell.appendChild(plus);
                }
            }
            return getString('heatsummary', 'mod_findatime',
                {available: s.available, ifneedbe: s.ifneedbe, members: this.members}).then((summary) => {
                cell.setAttribute('aria-label', cell.dataset.label + ': ' + summary);
                return null;
            });
        });

        const candidates = data.candidates.map((c, index) => Object.assign({}, c, {
            first: index === 0,
            availablelist: c.availablenames.join(', '),
            ifneedbelist: c.ifneedbenames.join(', '),
            hasifneedbe: c.ifneedbe > 0,
        }));
        const context = {
            groupid: data.groupid,
            membercount: data.membercount,
            canconfirm: data.canconfirm,
            candidates: candidates,
            hascandidates: candidates.length > 0,
        };
        const region = this.root.querySelector('[data-region="candidates"]');
        const rendered = Templates.renderForPromise('mod_findatime/candidates', context).then(({html, js}) => {
            Templates.replaceNodeContents(region, html, js);
            return null;
        });

        const responded = this.root.querySelector('[data-region="responded"]');
        const counts = Promise.all([
            getString('respondedcount', 'mod_findatime', {responded: data.respondedcount, members: data.membercount}),
            data.pendingnames.length ? getString('pendinglist', 'mod_findatime', data.pendingnames.join(', ')) : '',
        ]).then(([text, pending]) => {
            responded.textContent = text + ' ';
            if (pending) {
                const span = document.createElement('span');
                span.className = 'mod-findatime-pending';
                span.textContent = pending;
                responded.appendChild(span);
            }
            return null;
        });
        return Promise.all(summaries.concat([rendered, counts]));
    }
}

/**
 * Initialise a heatmap.
 *
 * @param {string} id Container id.
 */
export const init = (id) => {
    const root = document.getElementById(id);
    if (!root) {
        return;
    }
    getStrings([
        {key: 'statusavailable', component: 'mod_findatime'},
        {key: 'statusifneedbe', component: 'mod_findatime'},
        {key: 'heatnobody', component: 'mod_findatime'},
    ]).then((s) => {
        new Heatmap(root, {available: s[0], ifneedbe: s[1], nobody: s[2]});
        return null;
    }).catch(Notification.exception);
};
