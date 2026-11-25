import Alpine from 'alpinejs';
import { transitionLabel } from './format';

const MOUSE_DRAG_DISTANCE = 6;
const TOUCH_HOLD_MS = 300;
const TOUCH_MOVE_TOLERANCE = 10;
const SCROLL_EDGE = 90;
const SCROLL_MAX_SPEED = 18;

/**
 * Drag-and-drop for the Board, built on pointer events so it works with a mouse, a pen and a touch screen.
 *
 * Mouse: press and move a few pixels. Touch: hold the card briefly, then drag (a quick swipe still scrolls the page).
 * Every allowed column lights up as soon as a card is picked up, the page scrolls near the screen edges,
 * and Escape cancels. Cards also have a "Pindahkan" menu for keyboard users and anyone who prefers not to drag.
 * Mixed into the tasklist component, which provides moveTask(), rejectionReason() and the revision dialog.
 */
export default {
    drag: { active: false, task: null, over: null, x: 0, y: 0, offsetX: 0, offsetY: 0, width: 0 },
    pendingDrag: null,
    suppressClick: false,
    moveMenuTaskId: null,
    scrollFrame: null,

    initBoardDrag() {
        // Window touchmove listeners are passive by default; this one must be able to stop the page from scrolling mid-drag.
        window.addEventListener('touchmove', (event) => {
            if (this.drag.active) event.preventDefault();
        }, { passive: false });
    },

    canDrag(task) {
        return task.abilities.transitions.length > 0 && !this.movingIds[task.id];
    },

    isDropAllowed(status) {
        return Boolean(this.drag.task?.abilities.transitions.includes(status));
    },

    /** How a column looks while a card is being dragged: its own column, an allowed target, or a refused one. */
    columnDropState(status) {
        if (!this.drag.active) return null;
        if (status === this.drag.task.status) return 'origin';

        return this.isDropAllowed(status) ? 'allowed' : 'refused';
    },

    dropHint(status) {
        return transitionLabel(this.drag.task?.status, status);
    },

    onCardPointerDown(event, task) {
        if (!this.canDrag(task) || event.button !== 0 || event.target.closest('[data-no-drag]')) return;

        const rect = event.currentTarget.getBoundingClientRect();

        this.cancelPendingDrag();
        this.pendingDrag = {
            task,
            pointerId: event.pointerId,
            pointerType: event.pointerType,
            startX: event.clientX,
            startY: event.clientY,
            offsetX: event.clientX - rect.left,
            offsetY: event.clientY - rect.top,
            width: rect.width,
            timer: event.pointerType === 'touch' ? setTimeout(() => this.beginDrag(), TOUCH_HOLD_MS) : null,
        };
    },

    onWindowPointerMove(event) {
        const pending = this.pendingDrag;

        if (!pending || event.pointerId !== pending.pointerId) return;

        if (!this.drag.active) {
            const distance = Math.hypot(event.clientX - pending.startX, event.clientY - pending.startY);

            if (pending.pointerType === 'touch') {
                // Moving before the hold time means the user is scrolling, not dragging.
                if (distance > TOUCH_MOVE_TOLERANCE) this.cancelPendingDrag();

                return;
            }

            if (distance < MOUSE_DRAG_DISTANCE) return;

            this.beginDrag();
        }

        event.preventDefault();
        this.drag.x = event.clientX;
        this.drag.y = event.clientY;
        this.drag.over = this.columnAt(event.clientX, event.clientY);
    },

    onWindowPointerUp(event) {
        const pending = this.pendingDrag;

        if (!pending || event.pointerId !== pending.pointerId) return;

        if (!this.drag.active) {
            this.cancelPendingDrag();

            return;
        }

        const { task, over } = this.drag;

        this.endDrag();
        this.suppressClick = true;
        setTimeout(() => {
            this.suppressClick = false;
        }, 0);

        if (over && over !== task.status) this.dropOn(task, over);
    },

    /** Escape, a lost pointer or a lost window focus cancels the drag; the card stays where it was. */
    cancelDrag() {
        if (this.drag.active) this.endDrag();
        this.cancelPendingDrag();
    },

    beginDrag() {
        const pending = this.pendingDrag;

        if (!pending) return;

        clearTimeout(pending.timer);
        this.moveMenuTaskId = null;
        this.drag = {
            active: true,
            task: pending.task,
            over: null,
            x: pending.startX,
            y: pending.startY,
            offsetX: pending.offsetX,
            offsetY: pending.offsetY,
            width: pending.width,
        };
        document.body.classList.add('select-none', 'cursor-grabbing');
        if (pending.pointerType === 'touch') navigator.vibrate?.(15);
        this.autoScroll();
    },

    endDrag() {
        cancelAnimationFrame(this.scrollFrame);
        document.body.classList.remove('select-none', 'cursor-grabbing');
        this.drag = { active: false, task: null, over: null, x: 0, y: 0, offsetX: 0, offsetY: 0, width: 0 };
        this.pendingDrag = null;
    },

    cancelPendingDrag() {
        clearTimeout(this.pendingDrag?.timer);
        this.pendingDrag = null;
    },

    columnAt(x, y) {
        return document.elementFromPoint(x, y)?.closest('[data-board-column]')?.dataset.boardColumn ?? null;
    },

    /** Scroll the page while the pointer rests near the top or bottom edge, faster the closer it gets. */
    autoScroll() {
        if (!this.drag.active) return;

        const { y } = this.drag;
        let speed = 0;

        if (y < SCROLL_EDGE) speed = -SCROLL_MAX_SPEED * (1 - y / SCROLL_EDGE);
        else if (y > window.innerHeight - SCROLL_EDGE) speed = SCROLL_MAX_SPEED * (1 - (window.innerHeight - y) / SCROLL_EDGE);

        if (speed !== 0) {
            window.scrollBy(0, speed);
            this.drag.over = this.columnAt(this.drag.x, y);
        }

        this.scrollFrame = requestAnimationFrame(() => this.autoScroll());
    },

    onCardClick(task) {
        if (this.suppressClick) return;

        this.openTask(task.id);
    },

    /* "Pindahkan ke…" menu -------------------------------------------------------------------- */

    toggleMoveMenu(task) {
        this.moveMenuTaskId = this.moveMenuTaskId === task.id ? null : task.id;
    },

    moveOptions(task) {
        return task.abilities.transitions.map((status) => ({
            status,
            label: transitionLabel(task.status, status),
            target: this.statusLabel(status),
        }));
    },

    chooseMove(task, status) {
        this.moveMenuTaskId = null;
        this.dropOn(task, status);
    },

    /** Apply a move chosen by dragging or from the menu, following the same workflow rules. */
    dropOn(task, status) {
        if (!task.abilities.transitions.includes(status)) {
            Alpine.store('toast').error(this.rejectionReason(task, status));

            return;
        }

        if (task.status === 'on_review' && status === 'backlog') {
            this.revision = { open: true, task, note: '', error: null, saving: false };

            return;
        }

        this.moveTask(task, status);
    },
};
