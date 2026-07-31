document.addEventListener("DOMContentLoaded", () => {
    const carousel = document.querySelector(".novo-carrossel .slider-3d");

    if (!carousel) {
        return;
    }

    const slides = [...carousel.querySelectorAll(".slide")];
    const nextButton = carousel.querySelector(".next");
    const previousButton = carousel.querySelector(".prev");

    if (slides.length === 0 || !nextButton || !previousButton) {
        return;
    }

    let activeIndex = 0;
    let autoplayTimer = null;

    function render() {
        const leftIndex = (activeIndex - 1 + slides.length) % slides.length;
        const rightIndex = (activeIndex + 1) % slides.length;

        slides.forEach((slide, index) => {
            slide.classList.remove("active", "left", "right", "hidden");

            if (index === activeIndex) {
                slide.classList.add("active");
                slide.setAttribute("aria-hidden", "false");
                return;
            }

            slide.setAttribute("aria-hidden", "true");

            if (index === leftIndex) {
                slide.classList.add("left");
            } else if (index === rightIndex) {
                slide.classList.add("right");
            } else {
                slide.classList.add("hidden");
            }
        });
    }

    function goTo(nextIndex) {
        activeIndex = (nextIndex + slides.length) % slides.length;
        render();
    }

    function stopAutoplay() {
        window.clearInterval(autoplayTimer);
        autoplayTimer = null;
    }

    function startAutoplay() {
        stopAutoplay();

        if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
            return;
        }

        autoplayTimer = window.setInterval(() => goTo(activeIndex + 1), 5200);
    }

    nextButton.addEventListener("click", () => {
        goTo(activeIndex + 1);
        startAutoplay();
    });

    previousButton.addEventListener("click", () => {
        goTo(activeIndex - 1);
        startAutoplay();
    });

    carousel.addEventListener("keydown", (event) => {
        if (event.key === "ArrowLeft") {
            event.preventDefault();
            goTo(activeIndex - 1);
        }

        if (event.key === "ArrowRight") {
            event.preventDefault();
            goTo(activeIndex + 1);
        }
    });

    carousel.addEventListener("mouseenter", stopAutoplay);
    carousel.addEventListener("mouseleave", startAutoplay);
    carousel.addEventListener("focusin", stopAutoplay);
    carousel.addEventListener("focusout", startAutoplay);
    document.addEventListener("visibilitychange", () => {
        if (document.hidden) {
            stopAutoplay();
        } else {
            startAutoplay();
        }
    });

    render();
    startAutoplay();
});
