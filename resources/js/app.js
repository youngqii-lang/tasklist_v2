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
