import { http } from '../http';
import { addDays, formatting, startOfWeek, toDateString } from './format';

const monthFormatter = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' });
const longDateFormatter = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
const timeFormatter = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' });

/**
 * Monthly calendar of shared events and deadline markers, with the accessible deadlines of the selected date below.
 */
export default (config, divisionId = '') => ({
    ...formatting,
    config,
    divisionId,
    month: new Date(new Date().getFullYear(), new Date().getMonth(), 1),
    selectedDate: toDateString(new Date()),
    events: [],
    deadlines: {},
    loading: true,
    error: null,
    day: { events: [], deadlines: [], loading: true, error: null },
    tickets: { month: 0, day: 0, preview: 0 },
    preview: { open: false, loading: false, data: null, error: null },
    weekdays: ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],

    init() {
        this.load();
    },

    get monthLabel() {
        return monthFormatter.format(this.month);
    },

    get selectedLabel() {
        const [year, month, day] = this.selectedDate.split('-').map(Number);

        return longDateFormatter.format(new Date(year, month - 1, day));
    },

    get monthKey() {
        return toDateString(this.month).slice(0, 7);
    },

    /** Every day shown in the month grid, padded to whole weeks starting on Monday. */
    get cells() {
        const first = startOfWeek(this.month);
        const last = new Date(this.month.getFullYear(), this.month.getMonth() + 1, 0);
        const total = Math.ceil((Math.round((last - first) / 86400000) + 1) / 7) * 7;
        const today = toDateString(new Date());

        return Array.from({ length: total }, (_, index) => {
            const date = addDays(first, index);
            const key = toDateString(date);

            return {
                key,
                day: date.getDate(),
                inMonth: date.getMonth() === this.month.getMonth(),
                isToday: key === today,
                deadline: this.deadlines[key] ?? null,
                events: this.events.filter((event) => event.start <= key && event.end >= key),
            };
        });
    },

    onFiltersChanged(filters) {
        this.divisionId = filters.division_id;
        this.load();
    },

    shiftMonth(direction) {
        this.month = new Date(this.month.getFullYear(), this.month.getMonth() + direction, 1);
        this.load({ keepSelection: false });
    },

    goToToday() {
        this.month = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
        this.selectedDate = toDateString(new Date());
        this.load();
    },

    async load({ keepSelection = true } = {}) {
        if (!keepSelection && !this.selectedDate.startsWith(this.monthKey)) {
            this.selectedDate = `${this.monthKey}-01`;
        }

        this.loadDay();

        const ticket = ++this.tickets.month;
        this.loading = true;
        this.error = null;

        try {
            const { data } = await http(this.config.urls.calendar, {
                query: { month: this.monthKey, division_id: this.divisionId },
            });

            if (ticket !== this.tickets.month) return;

            this.events = data.events.map((event) => this.withDays(event));
            this.deadlines = Object.fromEntries(data.deadlines.map((deadline) => [deadline.date, deadline]));
        } catch (error) {
            if (ticket === this.tickets.month) this.error = error.message;
        } finally {
            if (ticket === this.tickets.month) this.loading = false;
        }
    },

    selectDate(key) {
        this.selectedDate = key;

        if (!key.startsWith(this.monthKey)) {
            const [year, month] = key.split('-').map(Number);
            this.month = new Date(year, month - 1, 1);
            this.load();

            return;
        }

        this.loadDay();
    },

    async loadDay() {
        const ticket = ++this.tickets.day;
        this.day.loading = true;
        this.day.error = null;

        try {
            const { data } = await http(this.config.urls.calendar_deadlines, {
                query: { date: this.selectedDate, division_id: this.divisionId },
            });

            if (ticket !== this.tickets.day) return;

            this.day.events = data.events.map((event) => this.withDays(event));
            this.day.deadlines = data.deadlines;
        } catch (error) {
            if (ticket === this.tickets.day) this.day.error = error.message;
        } finally {
            if (ticket === this.tickets.day) this.day.loading = false;
        }
    },

    withDays(event) {
        return {
            ...event,
            start: toDateString(new Date(event.start_at)),
            end: toDateString(new Date(event.end_at ?? event.start_at)),
        };
    },

    eventTime(event) {
        const start = timeFormatter.format(new Date(event.start_at));

        return event.end_at ? `${start} – ${timeFormatter.format(new Date(event.end_at))}` : start;
    },

    /** Show the latest summary of a task in a pop-up; the calendar keeps its month and selected date. */
    async openPreview(task) {
        const ticket = ++this.tickets.preview;
        this.preview = { open: true, loading: true, data: null, error: null };

        try {
            const { data } = await http(`${this.config.urls.tasks}/${task.id}/preview`);
            if (ticket === this.tickets.preview) this.preview.data = data;
        } catch (error) {
            if (ticket === this.tickets.preview) this.preview.error = error.message;
        } finally {
            if (ticket === this.tickets.preview) this.preview.loading = false;
        }
    },

    closePreview() {
        this.preview.open = false;
    },

    viewOnBoard() {
        const preview = this.preview.data;
        this.closePreview();
        this.$dispatch('focus-task-on-board', preview);
    },
});
