(function () {
    function revealAll(elements) {
        elements.forEach((element) => element.classList.add("is-visible"));
    }

    function initializeScrollReveal() {
        const elements = [...document.querySelectorAll("[data-reveal]")];

        if (elements.length === 0) {
            return;
        }

        const reducedMotion = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;

        if (reducedMotion || !("IntersectionObserver" in window)) {
            revealAll(elements);
            return;
        }

        document.documentElement.classList.add("reveal-enabled");

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                entry.target.classList.add("is-visible");
                observer.unobserve(entry.target);
            });
        }, {
            threshold: 0.18,
            rootMargin: "0px 0px -5% 0px",
        });

        elements.forEach((element) => observer.observe(element));
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initializeScrollReveal, { once: true });
    } else {
        initializeScrollReveal();
    }
}());
