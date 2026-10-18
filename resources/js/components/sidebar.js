/**
 * Sidebar Component
 * Handle toggle, collapse, contextual module panels, favorites, and mobile overlay
 */

document.addEventListener("DOMContentLoaded", () => {
  "use strict";

  const appSidebar = document.getElementById("appSidebar");
  const appBackdrop = document.getElementById("appSidebarBackdrop");
  const appOpenBtn = document.getElementById("appSidebarOpen");
  const appCloseBtn = document.getElementById("appSidebarClose");
  const appShell = document.querySelector(".app-shell");

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

  // Scroll active item into view
  setTimeout(() => {
    const activeItem = document.querySelector(
      ".app-nav__module-panel:not(.is-hidden) .app-nav__link.is-active, .nav-item.active",
    );
    if (activeItem) {
      activeItem.scrollIntoView({ behavior: "auto", block: "nearest" });
    }
  }, 50);

  // Restore collapse state
  const isCollapsed = localStorage.getItem("sidebar-collapsed") === "true";
  if (isCollapsed && window.innerWidth >= 992) {
    sidebar?.classList.add("collapsed");
    mainContent?.classList.add("sidebar-collapsed");
    appShell?.classList.add("sidebar-collapsed");
  }

  // Contextual module panel switching (mobile module switcher & client-side preview)
  function switchModulePanel(moduleId, label, icon) {
    if (!appSidebar || !moduleId) return;

    appSidebar.querySelectorAll("[data-module-panel]").forEach((panel) => {
      panel.classList.toggle("is-hidden", panel.dataset.modulePanel !== moduleId);
    });

    appSidebar.querySelectorAll("[data-module-switch]").forEach((btn) => {
      btn.classList.toggle("is-active", btn.dataset.moduleSwitch === moduleId);
    });

    document.querySelectorAll("[data-module-trigger]").forEach((trigger) => {
      const isMatch = trigger.dataset.moduleTrigger === moduleId;
      trigger.classList.toggle("is-active", isMatch);
      trigger.setAttribute("aria-current", isMatch ? "page" : "false");
    });

    const titleEl = appSidebar.querySelector("[data-active-module-title]");
    if (titleEl && label) {
      titleEl.textContent = label;
    }

    const iconEl = appSidebar.querySelector("[data-active-module-icon]");
    if (iconEl && icon) {
      iconEl.className = `ph ${icon} text-sm`;
    }
  }

  // Favorites management (lightweight, max 5 items, permission-safe via DOM lookup)
  const FAVORITES_STORAGE_KEY = "wapro-nav-favorites";
  const MAX_FAVORITES = 5;

  function loadFavorites() {
    try {
      const raw = localStorage.getItem(FAVORITES_STORAGE_KEY);
      const parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed.slice(0, MAX_FAVORITES) : [];
    } catch {
      return [];
    }
  }

  function saveFavorites(routes) {
    try {
      localStorage.setItem(
        FAVORITES_STORAGE_KEY,
        JSON.stringify(routes.slice(0, MAX_FAVORITES)),
      );
    } catch {
      // Ignore storage quota errors
    }
  }

  function renderFavorites() {
    if (!appSidebar) return;

    const container = document.getElementById("appSidebarFavorites");
    const list = document.getElementById("appSidebarFavoritesList");
    if (!container || !list) return;

    const storedRoutes = loadFavorites();
    const validRoutes = [];
    list.innerHTML = "";

    storedRoutes.forEach((routeName) => {
      const sourceLink = appSidebar.querySelector(
        `.app-nav__module-panel a[data-nav-route="${CSS.escape(routeName)}"]`,
      );
      if (!sourceLink) return;

      validRoutes.push(routeName);

      const label = sourceLink.dataset.navLabel || sourceLink.textContent.trim();
      const icon = sourceLink.dataset.navIcon || "ph-star";
      const href = sourceLink.getAttribute("href") || "#";
      const isActive = sourceLink.classList.contains("is-active");

      const row = document.createElement("div");
      row.className = "app-nav__item group/nav relative flex items-center";

      const link = document.createElement("a");
      link.href = href;
      link.className = `app-nav__link flex-1 ${isActive ? "is-active" : ""}`;
      link.setAttribute("aria-label", label);

      const iconWrap = document.createElement("span");
      iconWrap.className = "relative inline-grid shrink-0 place-items-center";
      const iconNode = document.createElement("i");
      iconNode.className = `ph ${icon} text-xl`;
      iconWrap.appendChild(iconNode);

      const textNode = document.createElement("span");
      textNode.className = "app-nav__text truncate";
      textNode.textContent = label;

      link.appendChild(iconWrap);
      link.appendChild(textNode);

      const unpinBtn = document.createElement("button");
      unpinBtn.type = "button";
      unpinBtn.className = "app-nav__pin is-pinned";
      unpinBtn.dataset.action = "toggle-favorite";
      unpinBtn.dataset.favRoute = routeName;
      unpinBtn.setAttribute("aria-label", "Unpin from favorites");
      unpinBtn.setAttribute("title", "Unpin from favorites");
      const pinIcon = document.createElement("i");
      pinIcon.className = "ph-fill ph-push-pin text-xs";
      unpinBtn.appendChild(pinIcon);

      row.appendChild(link);
      row.appendChild(unpinBtn);
      list.appendChild(row);
    });

    if (validRoutes.length !== storedRoutes.length) {
      saveFavorites(validRoutes);
    }

    container.classList.toggle("is-hidden", validRoutes.length === 0);

    appSidebar
      .querySelectorAll(".app-nav__module-panel [data-fav-route]")
      .forEach((btn) => {
        const route = btn.dataset.favRoute;
        const isPinned = validRoutes.includes(route);
        btn.classList.toggle("is-pinned", isPinned);
        const icon = btn.querySelector("i");
        if (icon) {
          icon.className = isPinned
            ? "ph-fill ph-push-pin text-xs"
            : "ph ph-push-pin text-xs";
        }
      });
  }

  renderFavorites();

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
      tooltip.className = "sidebar-tooltip px-3.5 py-2";
    }

    tooltip.classList.add("show");

    const tooltipWidth = tooltip.offsetWidth;
    const gap = 12;

    let left;
    if (isRTL) {
      left = rect.left - tooltipWidth - gap;
    } else {
      left = rect.right + gap;
    }

    tooltip.style.left = `${left}px`;
    tooltip.style.top = `${rect.top}px`;
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

    const activeSidebar = document.getElementById("sidebar") || appSidebar;
    if (!activeSidebar || !activeSidebar.classList.contains("collapsed")) return;

    const item = e.target.closest(
      ".nav-item, .app-nav__link, .sidebar-logo, .app-sidebar__brand",
    );
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
    const item = e.target.closest(
      ".nav-item, .app-nav__link, .sidebar-logo, .app-sidebar__brand, .sidebar-tooltip",
    );
    if (item) hideTooltip();
  });

  // Hide tooltip on scroll to prevent "stuck" tooltips
  document.addEventListener("scroll", hideTooltip, true);
  window.addEventListener("resize", hideTooltip);

  document.addEventListener("click", (e) => {
    const moduleSwitchBtn = e.target.closest("[data-module-switch]");
    if (moduleSwitchBtn) {
      e.preventDefault();
      switchModulePanel(
        moduleSwitchBtn.dataset.moduleSwitch,
        moduleSwitchBtn.dataset.moduleLabel,
        moduleSwitchBtn.dataset.moduleIcon,
      );
      return;
    }

    const trigger = e.target.closest("[data-action]");
    if (!trigger) return;

    const action = trigger.dataset.action;
    const activeSidebar = document.getElementById("sidebar") || appSidebar;
    const activeMainContent = document.getElementById("mainContent");
    const activeSidebarOverlay = document.getElementById("sidebarOverlay");

    if (action === "toggle-favorite") {
      e.preventDefault();
      e.stopPropagation();
      const favRoute = trigger.dataset.favRoute;
      if (!favRoute) return;

      const current = loadFavorites();
      const idx = current.indexOf(favRoute);
      if (idx >= 0) {
        current.splice(idx, 1);
      } else if (current.length < MAX_FAVORITES) {
        current.push(favRoute);
      }
      saveFavorites(current);
      renderFavorites();
      return;
    }

    if (action === "toggle-sidebar") {
      if (!activeSidebar) return;

      if (window.innerWidth < 992) {
        const isOpen = activeSidebar.classList.toggle("open");
        activeSidebarOverlay?.classList.toggle("hidden", !isOpen);
        document.body.classList.toggle("overflow-hidden", isOpen);
      } else {
        const collapsed = activeSidebar.classList.toggle("collapsed");
        activeMainContent?.classList.toggle("sidebar-collapsed", collapsed);
        appShell?.classList.toggle("sidebar-collapsed", collapsed);
        localStorage.setItem("sidebar-collapsed", collapsed);

        if (collapsed) {
          activeSidebar
            .querySelectorAll(".nav-item-wrapper.expanded")
            .forEach((item) => item.classList.remove("expanded"));
        }

        hideTooltip(true);
      }
    }

    if (action === "toggle-submenu") {
      const wrapper = trigger.closest(".nav-item-wrapper");
      if (!wrapper || !activeSidebar) return;

      if (activeSidebar.classList.contains("collapsed")) {
        activeSidebar.classList.remove("collapsed");
        activeMainContent?.classList.remove("sidebar-collapsed");
        appShell?.classList.remove("sidebar-collapsed");
        localStorage.setItem("sidebar-collapsed", "false");

        wrapper.classList.add("expanded");
        return;
      }

      const otherExpanded = activeSidebar.querySelectorAll(
        ".nav-item-wrapper.expanded",
      );
      otherExpanded.forEach((item) => {
        if (item !== wrapper) item.classList.remove("expanded");
      });

      wrapper.classList.toggle("expanded");
    }

    if (action === "close-sidebar-mobile") {
      activeSidebar?.classList.remove("open");
      activeSidebarOverlay?.classList.add("hidden");
      document.body.classList.remove("overflow-hidden");
    }
  });

  sidebarOverlay?.addEventListener("click", () => {
    sidebar?.classList.remove("open");
    sidebarOverlay.classList.add("hidden");
    document.body.classList.remove("overflow-hidden");
  });
});
