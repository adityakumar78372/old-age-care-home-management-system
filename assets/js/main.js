// assets/js/main.js

document.addEventListener("DOMContentLoaded", () => {

    // ---- Sidebar Toggle ----
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const sidebarCollapseBtn = document.getElementById('sidebarCollapse');

    function openSidebar() {
        if (sidebar) sidebar.classList.add('active');
        if (overlay) overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('active');
        if (overlay) overlay.classList.remove('show');
        document.body.style.overflow = '';
    }

    // Make closeSidebar globally accessible (called from overlay onclick)
    window.closeSidebar = closeSidebar;

    if (sidebarCollapseBtn) {
        sidebarCollapseBtn.addEventListener('click', () => {
            if (sidebar.classList.contains('active')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    // Close sidebar on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeSidebar();
    });

    // ---- Dark Mode Toggle ----
    const toggleButton = document.getElementById('theme-toggle');
    const toggleIcon = document.getElementById('theme-icon');

    // Theme is already applied in <head> to prevent flash.
    // Just sync the icon on load.
    const currentTheme = localStorage.getItem('theme') || 'light';
    updateIcon(currentTheme);

    if (toggleButton) {
        toggleButton.addEventListener('click', () => {
            const theme = document.documentElement.getAttribute('data-theme');
            const newTheme = theme === 'dark' ? 'light' : 'dark';

            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateIcon(newTheme);
        });
    }

    function updateIcon(theme) {
        if (!toggleIcon) return;
        toggleIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }

    // ---- Legacy Search Filter (fallback if DataTables not active) ----
    // DataTables will hide #tableSearch if it initializes.
    // This is the fallback for pages that don't use filterable-table.
    const searchInput = document.getElementById('tableSearch');
    const tableBody = document.querySelector('.filterable-table tbody');

    if (searchInput && tableBody && !searchInput.style.display) {
        const tableRows = tableBody.querySelectorAll('tr');
        searchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase().trim();
            tableRows.forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
            });
        });
    }

});
