import './bootstrap';
import Alpine from 'alpinejs';

// Core UI components (always loaded)
import './components/sidebar.js';
import './components/modal.js';
import './components/drawer.js';
import './components/toast.js';
import './components/topbar.js';
import './components/floating-dropdown.js';
import './components/table-selection.js';
import './components/file-upload.js';
import './components/search-shortcuts.js';
import './components/settings.js';
import './components/media-picker.js';
import './components/tom-select-init.js';
import './components/air-datepicker.js';
import './components/feature-switch.js';
import './components/charts.js';
import './components/wapro-page.js';
import './components/team-filter.js';
import './components/message-template-editor.js';
import './components/automation-builder.js';
import './components/auto-reply-editor.js';
import './components/inbox.js';
import './components/commerce-product-wizard.js';
import './components/crm-board.js';

// Alpine.js components (register before Alpine.start())
import './components/datatable.js';
import './components/bulk-actions.js';
import './components/import.js';
import './components/notification-bell.js';
import './components/global-search.js';
import ticketReply from './components/ticket-reply.js';

// Alpine.js setup
window.Alpine = Alpine;
Alpine.data('ticketReply', ticketReply);
Alpine.start();

// ===== Theme Handling =====
function getPreferredTheme() {
  const storedTheme = localStorage.getItem("theme");
  if (storedTheme === "dark" || storedTheme === "light") {
    return storedTheme;
  }

  return window.matchMedia?.("(prefers-color-scheme: dark)").matches ? "dark" : "light";
}

function applyTheme(theme) {
  document.documentElement.classList.toggle("dark", theme === "dark");
  localStorage.setItem("theme", theme);
  updateThemeIcons();
}

function toggleTheme() {
  applyTheme(document.documentElement.classList.contains("dark") ? "light" : "dark");
}

function updateThemeIcons() {
  const isDark = document.documentElement.classList.contains("dark");
  const sunIcon = document.getElementById("sunIcon");
  const moonIcon = document.getElementById("moonIcon");

  if (sunIcon && moonIcon) {
    if (isDark) {
      sunIcon.classList.add("hidden");
      moonIcon.classList.remove("hidden");
    } else {
      sunIcon.classList.remove("hidden");
      moonIcon.classList.add("hidden");
    }
  }
}

// ===== Event Delegation =====
document.addEventListener("click", (e) => {
  'use strict';

  const trigger = e.target.closest("[data-action]");
  if (trigger) {
    switch (trigger.dataset.action) {
      case "toggle-theme":
        toggleTheme();
        break;
      case "toggle-switch":
        trigger.classList.toggle("checked");
        const isChecked = trigger.classList.contains("checked");
        trigger.setAttribute("aria-checked", isChecked);
        // Sync hidden input value for form submission
        const hiddenInput = trigger.closest('.toggle-label')?.querySelector('[data-toggle-input]');
        if (hiddenInput) {
          hiddenInput.value = isChecked ? '1' : '0';
          hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
        break;
    }
  }
});

// ===== Global Init =====
document.addEventListener("DOMContentLoaded", () => {
  'use strict';

  applyTheme(getPreferredTheme());
});
