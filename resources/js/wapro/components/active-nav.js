// Marketing nav active state — marks the current page's link with one semantic
// state class (CLAUDE.md §4). Replaces the old EJS `active` plumbing so the nav
// can be written as static HTML in the partial.
document.addEventListener("DOMContentLoaded", () => {
  const links = document.querySelectorAll(".nav-link, .mobile-nav-link");
  if (!links.length) return;

  links.forEach((link) => {
    const href = link.getAttribute("href");
    if (!href || href === "#") return;

    const target = new URL(href, window.location.origin);
    if (target.pathname === window.location.pathname) {
      link.classList.add("is-active");
    }
  });
});
