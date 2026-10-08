import Alpine from 'alpinejs';

Alpine.data('commercePos', () => {
    'use strict';

    return {
        config: {}, products: [], groups: [], search: '', quote: null, error: '', busy: false, searching: false,
        searchVersion: 0, revision: 0, fulfillment: 'pickup', customerType: 'walk_in', contactId: '',
        customer: { name: '', phone: '', email: '' }, address: { name: '', phone: '', line1: '', line2: '', city: '', state: '', postal_code: '', country: '' },
        shippingMethod: '', handover: true, paymentType: 'full', amount: '', method: 'cash', reference: '', tendered: '', balances: [],
        submission: '', paymentSubmission: '',
        init() {
            'use strict';
            this.config = JSON.parse(this.$el.dataset.config);
            this.submission = crypto.randomUUID();
            this.paymentSubmission = crypto.randomUUID();
            this.$watch('groups', () => this.invalidate());
            this.$watch('address', () => this.invalidate());
            this.$watch('shippingMethod', () => this.invalidate());
            this.$watch('fulfillment', () => {
                this.handover = this.fulfillment === 'pickup';
                this.shippingMethod = '';
                if (this.fulfillment === 'delivery' && this.customerType === 'walk_in') this.customerType = 'new';
                this.invalidate();
            });
            this.$watch('customerType', () => {
                if (this.customerType === 'walk_in') {
                    this.paymentType = 'full';
                    this.handover = true;
                }
                this.balances = [];
            });
            this.loadProducts();
        },
        invalidate() { this.quote = null; this.revision++; },
        async request(url, payload = null) {
            const response = await fetch(url, {
                method: payload ? 'POST' : 'GET',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.config.csrf },
                ...(payload ? { body: JSON.stringify(payload) } : {}),
            });
            let result;
            try { result = await response.json(); } catch { throw new Error('The session or server response is unavailable. Refresh and try again.'); }
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'Unable to complete the request.');
            return result.data;
        },
        async loadProducts() {
            const version = ++this.searchVersion;
            this.searching = true;
            try {
                const products = await this.request(`${this.config.products}?search=${encodeURIComponent(this.search)}`);
                if (version === this.searchVersion) this.products = products;
            } catch (error) { this.error = error.message; }
            finally { if (version === this.searchVersion) this.searching = false; }
        },
        add(product) {
            this.groups.push({ key: crypto.randomUUID(), product, mode: product.mode === 'wholesale' ? 'wholesale' : 'retail', variant_id: product.variants[0]?.id || '', color_id: product.colors[0]?.id || '', count: 1 });
            this.error = '';
        },
        remove(key) { this.groups = this.groups.filter(group => group.key !== key); },
        ratio(group) {
            const ratio = group.product.ratios?.[group.color_id] || {};
            const multiplier = group.product.multiplier;
            const pieces = Object.values(ratio).reduce((sum, qty) => sum + Number(qty) * multiplier, 0);
            return `${Object.entries(ratio).map(([size, qty]) => `${size}: ${qty * multiplier}`).join(' · ')} — ${pieces} pieces per pack · ${pieces * group.count} pieces selected · Minimum ${group.product.minimum || 1} pieces`;
        },
        payload() {
            const data = {
                submission_reference: this.submission, fulfillment_type: this.fulfillment, walk_in: this.customerType === 'walk_in',
                handover: this.fulfillment === 'pickup' && this.handover,
                groups: this.groups.map(group => ({ product_id: group.product.id, mode: group.mode, ...(group.mode === 'retail' ? { variant_id: Number(group.variant_id), quantity: Number(group.count) } : { color_id: Number(group.color_id), box_count: Number(group.count) }) })),
            };
            if (this.customerType === 'existing') data.contact_id = Number(this.contactId);
            if (this.customerType === 'new') data.customer = this.customer;
            if (this.fulfillment === 'delivery') {
                data.shipping_address = this.address;
                if (this.shippingMethod) data.shipping_method_id = Number(this.shippingMethod);
            }
            return data;
        },
        paymentAmount() { return this.paymentType === 'none' ? '0' : this.paymentType === 'full' ? this.quote?.total || '0' : this.amount || '0'; },
        money(amount) { return `${this.quote?.currency || this.config.currency} ${Number(amount || 0).toFixed(this.quote?.precision ?? this.config.precision)}`; },
        change() { return this.method === 'cash' && this.tendered !== '' ? this.money(Math.max(0, Number(this.tendered) - Number(this.paymentAmount()))) : this.money(0); },
        hasStock() { return this.quote && this.quote.availability.every(stock => stock.required <= stock.available); },
        async preview() {
            this.busy = true;
            this.error = '';
            const revision = this.revision;
            try {
                const quote = await this.request(this.config.preview, this.payload());
                if (revision === this.revision) {
                    this.quote = quote;
                    if (quote.shipping_quote_required) this.paymentType = 'none';
                }
            } catch (error) { this.error = error.message; }
            finally { this.busy = false; }
        },
        async checkout() {
            if (!this.hasStock() || this.busy) return;
            this.busy = true;
            this.error = '';
            try {
                const data = this.payload();
                if (this.paymentType !== 'none') {
                    data.payment = { amount: this.paymentAmount(), currency: this.quote.currency, method: this.method, reference: this.reference || null, submission_reference: this.paymentSubmission };
                    if (this.method === 'cash' && this.tendered !== '') data.payment.tendered_amount = this.tendered;
                }
                const result = await this.request(this.config.checkout, data);
                window.location.assign(result.url);
            } catch (error) { this.error = error.message; this.busy = false; }
        },
        async selectContact() {
            this.balances = [];
            const selectedId = this.contactId;
            const contact = this.config.contacts.find(contact => String(contact.id) === String(selectedId));
            if (contact) {
                this.address.name ||= contact.name || '';
                this.address.phone ||= contact.phone || '';
                try {
                    const balances = await this.request(this.config.balance.replace('__CONTACT__', selectedId));
                    if (this.contactId === selectedId) this.balances = balances;
                } catch (error) { this.error = error.message; }
            }
        },
    };
});
