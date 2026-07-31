(function () {
    const STORAGE_KEY = "velle-dulcis-theme";
    const LEGACY_STORAGE_KEY = "imperio_theme";
    const VALID_THEMES = new Set(["light", "dark"]);
    let storedTheme = "";

    try {
        storedTheme = localStorage.getItem(STORAGE_KEY) || localStorage.getItem(LEGACY_STORAGE_KEY) || "";

        if (VALID_THEMES.has(storedTheme) && !localStorage.getItem(STORAGE_KEY)) {
            localStorage.setItem(STORAGE_KEY, storedTheme);
        }
    } catch (error) {
        storedTheme = "";
    }

    const systemTheme = window.matchMedia?.("(prefers-color-scheme: dark)").matches
        ? "dark"
        : "light";
    const initialTheme = VALID_THEMES.has(storedTheme) ? storedTheme : systemTheme;

    document.documentElement.dataset.theme = initialTheme;
    document.documentElement.style.colorScheme = initialTheme;
}());
