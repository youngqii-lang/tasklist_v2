import Alpine from 'alpinejs';
import { http } from '../http';
import { formatting } from './format';

const VIEWS = ['board', 'table', 'timeline', 'calendar'];
const DEFAULT_FILTERS = {
    search: '',
    division_id: '',
    pic_id: '',
    priority: '',
    status: '',
    date_field: '',
    date: '',
    month: '',
    year: '',
    date_from: '',
    date_to: '',
};
const PERIOD_KEYS = ['date', 'month', 'year', 'date_from', 'date_to'];
const emptyPeriod = () => ({ mode: '', date: '', monthNumber: '', monthYear: '', year: '', from: '', to: '' });
const monthNames = Array.from({ length: 12 }, (_, index) =>
    new Intl.DateTimeFormat('id-ID', { month: 'long' }).format(new Date(2000, index, 1)));
const DEFAULT_SORT = { sort: 'created_at', direction: 'desc' };
const BOARD_LIMIT = 100;
const TABLE_PAGE_SIZE = 15;

/**
 * The Tasklist page: shared filters, the Board and Table views (same API, same filters), the KPI summary and drag-and-drop.
 */
export default (config) => ({
    ...formatting,
    config,
    view: 'board',
    filters: { ...DEFAULT_FILTERS },
    period: emptyPeriod(),
    monthNames,
    highlightId: null,
    sort: DEFAULT_SORT.sort,
    direction: DEFAULT_SORT.direction,
    page: 1,
    board: { tasks: [], counts: {}, total: 0, loading: true, error: null },
    table: { tasks: [], meta: null, loading: true, error: null },
    summary: { data: null, loading: true, error: null },
    drag: { task: null, over: null },
    movingIds: {},
    revision: { open: false, task: null, note: '', error: null, saving: false },
    sequence: { board: 0, table: 0, summary: 0 },

    init() {
        this.readUrl();
        this.refresh();

        const taskId = new URLSearchParams(window.location.search).get('task');

        if (taskId) {
            this.$nextTick(() => this.openTask(Number(taskId)));
        }
    },

    /** The date basis alone filters nothing, so it does not count as an active filter. */
    get hasActiveFilters() {
        return Object.entries(this.filters).some(([key, value]) => key !== 'date_field' && value !== '');
    },

    get hasPeriod() {
        return PERIOD_KEYS.some((key) => this.filters[key] !== '');
    },

    get yearOptions() {
        const current = new Date().getFullYear();

        return Array.from({ length: 8 }, (_, index) => current - 5 + index);
    },

    get usesTaskFilters() {
        return this.view === 'board' || this.view === 'table';
    },

    get emptyMessage() {
        return this.hasActiveFilters ? 'Tidak ada tugas yang sesuai dengan filter.' : 'Belum ada tugas yang tersedia.';
    },

    statusLabel(status) {
        return this.config.statuses.find((option) => option.value === status)?.label ?? status;
    },

    divisionName(id) {
        return this.config.divisions.find((division) => String(division.id) === String(id))?.name ?? 'Divisi';
    },

    column(status) {
        return this.board.tasks.filter((task) => task.status === status);
    },

    activeFilters() {
        return Object.fromEntries(Object.entries(this.filters).filter(([key, value]) =>
            value !== '' && (key !== 'date_field' || this.hasPeriod)));
    },

    readUrl() {
        const params = new URLSearchParams(window.location.search);

        this.view = VIEWS.includes(params.get('view')) ? params.get('view') : 'board';
        Object.keys(DEFAULT_FILTERS).forEach((key) => {
            this.filters[key] = params.get(key) ?? '';
        });
        this.sort = params.get('sort') ?? DEFAULT_SORT.sort;
        this.direction = params.get('direction') === 'asc' ? 'asc' : 'desc';
        this.page = Math.max(1, Number(params.get('page')) || 1);
        this.syncPeriodFromFilters();
    },

    /** Rebuild the period controls from the API filters (after reading the URL). */
    syncPeriodFromFilters() {
        const filters = this.filters;
        const [monthYear = '', monthNumber = ''] = filters.month.split('-');

        this.period = {
            ...emptyPeriod(),
            mode: filters.date ? 'date' : filters.month ? 'month' : filters.year ? 'year' : (filters.date_from || filters.date_to) ? 'range' : '',
            date: filters.date,
            monthNumber: monthNumber ? String(Number(monthNumber)) : '',
            monthYear,
            year: filters.year,
            from: filters.date_from,
            to: filters.date_to,
        };
    },

    setPeriodMode(mode) {
        const now = new Date();
        this.period = { ...emptyPeriod(), mode };

        if (mode === 'month') {
            this.period.monthNumber = String(now.getMonth() + 1);
            this.period.monthYear = String(now.getFullYear());
        }

        if (mode === 'year') this.period.year = String(now.getFullYear());

        if (mode === '' || mode === 'month' || mode === 'year') this.applyPeriod();
    },

    /** Turn the period controls into the API filters; only one kind of period is active at a time. */
    applyPeriod() {
        const period = this.period;

        if (period.mode === 'range' && period.from && period.to && period.to < period.from) {
            Alpine.store('toast').error('Tanggal akhir tidak boleh sebelum tanggal awal.');

            return;
        }

        PERIOD_KEYS.forEach((key) => {
            this.filters[key] = '';
        });

        if (period.mode === 'date') this.filters.date = period.date;
        if (period.mode === 'month' && period.monthNumber && period.monthYear) {
            this.filters.month = `${period.monthYear}-${String(period.monthNumber).padStart(2, '0')}`;
        }
        if (period.mode === 'year') this.filters.year = period.year;
        if (period.mode === 'range') {
            this.filters.date_from = period.from;
            this.filters.date_to = period.to;
        }

        this.applyFilters();
    },

    /** Keep the view and filters in the address bar, so a reload or a shared link shows the same data. */
    syncUrl() {
        const params = new URLSearchParams(window.location.search);
        const state = {
            view: this.view === 'board' ? '' : this.view,
            ...this.filters,
            sort: this.sort === DEFAULT_SORT.sort ? '' : this.sort,
            direction: this.direction === DEFAULT_SORT.direction ? '' : this.direction,
            page: this.page > 1 && this.view === 'table' ? this.page : '',
        };

        Object.entries(state).forEach(([key, value]) => (value === '' ? params.delete(key) : params.set(key, value)));

        const query = params.toString();
        window.history.replaceState({}, '', query ? `?${query}` : window.location.pathname);
    },

    setView(view) {
        this.view = view;
        this.syncUrl();
        this.refresh();
    },

    applyFilters() {
        this.page = 1;
        this.syncUrl();
        this.refresh();
        this.$dispatch('tasklist-filters-changed', { filters: { ...this.filters } });
    },

    resetFilters() {
        this.filters = { ...DEFAULT_FILTERS };
        this.period = emptyPeriod();
        this.sort = DEFAULT_SORT.sort;
        this.direction = DEFAULT_SORT.direction;
        this.applyFilters();
    },

    refresh() {
        if (!this.usesTaskFilters) return;

        this.loadSummary();
        this.view === 'board' ? this.loadBoard() : this.loadTable();
    },

    /** Run a request and ignore its answer when a newer request of the same kind was started meanwhile. */
    async latest(kind, request, onSuccess) {
        const ticket = ++this.sequence[kind];
        const state = this[kind];

        state.loading = true;
        state.error = null;

        try {
            const response = await request();
            if (ticket === this.sequence[kind]) onSuccess(response);
        } catch (error) {
            if (ticket === this.sequence[kind]) state.error = error.message;
        } finally {
            if (ticket === this.sequence[kind]) state.loading = false;
        }
    },

    loadBoard() {
        return this.latest(
            'board',
            () => http(this.config.urls.tasks, {
                query: { ...this.activeFilters(), sort: this.sort, direction: this.direction, per_page: BOARD_LIMIT },
            }),
            (response) => {
                this.board.tasks = response.data;
                this.board.counts = response.meta.status_counts;
                this.board.total = response.meta.total;
            },
        );
    },

    loadTable() {
        return this.latest(
            'table',
            () => http(this.config.urls.tasks, {
                query: { ...this.activeFilters(), sort: this.sort, direction: this.direction, per_page: TABLE_PAGE_SIZE, page: this.page },
            }),
            (response) => {
                this.table.tasks = response.data;
                this.table.meta = response.meta;

                if (response.meta.last_page > 0 && this.page > response.meta.last_page) {
                    this.goToPage(response.meta.last_page);
                }
            },
        );
    },

    loadSummary() {
        return this.latest(
            'summary',
            () => http(this.config.urls.summary, { query: this.activeFilters() }),
            (response) => {
                this.summary.data = response.data;
            },
        );
    },

    sortBy(column) {
        if (this.sort === column) {
            this.direction = this.direction === 'asc' ? 'desc' : 'asc';
        } else {
            this.sort = column;
            this.direction = ['title', 'deadline', 'start_date'].includes(column) ? 'asc' : 'desc';
        }

        this.page = 1;
        this.syncUrl();
        this.refresh();
    },

    sortIndicator(column) {
        if (this.sort !== column) return '';

        return this.direction === 'asc' ? '▲' : '▼';
    },

    goToPage(page) {
        this.page = page;
        this.syncUrl();
        this.loadTable();
    },

    /**
     * Arrive on the Board from the calendar preview: clear filters that could hide the task (keeping its division),
     * highlight its card and open its detail. Navigation never grants extra rights; the detail follows the API abilities.
     */
    async focusTaskOnBoard(preview) {
        this.filters = { ...DEFAULT_FILTERS, division_id: String(preview.board.filters.division_id ?? '') };
        this.period = emptyPeriod();
        this.sort = DEFAULT_SORT.sort;
        this.direction = DEFAULT_SORT.direction;
        this.page = 1;
        this.view = 'board';
        this.highlightId = preview.id;
        this.syncUrl();
        this.loadSummary();
        await this.loadBoard();

        if (!this.board.tasks.some((task) => task.id === preview.id)) {
            this.filters.search = preview.title.slice(0, 150);
            this.syncUrl();
            this.loadSummary();
            await this.loadBoard();
        }

        this.openTask(preview.id);
        this.$nextTick(() => document.getElementById(`task-card-${preview.id}`)
            ?.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' }));
        setTimeout(() => {
            if (this.highlightId === preview.id) this.highlightId = null;
        }, 8000);
    },

    openTask(id) {
        this.$dispatch('open-task', { id });
    },

    openCreateForm() {
        this.$dispatch('open-task-form', { task: null });
    },

    openEditForm(task) {
        this.$dispatch('open-task-form', { task });
    },

    /* Drag-and-drop on the Board ------------------------------------------------------------ */

    canDrag(task) {
        return task.abilities.transitions.length > 0 && !this.movingIds[task.id];
    },

    onDragStart(event, task) {
        this.drag.task = task;
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(task.id));
    },

    onDragOver(event, status) {
        if (!this.drag.task || this.drag.task.status === status) return;

        event.preventDefault();
        this.drag.over = status;
    },

    onDragLeave(status) {
        if (this.drag.over === status) this.drag.over = null;
    },

    onDragEnd() {
        this.drag.task = null;
        this.drag.over = null;
    },

    isDropAllowed(status) {
        return Boolean(this.drag.task?.abilities.transitions.includes(status));
    },

    onDrop(status) {
        const task = this.drag.task;
        this.onDragEnd();

        if (!task || task.status === status) return;

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

    /** Explain why a drop is refused, mirroring the workflow rules of the API. */
    rejectionReason(task, status) {
        if (task.abilities.transitions.length === 0) {
            return 'Anda tidak memiliki kewenangan untuk memindahkan tugas ini.';
        }

        if (task.status === 'in_progress' && status === 'done' && task.requires_review) {
            return 'Tugas ini wajib review, kirim ke Ready Review terlebih dahulu.';
        }

        return `Perpindahan dari ${this.statusLabel(task.status)} ke ${this.statusLabel(status)} tidak diizinkan untuk Anda.`;
    },

    /** Move the card at once, then save; on failure the card returns to its previous column. */
    async moveTask(task, status, note = null) {
        const previousStatus = task.status;

        this.movingIds[task.id] = true;
        this.setCardStatus(task, status);

        try {
            const response = await http(`${this.config.urls.tasks}/${task.id}/status`, {
                method: 'PATCH',
                body: { status, version: task.version, note },
            });

            Alpine.store('toast').success(response.message ?? 'Status tugas berhasil diperbarui.');
            this.$dispatch('tasks-changed', { id: task.id });

            return true;
        } catch (error) {
            this.setCardStatus(task, previousStatus);
            Alpine.store('toast').error(error.errors?.note?.[0] ?? error.message);

            if (error.status === 409) this.refresh();

            return false;
        } finally {
            delete this.movingIds[task.id];
        }
    },

    setCardStatus(task, status) {
        this.board.counts[task.status] = Math.max(0, (this.board.counts[task.status] ?? 0) - 1);
        this.board.counts[status] = (this.board.counts[status] ?? 0) + 1;
        task.status = status;
        task.status_label = this.statusLabel(status);
    },

    async submitRevision() {
        if (this.revision.saving) return;

        if (!this.revision.note.trim()) {
            this.revision.error = 'Catatan revisi wajib diisi.';

            return;
        }

        this.revision.saving = true;
        const moved = await this.moveTask(this.revision.task, 'backlog', this.revision.note.trim());
        this.revision.saving = false;

        if (moved) this.revision.open = false;
    },
});
