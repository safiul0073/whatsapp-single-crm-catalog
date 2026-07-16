/**
 * Drawer Component
 * Handles opening and closing of side drawers/panels.
 */

/**
 * Opens a drawer by its ID.
 * @param {string} drawerId
 */
export function openDrawer(drawerId) {
  const drawer = document.getElementById(drawerId);
  let drawerOverlay = document.getElementById("drawerOverlay");

  // Create overlay if it doesn't exist
  if (!drawerOverlay) {
    drawerOverlay = document.createElement("div");
    drawerOverlay.id = "drawerOverlay";
    drawerOverlay.className = "drawer-overlay";
    document.body.appendChild(drawerOverlay);

    // Add click listener to the new overlay
    drawerOverlay.addEventListener("click", closeAllDrawers);
  }

  if (drawer) {
    drawer.classList.add("active");
    drawerOverlay.classList.add("active");
    document.body.classList.add("overflow-hidden");
  } else {
    console.warn(`Drawer with ID '${drawerId}' not found.`);
  }
}

/**
 * Closes all active drawers.
 */
export function closeAllDrawers() {
  document
    .querySelectorAll(
      ".drawer.active, .drawer-left.active, .drawer-bottom.active",
    )
    .forEach((d) => {
      d.classList.remove("active");
    });

  const drawerOverlay = document.getElementById("drawerOverlay");
  if (drawerOverlay) {
    drawerOverlay.classList.remove("active");
  }

  document.body.classList.remove("overflow-hidden");
}

/* 
  Initialize event listeners when DOM is ready 
  This pattern mimics toast.js where it self-initializes its global listeners
*/
document.addEventListener("DOMContentLoaded", () => {
  document.addEventListener("click", (e) => {
    // 1. Open Drawer Trigger
    const drawerTrigger = e.target.closest("[data-drawer-trigger]");
    if (drawerTrigger) {
      // Prevent default mostly if it's an anchor tag
      if (drawerTrigger.tagName === "A") e.preventDefault();

      const targetId = drawerTrigger.dataset.drawerTrigger;
      if (drawerTrigger.dataset.drawerProvider) {
        const drawer = document.getElementById(targetId);
        if (drawer) {
          drawer.dataset.openProvider = drawerTrigger.dataset.drawerProvider;
        }
      }
      openDrawer(targetId);
      return;
    }

    // 2. Close Drawer Trigger (Button within drawer)
    if (e.target.closest("[data-drawer-close]")) {
      closeAllDrawers();
      return;
    }

    // 3. Close on Overlay Click (if overlay is already present)
    if (e.target.id === "drawerOverlay") {
      closeAllDrawers();
      return;
    }
  });
});
