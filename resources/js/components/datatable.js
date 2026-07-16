/**
 * DataTable - Alpine.js Progressive Enhancement Component
 *
 * Wraps server-rendered tables with AJAX search, sort, pagination,
 * and per-page selection. Falls back to standard GET behavior without JS.
 *
 * Usage:
 *   <div x-data="dataTable({ url: '/admin/products' })">
 *
 * The component expects the server to return JSON on AJAX requests:
 *   { html: '<tr>...</tr>', pagination: '<div>...</div>', total: 42 }
 */

import Alpine from 'alpinejs';

Alpine.data('dataTable', (config = {}) => ({
    url: config.url || window.location.pathname,
    search: config.search || '',
    sortBy: config.sortBy || 'created_at',
    sortOrder: config.sortOrder || 'desc',
    perPage: config.perPage || 15,
    loading: false,
    debounceTimer: null,
    abortController: null,
    rootEl: null,

    init() {
        // Cache root element reference for use inside async methods
        this.rootEl = this.$el;

        // Bind pagination links on initial load
        this.bindPaginationLinks();

        // Handle browser back/forward
        window.addEventListener('popstate', () => {
            this.readFromUrl();
            this.fetchData();
        });
    },

    /**
     * Debounced search — triggers 300ms after user stops typing.
     */
    onSearch() {
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => {
            this.currentPage = 1;
            this.fetchData();
        }, 300);
    },

    /**
     * Sort by column — toggles direction if same column.
     */
    sort(field) {
        if (this.sortBy === field) {
            this.sortOrder = this.sortOrder === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortBy = field;
            this.sortOrder = 'asc';
        }
        this.currentPage = 1;
        this.fetchData();
    },

    /**
     * Navigate to a specific page.
     */
    goToPage(page) {
        this.currentPage = page;
        this.fetchData();
    },

    /**
     * Change items per page — resets to page 1.
     */
    changePerPage() {
        this.currentPage = 1;
        this.fetchData();
    },

    /**
     * Fetch data from server via AJAX.
     */
    async fetchData() {
        // Abort any pending request
        if (this.abortController) {
            this.abortController.abort();
        }
        this.abortController = new AbortController();

        this.loading = true;

        const params = new URLSearchParams();
        if (this.search) params.set('search', this.search);
        if (this.sortBy) params.set('sort_by', this.sortBy);
        if (this.sortOrder) params.set('sort_order', this.sortOrder);
        if (this.perPage) params.set('per_page', this.perPage);
        if (this.currentPage && this.currentPage > 1) params.set('page', this.currentPage);

        const fetchUrl = `${this.url}?${params.toString()}`;

        // Update browser URL without reload
        const stateUrl = `${window.location.pathname}?${params.toString()}`;
        window.history.pushState({}, '', stateUrl);

        // Capture element references BEFORE the await (Alpine proxy-safe)
        const el = this.rootEl;

        try {
            const response = await window.fetch(fetchUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                signal: this.abortController.signal,
            });

            if (!response.ok) throw new Error('Network error');

            const contentType = response.headers.get('content-type') || '';

            // Guard against non-JSON responses (e.g. redirect to login page)
            if (!contentType.includes('application/json')) {
                window.location.href = fetchUrl;
                return;
            }

            const data = await response.json();

            // Update table body
            const tbody = el.querySelector('[data-datatable-body]');
            if (tbody) {
                tbody.innerHTML = data.html || '';
            }

            // Update pagination
            const paginationEl = el.querySelector('[data-datatable-pagination]');
            if (paginationEl) {
                paginationEl.innerHTML = data.pagination || '';
                this.bindPaginationLinks();
            }

            // Update total count display
            const totalEl = el.querySelector('[data-datatable-total]');
            if (totalEl && data.total !== undefined) {
                totalEl.textContent = data.total;
            }

            // Reinitialize any modals/dropdowns in new content
            this.reinitComponents();

        } catch (error) {
            if (error.name === 'AbortError') return;
            console.error('DataTable fetch error:', error);
        } finally {
            this.loading = false;
        }
    },

    /**
     * Bind click handlers to pagination links so they use AJAX.
     */
    bindPaginationLinks() {
        const el = this.rootEl || this.$el;
        const paginationEl = el.querySelector('[data-datatable-pagination]');
        if (!paginationEl) return;

        paginationEl.querySelectorAll('a.pagination-btn').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const url = new URL(link.href);
                this.currentPage = url.searchParams.get('page') || 1;
                this.fetchData();
            });
        });
    },

    /**
     * Read current state from URL query params (for popstate).
     */
    readFromUrl() {
        const params = new URLSearchParams(window.location.search);
        this.search = params.get('search') || '';
        this.sortBy = params.get('sort_by') || 'created_at';
        this.sortOrder = params.get('sort_order') || 'desc';
        this.perPage = parseInt(params.get('per_page')) || 15;
        this.currentPage = parseInt(params.get('page')) || 1;
    },

    /**
     * Reinitialize JS components (modals, dropdowns) after content swap.
     */
    reinitComponents() {
        document.dispatchEvent(new CustomEvent('datatable:updated', {
            detail: { el: this.rootEl }
        }));
    },

    /**
     * Check if a column is currently sorted.
     */
    isSorted(field) {
        return this.sortBy === field;
    },

    /**
     * Get sort icon class for a column header.
     */
    sortIconClass(field) {
        if (this.sortBy !== field) return '';
        return this.sortOrder === 'desc' ? 'sort-desc' : '';
    },

    /**
     * Export selected records via CSV download.
     * Reads selected IDs from the table-selection module.
     */
    exportSelected(exportUrl) {
        if (!exportUrl || typeof window.getSelectedIds !== 'function') return;

        // Find the checkbox group within this datatable
        const el = this.rootEl || this.$el;
        const firstItem = el.querySelector('[data-select-item]');
        if (!firstItem) {
            window.showToast('Info', 'No items available to select.', 'info');
            return;
        }

        const group = firstItem.dataset.selectItem;
        const ids = window.getSelectedIds(group);

        if (ids.length === 0) {
            window.showToast('Info', 'Please select at least one item to export.', 'info');
            return;
        }

        const params = new URLSearchParams();
        ids.forEach(id => params.append('ids[]', id));
        window.location.href = `${exportUrl}?${params.toString()}`;
    },
}));
