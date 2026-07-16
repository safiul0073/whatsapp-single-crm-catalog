/**
 * Table Selection Module
 * Handles "Select All" functionality within tables.
 *
 * Usage:
 * - Header checkbox: data-select-all="tableGroup"
 * - Individual checkboxes: data-select-item="tableGroup"
 * - Each item checkbox should have a value attribute with the record ID
 *
 * Events:
 * - Dispatches 'bulk-selection:changed' on document with detail: { group, selectedIds, count }
 *
 * API:
 * - window.getSelectedIds(group) — returns array of selected IDs for a group
 */

const selectedMap = {};

const getSelectedIds = (group) => {
  return selectedMap[group] || [];
};

const updateSelectedIds = (group) => {
  const items = document.querySelectorAll(`[data-select-item="${group}"]`);
  const ids = [];

  items.forEach((item) => {
    if (item.checked && item.value) {
      ids.push(item.value);
    }
  });

  selectedMap[group] = ids;

  document.dispatchEvent(
    new CustomEvent("bulk-selection:changed", {
      detail: { group, selectedIds: ids, count: ids.length },
    }),
  );
};

const updateRowStyle = (checkbox) => {
  const row = checkbox.closest("tr, .list-table__row");
  if (!row) return;

  if (checkbox.checked) {
    row.classList.add("is-selected");
  } else {
    row.classList.remove("is-selected");
  }
};

const handleSelectAll = (selectAll) => {
  const group = selectAll.dataset.selectAll;
  const items = document.querySelectorAll(`[data-select-item="${group}"]`);
  const isChecked = selectAll.checked;

  items.forEach((item) => {
    item.checked = isChecked;
    updateRowStyle(item);
  });

  updateSelectedIds(group);
};

const handleSelectItem = (selectItem) => {
  const group = selectItem.dataset.selectItem;
  const selectAll = document.querySelector(`[data-select-all="${group}"]`);
  const items = document.querySelectorAll(`[data-select-item="${group}"]`);

  const itemsArray = Array.from(items);
  const allChecked =
    itemsArray.length > 0 && itemsArray.every((item) => item.checked);
  const someChecked = itemsArray.some((item) => item.checked);

  if (selectAll) {
    selectAll.checked = allChecked;
    selectAll.indeterminate = someChecked && !allChecked;
  }

  updateRowStyle(selectItem);
  updateSelectedIds(group);
};

const initTableSelection = () => {
  document.addEventListener("change", (e) => {
    const selectAll = e.target.closest("[data-select-all]");
    const selectItem = e.target.closest("[data-select-item]");

    if (selectAll) {
      handleSelectAll(selectAll);
    } else if (selectItem) {
      handleSelectItem(selectItem);
    }
  });

  // Reset selection state after datatable content swap
  document.addEventListener("datatable:updated", (e) => {
    const el = e.detail?.el;
    if (!el) return;

    // Find all groups within the updated element and reset their state
    const items = el.querySelectorAll("[data-select-item]");
    const groups = new Set();

    items.forEach((item) => groups.add(item.dataset.selectItem));
    groups.forEach((group) => {
      selectedMap[group] = [];
      const selectAll = document.querySelector(
        `[data-select-all="${group}"]`,
      );
      if (selectAll) {
        selectAll.checked = false;
        selectAll.indeterminate = false;
      }
      document.dispatchEvent(
        new CustomEvent("bulk-selection:changed", {
          detail: { group, selectedIds: [], count: 0 },
        }),
      );
    });
  });
};

// Expose globally
window.getSelectedIds = getSelectedIds;

// Initialize
initTableSelection();
