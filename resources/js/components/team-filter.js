/**
 * Team Page Filter Component
 * Client-side filtering with URL hash persistence for role tabs and search.
 */
document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('[data-filter-root]');
    if (!root) return;

    const rangeGroup = root.querySelector('[data-range-group]');
    const rangeButtons = root.querySelectorAll('[data-range]');
    const searchInput = root.querySelector('[data-filter-search]');
    const listEl = root.querySelector('[data-filter-list]');
    const emptyEl = root.querySelector('[data-filter-empty]');
    const items = root.querySelectorAll('[data-filter-item]');

    if (!rangeGroup || !rangeButtons.length) return;

    function getActiveRange() {
        const activeBtn = rangeGroup.querySelector('[data-range].is-active');
        return activeBtn ? activeBtn.dataset.range : 'all';
    }

    function setActiveRange(range) {
        rangeButtons.forEach(function (btn) {
            btn.classList.toggle('is-active', btn.dataset.range === range);
        });

        if (rangeGroup.dataset.rangeValue !== range) {
            rangeGroup.dataset.rangeValue = range;
        }
    }

    function getSearchTerm() {
        if (!searchInput) return '';
        return searchInput.value.toLowerCase().trim();
    }

    function applyFilter() {
        const range = getActiveRange();
        const term = getSearchTerm();
        let visibleCount = 0;

        items.forEach(function (item) {
            const status = item.dataset.status || '';
            const name = item.dataset.name || '';
            const matchesRange = range === 'all' || status === range;
            const matchesSearch = !term || name.indexOf(term) !== -1;
            const visible = matchesRange && matchesSearch;

            item.style.display = visible ? '' : 'none';

            if (visible) {
                visibleCount++;
            }
        });

        if (listEl) {
            listEl.style.display = visibleCount > 0 ? '' : 'none';
        }

        if (emptyEl) {
            emptyEl.classList.toggle('hidden', visibleCount > 0);
        }
    }

    function updateHash(range) {
        if (window.history && window.history.replaceState) {
            var url = new URL(window.location);
            if (range === 'all') {
                url.hash = '';
            } else {
                url.hash = range;
            }
            window.history.replaceState(null, '', url.toString());
        }
    }

    rangeButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var range = this.dataset.range;
            setActiveRange(range);
            updateHash(range);
            applyFilter();
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            applyFilter();
        });
    }

    var hash = window.location.hash.replace('#', '');
    if (hash && document.querySelector('[data-range="' + hash + '"]')) {
        setActiveRange(hash);
    }

    applyFilter();
});
