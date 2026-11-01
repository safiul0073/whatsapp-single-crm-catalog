'use strict';

// Copies the private invoice link of an order to the clipboard.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-copy-invoice-link]').forEach((button) => {
        const label = button.querySelector('span');
        const original = label ? label.textContent : '';

        button.addEventListener('click', async () => {
            const link = button.dataset.copyInvoiceLink;
            try {
                await navigator.clipboard.writeText(link);
            } catch {
                const field = document.createElement('input');
                field.value = link;
                document.body.appendChild(field);
                field.select();
                document.execCommand('copy');
                field.remove();
            }
            if (label) {
                label.textContent = button.dataset.copiedLabel || 'Copied';
                setTimeout(() => {
                    label.textContent = original;
                }, 2000);
            }
        });
    });
});
