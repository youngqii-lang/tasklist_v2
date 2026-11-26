import { http } from '../http';
import { matchesFilters, normalizeTask } from './contract';
import { addDays, formatting, startOfWeek, toDateString } from './format';

const monthFormatter = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' });
const longDateFormatter = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
const monthNames = Array.from({ length: 12 }, (_, index) =>
    new Intl.DateTimeFormat('id-ID', { month: 'long' }).format(new Date(2000, index, 1)));
const ARROW_DAYS = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };

/**
 * Monthly calendar of shared events and deadline markers, with the accessible deadlines of the selected date below.
 */
export default (config, filters = {}) => ({
    ...formatting,
    config,
    filters: { ...filters },
    month: new Date(new Date().getFullYear(), new Date().getMonth(), 1),
    selectedDate: toDateString(new Date()),
    events: [],
    monthDeadlines: [],
    loading: true,
    error: null,
    tickets: { month: 0, preview: 0 },
    preview: { open: false, loading: false, data: null, error: null },
    weekdays: ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],
    monthNames,

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

    get yearOptions() {
        const current = new Date().getFullYear();
        const years = Array.from({ length: 7 }, (_, index) => current - 3 + index);

        return years.includes(this.month.getFullYear()) ? years : [...years, this.month.getFullYear()].sort();
    },

    /** The deadlines of the month that pass the Tasklist filters. */
    get visibleDeadlines() {
        return this.monthDeadlines.filter((task) => matchesFilters(task, this.filters, this.config.user?.id));
    },

    /** Deadline totals per date, for the day cells. */
    get deadlines() {
        return this.visibleDeadlines.reduce((days, task) => {
            const date = task.deadline.slice(0, 10);
            days[date] ??= { total: 0, overdue: 0 };
            days[date].total += 1;
            days[date].overdue += task.is_overdue ? 1 : 0;

            return days;
        }, {});
    },

    /** The agenda and deadlines of the selected date, taken from the month already loaded. */
    get day() {
        const date = this.selectedDate;

        return {
            events: this.events.filter((event) => event.start <= date && event.end >= date),
            deadlines: this.visibleDeadlines.filter((task) => task.deadline.slice(0, 10) === date),
            loading: this.loading,
        };
    },

    /** Filters that apply here although their controls are hidden on this view. */
    get hasHiddenFilters() {
        return ['search', 'priority', 'status', 'pic_id'].some((key) => this.filters[key]);
    },

    /** Totals for the shown month, so busy or late months stand out before opening a date. */
    get monthSummary() {
        const deadlines = Object.values(this.deadlines);

        return {
            deadlines: deadlines.reduce((sum, day) => sum + day.total, 0),
            overdue: deadlines.reduce((sum, day) => sum + day.overdue, 0),
            events: this.events.length,
        };
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
                isWeekend: date.getDay() === 0 || date.getDay() === 6,
                deadline: this.deadlines[key] ?? null,
                events: this.events.filter((event) => event.start <= key && event.end >= key),
            };
        });
    },

    /** Only the division is sent to the API; the other filters are applied to the loaded month. */
    onFiltersChanged(filters) {
        const divisionChanged = filters.division !== this.filters.division;
        this.filters = { ...filters };

        if (divisionChanged && this.config.user.is_admin) this.load();
    },

    jumpToMonth(monthIndex, year) {
        this.month = new Date(Number(year), Number(monthIndex), 1);
        this.load({ keepSelection: false });
    },

    /** Arrow keys move the selected date by a day or a week, Page Up/Down by a month. */
    onGridKeydown(event) {
        let target = null;

        if (ARROW_DAYS[event.key] !== undefined) {
            target = addDays(this.selectedAsDate(), ARROW_DAYS[event.key]);
        } else if (event.key === 'PageUp' || event.key === 'PageDown') {
            const current = this.selectedAsDate();
            target = new Date(current.getFullYear(), current.getMonth() + (event.key === 'PageUp' ? -1 : 1), current.getDate());
        }

        if (!target) return;

        event.preventDefault();
        const key = toDateString(target);
        this.selectDate(key);
        this.$nextTick(() => this.$root.querySelector(`[data-date="${key}"]`)?.focus());
    },

    selectedAsDate() {
        const [year, month, day] = this.selectedDate.split('-').map(Number);

        return new Date(year, month - 1, day);
    },

    /** Open every deadline of the selected date in the Table, where they can be sorted and paged. */
    showInTable() {
        this.$dispatch('show-deadlines-in-table', { date: this.selectedDate });
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

        const ticket = ++this.tickets.month;
        this.loading = true;
        this.error = null;

        try {
            // Only the Admin narrows the calendar by division (TASK-11); others keep the shared agenda of all divisions.
            const { data } = await http(this.config.urls.calendar, {
                query: { month: this.monthKey, division: this.config.user.is_admin ? this.filters.division : '' },
            });

            if (ticket !== this.tickets.month) return;

            this.events = data.events.map((event) => this.withDays(event));
            this.monthDeadlines = data.deadlines.map((task) => normalizeTask(task, this.config.user.id));
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
        }
    },

    /** An agenda carries plain dates; one without an end lasts a single day. */
    withDays(event) {
        return { ...event, start: event.start_date, end: event.end_date ?? event.start_date };
    },

    /** Show the latest summary of a task in a pop-up; the calendar keeps its month and selected date. */
    async openPreview(task) {
        const ticket = ++this.tickets.preview;
        this.preview = { open: true, loading: true, data: null, error: null };

        try {
            const { data } = await http(`${this.config.urls.tasks}/${task.id}/preview`);
            if (ticket === this.tickets.preview) this.preview.data = normalizeTask(data, this.config.user.id);
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
