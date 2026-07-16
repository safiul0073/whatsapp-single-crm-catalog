/**
 * Bulk Actions - Alpine.js Component
 *
 * Listens for bulk-selection:changed events and provides
 * bulk delete and bulk toggle status actions via AJAX.
 *
 * Usage:
 *   <div x-data="bulkActions({ group: 'products', deleteAction: '/url', toggleAction: '/url' })">
 */

import Alpine from "alpinejs";

Alpine.data("bulkActions", (config = {}) => ({
  group: config.group || "",
  deleteAction: config.deleteAction || "",
  toggleAction: config.toggleAction || "",
  exportAction: config.exportAction || "",
  selectedIds: [],
  count: 0,
  processing: false,

  init() {
    document.addEventListener("bulk-selection:changed", (e) => {
      if (e.detail.group !== this.group) return;
      this.selectedIds = e.detail.selectedIds;
      this.count = e.detail.count;
    });
  },

  async bulkDelete() {
    if (!this.deleteAction || this.selectedIds.length === 0) return;

    if (
      !confirm(
        `Are you sure you want to delete ${this.count} selected record(s)? This action cannot be undone.`,
      )
    ) {
      return;
    }

    await this.sendRequest(this.deleteAction, { ids: this.selectedIds });
  },

  async bulkToggleStatus() {
    if (!this.toggleAction || this.selectedIds.length === 0) return;

    await this.sendRequest(this.toggleAction, { ids: this.selectedIds });
  },

  exportSelected() {
    if (!this.exportAction || this.selectedIds.length === 0) return;

    const params = new URLSearchParams();
    this.selectedIds.forEach((id) => params.append("ids[]", id));
    window.location.href = `${this.exportAction}?${params.toString()}`;
  },

  async sendRequest(url, data) {
    this.processing = true;

    try {
      const response = await window.axios.post(url, data);

      if (response.data?.message) {
        window.showToast("Success", response.data.message, "success");
      }

      // Refresh the datatable
      this.refreshTable();
    } catch (error) {
      const message =
        error.response?.data?.message || "An error occurred. Please try again.";
      window.showToast("Error", message, "error");
    } finally {
      this.processing = false;
    }
  },

  refreshTable() {
    // Reset selection state
    this.selectedIds = [];
    this.count = 0;

    // Find the closest datatable and trigger a refresh
    const datatableEl = this.$el.closest("[x-data]");
    if (datatableEl) {
      // Trigger Alpine dataTable fetchData by dispatching popstate
      // or directly calling fetchData on the Alpine component
      const dataTableComponent = datatableEl.querySelector(
        "[data-datatable-body]",
      );
      if (dataTableComponent) {
        const dtRoot = dataTableComponent.closest("[x-data]");
        if (dtRoot && dtRoot._x_dataStack) {
          const dt = dtRoot._x_dataStack[0];
          if (typeof dt.fetchData === "function") {
            dt.fetchData();
            return;
          }
        }
      }
    }

    // Fallback: reload the page
    window.location.reload();
  },
}));
