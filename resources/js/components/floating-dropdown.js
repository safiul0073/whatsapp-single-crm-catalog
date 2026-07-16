/**
 * Reusable Floating Dropdown (Functional Approach)
 *
 * Usage:
 * - Trigger element: data-floating-dropdown="targetId"
 * - Dropdown element: id="targetId" class="floating-dropdown-panel"
 */

let activeDropdown = null;
let activeTrigger = null;

const updatePosition = () => {
  if (!activeDropdown || !activeTrigger) return;

  const triggerRect = activeTrigger.getBoundingClientRect();
  const viewportWidth = window.innerWidth;
  const viewportHeight = window.innerHeight;

  // Set width first if needed, as it affects dimensions
  if (activeTrigger.dataset.dropdownWidth === "trigger") {
    activeDropdown.style.width = `${triggerRect.width}px`;
  } else {
    activeDropdown.style.width = "";
  }

  // Force reflow/re-measure to get correct dimensions after width change
  const dropdownRect = activeDropdown.getBoundingClientRect();

  let top = triggerRect.bottom + 8; // 8px gap
  let left = triggerRect.left;

  // RTL Support
  if (document.documentElement.dir === "rtl") {
    left = triggerRect.right - dropdownRect.width;
  }

  // Vertical overflow check
  if (top + dropdownRect.height > viewportHeight) {
    const spaceAbove = triggerRect.top;
    if (spaceAbove > dropdownRect.height + 8) {
      top = triggerRect.top - dropdownRect.height - 8;
      activeDropdown.style.transformOrigin = "bottom left";
    } else {
      if (top + dropdownRect.height > viewportHeight) {
        top = viewportHeight - dropdownRect.height - 8;
      }
    }
  } else {
    activeDropdown.style.transformOrigin = "top left";
  }

  // Horizontal overflow check
  if (left + dropdownRect.width > viewportWidth) {
    left = viewportWidth - dropdownRect.width - 8;
    activeDropdown.style.transformOrigin =
      (activeDropdown.style.transformOrigin?.includes("bottom")
        ? "bottom"
        : "top") + " right";
  }

  if (left < 8) left = 8;

  if (activeTrigger.dataset.dropdownWidth === "trigger") {
    activeDropdown.style.width = `${triggerRect.width}px`;
  } else {
    activeDropdown.style.width = "";
  }

  activeDropdown.style.top = `${top}px`;
  activeDropdown.style.left = `${left}px`;
};

const close = () => {
  if (!activeDropdown) return;

  const dropdown = activeDropdown;
  activeDropdown = null;
  activeTrigger = null;

  dropdown.classList.remove("active");

  const transitionEndHandler = (e) => {
    if (e.target !== dropdown) return;
    dropdown.style.display = "";
    dropdown.removeEventListener("transitionend", transitionEndHandler);
  };

  dropdown.addEventListener("transitionend", transitionEndHandler);
  setTimeout(() => {
    dropdown.style.display = "";
    dropdown.removeEventListener("transitionend", transitionEndHandler);
  }, 250);
};

const open = (trigger, dropdown) => {
  activeTrigger = trigger;
  activeDropdown = dropdown;

  const searchInput = dropdown.querySelector(".dropdown-search-input");
  if (searchInput) {
    searchInput.value = "";
    dropdown
      .querySelectorAll(".floating-dropdown-item")
      .forEach((item) => (item.style.display = ""));
  }

  if (dropdown.parentElement !== document.body) {
    document.body.appendChild(dropdown);
  }

  dropdown.style.display = "block";
  void dropdown.offsetWidth;
  dropdown.classList.add("active");

  updatePosition();
};

const handleSearch = (e) => {
  const searchInput = e.target.closest(".dropdown-search-input");
  if (!searchInput || !activeDropdown) return;

  if (!searchInput.dataset.initialized) {
    searchInput.addEventListener("input", (inputEvent) => {
      const query = inputEvent.target.value.toLowerCase();
      const items = activeDropdown.querySelectorAll(".floating-dropdown-item");
      let matchCount = 0;

      items.forEach((item) => {
        const text = item.innerText.toLowerCase();
        const matches = text.includes(query);
        item.style.display = matches ? "" : "none";
        if (matches) matchCount++;
      });

      let noResults = activeDropdown.querySelector(".no-results-msg");
      if (matchCount === 0) {
        if (!noResults) {
          noResults = document.createElement("div");
          noResults.className =
            "no-results-msg p-4 text-center text-xs text-neutral-400";
          noResults.innerText = "No results found";
          activeDropdown.appendChild(noResults);
        }
      } else if (noResults) {
        noResults.remove();
      }

      updatePosition();
    });
    searchInput.dataset.initialized = "true";
  }
};

const handleItemClick = (e, item) => {
  if (!activeDropdown || !activeTrigger) return;

  const checkbox = item.querySelector('input[type="checkbox"]');
  const isMulti = activeTrigger.dataset.type === "multi" || !!checkbox;

  if (isMulti) {
    if (checkbox && e.target !== checkbox) {
      checkbox.checked = !checkbox.checked;
    }

    const selectedItems = activeDropdown.querySelectorAll(
      'input[type="checkbox"]:checked',
    );
    const labelEl =
      activeTrigger.querySelector(".dropdown-label") || activeTrigger;

    if (selectedItems.length > 0) {
      const names = Array.from(selectedItems).map((cb) => {
        const itemRow = cb.closest(".floating-dropdown-item");
        const titleEl =
          itemRow.querySelector(".item-title") ||
          itemRow.querySelector("label");
        return titleEl ? titleEl.innerText : itemRow.innerText;
      });
      labelEl.innerText = names.join(", ");
    } else {
      labelEl.innerText = activeTrigger.dataset.placeholder || "Select items";
    }

    activeTrigger.dispatchEvent(
      new CustomEvent("change", {
        detail: {
          values: Array.from(selectedItems).map(
            (cb) => cb.closest(".floating-dropdown-item").dataset.value,
          ),
          count: selectedItems.length,
        },
      }),
    );
    return;
  }

  const value = item.dataset.value || item.innerText;
  const labelEl =
    activeTrigger.querySelector(".dropdown-label") || activeTrigger;

  if (
    activeTrigger.classList.contains("floating-select-trigger") ||
    activeTrigger.dataset.type === "select"
  ) {
    const titleEl = item.querySelector(".item-title");
    labelEl.innerText = titleEl ? titleEl.innerText : item.innerText;
  }

  activeDropdown
    .querySelectorAll(".floating-dropdown-item")
    .forEach((i) => i.classList.remove("active"));
  item.classList.add("active");

  activeTrigger.dispatchEvent(
    new CustomEvent("change", {
      detail: { value, label: item.innerText },
    }),
  );

  close();
};

const handleClick = (e) => {
  const trigger = e.target.closest("[data-floating-dropdown]");
  const item = e.target.closest(".floating-dropdown-item");
  const searchInput = e.target.closest(".dropdown-search-input");

  if (searchInput) {
    handleSearch(e);
    return;
  }

  if (item && activeDropdown && activeDropdown.contains(item)) {
    handleItemClick(e, item);
    return;
  }

  if (trigger) {
    const targetId = trigger.dataset.floatingDropdown;
    const dropdown = document.getElementById(targetId);
    if (!dropdown) return;

    if (activeDropdown === dropdown) {
      close();
      return;
    }

    if (activeDropdown) close();
    open(trigger, dropdown);
    return;
  }

  if (activeDropdown && !activeDropdown.contains(e.target)) {
    close();
  }
};

// Initialize
const initFloatingDropdown = () => {
  document.addEventListener("click", handleClick);
  window.addEventListener("resize", close);
  window.addEventListener("scroll", updatePosition, { passive: true });
  document.addEventListener("scroll", updatePosition, {
    capture: true,
    passive: true,
  });
};

initFloatingDropdown();
