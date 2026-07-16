/* CTA background parallax — shifts the [data-parallax-bg] layer as its
   section scrolls through the viewport. Design/effect adapted from the
   components/ library; re-implemented to project conventions. */

document.addEventListener("DOMContentLoaded", () => {
  const sections = document.querySelectorAll("[data-parallax]");
  if (!sections.length) return;

  const reduceMotion = window.matchMedia(
    "(prefers-reduced-motion: reduce)",
  ).matches;
  if (reduceMotion) return;

  const visible = new Set();
  let ticking = false;

  function update() {
    const viewportH = window.innerHeight;
    visible.forEach((section) => {
      const bg = section.querySelector("[data-parallax-bg]");
      if (!bg) return;
      const rect = section.getBoundingClientRect();
      const speed = parseFloat(section.dataset.parallaxSpeed) || 0.3;
      const offset = rect.top + rect.height / 2 - viewportH / 2;
      const shift = -offset * speed;
      bg.style.transform = `translate3d(0, ${shift.toFixed(2)}px, 0)`;
    });
    ticking = false;
  }

  function requestUpdate() {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(update);
  }

  const io = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) visible.add(entry.target);
        else visible.delete(entry.target);
      });
      requestUpdate();
    },
    { rootMargin: "100px 0px" },
  );

  sections.forEach((section) => io.observe(section));
  window.addEventListener("scroll", requestUpdate, { passive: true });
  window.addEventListener("resize", requestUpdate);
  requestUpdate();
});
