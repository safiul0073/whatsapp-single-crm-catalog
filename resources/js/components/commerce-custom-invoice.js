// Line editor for owner-made custom invoices: catalog products (price prefilled, editable) or free-text lines.
// Totals shown here are an estimate; the server recalculates them with OrderMoney on save.
const parse = (value, fallback) => {
    try {
        return JSON.parse(value || '') ?? fallback;
    } catch {
        return fallback;
    }
};

// Amounts are handled in 1/10000 units so the estimate avoids floating-point drift.
const toUnits = (value) => {
    const number = Number(value);
    return Number.isFinite(number) && number > 0 ? Math.round(number * 10000) : 0;
};
const fromUnits = (units) => (units / 10000).toFixed(2);

let nextKey = 1;

window.customInvoiceBuilder = function customInvoiceBuilder() {
    'use strict';

    return {
        products: [],
        lines: [],
        currency: '',
        customer: null,
        customerUrl: '',
        customerQuery: '',
        customerResults: [],
        discount: '',
        tax: '',
        shipping: '',

        init() {
            const data = this.$el.dataset;
            this.products = parse(data.products, []);
            this.currency = data.currency || '';
            this.customer = parse(data.customer, null);
            this.customerUrl = data.customerUrl || '';
            const amounts = parse(data.amounts, {});
            this.discount = amounts.discount ?? '';
            this.tax = amounts.tax ?? '';
            this.shipping = amounts.shipping ?? '';
            this.lines = parse(data.lines, []).map((line) => this.makeLine(line));
            if (!this.lines.length) {
                this.addLine(true);
            }
        },

        makeLine(line = {}) {
            return {
                key: nextKey++,
                product_id: line.product_id ? String(line.product_id) : '',
                variant_id: line.variant_id ? String(line.variant_id) : '',
                name: line.name ?? '',
                description: line.description ?? '',
                quantity: Number(line.quantity) || 1,
                unit_price: line.unit_price ?? '',
            };
        },

        addLine() {
            this.lines.push(this.makeLine());
        },

        removeLine(line) {
            this.lines = this.lines.filter((item) => item.key !== line.key);
        },

        productOf(line) {
            return this.products.find((product) => Number(product.id) === Number(line.product_id)) || null;
        },

        variantsOf(line) {
            return this.productOf(line)?.variants || [];
        },

        pickProduct(line) {
            const product = this.productOf(line);
            line.variant_id = '';
            if (!product) {
                return;
            }
            line.name = product.name;
            line.unit_price = product.price ?? '';
        },

        pickVariant(line) {
            const product = this.productOf(line);
            const variant = this.variantsOf(line).find((item) => Number(item.id) === Number(line.variant_id));
            if (!product) {
                return;
            }
            line.name = variant ? `${product.name} (${variant.label})` : product.name;
            line.unit_price = (variant ? variant.price : product.price) ?? '';
        },

        lineUnits(line) {
            return toUnits(line.unit_price) * Math.max(1, Number(line.quantity) || 1);
        },

        lineTotal(line) {
            return fromUnits(this.lineUnits(line));
        },

        grandTotal() {
            const subtotal = this.lines.reduce((sum, line) => sum + this.lineUnits(line), 0);
            return fromUnits(Math.max(0, subtotal + toUnits(this.tax) + toUnits(this.shipping) - toUnits(this.discount)));
        },

        async searchCustomers() {
            const query = this.customerQuery.trim();
            if (query.length < 2) {
                this.customerResults = [];
                return;
            }
            try {
                const response = await fetch(`${this.customerUrl}?q=${encodeURIComponent(query)}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                this.customerResults = response.ok ? (await response.json()).data || [] : [];
            } catch {
                this.customerResults = [];
            }
        },

        pickCustomer(result) {
            this.customer = result;
            this.customerResults = [];
            this.customerQuery = '';
            const fill = (ref, value) => {
                if (ref && !ref.value && value) {
                    ref.value = value;
                }
            };
            fill(this.$refs.billName, result.name);
            fill(this.$refs.billPhone, result.phone);
            fill(this.$refs.billEmail, result.email);
        },

        clearCustomer() {
            this.customer = null;
        },
    };
};
