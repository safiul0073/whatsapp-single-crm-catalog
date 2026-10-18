/**
 * Modal Component
 * Handles opening and closing of modals.
 *
 * Usage:
 * - Trigger: data-modal-trigger="modalId"
 * - Close: data-modal-close
 * - Modal ID: id="modalId"
 */

/**
 * Opens a modal by its ID.
 * @param {string} modalId
 */
export function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove("hidden"); // Ensure hidden class is removed if present
    modal.style.display = "flex";
    // Double RAF to ensure browser registers the display change before transition
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        modal.classList.add("active");
        modal.classList.add("is-open");
        document.body.classList.add("overflow-hidden");
        document.body.classList.add("is-locked");
        modal.querySelector("input, textarea, select, button")?.focus();
      });
    });
  } else {
    console.warn(`Modal with ID '${modalId}' not found.`);
  }
}

function buildAndSubmitDynamicForm(action, method = "POST") {
  if (!action) return;

  const form = document.createElement("form");
  form.method = "POST";
  form.action = action;
  form.style.display = "none";

  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
  if (csrf) {
    const tokenInput = document.createElement("input");
    tokenInput.type = "hidden";
    tokenInput.name = "_token";
    tokenInput.value = csrf;
    form.appendChild(tokenInput);
  }

  const normalizedMethod = (method || "POST").toUpperCase();
  if (!["GET", "POST"].includes(normalizedMethod)) {
    const methodInput = document.createElement("input");
    methodInput.type = "hidden";
    methodInput.name = "_method";
    methodInput.value = normalizedMethod;
    form.appendChild(methodInput);
  } else if (normalizedMethod === "GET") {
    form.method = "GET";
  }

  document.body.appendChild(form);
  form.submit();
}

let activeConfirmResolver = null;

function applyConfirmModalAppearance(modal, { variant = "danger", icon = "", hideCancel = false, cancelText = "Cancel" } = {}) {
  const iconWrapEl = modal.querySelector("[data-confirm-icon-wrap]");
  const iconEl = modal.querySelector("[data-confirm-icon]");
  const buttonEl = modal.querySelector("[data-confirm-btn]");
  const cancelEl = modal.querySelector("[data-confirm-cancel]");

  const normalizedVariant = ["warning", "amber"].includes(variant)
    ? "warning"
    : ["primary", "info"].includes(variant)
      ? "primary"
      : "danger";

  if (iconWrapEl) {
    iconWrapEl.className = "mb-5 flex h-14 w-14 items-center justify-center rounded-full transition-colors ";
    if (normalizedVariant === "warning") {
      iconWrapEl.className += "bg-amber-500/15 text-amber-600 dark:text-amber-400";
    } else if (normalizedVariant === "primary") {
      iconWrapEl.className += "bg-primary/10 text-primary";
    } else {
      iconWrapEl.className += "bg-error/10 text-error";
    }
  }

  if (iconEl) {
    const resolvedIcon =
      icon ||
      (normalizedVariant === "warning"
        ? "ph-warning"
        : normalizedVariant === "primary"
          ? "ph-info"
          : "ph-warning-circle");
    iconEl.className = `ph-bold ${resolvedIcon} text-2xl`;
  }

  if (buttonEl) {
    buttonEl.className = "btn w-full justify-center ";
    if (normalizedVariant === "warning") {
      buttonEl.className += "bg-amber-600 text-white hover:bg-amber-700 border-amber-600";
    } else if (normalizedVariant === "primary") {
      buttonEl.className += "btn-primary";
    } else {
      buttonEl.className += "btn-danger";
    }
  }

  if (cancelEl) {
    cancelEl.textContent = cancelText || "Cancel";
    cancelEl.classList.toggle("hidden", Boolean(hideCancel));
  }
}

function configureGlobalConfirm(trigger) {
  const modal = document.getElementById("globalConfirmModal");
  if (!modal) return;

  activeConfirmResolver = null;

  const title = trigger.dataset.confirmTitle || "Confirm Action";
  const message = trigger.dataset.confirmMessage || trigger.dataset.confirmBody || "Please confirm this action.";
  const action = trigger.dataset.confirmAction || "";
  const method = trigger.dataset.confirmMethod || "POST";
  const buttonText = trigger.dataset.confirmButton || trigger.dataset.confirmLabel || "Confirm";
  const cancelText = trigger.dataset.confirmCancelLabel || "Cancel";
  const variant = trigger.dataset.confirmVariant || "danger";
  const icon = trigger.dataset.confirmIcon || "";
  let formId = trigger.dataset.confirmForm || "";

  if (!formId && !action) {
    const form = trigger.tagName === "FORM" ? trigger : trigger.closest("form");
    if (form) {
      if (!form.id) {
        form.id = `confirm-form-${Date.now()}-${Math.random().toString(36).slice(2)}`;
      }
      formId = form.id;
    }
  }

  const titleEl = modal.querySelector("[data-confirm-title]");
  const messageEl = modal.querySelector("[data-confirm-message]");
  const buttonEl = modal.querySelector("[data-confirm-btn]");
  const buttonLabelEl = modal.querySelector("[data-confirm-button-label]");

  if (titleEl) titleEl.textContent = title;
  if (messageEl) messageEl.textContent = message;
  if (buttonEl) {
    delete buttonEl.dataset.confirmAlertOnly;
    if (trigger.name) {
      buttonEl.dataset.submitterName = trigger.name;
      buttonEl.dataset.submitterValue = trigger.value || "";
    } else {
      delete buttonEl.dataset.submitterName;
      delete buttonEl.dataset.submitterValue;
    }

    if (formId) {
      buttonEl.dataset.confirmForm = formId;
      delete buttonEl.dataset.confirmAction;
      delete buttonEl.dataset.confirmMethod;
    } else {
      buttonEl.dataset.confirmAction = action;
      buttonEl.dataset.confirmMethod = method;
      delete buttonEl.dataset.confirmForm;
    }
  }
  if (buttonLabelEl) buttonLabelEl.textContent = buttonText;

  applyConfirmModalAppearance(modal, { variant, icon, hideCancel: false, cancelText });
}

/**
 * Opens the global confirmation modal programmatically and returns a Promise<boolean>.
 */
export function requestConfirm({
  title = "Confirm Action",
  message = "Please confirm this action.",
  confirmText = "Confirm",
  cancelText = "Cancel",
  variant = "danger",
  icon = "",
} = {}) {
  const modal = document.getElementById("globalConfirmModal");
  if (!modal) {
    return Promise.resolve(false);
  }

  const titleEl = modal.querySelector("[data-confirm-title]");
  const messageEl = modal.querySelector("[data-confirm-message]");
  const buttonEl = modal.querySelector("[data-confirm-btn]");
  const buttonLabelEl = modal.querySelector("[data-confirm-button-label]");

  if (titleEl) titleEl.textContent = title;
  if (messageEl) messageEl.textContent = message;
  if (buttonLabelEl) buttonLabelEl.textContent = confirmText;

  if (buttonEl) {
    delete buttonEl.dataset.confirmForm;
    delete buttonEl.dataset.confirmAction;
    delete buttonEl.dataset.confirmMethod;
    delete buttonEl.dataset.submitterName;
    delete buttonEl.dataset.submitterValue;
    delete buttonEl.dataset.confirmAlertOnly;
  }

  applyConfirmModalAppearance(modal, { variant, icon, hideCancel: false, cancelText });
  openModal("globalConfirmModal");

  return new Promise((resolve) => {
    activeConfirmResolver = resolve;
  });
}

/**
 * Opens the global modal in single-button alert mode.
 */
export function showAlertModal({
  title = "Notice",
  message = "",
  buttonText = "OK",
  variant = "warning",
  icon = "",
} = {}) {
  const modal = document.getElementById("globalConfirmModal");
  if (!modal) {
    return;
  }

  activeConfirmResolver = null;

  const titleEl = modal.querySelector("[data-confirm-title]");
  const messageEl = modal.querySelector("[data-confirm-message]");
  const buttonEl = modal.querySelector("[data-confirm-btn]");
  const buttonLabelEl = modal.querySelector("[data-confirm-button-label]");

  if (titleEl) titleEl.textContent = title;
  if (messageEl) messageEl.textContent = message;
  if (buttonLabelEl) buttonLabelEl.textContent = buttonText;

  if (buttonEl) {
    delete buttonEl.dataset.confirmForm;
    delete buttonEl.dataset.confirmAction;
    delete buttonEl.dataset.confirmMethod;
    delete buttonEl.dataset.submitterName;
    delete buttonEl.dataset.submitterValue;
    buttonEl.dataset.confirmAlertOnly = "true";
  }

  applyConfirmModalAppearance(modal, { variant, icon, hideCancel: true });
  openModal("globalConfirmModal");
}

/**
 * Closes a specific modal or the closest modal to the element.
 * @param {HTMLElement|string} elementOrId - The modal element, its ID, or a child element.
 */
export function closeModal(elementOrId) {
  let modal;

  if (typeof elementOrId === "string") {
    modal = document.getElementById(elementOrId);
  } else if (elementOrId instanceof HTMLElement) {
    modal = elementOrId.closest(".modal");
  }

  if (modal) {
    if (modal.id === "globalConfirmModal" && typeof activeConfirmResolver === "function") {
      const resolve = activeConfirmResolver;
      activeConfirmResolver = null;
      resolve(false);
    }

    modal.classList.remove("active");
    modal.classList.remove("is-open");
    document.dispatchEvent(new CustomEvent('modal:closed', { detail: { id: modal.id, modal } }));
    // Wait for transition to finish before hiding
    setTimeout(() => {
      modal.style.display = "none";
      modal.classList.add("hidden"); // Re-add hidden class for consistency
      document.body.classList.remove("overflow-hidden");
      document.body.classList.remove("is-locked");
    }, 300); // Match duration-300 in CSS
  }
}

/**
 * Closes all active modals.
 */
export function closeAllModals() {
  document.querySelectorAll(".modal.active, .modal.is-open").forEach((modal) => {
    closeModal(modal);
  });
}

// Event delegation — registered on document immediately (no DOMContentLoaded needed)
document.addEventListener("click", (e) => {
  const confirmTrigger = e.target.closest("[data-confirm]");
  if (confirmTrigger && confirmTrigger.tagName !== "FORM" && !confirmTrigger.closest("[data-confirm-btn]")) {
    e.preventDefault();
    configureGlobalConfirm(confirmTrigger);
    openModal("globalConfirmModal");
    return;
  }

  const submitTrigger = e.target.closest("[data-submit-action]");
  if (submitTrigger) {
    e.preventDefault();
    buildAndSubmitDynamicForm(
      submitTrigger.dataset.submitAction,
      submitTrigger.dataset.submitMethod || "POST",
    );
    return;
  }

  // 1. Open Modal Trigger (supports both data-modal-trigger and data-modal-open)
  const trigger =
    e.target.closest("[data-modal-trigger]") ||
    e.target.closest("[data-modal-open]");
  if (trigger) {
    e.preventDefault();
    const modalId =
      trigger.dataset.modalTrigger || trigger.dataset.modalOpen;
    if (modalId === "globalConfirmModal") {
      configureGlobalConfirm(trigger);
    }
    if (trigger.dataset.modalProvider) {
      const modal = document.getElementById(modalId);
      if (modal) {
        modal.dataset.openProvider = trigger.dataset.modalProvider;
      }
    }
    openModal(modalId);
    return;
  }

  // 2. Close Modal Trigger
  const closeBtn = e.target.closest("[data-modal-close]");
  if (closeBtn) {
    e.preventDefault();
    closeModal(closeBtn);
    return;
  }

  // 3. Close on Backdrop Click
  if (
    e.target.classList.contains("modal-backdrop") ||
    e.target.classList.contains("modal__backdrop")
  ) {
    closeModal(e.target.closest(".modal"));
    return;
  }

  // 4. Confirm Button — submit the linked form or resolve promise
  const confirmBtn = e.target.closest("[data-confirm-btn]");
  if (confirmBtn) {
    if (confirmBtn.dataset.confirmAlertOnly === "true") {
      closeModal("globalConfirmModal");
      return;
    }

    if (typeof activeConfirmResolver === "function") {
      const resolve = activeConfirmResolver;
      activeConfirmResolver = null;
      closeModal("globalConfirmModal");
      resolve(true);
      return;
    }

    const formId = confirmBtn.dataset.confirmForm;
    if (formId) {
      const form = document.getElementById(formId);
      if (form) {
        if (confirmBtn.dataset.submitterName) {
          let hiddenInput = form.querySelector(`input[data-confirm-submitter="${confirmBtn.dataset.submitterName}"]`);
          if (!hiddenInput) {
            hiddenInput = document.createElement("input");
            hiddenInput.type = "hidden";
            hiddenInput.name = confirmBtn.dataset.submitterName;
            hiddenInput.dataset.confirmSubmitter = confirmBtn.dataset.submitterName;
            form.appendChild(hiddenInput);
          }
          hiddenInput.value = confirmBtn.dataset.submitterValue || "";
        }
        form.dataset.confirmed = "true";
        closeModal("globalConfirmModal");
        form.submit();
      }
      return;
    }

    closeModal("globalConfirmModal");
    buildAndSubmitDynamicForm(
      confirmBtn.dataset.confirmAction,
      confirmBtn.dataset.confirmMethod || "POST",
    );
    return;
  }
});

document.addEventListener("submit", (e) => {
  const form = e.target.closest("form[data-confirm]");
  if (form && form.dataset.confirmed !== "true") {
    e.preventDefault();
    configureGlobalConfirm(form);
    openModal("globalConfirmModal");
  }
});

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    closeAllModals();
  }
});

// Expose on window for global and Alpine access
window.openModal = openModal;
window.closeModal = closeModal;
window.closeAllModals = closeAllModals;
window.requestConfirm = requestConfirm;
window.showAlertModal = showAlertModal;


