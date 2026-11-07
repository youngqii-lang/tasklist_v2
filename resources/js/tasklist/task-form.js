import Alpine from 'alpinejs';
import { http } from '../http';

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
});

const nullable = (value) => (value === '' || value === undefined ? null : value);

/**
 * Create and edit dialog. The fields shown follow the abilities the API returns for the task.
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
    pics: { items: [], loading: false, error: null, allDivisions: false },

    get isEdit() {
        return this.editing;
    },

    /** Admins and division managers may change everything; a PIC only the work details. */
    get canManage() {
        return !this.isEdit || Boolean(this.task?.abilities.manage);
    },

    get canChangeDivision() {
        return !this.isEdit || this.config.user.is_admin;
    },

    get selectedCategory() {
        return this.options?.categories.find((category) => String(category.id) === String(this.form.category_id)) ?? null;
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
        this.pics = { items: [], loading: false, error: null, allDivisions: false };
        this.form = emptyForm();
        this.open = true;

        try {
            const [fullTask] = await Promise.all([
                task ? http(`${this.config.urls.tasks}/${task.id}`).then((response) => response.data) : null,
                this.loadOptions(),
            ]);

            if (fullTask) {
                this.task = fullTask;
                this.form = this.fromTask(fullTask);
            }
        } catch (error) {
            this.generalError = error.message;
            this.loadFailed = true;

            return;
        } finally {
            this.loadingTask = false;
        }

        if (!task && this.options?.divisions.length === 1) {
            this.form.division_id = String(this.options.divisions[0].id);
        }

        if (!task && this.form.division_id) this.loadPics();

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
            division_id: task.division ? String(task.division.id) : '',
            platform_id: task.platform ? String(task.platform.id) : '',
            priority: task.priority,
            status: task.status,
            start_date: task.start_date ?? '',
            deadline: task.deadline ?? '',
            requires_review: Boolean(task.requires_review),
        };
    },

    /** The master data endpoint is open to task creators only; a PIC edits without the option lists. */
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

    onCategoryChange() {
        if (this.selectedCategory) {
            this.form.requires_review = this.selectedCategory.requires_review;
        }
    },

    onDivisionChange() {
        if (!this.pics.allDivisions) this.form.pic_ids = [];
        this.loadPics();
    },

    async loadPics() {
        const allDivisions = this.config.user.is_admin && this.pics.allDivisions;

        if (!allDivisions && !this.form.division_id) {
            this.pics.items = [];

            return;
        }

        this.pics.loading = true;
        this.pics.error = null;

        try {
            const response = await http(this.config.urls.pics, {
                query: { division_id: allDivisions ? '' : this.form.division_id },
            });
            this.pics.items = response.data;
            const available = response.data.map((pic) => pic.id);
            this.form.pic_ids = this.form.pic_ids.filter((id) => available.includes(id));
        } catch (error) {
            this.pics.error = error.message;
        } finally {
            this.pics.loading = false;
        }
    },

    togglePic(id) {
        this.form.pic_ids = this.form.pic_ids.includes(id)
            ? this.form.pic_ids.filter((picId) => picId !== id)
            : [...this.form.pic_ids, id];
    },

    payload() {
        const common = {
            title: this.form.title.trim(),
            description: nullable(this.form.description),
            priority: this.form.priority,
            start_date: nullable(this.form.start_date),
            deadline: nullable(this.form.deadline),
        };

        if (this.options) common.platform_id = nullable(this.form.platform_id);

        if (!this.isEdit) {
            return {
                ...common,
                division_id: nullable(this.form.division_id),
                category_id: nullable(this.form.category_id),
                requires_review: this.form.requires_review,
                status: this.form.status,
                pic_ids: this.form.pic_ids,
            };
        }

        const payload = { ...common, version: this.task.version };

        if (this.canManage) {
            payload.category_id = nullable(this.form.category_id);
            payload.requires_review = this.form.requires_review;
        }

        if (this.canManage && this.canChangeDivision) payload.division_id = nullable(this.form.division_id);

        return payload;
    },

    async submit() {
        if (this.saving || this.loadingTask || this.loadFailed) return;

        this.saving = true;
        this.errors = {};
        this.generalError = null;

        try {
            const response = this.isEdit
                ? await http(`${this.config.urls.tasks}/${this.task.id}`, { method: 'PATCH', body: this.payload() })
                : await http(this.config.urls.tasks, { method: 'POST', body: this.payload() });

            Alpine.store('toast').success(response.message);
            this.$dispatch('tasks-changed', { id: response.data.id });
            this.saving = false;
            this.open = false;
        } catch (error) {
            this.errors = error.errors;
            this.generalError = Object.keys(this.errors).length ? 'Periksa kembali isian yang ditandai.' : error.message;
            this.saving = false;
        }
    },

    error(field) {
        const key = Object.keys(this.errors).find((name) => name === field || name.startsWith(`${field}.`));

        return key ? this.errors[key][0] : null;
    },
});
