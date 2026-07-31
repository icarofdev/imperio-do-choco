(function () {
    const STORAGE_KEY = "velle-dulcis-theme";
    const VALID_THEMES = new Set(["light", "dark"]);
    const systemPreference = window.matchMedia?.("(prefers-color-scheme: dark)");

    function readStoredTheme() {
        try {
            const theme = localStorage.getItem(STORAGE_KEY);
            return VALID_THEMES.has(theme) ? theme : "";
        } catch (error) {
            return "";
        }
    }

    function getSystemTheme() {
        return systemPreference?.matches ? "dark" : "light";
    }

    function updateButtons(theme) {
        const nextTheme = theme === "dark" ? "light" : "dark";
        const label = nextTheme === "dark" ? "Ativar modo escuro" : "Ativar modo claro";

        document.querySelectorAll("[data-theme-toggle]").forEach((button) => {
            button.setAttribute("aria-label", label);
            button.setAttribute("title", label);
            button.setAttribute("aria-pressed", String(theme === "dark"));
            button.dataset.nextTheme = nextTheme;
        });
    }

    function applyTheme(theme, options = {}) {
        const normalizedTheme = VALID_THEMES.has(theme) ? theme : getSystemTheme();

        document.documentElement.dataset.theme = normalizedTheme;
        document.documentElement.style.colorScheme = normalizedTheme;

        if (document.body) {
            document.body.dataset.theme = normalizedTheme;
        }

        updateButtons(normalizedTheme);

        if (options.persist) {
            try {
                localStorage.setItem(STORAGE_KEY, normalizedTheme);
            } catch (error) {
                // O tema continua funcional mesmo quando o armazenamento está indisponível.
            }
        }

        window.dispatchEvent(new CustomEvent("velle-theme-change", {
            detail: { theme: normalizedTheme },
        }));
    }

    function animateToggle(button) {
        button.classList.remove("is-switching");
        requestAnimationFrame(() => button.classList.add("is-switching"));
        window.setTimeout(() => button.classList.remove("is-switching"), 280);
    }

    function createThemeToggle(extraClass = "") {
        const button = document.createElement("button");
        button.type = "button";
        button.className = `theme-toggle ${extraClass}`.trim();
        button.setAttribute("data-theme-toggle", "");
        button.innerHTML = `
            <svg class="theme-toggle__icon theme-toggle__sun" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="3.5"></circle>
                <path d="M12 2.5v2M12 19.5v2M4.5 12h-2M21.5 12h-2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M18.7 5.3l-1.4 1.4M6.7 17.3l-1.4 1.4"></path>
            </svg>
            <svg class="theme-toggle__icon theme-toggle__moon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M20.5 15.2A8.4 8.4 0 0 1 8.8 3.5a8.5 8.5 0 1 0 11.7 11.7Z"></path>
            </svg>
        `;
        return button;
    }

    function mountThemeControls() {
        document.querySelectorAll("[data-theme-toggle-host]").forEach((host) => {
            if (!host.querySelector("[data-theme-toggle]")) {
                host.prepend(createThemeToggle(host.dataset.themeToggleClass || ""));
            }
        });

        if (document.body?.hasAttribute("data-theme-toggle-floating")
            && !document.querySelector("[data-theme-toggle]")) {
            document.body.appendChild(createThemeToggle("theme-toggle--floating"));
        }
    }

    function initializeThemeControls() {
        mountThemeControls();
        const initialTheme = document.documentElement.dataset.theme || readStoredTheme() || getSystemTheme();
        applyTheme(initialTheme);

        document.querySelectorAll("[data-theme-toggle]").forEach((button) => {
            button.addEventListener("click", () => {
                const currentTheme = document.documentElement.dataset.theme === "dark" ? "dark" : "light";
                const nextTheme = currentTheme === "dark" ? "light" : "dark";

                animateToggle(button);
                applyTheme(nextTheme, { persist: true });
            });
        });

        requestAnimationFrame(() => document.documentElement.classList.add("theme-ready"));
    }

    window.addEventListener("storage", (event) => {
        if (event.key !== STORAGE_KEY) {
            return;
        }

        applyTheme(VALID_THEMES.has(event.newValue) ? event.newValue : getSystemTheme());
    });

    systemPreference?.addEventListener("change", () => {
        if (!readStoredTheme()) {
            applyTheme(getSystemTheme());
        }
    });

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initializeThemeControls, { once: true });
    } else {
        initializeThemeControls();
    }
}());
