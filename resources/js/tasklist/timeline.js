import Alpine from 'alpinejs';
import { http } from '../http';
import { matchesFilters, STATUS_LABELS } from './contract';
import { addDays, formatting, parseDate, startOfWeek, toDateString } from './format';

const MODE_DAYS = { weekly: 7, biweekly: 14 };
const DAY_MS = 86400000;
const dayFormatter = new Intl.DateTimeFormat('id-ID', { weekday: 'short' });
const rangeFormatter = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });

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
 * Read-only weekly or two-weekly timeline of tasks and shared events across divisions.
 */
export default (config, filters = {}) => ({
    ...formatting,
    config,
    filters: { ...filters },
    mode: 'weekly',
    from: startOfWeek(new Date()),
    compact: true,
    tasks: [],
    events: [],
    loading: true,
    error: null,
    selectedEvent: null,
    ticket: 0,

    init() {
        this.load();
    },

    get dayCount() {
        return MODE_DAYS[this.mode];
    },

    get days() {
        const today = toDateString(new Date());

        return Array.from({ length: this.dayCount }, (_, index) => {
            const date = addDays(this.from, index);

            return {
                key: toDateString(date),
                weekday: dayFormatter.format(date),
                day: date.getDate(),
                isToday: toDateString(date) === today,
                isWeekend: date.getDay() === 0 || date.getDay() === 6,
            };
        });
    },

    get periodLabel() {
        return `${rangeFormatter.format(this.from)} – ${rangeFormatter.format(addDays(this.from, this.dayCount - 1))}`;
    },

    get gridStyle() {
        return `grid-template-columns: repeat(${this.dayCount}, minmax(0, 1fr));`;
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

    get includesToday() {
        return this.days.some((day) => day.isToday);
    },

    get overdueCount() {
        return this.visibleTasks.filter((task) => task.isOverdue).length;
    },

    /** Rows of task bars: packed together in compact mode, one task per row otherwise. */
    get taskLanes() {
        return this.compact ? packLanes(this.visibleTasks) : this.visibleTasks.map((task) => [task]);
    },

    get eventLanes() {
        return this.compact ? packLanes(this.events) : this.events.map((event) => [event]);
    },

    /** Only the division is sent to the API; search, priority and status are applied to the loaded period. */
    onFiltersChanged(filters) {
        const divisionChanged = filters.division !== this.filters.division;
        this.filters = { ...filters };

        if (divisionChanged) this.load();
    },

    setMode(mode) {
        this.mode = mode;
        this.load();
    },

    shift(direction) {
        this.from = addDays(this.from, direction * this.dayCount);
        this.load();
    },

    goToToday() {
        this.from = startOfWeek(new Date());
        this.load();
    },

    /** Jump to the week that contains the picked date. */
    goToDate(value) {
        if (!value) return;

        this.from = startOfWeek(parseDate(value));
        this.load();
    },

    async load() {
        const ticket = ++this.ticket;
        this.loading = true;
        this.error = null;

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

                return this.withPlacement({
                    ...item,
                    id: item.task_id ?? item.id,
                    division: item.division ?? { name: '—' },
                    start,
                    end,
                    status_label: STATUS_LABELS[item.status] ?? item.status,
                    isOverdue: item.status !== 'done' && end < today,
                });
            });
            this.events = data.filter((item) => item.type === 'event').map((item) => this.withPlacement({ ...item, ...span(item) }));
        } catch (error) {
            if (ticket === this.ticket) this.error = error.message;
        } finally {
            if (ticket === this.ticket) this.loading = false;
        }
    },

    /** Work out once where an item sits on the grid, clipped to the visible period. */
    withPlacement(item) {
        const dayIndex = (value) => Math.round((parseDate(value) - this.from) / DAY_MS);
        const rawStart = dayIndex(item.start);
        const rawEnd = dayIndex(item.end);
        const startIndex = Math.max(0, rawStart);
        const endIndex = Math.min(this.dayCount - 1, rawEnd);

        return {
            ...item,
            startIndex,
            endIndex,
            continuesBefore: rawStart < 0,
            continuesAfter: rawEnd > this.dayCount - 1,
            gridStyle: `grid-column: ${startIndex + 1} / ${endIndex + 2}; grid-row: 1;`,
        };
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
