import Alpine from 'alpinejs';
import tasklist from './tasklist/tasklist';
import taskForm from './tasklist/task-form';
import taskDetail from './tasklist/task-detail';
import taskTimeline from './tasklist/timeline';
import taskCalendar from './tasklist/calendar';
import toast from './tasklist/toast';

/**
 * Open and close the sidebar on small screens.
 */
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.querySelector('[data-sidebar-overlay]');

if (sidebar && sidebarOverlay) {
    const setSidebarOpen = (isOpen) => {
        sidebar.classList.toggle('-translate-x-full', !isOpen);
        sidebarOverlay.classList.toggle('hidden', !isOpen);
    };

    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            setSidebarOpen(sidebar.classList.contains('-translate-x-full'));
        });
    });

    sidebarOverlay.addEventListener('click', () => setSidebarOpen(false));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setSidebarOpen(false);
        }
    });
}

/**
 * Submit a filter form as soon as one of its [data-auto-submit] fields changes.
 */
document.querySelectorAll('[data-auto-submit]').forEach((field) => {
    field.addEventListener('change', () => field.form?.requestSubmit());
});

/**
 * Ask for confirmation in a dialog before submitting a [data-confirm-delete] form.
 */
const deleteDialog = document.getElementById('confirm-delete-dialog');

if (deleteDialog) {
    let pendingDeleteForm = null;
    const nameElement = deleteDialog.querySelector('[data-confirm-delete-name]');
    const noteElement = deleteDialog.querySelector('[data-confirm-delete-note]');

    document.querySelectorAll('form[data-confirm-delete]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            pendingDeleteForm = form;
            nameElement.textContent = form.dataset.confirmDelete;
            noteElement.textContent = form.dataset.confirmDeleteNote ?? '';
            noteElement.hidden = !form.dataset.confirmDeleteNote;
            deleteDialog.showModal();
        });
    });

    deleteDialog.querySelector('[data-confirm-delete-cancel]').addEventListener('click', () => deleteDialog.close());

    deleteDialog.querySelector('[data-confirm-delete-submit]').addEventListener('click', () => {
        pendingDeleteForm?.submit();
    });

    deleteDialog.addEventListener('close', () => {
        pendingDeleteForm = null;
    });
}

/**
 * Toggle password visibility for any button with a [data-password-toggle]
 * attribute pointing at the id of a password input.
 */
document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = document.getElementById(button.dataset.passwordToggle);

    if (!input) {
        return;
    }

    const showIcon = button.querySelector('[data-icon="show"]');
    const hideIcon = button.querySelector('[data-icon="hide"]');

    button.addEventListener('click', () => {
        const isVisible = input.type === 'text';

        input.type = isVisible ? 'password' : 'text';
        showIcon?.classList.toggle('hidden', !isVisible);
        hideIcon?.classList.toggle('hidden', isVisible);
        button.setAttribute('aria-pressed', String(!isVisible));
        button.setAttribute('aria-label', isVisible ? 'Tampilkan password' : 'Sembunyikan password');
        input.focus();
    });
});

/**
 * Alpine.js components of the Tasklist page.
 */

Alpine.store('toast', toast);
Alpine.data('tasklist', tasklist);
Alpine.data('taskForm', taskForm);
Alpine.data('taskDetail', taskDetail);
Alpine.data('taskTimeline', taskTimeline);
Alpine.data('taskCalendar', taskCalendar);

window.Alpine = Alpine;
Alpine.start();
