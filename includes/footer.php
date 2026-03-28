    <script src="static/js/main.js?v=2"></script>
    <script>
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('sidebarToggle');
        // Restore collapsed state from localStorage
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
            document.body.classList.add('sidebar-collapsed');
        }
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            document.body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        });
    </script>
</body>
</html>
