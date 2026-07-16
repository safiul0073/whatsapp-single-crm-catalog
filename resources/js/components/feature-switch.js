/**
 * Feature Switch Component
 * Handles toggle-feature action for config-tile cards
 */
document.addEventListener("click", (e) => {
  const trigger = e.target.closest('[data-action="toggle-feature"]');
  if (!trigger) return;

  const tile = trigger.closest(".config-tile");
  if (!tile) return;

  const stateStrip = tile.querySelector(".state-strip");
  const statusText = tile.querySelector(".status-text");
  const knob = trigger.querySelector(".knob");
  const hiddenInput = tile.querySelector("[data-feature-input]");

  const isCurrentlyOn = tile.dataset.state === "on";

  if (isCurrentlyOn) {
    // Switch to OFF
    tile.dataset.state = "off";

    if (stateStrip) {
      stateStrip.classList.remove(
        "bg-success",
        "opacity-100",
        "shadow-[0_0_8px_var(--color-success)]",
      );
      stateStrip.classList.add("bg-error", "opacity-20");
    }

    if (statusText) {
      statusText.textContent = "Disabled";
      statusText.classList.remove("text-success");
      statusText.classList.add("text-error");
    }

    trigger.classList.remove("bg-success/10", "border-success/30");
    trigger.classList.add("bg-error/10", "border-error/30");

    if (knob) {
      knob.classList.remove("start-7", "bg-success");
      knob.classList.add("start-1", "bg-error");

      const icon = knob.querySelector("i");
      if (icon) {
        icon.classList.remove("ph-check");
        icon.classList.add("ph-x");
      }
    }

    if (hiddenInput) hiddenInput.value = "0";
  } else {
    // Switch to ON
    tile.dataset.state = "on";

    if (stateStrip) {
      stateStrip.classList.remove("bg-error", "opacity-20");
      stateStrip.classList.add(
        "bg-success",
        "opacity-100",
        "shadow-[0_0_8px_var(--color-success)]",
      );
    }

    if (statusText) {
      statusText.textContent = "Enabled";
      statusText.classList.remove("text-error");
      statusText.classList.add("text-success");
    }

    trigger.classList.remove("bg-error/10", "border-error/30");
    trigger.classList.add("bg-success/10", "border-success/30");

    if (knob) {
      knob.classList.remove("start-1", "bg-error");
      knob.classList.add("start-7", "bg-success");

      const icon = knob.querySelector("i");
      if (icon) {
        icon.classList.remove("ph-x");
        icon.classList.add("ph-check");
      }
    }

    if (hiddenInput) hiddenInput.value = "1";
  }
});
