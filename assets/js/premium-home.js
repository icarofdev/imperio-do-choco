document.addEventListener("DOMContentLoaded", () => {
    const revealSections = [...document.querySelectorAll(".reveal-section")];
    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    if (revealSections.length > 0 && !reduceMotion && "IntersectionObserver" in window) {
        document.body.classList.add("js-reveal");

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                entry.target.classList.add("is-visible");
                observer.unobserve(entry.target);
            });
        }, {
            threshold: 0.12,
            rootMargin: "0px 0px -7% 0px",
        });

        revealSections.forEach((section) => observer.observe(section));
    }

    const sortDropdown = document.querySelector(".vitrine-toolbar__sort");
    const sortLabel = sortDropdown?.querySelector("[data-vitrine-sort-label]");
    const sortButtons = [...document.querySelectorAll("[data-vitrine-sort]")];

    function updateSortLabel(button) {
        if (!sortLabel || !button) {
            return;
        }

        sortLabel.textContent = button.dataset.vitrineSort === "price-asc"
            ? "Menor preço"
            : "Recomendados";
    }

    updateSortLabel(sortButtons.find((button) => button.classList.contains("ativo")) || sortButtons[0]);

    sortButtons.forEach((button) => {
        button.addEventListener("click", () => {
            updateSortLabel(button);
            sortDropdown?.removeAttribute("open");
        });
    });

    document.addEventListener("click", (event) => {
        if (sortDropdown?.open && !sortDropdown.contains(event.target)) {
            sortDropdown.removeAttribute("open");
        }
    });

});
