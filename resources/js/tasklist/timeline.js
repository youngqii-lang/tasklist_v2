import Alpine from 'alpinejs';
import { http } from '../http';
import { matchesFilters, STATUS_LABELS } from './contract';
import { addDays, formatting, parseDate, startOfWeek, toDateString } from './format';

const DAY_MS = 86400000;
const monthNames = Array.from({ length: 12 }, (_, index) =>
    new Intl.DateTimeFormat('id-ID', { month: 'long' }).format(new Date(2000, index, 1)));

/**
 * Spread items over as few rows as possible: an item goes into the first row where it does not overlap the previous one.
 * Items need `startIndex` and `endIndex` (inclusive day positions in the period).
 *
 * @template T
 * @param {Array<T & { startIndex: number, endIndex: number }>} items
 * @returns {Array<Array<T>>}
 */
export function packLanes(items) {
    const lanes = [];
    const laneEnds = [];
    const sorted = [...items].sort((a, b) => a.startIndex - b.startIndex || b.endIndex - a.endIndex);

    sorted.forEach((item) => {
        const laneIndex = laneEnds.findIndex((end) => end < item.startIndex);

        if (laneIndex === -1) {
            lanes.push([item]);
            laneEnds.push(item.endIndex);
        } else {
            lanes[laneIndex].push(item);
            laneEnds[laneIndex] = item.endIndex;
        }
    });

    return lanes;
}

/**
 * Read-only monthly timeline of tasks and shared events across divisions, laid out like the Calendar: one row per
 * week, with each item drawn as a bar from its start date to its deadline (or end) inside every week it touches.
 */
export default (config, filters = {}) => ({
    ...formatting,
    config,
    filters: { ...filters },
    month: new Date(new Date().getFullYear(), new Date().getMonth(), 1),
    weekdays: ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],
    monthNames,
    tasks: [],
    events: [],
    loading: true,
    error: null,
    selectedEvent: null,
    ticket: 0,

    init() {
        this.load();
    },

    /** The Monday on or before the first day of the month: the first day in the grid. */
    get from() {
        return startOfWeek(this.month);
    },

    /** Whole weeks from that Monday until the month's last day. */
    get dayCount() {
        const last = new Date(this.month.getFullYear(), this.month.getMonth() + 1, 0);

        return Math.ceil((Math.round((last - this.from) / DAY_MS) + 1) / 7) * 7;
    },

    get yearOptions() {
        const current = new Date().getFullYear();
        const years = Array.from({ length: 7 }, (_, index) => current - 3 + index);

        return years.includes(this.month.getFullYear()) ? years : [...years, this.month.getFullYear()].sort();
    },

    get isCurrentMonth() {
        const now = new Date();

        return this.month.getFullYear() === now.getFullYear() && this.month.getMonth() === now.getMonth();
    },

    /** Tasks passing the Tasklist filters; the shared agenda always shows. */
    get visibleTasks() {
        return this.tasks.filter((task) => matchesFilters(task, this.filters ?? {}, this.config?.user?.id));
    },

    get hasHiddenFilters() {
        return ['search', 'priority', 'status'].some((key) => this.filters[key]);
    },

    get isEmpty() {
        return this.visibleTasks.length === 0 && this.events.length === 0;
    },

    get overdueCount() {
        return this.visibleTasks.filter((task) => task.isOverdue).length;
    },

    /** One row per week: its seven days, and the agenda and task bars clipped to that week and packed into lanes. */
    get weeks() {
        const today = toDateString(new Date());

        return Array.from({ length: this.dayCount / 7 }, (_, week) => {
            const first = week * 7;
            const days = Array.from({ length: 7 }, (__, offset) => {
                const date = addDays(this.from, first + offset);
                const key = toDateString(date);

                return {
                    key,
                    day: date.getDate(),
                    inMonth: date.getMonth() === this.month.getMonth(),
                    isToday: key === today,
                    isWeekend: date.getDay() === 0 || date.getDay() === 6,
                };
            });

            return {
                key: days[0].key,
                days,
                eventLanes: packLanes(this.inWeek(this.events, first)),
                taskLanes: packLanes(this.inWeek(this.visibleTasks, first)),
            };
        });
    },

    /** The items that touch the week starting at the given day index, placed on that week's seven columns. */
    inWeek(items, first) {
        const last = first + 6;

        return items
            .filter((item) => item.rawStart <= last && item.rawEnd >= first)
            .map((item) => {
                const startIndex = Math.max(item.rawStart, first) - first;
                const endIndex = Math.min(item.rawEnd, last) - first;

                return {
                    ...item,
                    startIndex,
                    endIndex,
                    continuesBefore: item.rawStart < first,
                    continuesAfter: item.rawEnd > last,
                    gridStyle: `grid-column: ${startIndex + 1} / ${endIndex + 2}; grid-row: 1;`,
                };
            });
    },

    /** Only the division is sent to the API; search, priority and status are applied to the loaded month. */
    onFiltersChanged(filters) {
        const divisionChanged = filters.division !== this.filters.division;
        this.filters = { ...filters };

        if (divisionChanged) this.load();
    },

    jumpToMonth(monthIndex, year) {
        this.month = new Date(Number(year), Number(monthIndex), 1);
        this.load();
    },

    shiftMonth(direction) {
        this.month = new Date(this.month.getFullYear(), this.month.getMonth() + direction, 1);
        this.load();
    },

    goToToday() {
        this.month = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
        this.load();
    },

    async load() {
        const ticket = ++this.ticket;
        this.loading = true;
        this.error = null;
        this.selectedEvent = null;

        try {
            // GET /api/tasks/schedule returns tasks and agenda in one list, told apart by type.
            const { data } = await http(this.config.urls.timeline, {
                query: { from: toDateString(this.from), to: toDateString(addDays(this.from, this.dayCount - 1)), division: this.filters.division },
            });

            if (ticket !== this.ticket) return;

            const today = toDateString(new Date());
            // A task with only one of its dates is shown on that single day; an agenda without end lasts one day.
            const span = (item) => ({ start: item.start_date ?? item.end_date, end: item.end_date ?? item.start_date });

            this.tasks = data.filter((item) => item.type === 'task').map((item) => {
                const { start, end } = span(item);

                return this.withPosition({
                    ...item,
                    id: item.task_id ?? item.id,
                    division: item.division ?? { name: '—' },
                    start,
                    end,
                    status_label: STATUS_LABELS[item.status] ?? item.status,
                    isOverdue: item.is_overdue ?? (item.status !== 'done' && end < today),
                });
            });
            this.events = data.filter((item) => item.type === 'event').map((item) => this.withPosition({ ...item, ...span(item) }));
        } catch (error) {
            if (ticket === this.ticket) this.error = error.message;
        } finally {
            if (ticket === this.ticket) this.loading = false;
        }
    },

    /** Day positions of the item's first and last day, counted from the first day in the grid. */
    withPosition(item) {
        const dayIndex = (value) => Math.round((parseDate(value) - this.from) / DAY_MS);

        return { ...item, rawStart: dayIndex(item.start), rawEnd: dayIndex(item.end) };
    },

    taskTitle(task) {
        const range = task.start === task.end ? this.formatDate(task.start) : `${this.formatDate(task.start)} – ${this.formatDate(task.end)}`;

        return `${task.title} · ${task.division.name} · ${range} · ${task.status_label}${task.isOverdue ? ' · Terlambat' : ''}`;
    },

    openTask(task) {
        if (!task.can_view_detail) {
            Alpine.store('toast').error('Anda hanya dapat melihat jadwal tugas ini, tanpa akses ke detailnya.');

            return;
        }

        this.$dispatch('open-task', { id: task.id });
    },
});
