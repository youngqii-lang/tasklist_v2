import Alpine from 'alpinejs';
import { http } from '../http';
import { normalizeTask } from './contract';

const emptyForm = () => ({
    title: '',
    description: '',
    category_id: '',
    division_id: '',
    platform_id: '',
    pic_ids: [],
    priority: 'medium',
    status: 'backlog',
    start_date: '',
    deadline: '',
    requires_review: false,
    evidence_required: false,
});

const nullable = (value) => (value === '' || value === undefined ? null : value);
const toId = (value) => (value === '' || value === null || value === undefined ? null : Number(value));

/** Fields of the edit form, as the API names them in editable_fields. */
const EDIT_FIELDS = ['title', 'description', 'category_id', 'division_id', 'platform_id', 'priority', 'start_date', 'deadline', 'requires_review'];

/**
 * Create and edit dialog. Options come from GET /api/tasks/create-options; on edit the fields shown and sent follow
 * the task's editable_fields from the API.
 */
export default (config) => ({
    config,
    open: false,
    task: null,
    form: emptyForm(),
    errors: {},
    generalError: null,
    saving: false,
    loadingTask: false,
    loadFailed: false,
    editing: false,
    options: null,
    optionsLoading: false,
    optionsError: null,
    showAllPics: false,
    picQuery: '',

    get isEdit() {
        return this.editing;
    },

    /** Whether the field can be changed: always on create, on edit only when the API lists it. */
    canEdit(field) {
        return !this.isEdit || Boolean(this.task?.abilities.editable_fields.includes(field));
    },

    /** Admins and division managers change the task's details; others see them read-only. */
    get canManage() {
        return !this.isEdit || Boolean(this.task?.abilities.manage);
    },

    get canChangeDivision() {
        return this.canEdit('division_id');
    },

    get selectedCategory() {
        return this.options?.categories.find((category) => String(category.id) === String(this.form.category_id)) ?? null;
    },

    /** PIC choices: the chosen division's accounts (or all), matching the search, with the chosen ones first. */
    get picChoices() {
        const query = this.picQuery.trim().toLowerCase();
        const chosen = this.form.pic_ids;
        const items = (this.options?.pics ?? []).filter((pic) => {
            const inDivision = this.showAllPics || String(pic.division?.id ?? '') === String(this.form.division_id);
            const matches = !query || pic.fullname.toLowerCase().includes(query) || (pic.division?.name ?? '').toLowerCase().includes(query);

            return (inDivision || chosen.includes(pic.id)) && matches;
        });

        return items.sort((a, b) => Number(chosen.includes(b.id)) - Number(chosen.includes(a.id)));
    },

    /** Edits always start from the full, latest task so no field is cleared and the version is current. */
    async show({ task = null } = {}) {
        this.task = null;
        this.editing = Boolean(task);
        this.loadFailed = false;
        this.errors = {};
        this.generalError = null;
        this.saving = false;
        this.loadingTask = Boolean(task);
        this.showAllPics = false;
        this.picQuery = '';
        this.form = emptyForm();
        this.open = true;

        try {
            const [fullTask] = await Promise.all([
                task ? http(`${this.config.urls.tasks}/${task.id}`).then((response) => response.data) : null,
                this.loadOptions(),
            ]);

            if (fullTask) {
                this.task = normalizeTask(fullTask, this.config.user.id);
                this.form = this.fromTask(this.task);
            }
        } catch (error) {
            this.generalError = error.message;
            this.loadFailed = true;

            return;
        } finally {
            this.loadingTask = false;
        }

        if (!task && this.options) {
            this.form.priority = this.options.defaults?.priority ?? 'medium';
            this.form.status = this.options.defaults?.status ?? 'backlog';

            if (this.options.divisions.length === 1) this.form.division_id = String(this.options.divisions[0].id);
        }

        this.$nextTick(() => this.$refs.title?.focus());
    },

    close() {
        if (!this.saving) this.open = false;
    },

    fromTask(task) {
        return {
            ...emptyForm(),
            title: task.title,
            description: task.description ?? '',
            category_id: task.category ? String(task.category.id) : '',
            division_id: task.division?.id ? String(task.division.id) : '',
            platform_id: task.platform ? String(task.platform.id) : '',
            priority: task.priority,
            status: task.status,
            start_date: task.start_date ?? '',
            deadline: task.deadline ?? '',
            requires_review: Boolean(task.requires_review),
            evidence_required: Boolean(task.evidence_required),
        };
    },

    /** The options endpoint is open to task creators only; anyone else edits without the option lists. */
    async loadOptions() {
        if (this.options || !this.config.can_create) return;

        this.optionsLoading = true;
        this.optionsError = null;

        try {
            this.options = (await http(this.config.urls.options)).data;
        } catch (error) {
            this.optionsError = error.message;
        } finally {
            this.optionsLoading = false;
        }
    },

    /** The category decides whether review (and evidence) is required. */
    onCategoryChange() {
        if (!this.selectedCategory) return;

        this.form.requires_review = Boolean(this.selectedCategory.requires_review);
        if (this.selectedCategory.evidence_required) this.form.evidence_required = true;
    },

    /** PICs outside the new division are dropped unless the user chose to see every division. */
    onDivisionChange() {
        if (this.showAllPics) return;

        const inDivision = (this.options?.pics ?? [])
            .filter((pic) => String(pic.division?.id ?? '') === String(this.form.division_id))
            .map((pic) => pic.id);
        this.form.pic_ids = this.form.pic_ids.filter((id) => inDivision.includes(id));
    },

    togglePic(id) {
        this.form.pic_ids = this.form.pic_ids.includes(id)
            ? this.form.pic_ids.filter((picId) => picId !== id)
            : [...this.form.pic_ids, id];
    },

    createPayload() {
        return {
            title: this.form.title.trim(),
            description: nullable(this.form.description),
            category_id: toId(this.form.category_id),
            division_id: toId(this.form.division_id),
            platform_id: toId(this.form.platform_id),
            pic_ids: this.form.pic_ids,
            priority: this.form.priority,
            status: this.form.status,
            start_date: nullable(this.form.start_date),
            deadline: nullable(this.form.deadline),
            requires_review: this.form.requires_review,
            evidence_required: this.form.evidence_required,
        };
    },

    /** Only the fields the API allows this user to change are sent, with the version that was loaded. */
    editPayload() {
        const values = {
            title: this.form.title.trim(),
            description: nullable(this.form.description),
            category_id: toId(this.form.category_id),
            division_id: toId(this.form.division_id),
            platform_id: toId(this.form.platform_id),
            priority: this.form.priority,
            start_date: nullable(this.form.start_date),
            deadline: nullable(this.form.deadline),
            requires_review: this.form.requires_review,
        };
        const payload = { version: this.task.version };

        EDIT_FIELDS.filter((field) => this.canEdit(field)).forEach((field) => {
            payload[field] = values[field];
        });

        // Option lists are only loaded for task creators; without them these fields keep their stored value.
        if (!this.options) {
            delete payload.category_id;
            delete payload.platform_id;
            delete payload.division_id;
        }

        return payload;
    },

    async submit() {
        if (this.saving || this.loadingTask || this.loadFailed) return;

        this.saving = true;
        this.errors = {};
        this.generalError = null;

        try {
            const response = this.isEdit
                ? await http(`${this.config.urls.tasks}/${this.task.id}`, { method: 'PATCH', body: this.editPayload() })
                : await http(this.config.urls.tasks, { method: 'POST', body: this.createPayload() });

            Alpine.store('toast').success(response.message);
            this.$dispatch('tasks-changed', { id: response.data.id });
            this.saving = false;
            this.open = false;
        } catch (error) {
            this.errors = error.errors;
            this.generalError = error.status === 409
                ? error.message
                : (Object.keys(this.errors).length ? 'Periksa kembali isian yang ditandai.' : error.message);
            Alpine.store('toast').failure(error);
            this.saving = false;

            if (error.status === 409) this.$dispatch('tasks-changed', { id: this.task?.id });
        }
    },

    error(field) {
        const key = Object.keys(this.errors).find((name) => name === field || name.startsWith(`${field}.`));

        return key ? this.errors[key][0] : null;
    },
});
