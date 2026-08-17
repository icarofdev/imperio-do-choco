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

    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
    const AUTOPLAY_DELAY = 5200;
    const RESUME_DELAY = 4200;
    const SWIPE_THRESHOLD = 46;
    let activeIndex = 0;
    let autoplayTimer = null;
    let resumeTimer = null;
    let pointerStartX = null;
    let pointerStartY = null;
    let pointerId = null;
    let pointerAxis = null;
    let hovered = false;
    let focused = false;
    let interacting = false;

    function render() {
        const leftIndex = (activeIndex - 1 + slides.length) % slides.length;
        const rightIndex = (activeIndex + 1) % slides.length;

        slides.forEach((slide, index) => {
            slide.classList.remove("active", "left", "right", "hidden");

            if (index === activeIndex) {
                slide.classList.add("active");
                slide.setAttribute("aria-hidden", "false");
            } else {
                slide.setAttribute("aria-hidden", "true");
                slide.classList.add(index === leftIndex ? "left" : index === rightIndex ? "right" : "hidden");
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

    function canAutoplay() {
        return !reducedMotion.matches && !document.hidden && !hovered && !focused && !interacting;
    }

    function startAutoplay() {
        stopAutoplay();

        if (canAutoplay()) {
            autoplayTimer = window.setInterval(() => goTo(activeIndex + 1), AUTOPLAY_DELAY);
        }
    }

    function scheduleResume() {
        stopAutoplay();
        window.clearTimeout(resumeTimer);
        resumeTimer = window.setTimeout(startAutoplay, RESUME_DELAY);
    }

    function registerInteraction(callback) {
        stopAutoplay();
        callback();
        scheduleResume();
    }

    nextButton.addEventListener("click", () => registerInteraction(() => goTo(activeIndex + 1)));
    previousButton.addEventListener("click", () => registerInteraction(() => goTo(activeIndex - 1)));

    carousel.addEventListener("keydown", (event) => {
        if (event.key !== "ArrowLeft" && event.key !== "ArrowRight") {
            return;
        }

        event.preventDefault();
        registerInteraction(() => goTo(activeIndex + (event.key === "ArrowLeft" ? -1 : 1)));
    });

    carousel.addEventListener("mouseenter", () => {
        hovered = true;
        stopAutoplay();
        window.clearTimeout(resumeTimer);
    });

    carousel.addEventListener("mouseleave", () => {
        hovered = false;
        scheduleResume();
    });

    carousel.addEventListener("focusin", () => {
        focused = true;
        stopAutoplay();
        window.clearTimeout(resumeTimer);
    });

    carousel.addEventListener("focusout", (event) => {
        if (carousel.contains(event.relatedTarget)) {
            return;
        }

        focused = false;
        scheduleResume();
    });

    carousel.addEventListener("pointerdown", (event) => {
        if (event.pointerType === "mouse" && event.button !== 0) {
            return;
        }

        interacting = true;
        pointerId = event.pointerId;
        pointerStartX = event.clientX;
        pointerStartY = event.clientY;
        pointerAxis = null;
        stopAutoplay();
        window.clearTimeout(resumeTimer);
    });

    carousel.addEventListener("pointermove", (event) => {
        if (pointerId !== event.pointerId || pointerStartX === null || pointerStartY === null || pointerAxis !== null) {
            return;
        }

        const distanceX = event.clientX - pointerStartX;
        const distanceY = event.clientY - pointerStartY;

        if (Math.hypot(distanceX, distanceY) < 8) {
            return;
        }

        pointerAxis = Math.abs(distanceX) > Math.abs(distanceY) ? "x" : "y";

        if (pointerAxis === "x") {
            carousel.classList.add("is-dragging");
            carousel.setPointerCapture?.(event.pointerId);
        }
    });

    carousel.addEventListener("pointerup", (event) => {
        if (pointerId !== event.pointerId || pointerStartX === null) {
            return;
        }

        const distance = event.clientX - pointerStartX;
        interacting = false;
        pointerId = null;
        pointerStartX = null;
        pointerStartY = null;

        if (pointerAxis === "x" && Math.abs(distance) >= SWIPE_THRESHOLD) {
            goTo(activeIndex + (distance < 0 ? 1 : -1));
        }

        pointerAxis = null;
        carousel.classList.remove("is-dragging");
        scheduleResume();
    });

    carousel.addEventListener("pointercancel", () => {
        interacting = false;
        pointerId = null;
        pointerStartX = null;
        pointerStartY = null;
        pointerAxis = null;
        carousel.classList.remove("is-dragging");
        scheduleResume();
    });

    document.addEventListener("visibilitychange", () => {
        if (document.hidden) {
            stopAutoplay();
            return;
        }

        scheduleResume();
    });

    reducedMotion.addEventListener?.("change", () => {
        if (reducedMotion.matches) {
            stopAutoplay();
            window.clearTimeout(resumeTimer);
        } else {
            scheduleResume();
        }
    });

    render();
    startAutoplay();
});
