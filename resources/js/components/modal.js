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

function configureGlobalConfirm(trigger) {
  const modal = document.getElementById("globalConfirmModal");
  if (!modal) return;

  const title = trigger.dataset.confirmTitle || "Confirm Action";
  const message = trigger.dataset.confirmMessage || "Please confirm this action.";
  const action = trigger.dataset.confirmAction || "";
  const method = trigger.dataset.confirmMethod || "POST";
  const buttonText = trigger.dataset.confirmButton || "Confirm";

  const titleEl = modal.querySelector("[data-confirm-title]");
  const messageEl = modal.querySelector("[data-confirm-message]");
  const buttonEl = modal.querySelector("[data-confirm-btn]");
  const buttonLabelEl = modal.querySelector("[data-confirm-button-label]");

  if (titleEl) titleEl.textContent = title;
  if (messageEl) messageEl.textContent = message;
  if (buttonEl) {
    buttonEl.dataset.confirmAction = action;
    buttonEl.dataset.confirmMethod = method;
    delete buttonEl.dataset.confirmForm;
  }
  if (buttonLabelEl) buttonLabelEl.textContent = buttonText;
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
    modal.classList.remove("active");
    modal.classList.remove("is-open");
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
  document.querySelectorAll(".modal.active").forEach((modal) => {
    closeModal(modal);
  });
}

// Event delegation — registered on document immediately (no DOMContentLoaded needed)
document.addEventListener("click", (e) => {
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

  // 4. Confirm Button — submit the linked form
  const confirmBtn = e.target.closest("[data-confirm-btn]");
  if (confirmBtn) {
    const formId = confirmBtn.dataset.confirmForm;
    if (formId) {
      const form = document.getElementById(formId);
      if (form) form.submit();
      return;
    }

    buildAndSubmitDynamicForm(
      confirmBtn.dataset.confirmAction,
      confirmBtn.dataset.confirmMethod || "POST",
    );
    return;
  }
});

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    closeAllModals();
  }
});
