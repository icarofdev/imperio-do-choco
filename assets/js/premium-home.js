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
    const sortSummary = sortDropdown?.querySelector("summary");
    const sortButtons = [...document.querySelectorAll("[data-vitrine-sort]")];

    sortButtons.forEach((button) => {
        button.addEventListener("click", () => {
            if (sortSummary) {
                const label = button.dataset.vitrineSort === "price-asc"
                    ? "Menor preço"
                    : "Recomendados";
                const textNode = [...sortSummary.childNodes].find((node) => node.nodeType === Node.TEXT_NODE);

                if (textNode) {
                    textNode.textContent = `${label} `;
                }
            }

            sortDropdown?.removeAttribute("open");
        });
    });

    document.addEventListener("click", (event) => {
        if (sortDropdown?.open && !sortDropdown.contains(event.target)) {
            sortDropdown.removeAttribute("open");
        }
    });

    const newsletterForm = document.querySelector(".newsletter-form");

    newsletterForm?.addEventListener("submit", (event) => {
        event.preventDefault();
        const input = newsletterForm.querySelector("input");

        if (!input?.checkValidity()) {
            input?.reportValidity();
            return;
        }

        input.value = "";
        input.placeholder = "Cadastro realizado com carinho";
    });
});
