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
 * Editable availability grid: click-and-drag painting, touch taps and keyboard control, auto-saved.
 *
 * Mouse and pen drag a rectangle. Touch taps set one cell and leave page scrolling alone, unless the
 * "drag to select" toggle is on. The keyboard moves with the arrow keys, Home and End; Space or Enter
 * applies the chosen status and Shift+arrow applies it while moving.
 *
 * @module     mod_findatime/grid
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {getStrings} from 'core/str';

/** @var {number} Delay before changes are saved, in milliseconds. */
const SAVE_DELAY = 800;

/** @var {string} Event dispatched on document after a successful save. */
export const SAVED_EVENT = 'mod_findatime:availabilitysaved';

/**
 * One availability grid.
 */
class Grid {
    /**
     * Constructor.
     *
     * @param {HTMLElement} root The grid container.
     * @param {Object} strings Loaded strings.
     */
    constructor(root, strings) {
        this.root = root;
        this.strings = strings;
        this.cmid = parseInt(root.dataset.cmid, 10);
        this.table = root.querySelector('table');
        this.cells = Array.from(root.querySelectorAll('.mod-findatime-cell'));
        this.positions = new Map();
        this.rowCount = 0;
        this.colCount = 0;
        this.cells.forEach((cell) => {
            this.positions.set(cell.dataset.row + ':' + cell.dataset.col, cell);
            this.rowCount = Math.max(this.rowCount, parseInt(cell.dataset.row, 10) + 1);
            this.colCount = Math.max(this.colCount, parseInt(cell.dataset.col, 10) + 1);
        });
        this.brush = 1;
        this.paintMode = false;
        this.drag = null;
        this.timer = null;
        this.saving = false;
        this.pending = false;
        this.dirty = false;

        if (this.cells.length) {
            this.cells[0].tabIndex = 0;
        }
        this.bind();
    }

    /**
     * Attach the event listeners.
     */
    bind() {
        this.root.querySelectorAll('.mod-findatime-brush').forEach((button) => {
            button.addEventListener('click', () => this.setBrush(parseInt(button.dataset.brush, 10)));
        });
        const toggle = this.root.querySelector('[data-action="togglepaint"]');
        if (toggle && window.matchMedia && window.matchMedia('(pointer: coarse)').matches) {
            toggle.hidden = false;
            toggle.addEventListener('click', () => {
                this.paintMode = !this.paintMode;
                toggle.setAttribute('aria-pressed', this.paintMode ? 'true' : 'false');
                this.table.classList.toggle('mod-findatime-painting', this.paintMode);
            });
        }
        this.root.querySelector('[data-action="save"]').addEventListener('click', () => this.save());

        this.table.addEventListener('pointerdown', (e) => this.pointerDown(e));
        this.table.addEventListener('pointermove', (e) => this.pointerMove(e));
        document.addEventListener('pointerup', (e) => this.pointerUp(e));
        document.addEventListener('pointercancel', () => this.pointerCancel());
        this.table.addEventListener('keydown', (e) => this.keyDown(e));
        this.table.addEventListener('focusin', (e) => {
            const cell = e.target.closest('.mod-findatime-cell');
            if (cell) {
                this.setFocusCell(cell, false);
            }
        });
        window.addEventListener('beforeunload', (e) => {
            if (this.dirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }

    /**
     * Choose the status that painting applies.
     *
     * @param {number} brush 0, 1 or 2.
     */
    setBrush(brush) {
        this.brush = brush;
        this.root.querySelectorAll('.mod-findatime-brush').forEach((button) => {
            button.setAttribute('aria-pressed', parseInt(button.dataset.brush, 10) === brush ? 'true' : 'false');
        });
    }

    /**
     * The status a paint action starting on a cell applies: toggles off when the cell already has the brush.
     *
     * @param {HTMLElement} cell The starting cell.
     * @returns {number}
     */
    targetValue(cell) {
        const current = parseInt(cell.dataset.status, 10);
        return current === this.brush ? 0 : this.brush;
    }

    /**
     * Set the status of a cell.
     *
     * @param {HTMLElement} cell The cell.
     * @param {number} status 0, 1 or 2.
     */
    setStatus(cell, status) {
        if (parseInt(cell.dataset.status, 10) === status) {
            return;
        }
        cell.dataset.status = String(status);
        cell.setAttribute('aria-label', cell.dataset.label + ': ' + this.strings.status[status]);
        this.markChanged();
    }

    /**
     * Start painting.
     *
     * @param {PointerEvent} e The event.
     */
    pointerDown(e) {
        const cell = e.target.closest('.mod-findatime-cell');
        if (!cell || e.button !== 0) {
            return;
        }
        const tapOnly = e.pointerType === 'touch' && !this.paintMode;
        this.drag = {
            start: cell,
            value: this.targetValue(cell),
            tapOnly: tapOnly,
            snapshot: new Map(this.cells.map((c) => [c, parseInt(c.dataset.status, 10)])),
        };
        this.setFocusCell(cell, false);
        if (!tapOnly) {
            // Stop text selection and, in paint mode, scrolling.
            e.preventDefault();
            this.paintRectangle(cell, cell);
        }
    }

    /**
     * Extend the painted rectangle.
     *
     * @param {PointerEvent} e The event.
     */
    pointerMove(e) {
        if (!this.drag || this.drag.tapOnly) {
            return;
        }
        const element = document.elementFromPoint(e.clientX, e.clientY);
        const cell = element ? element.closest('.mod-findatime-cell') : null;
        if (cell && this.table.contains(cell)) {
            this.paintRectangle(this.drag.start, cell);
        }
    }

    /**
     * Finish painting (a touch tap sets its one cell here).
     *
     * @param {PointerEvent} e The event.
     */
    pointerUp(e) {
        if (!this.drag) {
            return;
        }
        if (this.drag.tapOnly) {
            const element = document.elementFromPoint(e.clientX, e.clientY);
            if (element && element.closest('.mod-findatime-cell') === this.drag.start) {
                this.setStatus(this.drag.start, this.drag.value);
            }
        }
        this.drag = null;
    }

    /**
     * The browser took the gesture over (usually to scroll): a touch tap must not paint.
     */
    pointerCancel() {
        this.drag = null;
    }

    /**
     * Apply the drag value inside the rectangle between two cells and restore everything outside it.
     *
     * @param {HTMLElement} a One corner.
     * @param {HTMLElement} b The other corner.
     */
    paintRectangle(a, b) {
        const rows = [parseInt(a.dataset.row, 10), parseInt(b.dataset.row, 10)].sort((x, y) => x - y);
        const cols = [parseInt(a.dataset.col, 10), parseInt(b.dataset.col, 10)].sort((x, y) => x - y);
        this.drag.snapshot.forEach((original, cell) => {
            const row = parseInt(cell.dataset.row, 10);
            const col = parseInt(cell.dataset.col, 10);
            const inside = row >= rows[0] && row <= rows[1] && col >= cols[0] && col <= cols[1];
            this.setStatus(cell, inside ? this.drag.value : original);
        });
    }

    /**
     * Keyboard control.
     *
     * @param {KeyboardEvent} e The event.
     */
    keyDown(e) {
        const cell = e.target.closest('.mod-findatime-cell');
        if (!cell) {
            return;
        }
        const moves = {ArrowUp: [-1, 0], ArrowDown: [1, 0], ArrowLeft: [0, -1], ArrowRight: [0, 1]};
        if (moves[e.key]) {
            e.preventDefault();
            const next = this.neighbour(cell, moves[e.key][0], moves[e.key][1]);
            if (next) {
                this.setFocusCell(next, true);
                if (e.shiftKey) {
                    this.setStatus(next, this.brush);
                }
            }
        } else if (e.key === 'Home' || e.key === 'End') {
            e.preventDefault();
            const row = this.cells.filter((c) => c.dataset.row === cell.dataset.row);
            this.setFocusCell(e.key === 'Home' ? row[0] : row[row.length - 1], true);
        } else if (e.key === ' ' || e.key === 'Enter') {
            e.preventDefault();
            this.setStatus(cell, this.targetValue(cell));
        }
    }

    /**
     * The nearest slot cell from a cell in a direction, skipping gaps.
     *
     * @param {HTMLElement} cell Starting cell.
     * @param {number} dr Row step.
     * @param {number} dc Column step.
     * @returns {HTMLElement|null}
     */
    neighbour(cell, dr, dc) {
        let row = parseInt(cell.dataset.row, 10) + dr;
        let col = parseInt(cell.dataset.col, 10) + dc;
        while (row >= 0 && col >= 0 && row < this.rowCount && col < this.colCount) {
            const next = this.positions.get(row + ':' + col);
            if (next) {
                return next;
            }
            row += dr;
            col += dc;
        }
        return null;
    }

    /**
     * Make a cell the one in the tab order (roving tabindex).
     *
     * @param {HTMLElement} cell The cell.
     * @param {boolean} focus Whether to move focus to it.
     */
    setFocusCell(cell, focus) {
        this.cells.forEach((c) => {
            c.tabIndex = c === cell ? 0 : -1;
        });
        if (focus) {
            cell.focus();
        }
    }

    /**
     * Note a change and schedule a save.
     */
    markChanged() {
        this.dirty = true;
        this.setSaveStatus(this.strings.unsaved);
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.save(), SAVE_DELAY);
    }

    /**
     * Show a save status message.
     *
     * @param {string} text The message.
     */
    setSaveStatus(text) {
        this.root.querySelector('[data-region="savestatus"]').textContent = text;
    }

    /**
     * Save the whole grid.
     *
     * @returns {Promise}
     */
    save() {
        clearTimeout(this.timer);
        if (this.saving) {
            this.pending = true;
            return Promise.resolve();
        }
        this.saving = true;
        this.dirty = false;
        this.setSaveStatus(this.strings.saving);
        const slots = this.cells
            .filter((c) => parseInt(c.dataset.status, 10) > 0)
            .map((c) => ({slotstart: parseInt(c.dataset.slot, 10), status: parseInt(c.dataset.status, 10)}));
        // A core/ajax promise is a jQuery Deferred without finally(): wrap it.
        return Promise.resolve(Ajax.call([{
            methodname: 'mod_findatime_save_availability',
            args: {cmid: this.cmid, slots: slots},
        }])[0]).then(() => {
            this.setSaveStatus(this.strings.saved);
            document.dispatchEvent(new CustomEvent(SAVED_EVENT, {detail: {cmid: this.cmid}}));
            return null;
        }).catch((error) => {
            this.dirty = true;
            this.setSaveStatus(this.strings.error);
            Notification.exception(error);
        }).finally(() => {
            this.saving = false;
            if (this.pending) {
                this.pending = false;
                this.save();
            }
        });
    }
}

/**
 * Initialise a grid.
 *
 * @param {string} id Id of the grid container.
 */
export const init = (id) => {
    const root = document.getElementById(id);
    if (!root || !root.querySelector('table')) {
        return;
    }
    getStrings([
        {key: 'statusunavailable', component: 'mod_findatime'},
        {key: 'statusavailable', component: 'mod_findatime'},
        {key: 'statusifneedbe', component: 'mod_findatime'},
        {key: 'savestatusunsaved', component: 'mod_findatime'},
        {key: 'savestatussaving', component: 'mod_findatime'},
        {key: 'savestatussaved', component: 'mod_findatime'},
        {key: 'savestatuserror', component: 'mod_findatime'},
    ]).then((s) => {
        new Grid(root, {
            status: {0: s[0], 1: s[1], 2: s[2]},
            unsaved: s[3],
            saving: s[4],
            saved: s[5],
            error: s[6],
        });
        return null;
    }).catch(Notification.exception);
};
