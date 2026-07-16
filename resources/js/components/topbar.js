/**
 * Topbar Component
 * Handles dropdowns and topbar specific actions
 */

let activeDropdown = null;

export function closeAllDropdowns() {
  if (activeDropdown) {
    activeDropdown.classList.remove("open");
    activeDropdown = null;
  }
}

export function toggleDropdown(targetId) {
  const panel = document.getElementById(targetId);
  if (!panel) return;

  if (activeDropdown && activeDropdown !== panel) {
    activeDropdown.classList.remove("open");
  }

  panel.classList.toggle("open");
  activeDropdown = panel.classList.contains("open") ? panel : null;
}

// Global click handler to close dropdowns when clicking outside
document.addEventListener("click", (e) => {
  const trigger = e.target.closest("[data-action]");

  if (trigger && trigger.dataset.action === "toggle-dropdown") {
    toggleDropdown(trigger.dataset.target);
  } else if (activeDropdown && !e.target.closest(".dropdown-wrapper")) {
    closeAllDropdowns();
  }
});
