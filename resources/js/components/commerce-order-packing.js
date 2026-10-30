'use strict';

// Packing checklist on the order page: ticking a pack saves immediately and updates the progress bar.
document.addEventListener('DOMContentLoaded', () => {
    const progress = document.querySelector('[data-packing-progress]');
    if (!progress) {
        return;
    }
    const fill = progress.querySelector('[data-packing-bar-fill]');
    const count = progress.querySelector('[data-packing-count]');
    const bar = progress.querySelector('[data-packing-bar]');
    const total = Number(progress.dataset.total || 0);

    const render = (packed) => {
        fill.style.width = `${total ? Math.round((packed / total) * 100) : 0}%`;
        count.textContent = String(packed);
        bar.setAttribute('aria-valuenow', String(packed));
    };
    fill.style.width = `${fill.dataset.width}%`;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const send = async (form, packed) => {
        const body = new FormData(form);
        if (packed !== undefined) {
            body.set('packed', packed ? '1' : '0');
        }
        const response = await fetch(form.action, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body,
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(data.message || Object.values(data.errors || {}).flat().join(' ') || 'Packing could not be updated.');
        }

        return data;
    };

    document.querySelectorAll('[data-packing-toggle]').forEach((box) => {
        box.addEventListener('change', async () => {
            const form = box.closest('form');
            const stamp = form.querySelector('[data-packed-at]');
            box.disabled = true;
            try {
                const data = await send(form, box.checked);
                render(data.packed);
                if (stamp) {
                    stamp.textContent = box.checked ? (data.packed_at || '') : '';
                }
                // The order moves to/from "packed" automatically, so refresh the status stepper.
                if (data.status !== progress.dataset.status) {
                    window.location.reload();
                }
            } catch (error) {
                box.checked = !box.checked;
                window.showToast?.('Packing', error.message, 'error');
            } finally {
                box.disabled = false;
            }
        });
    });

    progress.querySelector('[data-packing-all]')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        try {
            await send(event.currentTarget);
            window.location.reload();
        } catch (error) {
            window.showToast?.('Packing', error.message, 'error');
        }
    });
});
