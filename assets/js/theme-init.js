(function () {
    const THEME_STORAGE_KEY = "imperio_theme";
    const VALID_THEMES = new Set(["dark", "light"]);

    function readStoredTheme() {
        try {
            const storedTheme = localStorage.getItem(THEME_STORAGE_KEY);
            return VALID_THEMES.has(storedTheme) ? storedTheme : "";
        } catch (error) {
            return "";
        }
    }

    function getPreferredTheme() {
        if (window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches) {
            return "dark";
        }

        return "light";
    }

    function applyTheme(theme) {
        if (!document.body || !VALID_THEMES.has(theme)) {
            return;
        }

        document.body.setAttribute("data-theme", theme);
    }

    applyTheme(readStoredTheme() || getPreferredTheme());

    window.addEventListener("storage", (event) => {
        if (event.key !== THEME_STORAGE_KEY || !VALID_THEMES.has(event.newValue)) {
            return;
        }

        applyTheme(event.newValue);
    });
}());
