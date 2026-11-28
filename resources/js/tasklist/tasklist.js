import Alpine from 'alpinejs';
import { http } from '../http';
import boardDrag from './board-drag';
import { normalizeTask, reviewDecisionFor, STATUS_LABELS } from './contract';
import { formatting } from './format';

const VIEWS = ['board', 'table', 'timeline', 'calendar'];
const SCOPE_MY_TASKS = 'my_tasks';
const DEFAULT_FILTERS = {
    scope: '',
    search: '',
    division: '',
    pic_id: '',
    priority: '',
    status: '',
    filter_mode: '',
    date_field: '',
    date: '',
    month: '',
    year: '',
    start_date: '',
    end_date: '',
};
const BASIC_FILTER_KEYS = ['search', 'division', 'pic_id', 'priority', 'status'];
const PERIOD_KEYS = ['filter_mode', 'date', 'month', 'year', 'start_date', 'end_date'];
const PERIOD_PARAMS = { date: ['date'], month: ['month', 'year'], year: ['year'], range: ['start_date', 'end_date'] };
const emptyPeriod = () => ({ mode: '', date: '', monthNumber: '', monthYear: '', year: '', from: '', to: '' });
const monthNames = Array.from({ length: 12 }, (_, index) =>
    new Intl.DateTimeFormat('id-ID', { month: 'long' }).format(new Date(2000, index, 1)));
const DEFAULT_SORT = { sort: 'created_at', direction: 'desc' };
const TABLE_PAGE_SIZES = [15, 25, 50, 100];

/**
 * The Tasklist page: shared filters, the Board and Table views (same API, same filters), the KPI summary and drag-and-drop.
 * Data comes from the Tasklist API (/api/tasks); every right follows the flags the API returns per task.
 */
export default (config) => ({
    ...formatting,
    ...boardDrag,
    config,
    meta: { divisions: [], pics: [], default_division: null, role_code: null, loaded: false },
    view: 'board',
    filters: { ...DEFAULT_FILTERS },
    period: emptyPeriod(),
    periodErrors: {},
    monthNames,
    highlightId: null,
    sort: DEFAULT_SORT.sort,
    direction: DEFAULT_SORT.direction,
    page: 1,
    perPage: TABLE_PAGE_SIZES[0],
    tablePageSizes: TABLE_PAGE_SIZES,
    tableMenu: { task: null, top: 0, right: 0 },
    board: { tasks: [], counts: {}, total: 0, loading: true, error: null },
    table: { tasks: [], meta: null, loading: true, error: null },
    summary: { data: null, loading: true, error: null },
    movingIds: {},
    revision: { open: false, task: null, note: '', error: null, saving: false },
    sequence: { board: 0, table: 0, summary: 0 },

    async init() {
        this.initBoardDrag();
        const hasDivisionInUrl = this.readUrl();

        await this.loadMeta();

        // A Manager starts on the division they manage; a division in the URL always wins.
        if (!hasDivisionInUrl && this.meta.default_division) {
            this.filters.division = this.meta.default_division;
            this.syncUrl();
        }

        this.refresh();
        this.$dispatch('tasklist-filters-changed', { filters: { ...this.filters } });

        const taskId = new URLSearchParams(window.location.search).get('task');

        if (taskId) {
            this.openTask(Number(taskId));
        }
    },

    /** Divisions and PICs for the filters, and the role, from GET /api/tasks/meta. */
    async loadMeta() {
        try {
            const { data } = await http(this.config.urls.meta);
            this.meta = { ...data, loaded: true };
        } catch (error) {
            this.meta = { ...this.meta, loaded: true };
            Alpine.store('toast').error(`Pilihan filter gagal dimuat. ${error.message}`);
        }
    },

    /** The date basis alone filters nothing, and the task scope is a tab, so neither counts as an active filter. */
    get hasActiveFilters() {
        return Object.entries(this.filters).some(([key, value]) => !['date_field', 'scope'].includes(key) && value !== '');
    },

    /** How many filters narrow the list: each basic filter and the period count once. */
    get activeFilterCount() {
        if (!this.usesTaskFilters) return 0;

        return BASIC_FILTER_KEYS.filter((key) => this.filters[key] !== '').length + (this.filters.filter_mode ? 1 : 0);
    },

    get isMyTasks() {
        return this.filters.scope === SCOPE_MY_TASKS;
    },

    get hasPeriod() {
        return this.filters.filter_mode !== '';
    },

    get yearOptions() {
        const current = new Date().getFullYear();

        return Array.from({ length: 8 }, (_, index) => current - 5 + index);
    },

    get usesTaskFilters() {
        return this.view === 'board' || this.view === 'table';
    },

    get emptyMessage() {
        if (this.hasActiveFilters) return 'Tidak ada tugas yang sesuai dengan filter.';

        return this.isMyTasks ? 'Belum ada tugas yang ditugaskan kepada Anda.' : 'Belum ada tugas yang tersedia.';
    },

    statusLabel(status) {
        return STATUS_LABELS[status] ?? status;
    },

    divisionName(code) {
        return this.meta.divisions.find((division) => division.code_division === code)?.name ?? 'Divisi';
    },

    column(status) {
        return this.board.tasks.filter((task) => task.status === status);
    },

    /** The list filters as GET /api/tasks expects them: only set values, and only the parameters of the active period. */
    listQuery() {
        const filters = this.filters;
        const query = {};

        if (this.isMyTasks) query.scope = SCOPE_MY_TASKS;

        BASIC_FILTER_KEYS.forEach((key) => {
            if (filters[key] !== '') query[key] = filters[key];
        });

        if (filters.filter_mode) {
            query.filter_mode = filters.filter_mode;
            query.date_field = filters.date_field || 'deadline';
            PERIOD_PARAMS[filters.filter_mode].forEach((key) => {
                query[key] = filters[key];
            });
        }

        return query;
    },

    /** The same filters for the monitoring KPIs, so the summary matches the Board and Table. */
    summaryQuery() {
        const { scope, ...filters } = this.listQuery();

        return { ...filters, scope: 'tasklist', task_scope: scope ?? 'all_tasks' };
    },

    /** @returns {boolean} whether the URL chose a division */
    readUrl() {
        const params = new URLSearchParams(window.location.search);

        this.view = VIEWS.includes(params.get('view')) ? params.get('view') : 'board';
        Object.keys(DEFAULT_FILTERS).forEach((key) => {
            this.filters[key] = params.get(key) ?? '';
        });

        if (this.filters.scope !== SCOPE_MY_TASKS) this.filters.scope = '';
        if (!PERIOD_PARAMS[this.filters.filter_mode]) this.clearPeriodFilters();

        this.sort = params.get('sort') ?? DEFAULT_SORT.sort;
        this.direction = params.get('direction') === 'asc' ? 'asc' : 'desc';
        this.page = Math.max(1, Number(params.get('page')) || 1);
        this.perPage = TABLE_PAGE_SIZES.includes(Number(params.get('per_page'))) ? Number(params.get('per_page')) : TABLE_PAGE_SIZES[0];
        this.syncPeriodFromFilters();

        return params.has('division');
    },

    clearPeriodFilters() {
        PERIOD_KEYS.forEach((key) => {
            this.filters[key] = '';
        });
    },

    /** Rebuild the period controls from the filters (after reading the URL). */
    syncPeriodFromFilters() {
        const filters = this.filters;
        const mode = filters.filter_mode;

        this.period = {
            ...emptyPeriod(),
            mode,
            date: filters.date,
            monthNumber: mode === 'month' ? filters.month : '',
            monthYear: mode === 'month' ? filters.year : '',
            year: mode === 'year' ? filters.year : '',
            from: filters.start_date,
            to: filters.end_date,
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

    /** Turn the period controls into the API period filter; only one kind of period is active at a time. */
    applyPeriod() {
        const period = this.period;
        this.periodErrors = {};

        if (period.mode === 'range' && period.from && period.to && period.to < period.from) {
            this.periodErrors = { end_date: ['Tanggal akhir tidak boleh sebelum tanggal awal.'] };

            return;
        }

        const values = {
            date: { date: period.date },
            month: { month: period.monthNumber, year: period.monthYear },
            year: { year: period.year },
            range: { start_date: period.from, end_date: period.to },
        }[period.mode] ?? {};

        // A period is only sent once every value it needs is filled in.
        const isComplete = period.mode !== '' && Object.values(values).every((value) => value !== '');

        this.clearPeriodFilters();

        if (isComplete) {
            this.filters.filter_mode = period.mode;
            Object.assign(this.filters, values);
        }

        if (period.mode === '' || isComplete) this.applyFilters();
    },

    /** Keep the view and filters in the address bar, so a reload or a shared link shows the same data. */
    syncUrl() {
        const params = new URLSearchParams(window.location.search);
        const state = {
            view: this.view === 'board' ? '' : this.view,
            ...this.filters,
            date_field: this.filters.filter_mode ? this.filters.date_field : '',
            sort: this.sort === DEFAULT_SORT.sort ? '' : this.sort,
            direction: this.direction === DEFAULT_SORT.direction ? '' : this.direction,
            page: this.page > 1 && this.view === 'table' ? this.page : '',
            per_page: this.perPage !== TABLE_PAGE_SIZES[0] ? this.perPage : '',
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

    /** Switch between "Semua Tugas" and "Tugas Saya"; the filters stay. */
    setScope(scope) {
        const value = scope === SCOPE_MY_TASKS ? SCOPE_MY_TASKS : '';

        if (this.filters.scope === value) return;

        this.filters.scope = value;
        this.applyFilters();
    },

    /** Reset keeps the chosen scope tab; only the filters below it are cleared. */
    resetFilters() {
        this.filters = { ...DEFAULT_FILTERS, scope: this.filters.scope };
        this.period = emptyPeriod();
        this.periodErrors = {};
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
            if (ticket !== this.sequence[kind]) return;

            state.error = error.message;
            this.showPeriodErrors(error);
        } finally {
            if (ticket === this.sequence[kind]) state.loading = false;
        }
    },

    /** A 422 on the period parameters is shown next to the period controls. */
    showPeriodErrors(error) {
        const fields = ['filter_mode', 'date_field', ...PERIOD_KEYS];
        const periodErrors = Object.fromEntries(Object.entries(error.errors ?? {}).filter(([field]) => fields.includes(field)));

        if (Object.keys(periodErrors).length) this.periodErrors = periodErrors;
    },

    normalize(tasks) {
        return tasks.map((task) => normalizeTask(task, this.config.user.id));
    },

    /** The Board gets every matching task at once (no per_page), with the counts per status. */
    loadBoard() {
        return this.latest(
            'board',
            () => http(this.config.urls.tasks, { query: { ...this.listQuery(), sort: this.sort, direction: this.direction } }),
            (response) => {
                this.board.tasks = this.normalize(response.data);
                this.board.counts = response.meta?.status_counts ?? {};
                this.board.total = this.board.tasks.length;
            },
        );
    },

    loadTable() {
        return this.latest(
            'table',
            () => http(this.config.urls.tasks, {
                query: { ...this.listQuery(), sort: this.sort, direction: this.direction, per_page: this.perPage, page: this.page },
            }),
            (response) => {
                this.table.tasks = this.normalize(response.data);
                this.table.meta = response.meta;

                if (response.meta.last_page > 0 && this.page > response.meta.last_page) {
                    this.goToPage(response.meta.last_page);
                }
            },
        );
    },

    /** KPIs from GET /api/tasks/monitoring?scope=tasklist, mapped to the keys the summary cards use. */
    loadSummary() {
        return this.latest(
            'summary',
            () => http(this.config.urls.summary, { query: this.summaryQuery() }),
            ({ data }) => {
                const metrics = data.metrics;

                this.summary.data = {
                    scope: data.scope,
                    completion_rate: metrics.completion_rate,
                    metrics: {
                        total: metrics.total_tasks,
                        backlog: metrics.backlog,
                        todo: metrics.todo,
                        in_progress: metrics.on_process,
                        ready_review: metrics.ready_review,
                        on_review: metrics.on_review,
                        completed: metrics.completed,
                        unfinished: metrics.unfinished,
                        overdue: metrics.overdue,
                        due_today: metrics.due_today,
                        urgent: metrics.urgent,
                    },
                };
            },
        );
    },

    /** Clicking the sorted column again flips the direction; another column starts in its natural direction. */
    sortBy(column) {
        if (this.sort === column) {
            this.direction = this.direction === 'asc' ? 'desc' : 'asc';
            this.page = 1;
            this.syncUrl();
            this.refresh();

            return;
        }

        this.setSort(column);
    },

    setSort(column) {
        this.sort = column;
        this.direction = ['title', 'deadline', 'start_date', 'division', 'pic'].includes(column) ? 'asc' : 'desc';
        this.page = 1;
        this.syncUrl();
        this.refresh();
    },

    sortIndicator(column) {
        if (this.sort !== column) return '';

        return this.direction === 'asc' ? '▲' : '▼';
    },

    goToPage(page) {
        const lastPage = this.table.meta?.last_page ?? 1;

        this.page = Math.min(Math.max(1, page), Math.max(1, lastPage));
        this.syncUrl();
        this.loadTable();
        document.getElementById('table-view')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    },

    setPerPage(size) {
        this.perPage = Number(size);
        this.page = 1;
        this.syncUrl();
        this.loadTable();
    },

    /** "1–15 dari 120" for the current table page. */
    get tableRange() {
        const meta = this.table.meta;

        if (!meta || meta.total === 0) return '';

        return `${meta.from}–${meta.to} dari ${meta.total}`;
    },

    /** Page numbers around the current page, with gaps marked as null: 1 … 4 5 [6] 7 8 … 20. */
    get tablePages() {
        const last = this.table.meta?.last_page ?? 1;
        const current = this.page;
        const pages = [];

        for (let number = 1; number <= last; number++) {
            if (number === 1 || number === last || Math.abs(number - current) <= 1) {
                pages.push(number);
            } else if (pages[pages.length - 1] !== null) {
                pages.push(null);
            }
        }

        return pages;
    },

    /** Clicking anywhere on a row opens the task, except on its own buttons and links. */
    onTableRowClick(event, task) {
        if (event.target.closest('button, a, [role="menu"]')) return;

        this.openTask(task.id);
    },

    /** The row menu is positioned on the page, so the scrollable table never cuts it off. */
    toggleTableMenu(event, task) {
        if (this.tableMenu.task?.id === task.id) {
            this.closeTableMenu();

            return;
        }

        const rect = event.currentTarget.getBoundingClientRect();
        this.tableMenu = { task, top: rect.bottom + 4, right: Math.max(8, window.innerWidth - rect.right) };
    },

    closeTableMenu() {
        this.tableMenu = { task: null, top: 0, right: 0 };
    },

    chooseTableMove(status) {
        const task = this.tableMenu.task;

        this.closeTableMenu();
        if (task) this.dropOn(task, status);
    },

    /**
     * From the calendar: list every deadline of a date in the Table, with the same division and scope as the calendar.
     * Other filters are cleared so no deadline of that date is hidden.
     */
    showDeadlinesInTable(date) {
        this.filters = { ...DEFAULT_FILTERS, division: this.filters.division, scope: this.filters.scope, date_field: 'deadline' };
        this.period = { ...emptyPeriod(), mode: 'date', date };
        this.sort = 'priority';
        this.direction = 'desc';
        this.view = 'table';
        this.applyPeriod();
    },

    /**
     * Arrive on the Board from the calendar preview (TASK-15): clear filters that could hide the task (keeping its
     * division), highlight its card and open its detail. Navigation never grants extra rights; the detail follows the API.
     */
    async focusTaskOnBoard(preview) {
        this.filters = { ...DEFAULT_FILTERS, division: preview.division?.code_division ?? '' };
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

        this.$dispatch('tasklist-filters-changed', { filters: { ...this.filters } });
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

    /** Explain why a drop is refused, mirroring the workflow rules of the API. */
    rejectionReason(task, status) {
        if (task.abilities.transitions.length === 0) {
            return 'Anda tidak memiliki kewenangan untuk memindahkan tugas ini.';
        }

        if (task.status === 'in_progress' && status === 'done' && task.requires_review) {
            return 'Tugas ini wajib melalui Ready Review dan On Review sebelum Done.';
        }

        return `Status tidak dapat diubah dari ${this.statusLabel(task.status)} ke ${this.statusLabel(status)}.`;
    },

    /**
     * Move the card at once, then save; on failure the card returns to its previous column. Leaving On Review is a
     * review decision (POST /reviews); every other move is PATCH /status.
     */
    async moveTask(task, status, note = null) {
        const previousStatus = task.status;
        const decision = reviewDecisionFor(previousStatus, status);

        this.movingIds[task.id] = true;
        this.setCardStatus(task, status);

        try {
            const response = decision
                ? await http(`${this.config.urls.tasks}/${task.id}/reviews`, {
                    method: 'POST',
                    body: { decision, note, version: task.version },
                })
                : await http(`${this.config.urls.tasks}/${task.id}/status`, {
                    method: 'PATCH',
                    body: { status, version: task.version },
                });

            Alpine.store('toast').success(response.message ?? 'Status tugas berhasil diperbarui.');
            this.$dispatch('tasks-changed', { id: task.id });

            return true;
        } catch (error) {
            this.setCardStatus(task, previousStatus);
            Alpine.store('toast').failure(error);

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
            Alpine.store('toast').error(this.revision.error);

            return;
        }

        this.revision.saving = true;
        const moved = await this.moveTask(this.revision.task, 'backlog', this.revision.note.trim());
        this.revision.saving = false;

        if (moved) this.revision.open = false;
    },
});
