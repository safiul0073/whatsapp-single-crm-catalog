// Nav behaviors:
//   • shrink on scroll (.is-scrolled on <header>)
//   • mobile drawer toggle
//   • mega-menu open/close (hover + click) with hit-bridge tolerance
//   • magnetic hover pill that slides between nav items

import { renderIcons } from "./icons.js";

const SCROLL_THRESHOLD = 0;
const CLOSE_DELAY_MS = 140;

function initScrollShrink() {
  const nav = document.querySelector("[data-nav]");
  if (!nav) return;
  const sync = () => nav.classList.toggle("is-scrolled", window.scrollY > SCROLL_THRESHOLD);
  sync();
  window.addEventListener("scroll", sync, { passive: true });
}

function initDrawer() {
  const toggle = document.querySelector("[data-nav-toggle]");
  const drawer = document.querySelector("[data-nav-drawer]");
  const iconMenu = document.querySelector("[data-nav-icon-menu]");
  const iconClose = document.querySelector("[data-nav-icon-close]");
  if (!toggle || !drawer) return;

  const setOpen = (open) => {
    toggle.setAttribute("aria-expanded", String(open));
    drawer.classList.toggle("hidden", !open);
    if (iconMenu) iconMenu.classList.toggle("hidden", open);
    if (iconClose) iconClose.classList.toggle("hidden", !open);
  };

  toggle.addEventListener("click", () => {
    setOpen(toggle.getAttribute("aria-expanded") !== "true");
  });

  drawer.querySelectorAll("a").forEach((a) => a.addEventListener("click", () => setOpen(false)));
}

function initMegaMenus() {
  const nav = document.querySelector("[data-nav]");
  const list = document.querySelector("[data-nav-list]");
  const panels = document.querySelector("[data-nav-panels]");
  if (!nav || !list || !panels) return;

  const triggers = list.querySelectorAll("[data-nav-trigger]");
  const panelEls = panels.querySelectorAll("[data-nav-panel]");
  let activeKey = null;
  let closeTimer = 0;

  const setActive = (key) => {
    if (activeKey === key) return;
    activeKey = key;
    triggers.forEach((t) => {
      const isActive = t.dataset.navTrigger === key;
      t.classList.toggle("is-active", isActive);
      t.setAttribute("aria-expanded", String(isActive));
    });
    panelEls.forEach((p) => {
      p.classList.toggle("is-open", p.dataset.navPanel === key);
    });
  };

  const open = (key) => {
    clearTimeout(closeTimer);
    setActive(key);
  };
  const scheduleClose = () => {
    closeTimer = window.setTimeout(() => setActive(null), CLOSE_DELAY_MS);
  };

  triggers.forEach((t) => {
    const key = t.dataset.navTrigger;
    t.addEventListener("mouseenter", () => open(key));
    t.addEventListener("mouseleave", scheduleClose);
    t.addEventListener("click", (e) => {
      e.preventDefault();
      setActive(activeKey === key ? null : key);
    });
  });

  panelEls.forEach((p) => {
    p.addEventListener("mouseenter", () => clearTimeout(closeTimer));
    p.addEventListener("mouseleave", scheduleClose);
  });

  document.addEventListener("mousedown", (e) => {
    if (!nav.contains(e.target)) setActive(null);
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") setActive(null);
  });
}

function initHoverPill() {
  const list = document.querySelector("[data-nav-list]");
  const pill = document.querySelector("[data-nav-pill]");
  if (!list || !pill) return;

  const items = list.querySelectorAll(".nav__item");
  let lastHover = null;

  const measure = (el) => {
    if (!el) return;
    const lRect = list.getBoundingClientRect();
    const eRect = el.getBoundingClientRect();
    pill.style.transform = `translate3d(${eRect.left - lRect.left}px, -50%, 0)`;
    pill.style.width = `${eRect.width}px`;
    pill.style.opacity = "1";
  };
  const hide = () => {
    lastHover = null;
    pill.style.opacity = "0";
  };

  items.forEach((el) => {
    el.addEventListener("mouseenter", () => {
      lastHover = el;
      measure(el);
    });
  });
  list.addEventListener("mouseleave", hide);

  window.addEventListener("resize", () => {
    if (lastHover) measure(lastHover);
  });
}

function initHelplines() {
  const root = document.querySelector("[data-nav-helplines-root]");
  const toggle = document.querySelector("[data-nav-helplines-toggle]");
  const panel = document.querySelector("[data-nav-helplines-panel]");
  if (!root || !toggle || !panel) return;

  const setOpen = (open) => {
    toggle.setAttribute("aria-expanded", String(open));
    panel.classList.toggle("hidden", !open);
  };

  toggle.addEventListener("click", () => {
    setOpen(toggle.getAttribute("aria-expanded") !== "true");
  });
  document.addEventListener("mousedown", (e) => {
    if (!root.contains(e.target)) setOpen(false);
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") setOpen(false);
  });
}

function initDrawerAccordion() {
  const groups = document.querySelectorAll("[data-drawer-group]");
  groups.forEach((group) => {
    const trigger = group.querySelector("[data-drawer-trigger]");
    const children = group.querySelector("[data-drawer-children]");
    if (!trigger || !children) return;
    trigger.addEventListener("click", () => {
      const open = trigger.getAttribute("aria-expanded") !== "true";
      trigger.setAttribute("aria-expanded", String(open));
      children.classList.toggle("hidden", !open);
    });
  });
}

function init() {
  initScrollShrink();
  initDrawer();
  initMegaMenus();
  initHoverPill();
  initHelplines();
  initDrawerAccordion();
  renderIcons();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", init);
} else {
  init();
}
