/**
 * Sidebar Component
 * Handle toggle, collapse, and mobile overlay
 */

document.addEventListener("DOMContentLoaded", () => {
  const appSidebar = document.getElementById("appSidebar");
  const appBackdrop = document.getElementById("appSidebarBackdrop");
  const appOpenBtn = document.getElementById("appSidebarOpen");
  const appCloseBtn = document.getElementById("appSidebarClose");

  if (appSidebar && appBackdrop) {
    const openAppSidebar = () => {
      appSidebar.classList.add("is-open");
      appBackdrop.classList.add("is-open");
      document.body.classList.add("overflow-hidden");
    };

    const closeAppSidebar = () => {
      appSidebar.classList.remove("is-open");
      appBackdrop.classList.remove("is-open");
      document.body.classList.remove("overflow-hidden");
    };

    appOpenBtn?.addEventListener("click", openAppSidebar);
    appCloseBtn?.addEventListener("click", closeAppSidebar);
    appBackdrop.addEventListener("click", closeAppSidebar);
  }

  const sidebar = document.getElementById("sidebar") || appSidebar;
  const sidebarOverlay = document.getElementById("sidebarOverlay");
  const mainContent = document.getElementById("mainContent");

  // Restore collapse state
  const isCollapsed = localStorage.getItem("sidebar-collapsed") === "true";
  if (isCollapsed && window.innerWidth >= 992) {
    sidebar?.classList.add("collapsed");
    mainContent?.classList.add("sidebar-collapsed");
  }

  // Handle actions
  let hideTimeout = null;

  // Singleton tooltip element
  let tooltipEl = null;

  function createTooltip() {
    if (tooltipEl) return tooltipEl;
    tooltipEl = document.createElement("div");
    tooltipEl.className = "sidebar-tooltip";
    document.body.appendChild(tooltipEl);
    return tooltipEl;
  }

  function showTooltip(target, text) {
    if (hideTimeout) clearTimeout(hideTimeout);

    const tooltip = createTooltip();
    const rect = target.getBoundingClientRect();
    const isRTL = document.documentElement.dir === "rtl";

    // Check for sub-menu items
    const wrapper = target.closest(".nav-item-wrapper");
    const submenuItems = wrapper?.querySelectorAll(".submenu-item");

    if (submenuItems && submenuItems.length > 0) {
      let content = `<div class="font-bold border-b border-neutral-100 px-3 pb-2 mb-1">${text}</div>`;
      content += `<div class="space-y-0.5">`;
      submenuItems.forEach((item) => {
        const href = item.getAttribute("href") || "#";
        const isActive = item.classList.contains("active")
          ? "bg-primary/10 text-primary"
          : "";
        content += `<a href="${href}" class="sidebar-tooltip-item ${isActive}">${item.textContent.trim()}</a>`;
      });
      content += `</div>`;
      tooltip.innerHTML = content;
    } else {
      tooltip.textContent = text;
      tooltip.className = "sidebar-tooltip px-3.5 py-2"; // Standard padding for single line
    }

    tooltip.classList.add("show");

    const tooltipWidth = tooltip.offsetWidth;
    const tooltipHeight = tooltip.offsetHeight;
    const gap = 12;

    let left, top;

    if (isRTL) {
      left = rect.left - tooltipWidth - gap;
    } else {
      left = rect.right + gap;
    }

    top = rect.top;

    tooltip.style.left = `${left}px`;
    tooltip.style.top = `${top}px`;
  }

  function hideTooltip(immediate = false) {
    if (hideTimeout) clearTimeout(hideTimeout);

    if (immediate) {
      if (tooltipEl) {
        tooltipEl.classList.remove("show");
      }
      return;
    }

    hideTimeout = setTimeout(() => {
      if (tooltipEl) {
        tooltipEl.classList.remove("show");
      }
    }, 200);
  }

  document.addEventListener("mouseover", (e) => {
    if (e.target.closest(".sidebar-tooltip")) {
      if (hideTimeout) clearTimeout(hideTimeout);
    }

    const sidebar = document.getElementById("sidebar") || appSidebar;
    if (!sidebar || !sidebar.classList.contains("collapsed")) return;

    const item = e.target.closest(".nav-item, .app-nav__link, .sidebar-logo, .app-sidebar__brand");
    if (!item) return;

    const text =
      item.getAttribute("aria-label") ||
      (item.classList.contains("sidebar-logo") ||
      item.classList.contains("app-sidebar__brand")
        ? "WaPro"
        : null);
    if (text) {
      showTooltip(item, text);
    }
  });

  document.addEventListener("mouseout", (e) => {
    const item = e.target.closest(".nav-item, .app-nav__link, .sidebar-logo, .app-sidebar__brand, .sidebar-tooltip");
    if (item) hideTooltip();
  });

  // Hide tooltip on scroll to prevent "stuck" tooltips
  document.addEventListener("scroll", hideTooltip, true);
  window.addEventListener("resize", hideTooltip);

  document.addEventListener("click", (e) => {
    const trigger = e.target.closest("[data-action]");
    if (!trigger) return;

    const action = trigger.dataset.action;
    const sidebar = document.getElementById("sidebar") || appSidebar;
    const mainContent = document.getElementById("mainContent");
    const sidebarOverlay = document.getElementById("sidebarOverlay");

    if (action === "toggle-sidebar") {
      if (!sidebar) return;

      if (window.innerWidth < 992) {
        // Mobile toggle
        const isOpen = sidebar.classList.toggle("open");
        sidebarOverlay?.classList.toggle("hidden", !isOpen);
        document.body.classList.toggle("overflow-hidden", isOpen);
      } else {
        // Desktop collapse
        const collapsed = sidebar.classList.toggle("collapsed");
        mainContent?.classList.toggle("sidebar-collapsed", collapsed);
        localStorage.setItem("sidebar-collapsed", collapsed);

        // When collapsing, close all expanded submenus for a clean state
        if (collapsed) {
          sidebar
            .querySelectorAll(".nav-item-wrapper.expanded")
            .forEach((item) => item.classList.remove("expanded"));
        }

        hideTooltip(true);
      }
    }

    if (action === "toggle-submenu") {
      const wrapper = trigger.closest(".nav-item-wrapper");
      if (!wrapper || !sidebar) return;

      // If sidebar is collapsed, expand it first so user can see the submenu
      if (sidebar.classList.contains("collapsed")) {
        sidebar.classList.remove("collapsed");
        mainContent?.classList.remove("sidebar-collapsed");
        localStorage.setItem("sidebar-collapsed", "false");

        // Force expand the clicked one
        wrapper.classList.add("expanded");
        return;
      }

      // Optional: Close other expanded submenus (Accordion style)
      const otherExpanded = sidebar.querySelectorAll(
        ".nav-item-wrapper.expanded",
      );
      otherExpanded.forEach((item) => {
        if (item !== wrapper) item.classList.remove("expanded");
      });

      wrapper.classList.toggle("expanded");
    }

    if (action === "close-sidebar-mobile") {
      sidebar?.classList.remove("open");
      sidebarOverlay?.classList.add("hidden");
      document.body.classList.remove("overflow-hidden");
    }
  });

  // Close on overlay click
  sidebarOverlay?.addEventListener("click", () => {
    sidebar?.classList.remove("open");
    sidebarOverlay.classList.add("hidden");
    document.body.classList.remove("overflow-hidden");
  });
});
