/**
 * Tom Select Initialization
 *
 * Replaces jQuery Select2 with vanilla JS Tom Select.
 * Supports both legacy class names (.select2-basic, .select2-multi)
 * and new class names (.ts-basic, .ts-multi) for backward compatibility.
 */

import TomSelect from 'tom-select';

function tomSelectOptions(el, extra = {}) {
    const dropdownParent = el.dataset.dropdownParent;

    return {
        allowEmptyOption: true,
        controlClass: 'ts-control',
        dropdownClass: 'ts-dropdown',
        placeholder: el.dataset.placeholder || el.getAttribute('placeholder') || undefined,
        ...(dropdownParent ? { dropdownParent } : {}),
        ...extra,
    };
}

function initTomSelect() {
    // Basic searchable select
    document.querySelectorAll('.select2-basic, .ts-basic').forEach(el => {
        if (el.tomselect) {
            return;
        }

        new TomSelect(el, tomSelectOptions(el));
    });

    // Multi-select with tags
    document.querySelectorAll('.select2-multi, .ts-multi').forEach(el => {
        if (el.tomselect) {
            return;
        }

        new TomSelect(el, tomSelectOptions(el, {
            plugins: ['remove_button'],
        }));
    });
}

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    initTomSelect();
});

// Re-initialize on RTL toggle
document.addEventListener('rtl-toggled', () => {
    'use strict';

    document.querySelectorAll('.select2-basic, .ts-basic, .select2-multi, .ts-multi').forEach(el => {
        if (el.tomselect) {
            el.tomselect.destroy();
        }
    });
    initTomSelect();
});

// Re-initialize after datatable content swap
document.addEventListener('datatable:updated', () => {
    'use strict';

    initTomSelect();
});

export { initTomSelect };
