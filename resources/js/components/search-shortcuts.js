/**
 * Search Shortcuts Component
 * Handles global keyboard shortcuts for the global search modal (Ctrl+K / Cmd+K).
 */

import { openModal, closeModal } from "./modal.js";

export function initSearchShortcuts() {
  document.addEventListener("keydown", (e) => {
    // Ctrl+K or Cmd+K to open global search modal
    if ((e.ctrlKey || e.metaKey) && e.key === "k") {
      e.preventDefault();
      const modal = document.getElementById("globalSearchModal");
      if (modal) {
        if (modal.classList.contains("active")) {
          closeModal("globalSearchModal");
        } else {
          openModal("globalSearchModal");
          // Focus the search input after modal opens
          setTimeout(() => {
            const input = document.getElementById("globalSearchInput");
            if (input) {
              input.focus();
            }
          }, 100);
        }
      }
    }

    // Escape to close search modal
    if (e.key === "Escape") {
      const modal = document.getElementById("globalSearchModal");
      if (modal && modal.classList.contains("active")) {
        closeModal("globalSearchModal");
      }
    }
  });
}

// Initialize on load
initSearchShortcuts();
