import Alpine from 'alpinejs';
import { openDrawer } from './drawer.js';
import { showToast } from './toast.js';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const newKey = () => (window.crypto?.randomUUID ? window.crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-8xxx-xxxxxxxxxxxx'.replace(/x/g, () => Math.floor(Math.random() * 16).toString(16)));

// Inventory page: summary cards and the stock history drawer shared by every variant row.
Alpine.data('commerceInventory', (config) => ({
    summary: config.summary,
    history: { title: '', sku: '', loading: false, rows: [], error: '' },
    onUpdated(event) {
        'use strict';
        this.summary = event.detail.summary;
    },
    async openHistory(url, title, sku) {
        'use strict';
        this.history = { title, sku, loading: true, rows: [], error: '' };
        openDrawer('inventory-history-drawer');
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) {
                throw new Error(config.labels.historyError);
            }
            this.history.rows = (await response.json()).data ?? [];
        } catch (error) {
            this.history.error = error.message || config.labels.historyError;
        } finally {
            this.history.loading = false;
        }
    },
}));

// One variant row: type a new count, nudge it with +/-, or restock with a reason and note.
Alpine.data('inventoryRow', (row) => ({
    stock: row.stock,
    reserved: row.reserved,
    available: row.available,
    status: row.status,
    draft: row.stock,
    saving: false,
    restockOpen: false,
    restock: { quantity: '', reason: 'restock', note: '' },
    badgeLabel() {
        'use strict';
        return row.labels[this.status];
    },
    async save(mode, quantity, reason, note) {
        'use strict';
        reason = reason ?? 'correction';
        note = note ?? null;
        if (this.saving) {
            return false;
        }
        const previous = { stock: this.stock, available: this.available };
        this.saving = true;
        if (mode === 'add') {
            this.stock = Math.max(0, this.stock + quantity);
        } else {
            this.stock = quantity;
        }
        this.draft = this.stock;
        try {
            const response = await fetch(row.url, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ mode, quantity, reason, note, idempotency_key: newKey() }),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const message = payload.errors ? Object.values(payload.errors).flat().join(' ') : payload.message;
                throw new Error(message || row.labels.saveError);
            }
            this.stock = payload.stock_quantity;
            this.reserved = payload.reserved;
            this.available = payload.available;
            this.status = payload.status;
            this.draft = this.stock;
            this.$dispatch('inventory-updated', { summary: payload.summary });
            showToast(row.labels.saved, payload.message, 'success');

            return true;
        } catch (error) {
            this.stock = previous.stock;
            this.available = previous.available;
            this.draft = previous.stock;
            showToast(row.labels.saveError, error.message, 'error');

            return false;
        } finally {
            this.saving = false;
        }
    },
    step(delta) {
        'use strict';
        if (delta < 0 && this.stock <= 0) {
            return;
        }
        this.save('add', delta, 'other');
    },
    commitDraft() {
        'use strict';
        const value = Number.parseInt(this.draft, 10);
        if (Number.isNaN(value) || value < 0) {
            this.draft = this.stock;

            return;
        }
        if (value !== this.stock) {
            this.save('set', value);
        }
    },
    async submitRestock() {
        'use strict';
        const value = Number.parseInt(this.restock.quantity, 10);
        if (Number.isNaN(value) || value === 0) {
            return;
        }
        let signed = value;
        if (this.restock.reason === 'damaged') {
            signed = -Math.abs(value);
        } else if (['restock', 'returned'].includes(this.restock.reason)) {
            signed = Math.abs(value);
        }
        if (await this.save('add', signed, this.restock.reason, this.restock.note || null)) {
            this.restockOpen = false;
            this.restock = { quantity: '', reason: 'restock', note: '' };
        }
    },
}));
