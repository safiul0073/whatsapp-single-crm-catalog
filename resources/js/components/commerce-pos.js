import Alpine from 'alpinejs';

Alpine.data('commercePos', () => {
    'use strict';

    return {
        config: {}, products: [], groups: [], drafts: { retail: [], wholesale: [] }, mode: 'retail', wholesaleProductId: null,
        selectedProduct: null, selectedColorId: '', selectedVariantId: '', selectedQuantity: 1, selectedWholesaleColorIds: [], wholesaleSizeQuantities: {}, selectedWholesaleColorId: '', productModalTrigger: null, wholesaleColorTrigger: null, detailImageFailed: false,
        search: '', quote: null, error: '', busy: false, searching: false,
        searchVersion: 0, revision: 0, fulfillment: 'pickup', customerType: 'walk_in', contactId: '',
        customer: { name: '', phone: '', email: '' }, address: { name: '', phone: '', line1: '', line2: '', city: '', state: '', postal_code: '', country: '' },
        shippingMethod: '', handover: true, paymentType: 'full', amount: '', method: 'cash', reference: '', tendered: '', balances: [],
        submission: '', paymentSubmission: '',
        galleryImages: [], galleryTitle: '', galleryIndex: 0, galleryFailures: {}, galleryTrigger: null,
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
        switchMode(mode) {
            if (mode === this.mode) return;
            this.drafts[this.mode] = this.groups;
            this.mode = mode;
            this.groups = this.drafts[mode] || [];
            this.wholesaleProductId = mode === 'wholesale' ? this.groups[0]?.product?.id || null : null;
            this.invalidate();
            this.error = '';
        },
        openProduct(product, trigger) {
            if (this.mode === 'wholesale' && this.wholesaleProductId && this.wholesaleProductId !== product.id) {
                this.error = 'Clear the current wholesale color selections before choosing another product.';
                return;
            }
            if (this.mode === 'wholesale' && !product.wholesale_enabled) {
                this.error = 'Wholesale is unavailable for this product.';
                return;
            }
            if (this.mode === 'retail' && !['retail', 'both'].includes(product.mode)) {
                this.error = 'This product is only available for wholesale.';
                return;
            }
            this.selectedProduct = product;
            this.selectedColorId = String(product.colors.find(color => color.available > 0)?.id || product.colors[0]?.id || '');
            this.selectedVariantId = String(product.variants.find(variant => variant.available > 0 && (!this.selectedColorId || String(variant.color_id) === this.selectedColorId))?.id || '');
            this.selectedQuantity = 1;
            this.selectedWholesaleColorIds = [];
            this.wholesaleSizeQuantities = {};
            this.selectedWholesaleColorId = '';
            this.detailImageFailed = false;
            this.productModalTrigger = trigger;
            this.$refs.posProductModal.showModal();
            this.error = '';
        },
        closeProductModal() { this.restoreProductFocus(); },
        restoreProductFocus() { this.productModalTrigger?.focus(); },
        selectColor(color) {
            this.selectedColorId = String(color.id);
            const variant = this.selectedProduct?.variants.find(item => item.available > 0 && String(item.color_id) === this.selectedColorId);
            if (this.mode === 'retail') this.selectedVariantId = String(variant?.id || '');
            this.openColorGallery(this.selectedProduct, color, this.$refs.posProductModal.querySelector(`[data-color-id="${color.id}"]`));
        },
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
            this.invalidate();
            this.$refs.posWholesaleColorModal.close();
        },
        removeWholesaleColor(colorId) {
            this.selectedWholesaleColorIds = this.selectedWholesaleColorIds.filter(id => id !== String(colorId));
            this.invalidate();
        },
        selectVariant(variant) { this.selectedVariantId = String(variant.id); },
        addRetailSelection() {
            const product = this.selectedProduct;
            const variant = product?.variants.find(item => String(item.id) === String(this.selectedVariantId));
            if (!variant || variant.available < 1 || Number(this.selectedQuantity) < 1 || Number(this.selectedQuantity) > variant.available) return;
            this.groups.push({ key: crypto.randomUUID(), product, mode: 'retail', variant_id: variant.id, color_id: variant.color_id, count: Number(this.selectedQuantity) });
            this.drafts.retail = this.groups;
            this.invalidate();
            this.$refs.posProductModal.close();
        },
        addWholesaleSelection() {
            const product = this.selectedProduct;
            if (!product || !product.wholesale_enabled || !this.canAddWholesaleSelection()) {
                this.error = this.wholesaleSelectionError() || 'Select valid colors and sizes.';
                return;
            }
            if (this.wholesaleProductId && this.wholesaleProductId !== product.id) return;
            this.wholesaleProductId = product.id;
            for (const colorId of this.selectedWholesaleColorIds) {
                const ratio = Object.fromEntries(Object.entries(this.wholesaleSizeQuantities[colorId] || {}).filter(([, quantity]) => Number(quantity) > 0).map(([size, quantity]) => [size, Number(quantity)]));
                this.groups.push({ key: crypto.randomUUID(), product, mode: 'wholesale', color_id: Number(colorId), ratio, count: 1 });
            }
            this.drafts.wholesale = this.groups;
            this.invalidate();
            this.$refs.posProductModal.close();
        },
        wholesaleSelectionError() {
            if (!this.selectedWholesaleColorIds.length) return 'Select at least one color.';
            const product = this.selectedProduct;
            const minimumSizes = Math.max(1, Number(product?.minimum_sizes || 1));
            const colorMinimum = Math.max(1, Number(product?.color_minimum || 1));
            let orderPieces = 0;
            for (const colorId of this.selectedWholesaleColorIds) {
                const sizes = Object.entries(this.wholesaleSizeQuantities[colorId] || {}).filter(([, quantity]) => Number(quantity) > 0);
                if (sizes.length < minimumSizes) return `${this.wholesaleColorName(colorId) || 'Each color'} needs at least ${minimumSizes} sizes.`;
                for (const [size, quantity] of sizes) {
                    if (!Number.isInteger(Number(quantity)) || Number(quantity) < colorMinimum) return `Each selected size needs at least ${colorMinimum} pieces.`;
                    const variant = product.variants.find(item => String(item.color_id) === colorId && item.size === size);
                    if (!variant || Number(quantity) > Number(variant.available)) return `${size} has fewer pieces available than selected.`;
                    orderPieces += Number(quantity);
                }
            }
            if (orderPieces < Math.max(1, Number(product?.minimum || 1))) return `This order requires at least ${product.minimum} pieces.`;
            return '';
        },
        canAddWholesaleSelection() { return this.wholesaleSelectionError() === ''; },
        clearWholesaleDraft() {
            this.groups = [];
            this.drafts.wholesale = [];
            this.wholesaleProductId = null;
            this.invalidate();
        },
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
        openGallery(images, title, trigger) {
            this.galleryImages = images;
            this.galleryTitle = title;
            this.galleryIndex = 0;
            this.galleryTrigger = trigger;
            this.$refs.posGallery.showModal();
        },
        openColorGallery(product, color, trigger) {
            this.openGallery(this.colorGallery(product, color), `${product.name} · ${color.name}`, trigger);
        },
        moveGallery(direction) {
            if (this.galleryImages.length < 2) return;
            this.galleryIndex = (this.galleryIndex + direction + this.galleryImages.length) % this.galleryImages.length;
        },
        restoreGalleryFocus() { this.galleryTrigger?.focus(); },
        remove(key) {
            this.groups = this.groups.filter(group => group.key !== key);
            this.drafts[this.mode] = this.groups;
            if (this.mode === 'wholesale' && this.groups.length === 0) this.wholesaleProductId = null;
            this.invalidate();
        },
        ratio(group) {
            const pieces = Object.values(group.ratio || {}).reduce((sum, qty) => sum + Number(qty), 0);
            return `${Object.entries(group.ratio || {}).map(([size, qty]) => `${size}: ${qty}`).join(' · ')} — ${pieces} pieces selected`;
        },
        wholesaleSummary() {
            const totals = this.groups.reduce((summary, group) => {
                summary.pieces += Object.values(group.ratio || {}).reduce((sum, quantity) => sum + Number(quantity), 0);
                return summary;
            }, { pieces: 0 });

            return `${totals.pieces} pieces across ${this.groups.length} colors`;
        },
        payload() {
            const data = {
                submission_reference: this.submission, fulfillment_type: this.fulfillment, walk_in: this.customerType === 'walk_in',
                handover: this.fulfillment === 'pickup' && this.handover,
                groups: this.groups.map(group => ({ product_id: group.product.id, mode: group.mode, ...(group.mode === 'retail' ? { variant_id: Number(group.variant_id), quantity: Number(group.count) } : { color_id: Number(group.color_id), size_quantities: group.ratio }) })),
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
        money(amount, currency = this.quote?.currency || this.config.currency) {
            const precision = currency === this.quote?.currency ? this.quote.precision : this.config.precision;
            return new Intl.NumberFormat(undefined, { style: 'currency', currency, currencyDisplay: 'narrowSymbol', minimumFractionDigits: precision, maximumFractionDigits: precision }).format(Number(amount || 0));
        },
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
