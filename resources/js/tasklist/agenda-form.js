import Alpine from 'alpinejs';
import { http } from '../http';

const emptyForm = (date = '') => ({
    title: '',
    description: '',
    start_date: date,
    end_date: date,
    is_shared: true,
    division_id: '',
    target_division_ids: [],
});

/**
 * Add, edit and delete dialog for agendas on the Calendar. Admin and Managers choose any organising division; a
 * Supervisor's agenda always belongs to their own division. The API checks every rule again.
 */
export default (config) => ({
    config,
    open: false,
    event: null,
    form: emptyForm(),
    errors: {},
    generalError: null,
    saving: false,
    confirmingDelete: false,

    get agenda() {
        return this.config.agenda;
    },

    get isEdit() {
        return this.event !== null;
    },

    get ownDivisionId() {
        return this.agenda.own_division_id;
    },

    /** Divisions an internal agenda may be meant for: a Supervisor's own division, or every division. */
    get targetChoices() {
        return this.agenda.is_supervisor
            ? this.config.divisions.filter((division) => division.id === this.ownDivisionId)
            : this.config.divisions;
    },

    show({ event = null, date = '' } = {}) {
        this.event = event;
        this.errors = {};
        this.generalError = null;
        this.confirmingDelete = false;
        this.form = event
            ? {
                title: event.title,
                description: event.description ?? '',
                start_date: event.start_date,
                end_date: event.end_date ?? event.start_date,
                is_shared: event.is_shared,
                division_id: event.division ? String(event.division.id) : '',
                target_division_ids: event.target_divisions.map((division) => division.id),
            }
            : {
                ...emptyForm(date),
                division_id: this.agenda.is_admin || !this.ownDivisionId ? '' : String(this.ownDivisionId),
            };

        if (!event && !this.agenda.is_admin && this.ownDivisionId) {
            this.form.target_division_ids = [this.ownDivisionId];
        }

        this.open = true;
        this.$nextTick(() => this.$refs.title?.focus());
    },

    close() {
        if (this.saving) return;
        this.open = false;
    },

    error(field) {
        return this.errors[field]?.[0] ?? null;
    },

    toggleTarget(id) {
        const ids = this.form.target_division_ids;
        this.form.target_division_ids = ids.includes(id) ? ids.filter((value) => value !== id) : [...ids, id];
    },

    payload() {
        return {
            title: this.form.title.trim(),
            description: this.form.description.trim() || null,
            start_date: this.form.start_date,
            end_date: this.form.end_date || null,
            is_shared: this.form.is_shared,
            division_id: this.form.division_id === '' ? null : Number(this.form.division_id),
            ...(this.form.is_shared ? {} : { target_division_ids: this.form.target_division_ids }),
        };
    },

    async submit() {
        await this.send(
            () => http(this.isEdit ? `${this.config.urls.agendas}/${this.event.id}` : this.config.urls.agendas, {
                method: this.isEdit ? 'PUT' : 'POST',
                body: this.payload(),
            }),
        );
    },

    async destroy() {
        if (!this.confirmingDelete) {
            this.confirmingDelete = true;

            return;
        }

        await this.send(() => http(`${this.config.urls.agendas}/${this.event.id}`, { method: 'DELETE' }));
    },

    async send(request) {
        this.saving = true;
        this.errors = {};
        this.generalError = null;

        try {
            const response = await request();
            Alpine.store('toast').success(response.message ?? 'Agenda tersimpan.');
            this.saving = false;
            this.open = false;
            window.dispatchEvent(new CustomEvent('agendas-changed'));
        } catch (error) {
            this.errors = error.errors ?? {};
            this.generalError = Object.keys(this.errors).length ? null : error.message;
            this.confirmingDelete = false;
        } finally {
            this.saving = false;
        }
    },
});
