import Alpine from 'alpinejs';
import { http } from '../http';
import { formatting } from './format';

const TRANSITION_LABELS = {
    todo: 'Siap Dikerjakan (To Do)',
    in_progress: 'Mulai Kerjakan',
    ready_review: 'Kirim untuk Review',
    on_review: 'Mulai Review',
    done: 'Selesaikan Tugas',
    backlog: 'Kembalikan ke Back Log',
};

const FIELD_LABELS = {
    title: 'Judul',
    description: 'Deskripsi',
    status: 'Status',
    priority: 'Prioritas',
    pics: 'PIC',
    deadline: 'Deadline',
    start_date: 'Tanggal mulai',
    division: 'Divisi',
    category: 'Kategori',
    platform: 'Platform',
    requires_review: 'Kewajiban QA',
    evidence_required: 'Wajib evidence',
    evidence: 'Evidence',
    note: 'Catatan',
};

const emptyEvidence = () => ({ type: 'file', file: null, url: '', title: '', note: '', errors: {}, saving: false });

/**
 * The task detail drawer, opened by an `open-task` event. Every action reloads the task so all views stay in sync.
 */
export default (config) => ({
    ...formatting,
    config,
    open: false,
    taskId: null,
    task: null,
    loading: false,
    failure: null,
    tab: 'info',
    busy: null,
    assignment: { open: false, items: [], selected: [], note: '', loading: false, saving: false, error: null, allDivisions: false },
    evidence: emptyEvidence(),
    review: { note: '', error: null, saving: false },
    activities: { items: [], loading: false, error: null },
    comments: { items: [], loading: false, error: null, content: '', replyTo: null, saving: false, errors: {} },
    confirmation: { open: false, title: '', message: '', label: '', action: null, running: false },
    lightbox: null,

    get url() {
        return `${this.config.urls.tasks}/${this.taskId}`;
    },

    /** Transitions offered as buttons; leaving On Review is a review decision with its own panel. */
    get transitions() {
        if (!this.task || this.task.status === 'on_review') return [];

        return this.task.abilities.transitions;
    },

    /** Viewing never grants changes: a task without any allowed action is shown read-only. */
    get isReadOnly() {
        const abilities = this.task?.abilities;

        return Boolean(abilities) && !abilities.update && !abilities.delete && !abilities.assign && !abilities.review
            && !abilities.add_evidence && abilities.transitions.length === 0;
    },

    /** The latest review asked for a revision and the task has not been handed in again yet. */
    get pendingRevision() {
        const latest = this.task?.reviews?.[0];
        const inRework = ['backlog', 'todo', 'in_progress'].includes(this.task?.status);

        return latest?.decision === 'revision_requested' && inRework ? latest : null;
    },

    get canDecideReview() {
        return Boolean(this.task?.abilities.review && this.task.status === 'on_review');
    },

    statusLabel(status) {
        return this.config.statuses.find((option) => option.value === status)?.label ?? status;
    },

    priorityLabel(priority) {
        return this.config.priorities.find((option) => option.value === priority)?.label ?? priority;
    },

    transitionLabel(status) {
        if (status === 'done' && this.task?.status === 'in_progress') return 'Selesaikan (tanpa QA)';

        return TRANSITION_LABELS[status] ?? this.statusLabel(status);
    },

    show(id) {
        this.taskId = id;
        this.task = null;
        this.tab = 'info';
        this.open = true;
        this.assignment.open = false;
        this.evidence = emptyEvidence();
        this.review = { note: '', error: null, saving: false };
        this.setUrlTask(id);
        this.load();
    },

    close() {
        this.open = false;
        this.setUrlTask(null);
    },

    setUrlTask(id) {
        const params = new URLSearchParams(window.location.search);
        id ? params.set('task', id) : params.delete('task');
        const query = params.toString();
        window.history.replaceState({}, '', query ? `?${query}` : window.location.pathname);
    },

    async load({ silent = false } = {}) {
        if (!silent) this.loading = true;
        this.failure = null;

        try {
            this.task = (await http(this.url)).data;
            if (this.tab === 'history') this.loadActivities();
        } catch (error) {
            this.task = null;
            this.failure = {
                kind: error.status === 404 ? 'not_found' : error.status === 403 ? 'forbidden' : 'error',
                message: error.message,
            };
        } finally {
            this.loading = false;
        }
    },

    /** Reload after a change made elsewhere on the page (form, board drag-and-drop). */
    onTasksChanged(detail) {
        if (this.open && this.task && (!detail?.id || detail.id === this.taskId) && detail?.source !== 'detail') {
            this.load({ silent: true });
        }
    },

    afterChange(response) {
        Alpine.store('toast').success(response.message);
        this.$dispatch('tasks-changed', { id: this.taskId, source: 'detail' });

        return this.load({ silent: true });
    },

    /** Show the failure; a stale version means somebody else changed the task, so show its latest data. */
    onFailure(error) {
        Alpine.store('toast').failure(error);

        if (error.status === 409) {
            this.load({ silent: true });
            this.$dispatch('tasks-changed', { id: this.taskId, source: 'detail' });
        }
    },

    selectTab(tab) {
        this.tab = tab;
        if (tab === 'history') this.loadActivities();
        if (tab === 'comments') this.loadComments();
    },

    editTask() {
        this.$dispatch('open-task-form', { task: this.task });
    },

    askConfirmation({ title, message, label, action }) {
        this.confirmation = { open: true, title, message, label, action, running: false };
    },

    async runConfirmation() {
        if (this.confirmation.running) return;

        this.confirmation.running = true;
        await this.confirmation.action();
        this.confirmation = { open: false, title: '', message: '', label: '', action: null, running: false };
    },

    deleteTask() {
        this.askConfirmation({
            title: 'Hapus tugas?',
            message: `Tugas "${this.task.title}" akan dihapus dari daftar aktif. Histori tugas tetap tersimpan.`,
            label: 'Hapus Tugas',
            action: () => this.destroyTask(),
        });
    },

    async destroyTask() {
        this.busy = 'delete';

        try {
            const response = await http(this.url, { method: 'DELETE', body: { version: this.task.version } });
            Alpine.store('toast').success(response.message);
            this.$dispatch('tasks-changed', { id: this.taskId, source: 'detail' });
            this.close();
        } catch (error) {
            this.onFailure(error);
        } finally {
            this.busy = null;
        }
    },

    async changeStatus(status) {
        if (this.busy) return;

        this.busy = `status:${status}`;

        try {
            await this.afterChange(await http(`${this.url}/status`, {
                method: 'PATCH',
                body: { status, version: this.task.version },
            }));
        } catch (error) {
            this.onFailure(error);
        } finally {
            this.busy = null;
        }
    },

    /* PIC assignment ------------------------------------------------------------------------ */

    openAssignment() {
        this.assignment = {
            open: true,
            items: [],
            selected: this.task.pics.map((pic) => pic.id),
            note: '',
            loading: false,
            saving: false,
            error: null,
            allDivisions: false,
        };
        this.loadPicOptions();
    },

    async loadPicOptions() {
        const allDivisions = this.config.user.is_admin && this.assignment.allDivisions;

        this.assignment.loading = true;
        this.assignment.error = null;

        try {
            const response = await http(this.config.urls.pics, {
                query: { division_id: allDivisions ? '' : this.task.division.id },
            });
            this.assignment.items = response.data;
        } catch (error) {
            this.assignment.error = error.message;
        } finally {
            this.assignment.loading = false;
        }
    },

    togglePic(id) {
        const selected = this.assignment.selected;
        this.assignment.selected = selected.includes(id) ? selected.filter((picId) => picId !== id) : [...selected, id];
    },

    async saveAssignment() {
        if (this.assignment.saving) return;

        this.assignment.saving = true;
        this.assignment.error = null;

        try {
            const response = await http(`${this.url}/pics`, {
                method: 'PUT',
                body: { version: this.task.version, pic_ids: this.assignment.selected, note: this.assignment.note || null },
            });
            this.assignment.open = false;
            await this.afterChange(response);
        } catch (error) {
            const errors = error.errors;
            this.assignment.error = errors.pic_ids?.[0] ?? Object.values(errors)[0]?.[0] ?? error.message;
            this.onFailure(error);
        } finally {
            this.assignment.saving = false;
        }
    },

    /* Evidence ------------------------------------------------------------------------------ */

    get evidenceAccept() {
        return this.config.evidence.extensions.map((extension) => `.${extension}`).join(',');
    },

    pickEvidenceFile(event) {
        this.evidence.file = event.target.files[0] ?? null;
        this.evidence.errors = {};

        const maxBytes = this.config.evidence.max_size_kb * 1024;

        if (this.evidence.file && this.evidence.file.size > maxBytes) {
            this.evidence.errors = { file: [`Ukuran file maksimal ${this.config.evidence.max_size_kb} KB.`] };
        }
    },

    async submitEvidence() {
        if (this.evidence.saving) return;

        const body = new FormData();
        body.append('type', this.evidence.type);
        if (this.evidence.note) body.append('note', this.evidence.note);

        if (this.evidence.type === 'file') {
            if (this.evidence.file) body.append('file', this.evidence.file);
        } else {
            body.append('url', this.evidence.url.trim());
            if (this.evidence.title) body.append('title', this.evidence.title);
        }

        this.evidence.saving = true;
        this.evidence.errors = {};

        try {
            const response = await http(`${this.url}/evidences`, { method: 'POST', body });
            this.evidence = emptyEvidence();
            if (this.$refs.evidenceFile) this.$refs.evidenceFile.value = '';
            await this.afterChange(response);
        } catch (error) {
            this.evidence.errors = error.errors;
            this.onFailure(error);
        } finally {
            this.evidence.saving = false;
        }
    },

    deleteEvidence(item) {
        this.askConfirmation({
            title: 'Hapus evidence?',
            message: `Evidence "${item.name}" akan dihapus dari tugas ini.`,
            label: 'Hapus Evidence',
            action: () => this.destroyEvidence(item),
        });
    },

    async destroyEvidence(item) {
        this.busy = `evidence:${item.id}`;

        try {
            await this.afterChange(await http(`${this.url}/evidences/${item.id}`, { method: 'DELETE' }));
        } catch (error) {
            this.onFailure(error);
        } finally {
            this.busy = null;
        }
    },

    /* Review -------------------------------------------------------------------------------- */

    async decideReview(decision) {
        if (this.review.saving) return;

        if (decision === 'revision_requested' && !this.review.note.trim()) {
            this.review.error = 'Catatan revisi wajib diisi.';
            Alpine.store('toast').error(this.review.error);

            return;
        }

        this.review.saving = true;
        this.review.error = null;

        try {
            const response = await http(`${this.url}/reviews`, {
                method: 'POST',
                body: { decision, note: this.review.note.trim() || null, version: this.task.version },
            });
            this.review.note = '';
            await this.afterChange(response);
        } catch (error) {
            this.review.error = error.errors.note?.[0] ?? null;
            this.onFailure(error);
        } finally {
            this.review.saving = false;
        }
    },

    /* Activity history ---------------------------------------------------------------------- */

    async loadActivities() {
        this.activities.loading = true;
        this.activities.error = null;

        try {
            this.activities.items = (await http(`${this.url}/activities`)).data;
        } catch (error) {
            this.activities.error = error.message;
        } finally {
            this.activities.loading = false;
        }
    },

    describeValue(field, value) {
        if (value === null || value === undefined || value === '') return '—';
        if (Array.isArray(value)) return value.map((item) => item?.fullname ?? item).join(', ') || '—';
        if (typeof value === 'boolean') return value ? 'Ya' : 'Tidak';
        if (typeof value === 'object') return value.name ?? value.fullname ?? JSON.stringify(value);
        if (field === 'status') return this.statusLabel(value);
        if (field === 'priority') return this.priorityLabel(value);
        if (field === 'deadline' || field === 'start_date') return this.formatDate(value);

        return String(value);
    },

    /** Turn the stored before/after values into readable lines. */
    describeChanges(activity) {
        return Object.entries(activity.changes ?? {})
            .filter(([, change]) => change !== null && change !== undefined)
            .map(([field, change]) => {
                const label = FIELD_LABELS[field] ?? field;
                const isDiff = change && typeof change === 'object' && !Array.isArray(change) && 'to' in change;

                if (!isDiff) return `${label}: ${this.describeValue(field, change)}`;
                if (change.from === null || (Array.isArray(change.from) && change.from.length === 0)) {
                    return `${label}: ${this.describeValue(field, change.to)}`;
                }

                return `${label}: ${this.describeValue(field, change.from)} → ${this.describeValue(field, change.to)}`;
            });
    },

    /* Comments ------------------------------------------------------------------------------ */

    async loadComments() {
        this.comments.loading = true;
        this.comments.error = null;

        try {
            this.comments.items = (await http(`${this.url}/comments`)).data;
        } catch (error) {
            this.comments.error = error.message;
        } finally {
            this.comments.loading = false;
        }
    },

    async submitComment() {
        if (this.comments.saving) return;

        if (!this.comments.content.trim()) {
            this.comments.errors = { content: ['Komentar tidak boleh kosong.'] };
            Alpine.store('toast').error('Komentar tidak boleh kosong.');

            return;
        }

        this.comments.saving = true;
        this.comments.errors = {};

        try {
            const response = await http(`${this.url}/comments`, {
                method: 'POST',
                body: { content: this.comments.content, parent_comment_id: this.comments.replyTo?.id ?? null },
            });
            Alpine.store('toast').success(response.message ?? 'Komentar berhasil dikirim.');
            this.comments.content = '';
            this.comments.replyTo = null;
            await this.loadComments();
        } catch (error) {
            this.comments.errors = error.errors;
            Alpine.store('toast').failure(error);
        } finally {
            this.comments.saving = false;
        }
    },
});
