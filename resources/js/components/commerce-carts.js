import Alpine from 'alpinejs';

// Owner cart page: search retail products and pick a size to add to a shopper's cart.
Alpine.data('cartProductPicker', (productsUrl) => ({
    search: '',
    products: [],
    selected: null,
    variantId: '',
    loading: false,
    loaded: false,
    controller: null,
    async load() {
        'use strict';
        this.controller?.abort();
        this.controller = new AbortController();
        this.loading = true;
        try {
            const url = new URL(productsUrl, window.location.origin);
            url.searchParams.set('search', this.search.trim());
            const response = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: this.controller.signal });
            const payload = await response.json();
            this.products = payload.data ?? [];
            this.loaded = true;
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.products = [];
            }
        } finally {
            this.loading = false;
        }
    },
    choose(product) {
        'use strict';
        this.selected = product;
        const first = product.variants.find((variant) => variant.available > 0) ?? product.variants[0];
        this.variantId = first ? String(first.id) : '';
    },
    init() {
        'use strict';
        this.load();
    },
}));
