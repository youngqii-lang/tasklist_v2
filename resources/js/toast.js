/**
 * Global toast notifications for the outcome of every user action, successful or not.
 */
export default {
    items: [],
    nextId: 1,

    show(message, type = 'success') {
        const id = this.nextId++;
        this.items.push({ id, message, type });
        setTimeout(() => this.dismiss(id), type === 'error' ? 6000 : 3500);
    },

    success(message) {
        this.show(message || 'Tindakan berhasil dilakukan.', 'success');
    },

    error(message) {
        this.show(message || 'Tindakan gagal dilakukan. Silakan coba lagi.', 'error');
    },

    /**
     * Show a failed request. Validation errors are summarised; the fields themselves still show the detail.
     *
     * @param {{ message?: string, errors?: Record<string, string[]> }} error
     */
    failure(error) {
        const fieldErrors = Object.values(error?.errors ?? {});

        if (fieldErrors.length === 1) {
            this.error(fieldErrors[0][0]);
        } else if (fieldErrors.length > 1) {
            this.error(`Gagal menyimpan. Terdapat ${fieldErrors.length} isian yang perlu diperbaiki.`);
        } else {
            this.error(error?.message);
        }
    },

    dismiss(id) {
        this.items = this.items.filter((item) => item.id !== id);
    },
};
