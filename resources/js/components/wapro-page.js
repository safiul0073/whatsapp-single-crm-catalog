document.addEventListener("click", (event) => {
  const tab = event.target.closest("[data-tab-target]");
  if (tab) {
    const group = tab.closest("[data-tab-group]");
    const groupName = group?.dataset.tabGroup;
    const target = tab.dataset.tabTarget;
    if (!groupName || !target) return;

    group.querySelectorAll("[data-tab-target]").forEach((item) => {
      item.classList.toggle("is-active", item === tab);
    });

    document.querySelectorAll(`[data-tab-panel="${groupName}"]`).forEach((panel) => {
      panel.classList.toggle("hidden", panel.id !== target);
    });
  }

  const toggle = event.target.closest("[data-password-toggle]");
  if (toggle) {
    const input = document.getElementById(toggle.dataset.passwordToggle);
    if (!input) return;

    input.type = input.type === "password" ? "text" : "password";
    const icon = toggle.querySelector("i");
    icon?.classList.toggle("ph-eye", input.type === "password");
    icon?.classList.toggle("ph-eye-slash", input.type !== "password");
  }
});
