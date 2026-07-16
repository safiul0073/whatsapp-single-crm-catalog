// Hero editorial entrance. Mirrors the whatsapp-html GSAP choreography with
// lightweight CSS transitions so the marketing page keeps a visible no-JS state.
document.addEventListener("DOMContentLoaded", () => {
    const hero = document.getElementById("heroEd");
    if (!hero) return;

    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    if (reduceMotion) return;

    const lines = Array.from(hero.querySelectorAll(".hero-ed__line > span"));
    const fades = Array.from(hero.querySelectorAll("[data-hero-fade]"));
    const frames = Array.from(hero.querySelectorAll("[data-hero-frame]"));

    const ease = "cubic-bezier(0.16, 1, 0.3, 1)";

    lines.forEach((element) => {
        element.style.transform = "translateY(115%)";
        element.style.transition = `transform 900ms ${ease}`;
        element.style.willChange = "transform";
    });

    fades.forEach((element) => {
        element.style.opacity = "0";
        element.style.transform = "translateY(22px)";
        element.style.transition = `opacity 700ms ${ease}, transform 700ms ${ease}`;
        element.style.willChange = "opacity, transform";
    });

    frames.forEach((element) => {
        element.style.opacity = "0";
        element.style.transform = "scale(1.06)";
        element.style.transition = `opacity 1000ms ${ease}, transform 1000ms ${ease}`;
        element.style.willChange = "opacity, transform";
    });

    // Force the browser to commit the initial hidden state before the reveal.
    // Without this, fast module execution can collapse setup + final styles into
    // the same frame, making the animation appear not to run.
    hero.getBoundingClientRect();

    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            lines.forEach((element, index) => {
                element.style.transitionDelay = `${index * 120}ms`;
                element.style.transform = "translateY(0)";
            });

            frames.forEach((element, index) => {
                element.style.transitionDelay = `${150 + index * 120}ms`;
                element.style.opacity = "1";
                element.style.transform = "scale(1)";
            });

            fades.forEach((element, index) => {
                element.style.transitionDelay = `${500 + index * 100}ms`;
                element.style.opacity = "1";
                element.style.transform = "translateY(0)";
            });
        });
    });

    window.setTimeout(() => {
        [...lines, ...fades, ...frames].forEach((element) => {
            element.style.willChange = "";
        });
    }, 1400);
});
