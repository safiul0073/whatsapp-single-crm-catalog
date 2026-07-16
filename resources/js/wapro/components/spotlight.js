// Spotlight — sticky visual that crossfades as text steps scroll. On desktop,
// the step whose center is nearest the viewport center becomes active, and its
// matching visual fades in. Choreography ported from components/ trel
// (CLAUDE.md §10/§12). Functional, null-guarded; state via .is-active class.
document.addEventListener("DOMContentLoaded", () => {
  const section = document.getElementById("spotlight");
  if (!section) return;

  const desktop = window.matchMedia("(min-width: 992px)");
  const steps = Array.from(section.querySelectorAll(".spot-step"));
  const visuals = Array.from(section.querySelectorAll(".spot-vis"));
  if (!steps.length || !visuals.length) return;

  // Re-evaluate on breakpoint change (sticky layout differs).
  desktop.addEventListener("change", () => location.reload());
  if (!desktop.matches) return; // mobile: static stacked, no crossfade

  let ticking = false;

  function update() {
    ticking = false;
    const center = window.innerHeight / 2;
    let active = 1;
    let best = Infinity;
    steps.forEach((step) => {
      const rect = step.getBoundingClientRect();
      const distance = Math.abs(rect.top + rect.height / 2 - center);
      if (distance < best) {
        best = distance;
        active = Number(step.dataset.step);
      }
    });
    steps.forEach((step) =>
      step.classList.toggle("is-active", Number(step.dataset.step) === active),
    );
    visuals.forEach((vis) =>
      vis.classList.toggle("is-active", Number(vis.dataset.vis) === active),
    );
  }

  function onScroll() {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(update);
  }

  window.addEventListener("scroll", onScroll, { passive: true });
  window.addEventListener("resize", onScroll);
  update();
});
