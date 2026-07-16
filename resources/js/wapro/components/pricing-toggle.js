// Pricing billing toggle — Monthly / Yearly / Lifetime. Updates each price's
// text from data-monthly / data-yearly / data-lifetime and marks the active
// toggle button with a single semantic class (CLAUDE.md §4). The Lifetime mode
// is optional: cards without data-lifetime fall back to their yearly value, and
// pages without a lifetime button simply never trigger it. Functional,
// null-guarded.
document.addEventListener("DOMContentLoaded", () => {
  const root = document.getElementById("pricing");
  if (!root) return;

  const buttons = root.querySelectorAll("[data-billing-btn]");
  const prices = root.querySelectorAll("[data-monthly][data-yearly]");
  const periods = root.querySelectorAll("[data-period]");
  const saveTag = root.querySelector("[data-billing-save]");
  if (!buttons.length || !prices.length) return;

  const PERIOD_LABEL = {
    monthly: "/mo",
    yearly: "/mo · billed yearly",
    lifetime: "one-time",
  };

  function priceFor(el, mode) {
    if (mode === "lifetime") return el.dataset.lifetime ?? el.dataset.yearly;
    if (mode === "yearly") return el.dataset.yearly;
    return el.dataset.monthly;
  }

  function apply(mode) {
    prices.forEach((el) => {
      el.textContent = priceFor(el, mode);
    });
    periods.forEach((el) => {
      el.textContent = PERIOD_LABEL[mode] ?? PERIOD_LABEL.monthly;
    });
    buttons.forEach((btn) => {
      btn.classList.toggle("is-active", btn.dataset.billingBtn === mode);
    });
    if (saveTag) {
      saveTag.classList.toggle("is-shown", mode !== "monthly");
      // optional per-mode label (e.g. "Save 20%" vs "Best value")
      const label = saveTag.dataset[`save${mode[0].toUpperCase()}${mode.slice(1)}`];
      if (label) saveTag.textContent = label;
    }
  }

  buttons.forEach((btn) => {
    btn.addEventListener("click", () => apply(btn.dataset.billingBtn));
  });

  apply("monthly");
});
