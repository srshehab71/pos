</div> <!-- End Main Content -->

    <style>
        body[data-theme="dark"] {
            --bs-body-bg: #1a1a1a;
            --bs-body-color: #e0e0e0;
            --bs-border-color: #4a4a4a;
            background-color: var(--bg) !important;
            color: var(--text) !important;
        }

        body[data-theme="dark"] .card,
        body[data-theme="dark"] .stat-card,
        body[data-theme="dark"] .premium-card,
        body[data-theme="dark"] .sr-card,
        body[data-theme="dark"] .purchase-card,
        body[data-theme="dark"] .modal-content,
        body[data-theme="dark"] .dropdown-menu,
        body[data-theme="dark"] .list-group-item,
        body[data-theme="dark"] .accordion-item {
            background-color: var(--card) !important;
            color: var(--text) !important;
            border-color: var(--bs-border-color) !important;
        }

        body[data-theme="dark"] .table {
            --bs-table-bg: var(--card);
            --bs-table-color: var(--text);
            --bs-table-border-color: var(--bs-border-color);
            color: var(--text) !important;
        }

        body[data-theme="dark"] .table thead th,
        body[data-theme="dark"] .table-modern thead th,
        body[data-theme="dark"] .table-premium thead {
            background-color: #383838 !important;
            color: #e0e0e0 !important;
            border-color: var(--bs-border-color) !important;
        }

        body[data-theme="dark"] .form-control,
        body[data-theme="dark"] .form-select,
        body[data-theme="dark"] textarea,
        body[data-theme="dark"] input,
        body[data-theme="dark"] select {
            background-color: #383838 !important;
            color: var(--text) !important;
            border-color: var(--bs-border-color) !important;
        }

        body[data-theme="dark"] .form-control::placeholder,
        body[data-theme="dark"] input::placeholder,
        body[data-theme="dark"] textarea::placeholder {
            color: #bdbdbd !important;
        }

        body[data-theme="dark"] .bg-white {
            background-color: var(--card) !important;
        }

        body[data-theme="dark"] .table-light,
        body[data-theme="dark"] .bg-light {
            --bs-table-bg: #383838;
            background-color: #383838 !important;
            color: var(--text) !important;
        }

        body[data-theme="dark"] .text-dark {
            color: var(--text) !important;
        }

        body[data-theme="dark"] .text-muted {
            color: #bdbdbd !important;
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('active');
        }

        function toggleGlobalTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', newTheme);
            document.documentElement.setAttribute('data-bs-theme', newTheme);
            document.body.setAttribute('data-theme', newTheme);
            document.body.classList.toggle('dark', newTheme === 'dark');
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        }

        function updateThemeIcon(theme) {
            const btn = document.getElementById('theme-btn');
            if(btn) btn.innerHTML = (theme === 'dark') ? '<i class="fas fa-sun text-warning"></i>' : '<i class="fas fa-moon"></i>';
        }

        window.addEventListener('DOMContentLoaded', () => {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
            document.body.setAttribute('data-theme', savedTheme);
            document.body.classList.toggle('dark', savedTheme === 'dark');
            updateThemeIcon(savedTheme);
        });
    </script>
</body>
</html>