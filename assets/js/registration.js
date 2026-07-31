(() => {
    const form = document.querySelector("[data-registration-form]");

    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const submitButton = form.querySelector(".registration-submit");

    const restoreSubmit = () => {
        if (!(submitButton instanceof HTMLButtonElement) || submitButton.dataset.infrastructureDisabled === "true") {
            return;
        }

        submitButton.disabled = false;
        submitButton.classList.remove("is-loading");
        submitButton.removeAttribute("aria-busy");
    };

    if (submitButton instanceof HTMLButtonElement && submitButton.disabled) {
        submitButton.dataset.infrastructureDisabled = "true";
    }

    form.addEventListener("submit", (event) => {
        if (!form.checkValidity()) {
            event.preventDefault();
            form.reportValidity();
            return;
        }

        if (submitButton instanceof HTMLButtonElement) {
            submitButton.disabled = true;
            submitButton.classList.add("is-loading");
            submitButton.setAttribute("aria-busy", "true");
        }
    });

    form.querySelectorAll("input").forEach((field) => {
        field.addEventListener("invalid", () => {
            field.setAttribute("aria-invalid", "true");
            field.closest(".registration-field")?.classList.add("has-error");
        });

        field.addEventListener("input", () => {
            field.setAttribute("aria-invalid", "false");
            field.closest(".registration-field")?.classList.remove("has-error");
        });
    });

    window.addEventListener("pageshow", restoreSubmit);
})();
