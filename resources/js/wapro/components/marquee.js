// Marquee — clones the first track of every .marquee so the CSS scroll loops
// seamlessly. Reads data-speed (seconds) and data-reverse. Craft ported 1:1
// from components/ marquee (CLAUDE.md §10/§12). Functional, null-guarded.
document.addEventListener("DOMContentLoaded", () => {
  const marquees = document.querySelectorAll(".marquee");
  if (!marquees.length) return;

  marquees.forEach((marquee) => {
    const track = marquee.querySelector(".marquee__track");
    if (!track) return;

    // Duplicate the track for a seamless loop.
    const clone = track.cloneNode(true);
    clone.setAttribute("aria-hidden", "true");
    marquee.appendChild(clone);

    const speed = marquee.dataset.speed;
    if (speed) marquee.style.setProperty("--marquee-duration", `${speed}s`);

    if (marquee.dataset.reverse !== undefined) {
      marquee.classList.add("marquee--reverse");
    }
  });
});
