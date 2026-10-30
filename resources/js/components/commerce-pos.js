import Alpine from 'alpinejs';

Alpine.data('commercePos', () => {
    'use strict';

    return {
        config: {}, products: [], groups: [], mode: 'retail', category: '',
        selectedProduct: null, selectedColorId: '', selectedVariantId: '', selectedQuantity: 1, selectedWholesaleColorIds: [], wholesaleSizeQuantities: {}, selectedWholesaleColorId: '', packCounts: {}, productModalTrigger: null, wholesaleColorTrigger: null, detailImageFailed: false,
        search: '', quote: null, error: '', busy: false, searching: false, previewing: false,
        searchVersion: 0, revision: 0, previewTimer: null, fulfillment: 'pickup', customerType: 'walk_in', contactId: '', contactLabel: '',
        customerQuery: '', customerResults: [], customerSearching: false,
        customer: { name: '', phone: '', email: '' }, address: { name: '', phone: '', line1: '', line2: '', city: '', state: '', postal_code: '', country: '' },
        shippingMethod: '', handover: true, paymentType: 'full', payments: [], balances: [],
        discountType: 'amount', discountValue: '',
        held: [], heldOpen: false,
        submission: '',
        galleryImages: [], galleryTitle: '', galleryIndex: 0, galleryFailures: {}, galleryTrigger: null,
        init() {
            'use strict';
            this.config = JSON.parse(this.$el.dataset.config);
            this.submission = crypto.randomUUID();
            this.resetPayments();
            this.$watch('address', () => this.invalidate(), { deep: true });
            this.$watch('shippingMethod', () => this.invalidate());
            this.$watch('discountType', () => this.invalidate());
            this.$watch('discountValue', () => this.invalidate());
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
                this.invalidate();
            });
            this.$watch('paymentType', () => this.resetPayments());
            this.loadProducts();
            this.loadHeld();
            this.$nextTick(() => this.$refs.posSearch?.focus());
        },

        // Any cart change re-prices automatically after a short pause (no manual "Calculate").
        invalidate() {
            this.quote = null;
            this.revision++;
            clearTimeout(this.previewTimer);
            if (!this.groups.length) return;
            this.previewTimer = setTimeout(() => this.preview(), 400);
        },
        async request(url, payload = null, method = null) {
            const response = await fetch(url, {
                method: method || (payload ? 'POST' : 'GET'),
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.config.csrf },
                ...(payload ? { body: JSON.stringify(payload) } : {}),
            });
            let result;
            try { result = await response.json(); } catch { throw new Error('The session or server response is unavailable. Refresh and try again.'); }
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'Unable to complete the request.');
            return result;
        },
        notify(message, type = 'error') {
            if (!message) return;
            if (typeof window.showToast === 'function') window.showToast(type === 'error' ? 'POS' : 'Done', message, type);
            else this.error = message;
        },

        // ---- Catalog -------------------------------------------------------------------------
        async loadProducts() {
            const version = ++this.searchVersion;
            this.searching = true;
            try {
                const params = new URLSearchParams({ search: this.search, ...(this.category ? { category: this.category } : {}) });
                const result = await this.request(`${this.config.products}?${params}`);
                if (version === this.searchVersion) this.products = result.data;
            } catch (error) { this.notify(error.message); }
            finally { if (version === this.searchVersion) this.searching = false; }
        },
        setCategory(id) { this.category = id; this.loadProducts(); },
        // Barcode scanners type the code and press Enter: an exact SKU match is added straight to the cart.
        async scan() {
            const code = this.search.trim();
            if (!code) return;
            try {
                const result = await this.request(`${this.config.products}?code=${encodeURIComponent(code)}`);
                const product = result.data[0];
                const variant = product?.variants.find(item => item.id === result.matched_variant_id);
                if (product && variant) {
                    if (variant.available < 1) { this.notify(`${variant.name} is out of stock.`); return; }
                    if (!['retail', 'both'].includes(product.mode)) { this.notify('This product is only sold wholesale.'); return; }
                    this.addRetailLine(product, variant, 1);
                    this.notify(`${product.name} · ${variant.size} added`, 'success');
                    this.search = '';
                    this.loadProducts();
                    return;
                }
                this.loadProducts();
            } catch (error) { this.notify(error.message); }
        },
        canSell(product) {
            if (!product.available) return false;
            return this.mode === 'retail' ? ['retail', 'both'].includes(product.mode) : product.wholesale_enabled;
        },
        displayPrice(product) {
            if (this.mode === 'retail') return this.money(product.single_piece_price || product.variants[0]?.price);
            if (product.pack_pricing) return `${this.money(product.pack_prices[0].price)} / ${product.pack_prices[0].packs} packs`;
            return product.wholesale_price ? `${this.money(product.wholesale_price)} / pc` : 'Price on quote';
        },

        // ---- Product dialog ------------------------------------------------------------------
        openProduct(product, trigger) {
            if (!this.canSell(product)) {
                this.notify(this.mode === 'retail' ? 'This product is only available for wholesale.' : 'Wholesale is unavailable for this product.');
                return;
            }
            this.selectedProduct = product;
            this.selectedColorId = String(product.colors.find(color => color.available > 0)?.id || product.colors[0]?.id || '');
            this.selectedVariantId = String(product.variants.find(variant => variant.available > 0 && (!this.selectedColorId || String(variant.color_id) === this.selectedColorId))?.id || '');
            this.selectedQuantity = 1;
            this.selectedWholesaleColorIds = [];
            this.wholesaleSizeQuantities = {};
            this.selectedWholesaleColorId = '';
            this.packCounts = {};
            this.detailImageFailed = false;
            this.productModalTrigger = trigger;
            this.$refs.posProductModal.showModal();
        },
        closeProductModal() { this.productModalTrigger?.focus(); },
        selectColor(color) {
            this.selectedColorId = String(color.id);
            const variant = this.selectedProduct?.variants.find(item => item.available > 0 && String(item.color_id) === this.selectedColorId);
            this.selectedVariantId = String(variant?.id || '');
        },
        selectVariant(variant) { this.selectedVariantId = String(variant.id); },
        addRetailLine(product, variant, quantity) {
            const existing = this.groups.find(group => group.mode === 'retail' && String(group.variant_id) === String(variant.id));
            if (existing) existing.count = Math.min(Number(variant.available), Number(existing.count) + quantity);
            else this.groups.push({ key: crypto.randomUUID(), product, mode: 'retail', variant_id: variant.id, color_id: variant.color_id, count: quantity, discount: '' });
            this.invalidate();
        },
        addRetailSelection() {
            const product = this.selectedProduct;
            const variant = product?.variants.find(item => String(item.id) === String(this.selectedVariantId));
            if (!variant || variant.available < 1 || Number(this.selectedQuantity) < 1 || Number(this.selectedQuantity) > variant.available) return;
            this.addRetailLine(product, variant, Number(this.selectedQuantity));
            this.$refs.posProductModal.close();
        },
        stepQuantity(group, delta) {
            const variant = group.product.variants.find(item => String(item.id) === String(group.variant_id));
            const next = Number(group.count) + delta;
            if (next < 1) { this.remove(group.key); return; }
            group.count = Math.min(next, Number(variant?.available || next));
            this.invalidate();
        },
        variantLabel(group) {
            const variant = group.product.variants.find(item => String(item.id) === String(group.variant_id));
            const color = group.product.colors.find(item => item.id === variant?.color_id);
            return [color?.name, variant?.size].filter(Boolean).join(' · ');
        },

        // ---- Wholesale: whole packs (pack price list) ----------------------------------------
        packPiecesFor(colorId) {
            const product = this.selectedProduct;
            const ratio = product?.ratios?.[colorId] || product?.ratios?.[String(colorId)] || {};
            return Object.values(ratio).reduce((sum, qty) => sum + Number(qty || 0), 0) * Math.max(1, Number(product?.multiplier || 1));
        },
        totalPacks() { return Object.values(this.packCounts).reduce((sum, count) => sum + Number(count || 0), 0); },
        stepPack(colorId, delta) {
            const next = Math.max(0, Number(this.packCounts[colorId] || 0) + delta);
            this.packCounts = { ...this.packCounts, [colorId]: next };
        },
        packTotal(product, packs) {
            const row = [...(product?.pack_prices || [])].reverse().find(item => item.packs <= packs);
            return row ? (Number(row.price) / row.packs) * packs : 0;
        },
        addPackSelection() {
            const product = this.selectedProduct;
            const packs = this.totalPacks() + this.groups.filter(group => group.mode === 'wholesale' && group.packs && group.product.id === product.id).reduce((sum, group) => sum + group.packs, 0);
            if (packs < Number(product.min_packs || 1)) { this.notify(`${product.name} needs at least ${product.min_packs} packs.`); return; }
            for (const [colorId, count] of Object.entries(this.packCounts)) {
                if (Number(count) < 1) continue;
                const existing = this.groups.find(group => group.mode === 'wholesale' && group.packs && group.product.id === product.id && String(group.color_id) === String(colorId));
                if (existing) existing.packs += Number(count);
                else this.groups.push({ key: crypto.randomUUID(), product, mode: 'wholesale', color_id: Number(colorId), packs: Number(count), discount: '' });
            }
            this.invalidate();
            this.$refs.posProductModal.close();
        },
        stepGroupPacks(group, delta) {
            group.packs = Number(group.packs) + delta;
            if (group.packs < 1) { this.remove(group.key); return; }
            this.invalidate();
        },

        // ---- Wholesale: custom size quantities -------------------------------------------------
        openWholesaleColor(color, trigger) {
            if (!color || !this.selectedProduct) return;
            const colorId = String(color.id);
            this.selectedWholesaleColorId = colorId;
            this.wholesaleColorTrigger = trigger;
            if (!this.wholesaleSizeQuantities[colorId]) {
                const configured = this.selectedProduct.ratios?.[color.id] || this.selectedProduct.ratios?.[colorId] || {};
                const multiplier = Math.max(1, Number(this.selectedProduct.multiplier || 1));
                this.wholesaleSizeQuantities[colorId] = Object.fromEntries(this.selectedProduct.variants
                    .filter(variant => String(variant.color_id) === colorId)
                    .map(variant => [variant.size, Number(variant.available) > 0 ? Math.max(0, Number(configured[variant.size] || 0) * multiplier) : 0]));
            }
            this.$refs.posWholesaleColorModal.showModal();
        },
        closeWholesaleColor() { this.wholesaleColorTrigger?.focus(); },
        wholesaleColorName(colorId = this.selectedWholesaleColorId) {
            return this.selectedProduct?.colors.find(color => String(color.id) === String(colorId))?.name || '';
        },
        wholesaleColorSelectionError(colorId = this.selectedWholesaleColorId) {
            const product = this.selectedProduct;
            const quantities = this.wholesaleSizeQuantities[colorId] || {};
            const sizes = Object.entries(quantities).filter(([, quantity]) => Number(quantity) > 0);
            const minimumSizes = Math.max(1, Number(product?.minimum_sizes || 1));
            const colorMinimum = Math.max(1, Number(product?.color_minimum || 1));
            if (sizes.length < minimumSizes) return `${this.wholesaleColorName(colorId)} needs at least ${minimumSizes} sizes.`;
            for (const [size, quantity] of sizes) {
                if (!Number.isInteger(Number(quantity)) || Number(quantity) < colorMinimum) return `Each selected size needs at least ${colorMinimum} pieces.`;
                const variant = product.variants.find(item => String(item.color_id) === String(colorId) && item.size === size);
                if (!variant || Number(quantity) > Number(variant.available)) return `${size} has fewer pieces available than selected.`;
            }
            return '';
        },
        saveWholesaleColor() {
            const colorId = this.selectedWholesaleColorId;
            if (!colorId || this.wholesaleColorSelectionError(colorId)) return;
            if (!this.selectedWholesaleColorIds.includes(colorId)) this.selectedWholesaleColorIds = [...this.selectedWholesaleColorIds, colorId];
            this.$refs.posWholesaleColorModal.close();
        },
        removeWholesaleColor(colorId) { this.selectedWholesaleColorIds = this.selectedWholesaleColorIds.filter(id => id !== String(colorId)); },
        wholesaleSelectionError() {
            if (!this.selectedWholesaleColorIds.length) return 'Select at least one color.';
            const product = this.selectedProduct;
            let orderPieces = this.groups.filter(group => group.mode === 'wholesale' && group.ratio && group.product.id === product.id)
                .reduce((sum, group) => sum + Object.values(group.ratio).reduce((total, qty) => total + Number(qty), 0), 0);
            for (const colorId of this.selectedWholesaleColorIds) {
                const error = this.wholesaleColorSelectionError(colorId);
                if (error) return error;
                orderPieces += Object.values(this.wholesaleSizeQuantities[colorId] || {}).reduce((sum, quantity) => sum + Number(quantity || 0), 0);
            }
            if (!product.pack_pricing && orderPieces < Math.max(1, Number(product?.minimum || 1))) return `This product requires at least ${product.minimum} pieces.`;
            return '';
        },
        canAddWholesaleSelection() { return this.wholesaleSelectionError() === ''; },
        addWholesaleSelection() {
            const product = this.selectedProduct;
            const error = this.wholesaleSelectionError();
            if (error) { this.notify(error); return; }
            for (const colorId of this.selectedWholesaleColorIds) {
                const ratio = Object.fromEntries(Object.entries(this.wholesaleSizeQuantities[colorId] || {}).filter(([, quantity]) => Number(quantity) > 0).map(([size, quantity]) => [size, Number(quantity)]));
                this.groups.push({ key: crypto.randomUUID(), product, mode: 'wholesale', color_id: Number(colorId), ratio, discount: '' });
            }
            this.invalidate();
            this.$refs.posProductModal.close();
        },
        ratio(group) {
            const pieces = Object.values(group.ratio || {}).reduce((sum, qty) => sum + Number(qty), 0);
            return `${Object.entries(group.ratio || {}).map(([size, qty]) => `${size}: ${qty}`).join(' · ')} — ${pieces} pcs`;
        },
        colorName(group) { return group.product.colors.find(color => String(color.id) === String(group.color_id))?.name || ''; },

        // ---- Gallery -------------------------------------------------------------------------
        colorHex(color) { return /^#[\da-f]{3,8}$/i.test(color?.hex_code || '') ? color.hex_code : '#e5e7eb'; },
        currencySymbol(currency = this.config.currency) {
            return new Intl.NumberFormat(undefined, { style: 'currency', currency, currencyDisplay: 'narrowSymbol' })
                .formatToParts(0)
                .find(part => part.type === 'currency')?.value || currency;
        },
        colorGallery(product, color) {
            const selected = product.gallery.filter((image) => String(image.color_id) === String(color.id));
            if (selected.length) return selected;

            const fallback = [];
            if (color.swatch_image_url) fallback.push({ url: color.swatch_image_url, alt: `${product.name} · ${color.name}` });
            fallback.push(...product.gallery.filter((image) => image.color_id === null));
            if (product.image_url && !fallback.some((image) => image.url === product.image_url)) {
                fallback.push({ url: product.image_url, alt: product.name });
            }

            return fallback;
        },
        openColorGallery(product, color, trigger) {
            this.openGallery(this.colorGallery(product, color), `${product.name} · ${color.name}`, trigger);
        },
        openGallery(images, title, trigger) {
            this.galleryImages = images;
            this.galleryTitle = title;
            this.galleryIndex = 0;
            this.galleryTrigger = trigger;
            this.$refs.posGallery.showModal();
        },
        moveGallery(direction) {
            if (this.galleryImages.length < 2) return;
            this.galleryIndex = (this.galleryIndex + direction + this.galleryImages.length) % this.galleryImages.length;
        },
        restoreGalleryFocus() { this.galleryTrigger?.focus(); },

        // ---- Cart ----------------------------------------------------------------------------
        remove(key) {
            this.groups = this.groups.filter(group => group.key !== key);
            this.invalidate();
        },
        clearCart() {
            this.groups = [];
            this.discountValue = '';
            this.invalidate();
        },
        itemCount() {
            return this.groups.reduce((sum, group) => sum + (group.mode === 'retail' ? Number(group.count) : group.packs ? group.packs * this.packPieces(group) : Object.values(group.ratio || {}).reduce((total, qty) => total + Number(qty), 0)), 0);
        },
        packPieces(group) {
            const ratio = group.product.ratios?.[group.color_id] || group.product.ratios?.[String(group.color_id)] || {};
            return Object.values(ratio).reduce((sum, qty) => sum + Number(qty || 0), 0) * Math.max(1, Number(group.product.multiplier || 1));
        },
        lineTotal(index) {
            const group = this.quote?.groups?.[index];
            return group ? group.items.reduce((sum, item) => sum + Number(item.line_total), 0) : null;
        },

        // ---- Customer ------------------------------------------------------------------------
        async searchCustomers() {
            const query = this.customerQuery.trim();
            this.customerSearching = true;
            try {
                const result = await this.request(`${this.config.customers}?q=${encodeURIComponent(query)}`);
                if (query === this.customerQuery.trim()) this.customerResults = result.data;
            } catch (error) { this.notify(error.message); }
            finally { this.customerSearching = false; }
        },
        async pickCustomer(contact) {
            this.customerType = 'existing';
            this.contactId = String(contact.id);
            this.contactLabel = [contact.name, contact.phone].filter(Boolean).join(' · ');
            this.customerResults = [];
            this.customerQuery = '';
            this.address.name ||= contact.name || '';
            this.address.phone ||= contact.phone || '';
            this.balances = [];
            try {
                const result = await this.request(this.config.balance.replace('__CONTACT__', contact.id));
                if (this.contactId === String(contact.id)) this.balances = result.data;
            } catch { /* balance is informational */ }
        },
        clearCustomer() {
            this.customerType = 'walk_in';
            this.contactId = '';
            this.contactLabel = '';
        },

        // ---- Totals & payment ----------------------------------------------------------------
        payload() {
            const data = {
                submission_reference: this.submission, fulfillment_type: this.fulfillment, walk_in: this.customerType === 'walk_in',
                handover: this.fulfillment === 'pickup' && this.handover,
                groups: this.groups.map(group => ({
                    product_id: group.product.id,
                    mode: group.mode,
                    ...(group.mode === 'retail'
                        ? { variant_id: Number(group.variant_id), quantity: Number(group.count) }
                        : group.packs ? { color_id: Number(group.color_id), box_count: Number(group.packs) } : { color_id: Number(group.color_id), size_quantities: group.ratio }),
                    ...(Number(group.discount) > 0 ? { line_discount: Number(group.discount) } : {}),
                })),
            };
            if (Number(this.discountValue) > 0) data.discount = { type: this.discountType, value: Number(this.discountValue) };
            if (this.customerType === 'existing') data.contact_id = Number(this.contactId);
            if (this.customerType === 'new') data.customer = this.customer;
            if (this.fulfillment === 'delivery') {
                data.shipping_address = this.address;
                if (this.shippingMethod) data.shipping_method_id = Number(this.shippingMethod);
            }
            return data;
        },
        async preview() {
            if (!this.groups.length) return;
            this.previewing = true;
            const revision = this.revision;
            try {
                const result = await this.request(this.config.preview, this.payload());
                if (revision === this.revision) {
                    this.quote = result.data;
                    this.error = '';
                    if (this.quote.shipping_quote_required) this.paymentType = 'none';
                    this.syncFullPayment();
                }
            } catch (error) { if (revision === this.revision) this.error = error.message; }
            finally { if (revision === this.revision) this.previewing = false; }
        },
        precision() { return this.quote?.precision ?? this.config.precision; },
        round(value) { const factor = 10 ** this.precision(); return Math.round(Number(value || 0) * factor) / factor; },
        money(amount, currency = this.quote?.currency || this.config.currency) {
            const precision = currency === this.quote?.currency ? this.quote.precision : this.config.precision;
            return new Intl.NumberFormat(undefined, { style: 'currency', currency, currencyDisplay: 'narrowSymbol', minimumFractionDigits: precision, maximumFractionDigits: precision }).format(Number(amount || 0));
        },
        resetPayments() {
            this.payments = this.paymentType === 'none' ? [] : [{ key: crypto.randomUUID(), method: this.config.paymentMethods[0]?.id || 'cash', amount: '', reference: '', tendered: '' }];
            this.syncFullPayment();
        },
        syncFullPayment() {
            if (this.paymentType === 'full' && this.payments.length === 1 && this.quote?.total != null) this.payments[0].amount = this.round(this.quote.total);
        },
        addPaymentRow() {
            const method = this.config.paymentMethods.find(item => !this.payments.some(row => row.method === item.id))?.id || 'cash';
            this.payments.push({ key: crypto.randomUUID(), method, amount: this.round(this.remaining()), reference: '', tendered: '' });
        },
        removePaymentRow(key) { this.payments = this.payments.filter(row => row.key !== key); },
        paidTotal() { return this.round(this.payments.reduce((sum, row) => sum + Number(row.amount || 0), 0)); },
        remaining() { return Math.max(0, this.round(Number(this.quote?.total || 0) - this.paidTotal())); },
        cashRow() { return this.payments.find(row => row.method === 'cash'); },
        change() {
            const row = this.cashRow();
            return row && row.tendered !== '' ? Math.max(0, this.round(Number(row.tendered) - Number(row.amount || 0))) : 0;
        },
        quickCash() {
            const due = Number(this.cashRow()?.amount || this.quote?.total || 0);
            const notes = [due, Math.ceil(due / 10) * 10, Math.ceil(due / 50) * 50, Math.ceil(due / 100) * 100, Math.ceil(due / 500) * 500, Math.ceil(due / 1000) * 1000];
            return [...new Set(notes.filter(value => value >= due && value > 0).map(value => this.round(value)))].slice(0, 5);
        },
        setTendered(value) { const row = this.cashRow(); if (row) row.tendered = value; },
        hasStock() { return this.quote && this.quote.availability.every(stock => stock.required <= stock.available); },
        paymentError() {
            if (!this.quote || this.paymentType === 'none' || this.quote.shipping_quote_required) return '';
            const total = Number(this.quote.total || 0);
            if (this.payments.some(row => !(Number(row.amount) > 0))) return 'Enter an amount for each payment.';
            if (this.paidTotal() > this.round(total)) return 'Payments exceed the total.';
            if (this.paymentType === 'full' && this.paidTotal() < this.round(total)) return `${this.money(this.remaining())} still to collect.`;
            const cash = this.cashRow();
            if (cash && cash.tendered !== '' && Number(cash.tendered) < Number(cash.amount)) return 'Cash received is less than the cash amount.';
            return '';
        },
        canCheckout() { return !this.busy && this.groups.length && this.quote && this.hasStock() && !this.paymentError(); },
        async checkout() {
            if (!this.canCheckout()) { this.notify(this.paymentError() || this.error || 'Add products and wait for prices to update.'); return; }
            this.busy = true;
            try {
                const data = this.payload();
                if (this.paymentType !== 'none' && !this.quote.shipping_quote_required) {
                    data.payments = this.payments.map(row => ({
                        amount: String(this.round(row.amount)), currency: this.quote.currency, method: row.method, reference: row.reference || null,
                        submission_reference: crypto.randomUUID(),
                        ...(row.method === 'cash' && row.tendered !== '' ? { tendered_amount: String(this.round(row.tendered)) } : {}),
                    }));
                }
                const result = await this.request(this.config.checkout, data);
                window.open(`${result.data.receipt_url}?print=1`, '_blank', 'noopener');
                this.notify('Sale completed.', 'success');
                this.newSale();
            } catch (error) { this.notify(error.message); }
            finally { this.busy = false; }
        },
        newSale() {
            this.groups = [];
            this.quote = null;
            this.discountValue = '';
            this.clearCustomer();
            this.customer = { name: '', phone: '', email: '' };
            this.fulfillment = 'pickup';
            this.paymentType = 'full';
            this.submission = crypto.randomUUID();
            this.resetPayments();
            this.revision++;
            this.loadProducts();
            this.$nextTick(() => this.$refs.posSearch?.focus());
        },

        // ---- Hold / resume --------------------------------------------------------------------
        async loadHeld() {
            try { this.held = (await this.request(this.config.held)).data; } catch { this.held = []; }
        },
        async holdSale() {
            if (!this.groups.length) { this.notify('Add products before holding a sale.'); return; }
            const lines = this.groups.map(({ product, ...line }) => ({ ...line, product_id: product.id, product }));
            try {
                await this.request(this.config.held, { label: this.contactLabel || this.customer.name || '', payload: { lines, customerType: this.customerType, contactId: this.contactId, contactLabel: this.contactLabel, customer: this.customer, discountType: this.discountType, discountValue: this.discountValue } });
                this.notify('Sale held.', 'success');
                this.newSale();
                this.loadHeld();
            } catch (error) { this.notify(error.message); }
        },
        async resumeHeld(sale) {
            if (this.groups.length && !window.confirm('Replace the current cart with this held sale?')) return;
            const payload = sale.payload;
            this.groups = payload.lines.map(line => ({ ...line, key: crypto.randomUUID() }));
            this.customerType = payload.customerType || 'walk_in';
            this.contactId = payload.contactId || '';
            this.contactLabel = payload.contactLabel || '';
            this.customer = payload.customer || { name: '', phone: '', email: '' };
            this.discountType = payload.discountType || 'amount';
            this.discountValue = payload.discountValue || '';
            this.heldOpen = false;
            await this.request(this.config.heldDestroy.replace('__HELD__', sale.id), null, 'DELETE').catch(() => null);
            this.loadHeld();
            this.invalidate();
        },
        async deleteHeld(sale) {
            try {
                await this.request(this.config.heldDestroy.replace('__HELD__', sale.id), null, 'DELETE');
                this.loadHeld();
            } catch (error) { this.notify(error.message); }
        },

        // ---- Keyboard ------------------------------------------------------------------------
        shortcut(event) {
            const keys = { F2: () => this.$refs.posSearch?.focus(), F4: () => this.$refs.posCustomer?.focus(), F8: () => this.holdSale(), F9: () => this.checkout() };
            if (keys[event.key]) { event.preventDefault(); keys[event.key](); }
        },
    };
});
