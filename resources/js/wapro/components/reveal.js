// Reveal-on-scroll — adds the semantic .is-visible class to any [data-reveal]
// element once it enters the viewport (one-shot). Styling for the resting and
// revealed states lives in CSS (.reveal / .reveal.is-visible). Functional,
// null-guarded; respects reduced-motion (shows everything immediately).
document.addEventListener("DOMContentLoaded", () => {
  const items = document.querySelectorAll("[data-reveal]");
  if (!items.length) return;

  const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  if (reduceMotion || !("IntersectionObserver" in window)) {
    items.forEach((el) => el.classList.add("is-visible"));
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add("is-visible");
        observer.unobserve(entry.target);
      });
    },
    { threshold: 0.18 },
  );

  items.forEach((el) => observer.observe(el));
});
