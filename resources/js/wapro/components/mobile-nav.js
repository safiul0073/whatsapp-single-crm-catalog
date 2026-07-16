// Marketing mobile nav — JS toggles one semantic state class (CLAUDE.md §4).
document.addEventListener("DOMContentLoaded", () => {
  'use strict';

  const nav = document.getElementById("mobileNav");
  const openBtn = document.getElementById("mobileNavOpen");
  if (!nav || !openBtn) return;

  const setOpenState = (isOpen) => {
    nav.classList.toggle("is-open", isOpen);
    openBtn.setAttribute("aria-expanded", String(isOpen));
  };

  openBtn.addEventListener("click", () => {
    setOpenState(!nav.classList.contains("is-open"));
  });

  // Close when a nav link is clicked.
  nav.addEventListener("click", (e) => {
    if (e.target.closest("a")) setOpenState(false);
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") setOpenState(false);
  });
});
