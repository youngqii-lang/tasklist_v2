/**
 * Global toast messages for success and failure feedback.
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
        this.show(message, 'success');
    },

    error(message) {
        this.show(message, 'error');
    },

    dismiss(id) {
        this.items = this.items.filter((item) => item.id !== id);
    },
};
