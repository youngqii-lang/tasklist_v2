import Alpine from 'alpinejs';
import { http } from '../http';
import { normalizeTask, PRIORITY_LABELS, REVIEW_DECISIONS, STATUS_LABELS } from './contract';
import { formatting, transitionLabel } from './format';

const emptyEvidence = () => ({ type: 'file', file: null, url: '', note: '', errors: {}, saving: false });
const emptyAssignment = () => ({
    open: false,
    loading: false,
    saving: false,
    error: null,
    lockedReason: null,
    query: '',
    items: [],
    divisions: [],
    canChangeDivision: false,
    divisionId: null,
    originalDivisionId: null,
    selected: [],
    note: '',
});

/** A new id for each comment the user sends, so a retried request never posts the same comment twice. */
function newRequestId() {
    if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();

    const bytes = globalThis.crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('');

    return [hex.slice(0, 8), hex.slice(8, 12), hex.slice(12, 16), hex.slice(16, 20), hex.slice(20)].join('-');
}

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
    assignment: emptyAssignment(),
    evidence: emptyEvidence(),
    review: { note: '', error: null, saving: false },
    activities: { items: [], loading: false, error: null },
    comments: { items: [], loading: false, error: null, content: '', replyTo: null, saving: false, errors: {}, pending: null },
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

        return latest?.decision === REVIEW_DECISIONS.revision && inRework ? latest : null;
    },

    get canDecideReview() {
        return Boolean(this.task?.abilities.review && this.task.status === 'on_review');
    },

    statusLabel(status) {
        return STATUS_LABELS[status] ?? status;
    },

    priorityLabel(priority) {
        return PRIORITY_LABELS[priority] ?? priority;
    },

    transitionLabel(status) {
        return transitionLabel(this.task?.status, status);
    },

    show(id) {
        this.taskId = id;
        this.task = null;
        this.tab = 'info';
        this.open = true;
        this.assignment = emptyAssignment();
        this.evidence = emptyEvidence();
        this.comments = { ...this.comments, items: [], content: '', replyTo: null, errors: {}, pending: null };
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
            this.setTask((await http(this.url)).data);
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

    /** Show a TaskDetail from the API; the history list follows the history it carries. */
    setTask(task) {
        this.task = normalizeTask(task, this.config.user.id);
        this.activities.items = this.task.activities;
    },

    /** Changes that return the updated TaskDetail show it at once; others (evidence) reload the task. */
    afterChange(response) {
        Alpine.store('toast').success(response.message);
        this.$dispatch('tasks-changed', { id: this.taskId, source: 'detail' });

        if (response.data?.next_statuses !== undefined) {
            this.setTask(response.data);

            return Promise.resolve();
        }

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

    /** Load who may become PIC (and where the task may be forwarded); the API decides the list and any lock. */
    async openAssignment() {
        this.assignment = { ...emptyAssignment(), open: true, loading: true, selected: this.task.pics.map((pic) => pic.id) };

        try {
            const { data } = await http(`${this.url}/assignment-options`);

            this.assignment = {
                ...this.assignment,
                items: data.pics,
                divisions: data.divisions,
                canChangeDivision: data.can_change_division,
                divisionId: data.current_division.id,
                originalDivisionId: data.current_division.id,
                lockedReason: data.locked_reason,
                selected: data.current_pics.map((pic) => pic.id),
            };
        } catch (error) {
            this.assignment.error = error.message;
        } finally {
            this.assignment.loading = false;
        }
    },

    /** PIC options matching the search, with the chosen ones first. */
    get assignmentChoices() {
        const query = this.assignment.query.trim().toLowerCase();
        const selected = this.assignment.selected;
        const matches = this.assignment.items.filter((pic) => !query
            || pic.fullname.toLowerCase().includes(query)
            || (pic.division?.name ?? '').toLowerCase().includes(query));

        return [...matches].sort((a, b) => Number(selected.includes(b.id)) - Number(selected.includes(a.id)));
    },

    togglePic(id) {
        const selected = this.assignment.selected;
        this.assignment.selected = selected.includes(id) ? selected.filter((picId) => picId !== id) : [...selected, id];
    },

    /** Send the complete PIC list; a division is only sent when the task is forwarded to another one. */
    async saveAssignment() {
        if (this.assignment.saving) return;

        const body = { version: this.task.version, pic_ids: this.assignment.selected, note: this.assignment.note.trim() || null };

        if (this.assignment.canChangeDivision && Number(this.assignment.divisionId) !== this.assignment.originalDivisionId) {
            body.division_id = Number(this.assignment.divisionId);
        }

        this.assignment.saving = true;
        this.assignment.error = null;

        try {
            const response = await http(`${this.url}/assignment`, { method: 'PATCH', body });
            this.assignment = emptyAssignment();
            await this.afterChange(response);
        } catch (error) {
            const errors = error.errors;
            this.assignment.error = errors.pic_ids?.[0]
                ?? Object.entries(errors).find(([field]) => field.startsWith('pic_ids.'))?.[1]?.[0]
                ?? errors.division_id?.[0]
                ?? error.message;
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

        // One evidence is a file or a link, never both.
        const body = new FormData();
        if (this.evidence.note) body.append('note', this.evidence.note);

        if (this.evidence.type === 'file') {
            if (this.evidence.file) body.append('file', this.evidence.file);
        } else {
            body.append('url', this.evidence.url.trim());
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

        if (decision === REVIEW_DECISIONS.revision && !this.review.note.trim()) {
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

        const content = this.comments.content.trim();
        const parentId = this.comments.replyTo?.id ?? null;
        const pending = this.comments.pending;

        // Sending the same text again after a failure is a retry and keeps its id; anything else is a new comment.
        if (!pending || pending.content !== content || pending.parentId !== parentId) {
            this.comments.pending = { content, parentId, requestId: newRequestId() };
        }

        this.comments.saving = true;
        this.comments.errors = {};

        try {
            const response = await http(`${this.url}/comments`, {
                method: 'POST',
                body: { content, parent_comment_id: parentId, client_request_id: this.comments.pending.requestId },
            });
            Alpine.store('toast').success(response.message ?? 'Komentar berhasil dikirim.');
            this.comments.content = '';
            this.comments.replyTo = null;
            this.comments.pending = null;
            await this.loadComments();
        } catch (error) {
            this.comments.errors = error.errors;
            Alpine.store('toast').failure(error);
        } finally {
            this.comments.saving = false;
        }
    },
});
