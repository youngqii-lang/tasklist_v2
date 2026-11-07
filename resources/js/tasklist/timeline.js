import Alpine from 'alpinejs';
import { http } from '../http';
import { addDays, formatting, parseDate, startOfWeek, toDateString } from './format';

const MODE_DAYS = { weekly: 7, biweekly: 14 };
const dayFormatter = new Intl.DateTimeFormat('id-ID', { weekday: 'short' });
const rangeFormatter = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });

/**
 * Read-only weekly or two-weekly timeline of tasks and shared events across divisions.
 */
export default (config, divisionId = '') => ({
    ...formatting,
    config,
    divisionId,
    mode: 'weekly',
    from: startOfWeek(new Date()),
    tasks: [],
    events: [],
    loading: true,
    error: null,
    selectedEvent: null,
    ticket: 0,

    init() {
        this.load();
    },

    get days() {
        const today = toDateString(new Date());

        return Array.from({ length: MODE_DAYS[this.mode] }, (_, index) => {
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
        return `${rangeFormatter.format(this.from)} – ${rangeFormatter.format(addDays(this.from, MODE_DAYS[this.mode] - 1))}`;
    },

    get gridStyle() {
        return `grid-template-columns: repeat(${MODE_DAYS[this.mode]}, minmax(0, 1fr));`;
    },

    get isEmpty() {
        return this.tasks.length === 0 && this.events.length === 0;
    },

    onFiltersChanged(filters) {
        this.divisionId = filters.division_id;
        this.load();
    },

    setMode(mode) {
        this.mode = mode;
        this.load();
    },

    shift(direction) {
        this.from = addDays(this.from, direction * MODE_DAYS[this.mode]);
        this.load();
    },

    goToToday() {
        this.from = startOfWeek(new Date());
        this.load();
    },

    async load() {
        const ticket = ++this.ticket;
        this.loading = true;
        this.error = null;

        try {
            const { data } = await http(this.config.urls.timeline, {
                query: { start_date: toDateString(this.from), mode: this.mode, division_id: this.divisionId },
            });

            if (ticket !== this.ticket) return;

            this.tasks = data.tasks;
            this.events = data.events.map((event) => ({
                ...event,
                start: toDateString(new Date(event.start_at)),
                end: toDateString(new Date(event.end_at ?? event.start_at)),
            }));
        } catch (error) {
            if (ticket === this.ticket) this.error = error.message;
        } finally {
            if (ticket === this.ticket) this.loading = false;
        }
    },

    /** Place an item on the grid, clipped to the visible period. */
    placement(item) {
        const days = MODE_DAYS[this.mode];
        const dayIndex = (value) => Math.round((parseDate(value) - this.from) / 86400000);
        const start = Math.max(0, dayIndex(item.start));
        const end = Math.min(days - 1, dayIndex(item.end));

        return {
            style: `grid-column: ${start + 1} / ${end + 2};`,
            continuesBefore: dayIndex(item.start) < 0,
            continuesAfter: dayIndex(item.end) > days - 1,
        };
    },

    openTask(task) {
        if (!task.can_view_detail) {
            Alpine.store('toast').error('Anda hanya dapat melihat jadwal tugas ini, tanpa akses ke detailnya.');

            return;
        }

        this.$dispatch('open-task', { id: task.id });
    },
});
