(() => {
    const root = document.documentElement;
    const buttons = document.querySelectorAll('[data-theme-toggle]');
    const apply = (theme) => {
        root.dataset.bsTheme = theme;
        localStorage.setItem('leadpilot-theme', theme);
        buttons.forEach((button) => {
            const dark = theme === 'dark';
            button.innerHTML = `<i class="bi ${dark ? 'bi-sun' : 'bi-moon-stars'}"></i>`;
            button.setAttribute('aria-label', dark ? 'Switch to light theme' : 'Switch to dark theme');
            button.setAttribute('title', dark ? 'Light theme' : 'Dark theme');
        });
    };
    buttons.forEach((button) => button.addEventListener('click', () => apply(root.dataset.bsTheme === 'dark' ? 'light' : 'dark')));
    apply(root.dataset.bsTheme || 'light');
})();
