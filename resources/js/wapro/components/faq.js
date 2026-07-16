// FAQ accordion — event-delegated, toggles .is-open on the .faq-item.
// One open at a time. Styling for .is-open lives in CSS (CLAUDE.md §4).
document.addEventListener("DOMContentLoaded", () => {
  document.addEventListener("click", (e) => {
    const trigger = e.target.closest("[data-faq-toggle]");
    if (!trigger) return;
    const item = trigger.closest(".faq-item");
    if (!item) return;

    const wasOpen = item.classList.contains("is-open");
    item
      .closest("[data-faq-group]")
      ?.querySelectorAll(".faq-item.is-open")
      .forEach((el) => {
        el.classList.remove("is-open");
        el.querySelector("[data-faq-toggle]")?.setAttribute("aria-expanded", "false");
      });

    if (!wasOpen) {
      item.classList.add("is-open");
      trigger.setAttribute("aria-expanded", "true");
    } else {
      trigger.setAttribute("aria-expanded", "false");
    }
  });
});
