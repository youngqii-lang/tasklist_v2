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
    deadline_time: '',
    collaborating_division_ids: [],
});

const nullable = (value) => (value === '' || value === undefined ? null : value);
const toId = (value) => (value === '' || value === null || value === undefined ? null : Number(value));

/** Fields of the edit form, as the API names them in editable_fields. */
const EDIT_FIELDS = [
    'title', 'description', 'category_id', 'division_id', 'collaborating_division_ids', 'platform_id', 'priority',
    'start_date', 'deadline', 'deadline_time',
];

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

    /** Divisions of the chosen PICs (or, on edit, of the task's PICs): they always collaborate on the task. */
    get picDivisionIds() {
        if (this.isEdit) return (this.task?.pics ?? []).map((pic) => pic.division_id).filter(Boolean);

        return (this.options?.pics ?? [])
            .filter((pic) => this.form.pic_ids.includes(pic.id) && pic.division)
            .map((pic) => pic.division.id);
    },

    /** Every division except the owning one, each marked when a PIC brings it along. */
    get collaboratorChoices() {
        const fromPics = this.picDivisionIds;

        return (this.options?.collaborator_divisions ?? [])
            .filter((division) => String(division.id) !== String(this.form.division_id))
            .map((division) => ({ ...division, fromPic: fromPics.includes(division.id) }));
    },

    isCollaborator(division) {
        return division.fromPic || this.form.collaborating_division_ids.includes(division.id);
    },

    toggleCollaborator(division) {
        if (division.fromPic) return;

        const ids = this.form.collaborating_division_ids;
        this.form.collaborating_division_ids = ids.includes(division.id) ? ids.filter((id) => id !== division.id) : [...ids, division.id];
    },

    /** The deadline time is filled in with the deadline; clearing the date clears the time too. */
    onDeadlineChange() {
        if (!this.form.deadline) this.form.deadline_time = '';
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
            deadline_time: task.deadline_time ?? '',
            collaborating_division_ids: (task.collaborating_divisions ?? []).map((division) => division.id),
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
            deadline_time: this.form.deadline ? nullable(this.form.deadline_time) : null,
            collaborating_division_ids: this.selectedCollaboratorIds(),
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
            deadline_time: this.form.deadline ? nullable(this.form.deadline_time) : null,
            collaborating_division_ids: this.selectedCollaboratorIds(),
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
            delete payload.collaborating_division_ids;
        }

        return payload;
    },

    /** Only the divisions picked by hand are sent; the API adds the PICs' divisions itself. */
    selectedCollaboratorIds() {
        const owner = String(this.form.division_id);

        return this.form.collaborating_division_ids.filter((id) => String(id) !== owner);
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
