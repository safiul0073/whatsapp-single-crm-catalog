import Alpine from 'alpinejs';

Alpine.data('commercePos', () => {
    'use strict';

    return {
        config: {}, products: [], groups: [], mode: 'retail', category: '',
        selectedProduct: null, selectedColorId: '', selectedVariantId: '', selectedQuantity: 1, selectedWholesaleColorIds: [], wholesaleSizeQuantities: {}, wholesalePackCounts: {}, selectedWholesaleColorId: '', packCounts: {}, productModalTrigger: null, wholesaleColorTrigger: null, detailImageFailed: false,
        search: '', quote: null, error: '', busy: false, searching: false, previewing: false,
        searchVersion: 0, revision: 0, previewTimer: null, fulfillment: 'pickup', customerType: 'walk_in', contactId: '', contactLabel: '',
        customerQuery: '', customerResults: [], customerSearching: false, customerView: 'search', addressView: 'create',
        customerForm: { name: '', phone: '', email: '' }, address: { name: '', phone: '', line1: '', line2: '', city: '', state: '', postal_code: '', country: 'US' },
        addressForm: { name: '', phone: '', line1: '', line2: '', city: '', state: '', postal_code: '', country: 'US' }, savedAddresses: [], addressId: '',
        suggestions: [], suggestIndex: -1, suggestVersion: 0, suggestTimer: null, postalStatus: '', postalVersion: 0, countryTouched: false, formError: '', savingCustomer: false, savingAddress: false,
        shippingMethod: '', handover: true, paymentType: 'full', payments: [], balances: [],
        discountType: 'amount', discountValue: '',
        held: [], heldOpen: false, expandedPackDetails: {},
        submission: '',
        galleryImages: [], galleryTitle: '', galleryIndex: 0, galleryFailures: {}, galleryTrigger: null,
        init() {
            'use strict';
            this.config = JSON.parse(this.$el.dataset.config);
            this.mode = this.config.mode || 'retail';
            this.submission = crypto.randomUUID();
            this.resetPayments();
            this.$watch('address', () => this.invalidate(), { deep: true });
            this.$watch('shippingMethod', () => this.invalidate());
            this.$watch('addressForm', () => { this.formError = ''; }, { deep: true });
            this.$watch('customerForm', () => { this.formError = ''; }, { deep: true });
            this.$watch('discountType', () => this.invalidate());
            this.$watch('discountValue', () => this.invalidate());
            this.$watch('fulfillment', () => {
                this.handover = this.fulfillment === 'pickup';
                this.shippingMethod = '';
                if (this.fulfillment === 'delivery') {
                    if (this.customerType === 'walk_in') this.openCustomerModal();
                    else if (!this.address.line1) this.openAddressModal();
                }
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
            this.selectedWholesaleColorId = '';
            this.wholesaleSizeQuantities = {};
            this.wholesalePackCounts = {};
            this.selectedProduct = product;
            this.selectedColorId = String(product.colors.find(color => color.available > 0)?.id || product.colors[0]?.id || '');
            this.selectedVariantId = String(product.variants.find(variant => variant.available > 0 && (!this.selectedColorId || String(variant.color_id) === this.selectedColorId))?.id || '');
            this.selectedQuantity = 1;
            this.selectedWholesaleColorIds = [];
            this.packCounts = {};
            this.detailImageFailed = false;
            this.productModalTrigger = trigger;
            this.$refs.posProductModal.showModal();
        },
        closeProductModal() { this.selectedWholesaleColorId = ''; this.productModalTrigger?.focus(); },
        selectColor(color) {
            this.selectedColorId = String(color.id);
            this.detailImageFailed = false;
            const variant = this.selectedProduct?.variants.find(item => item.available > 0 && String(item.color_id) === this.selectedColorId);
            this.selectedVariantId = String(variant?.id || '');
        },
        activePreviewColor() {
            if (!this.selectedProduct?.colors?.length) return null;
            const id = this.mode === 'retail'
                ? this.selectedColorId
                : (this.selectedWholesaleColorId || this.selectedWholesaleColorIds[0] || this.selectedColorId);
            return this.selectedProduct.colors.find(color => String(color.id) === String(id)) || null;
        },
        activePreviewImages() {
            if (!this.selectedProduct) return [];
            const color = this.activePreviewColor();
            if (color) {
                const scoped = this.colorGallery(this.selectedProduct, color);
                if (scoped.length) return scoped;
            }
            return this.selectedProduct.gallery || [];
        },
        activePreviewImage() {
            return this.activePreviewImages()[0]?.url || this.selectedProduct?.image_url || null;
        },
        activePreviewTitle() {
            const color = this.activePreviewColor();
            return color ? `${this.selectedProduct?.name || ''} · ${color.name}` : (this.selectedProduct?.name || '');
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
        packRatioEntries(colorId) {
            const product = this.selectedProduct;
            const ratio = product?.ratios?.[colorId] || product?.ratios?.[String(colorId)] || {};
            const multiplier = Math.max(1, Number(product?.multiplier || 1));
            return Object.entries(ratio)
                .map(([size, qty]) => [size, Number(qty || 0) * multiplier])
                .filter(([, qty]) => qty > 0);
        },
        packRatioText(colorId) {
            return this.packRatioEntries(colorId).map(([size, qty]) => `${size}: ${qty}`).join(' · ');
        },
        packPiecesFor(colorId) {
            return this.packRatioEntries(colorId).reduce((sum, [, qty]) => sum + qty, 0);
        },
        maxPacksFor(colorId) {
            const product = this.selectedProduct;
            const entries = this.packRatioEntries(colorId);
            if (!entries.length) {
                const color = product?.colors?.find(item => String(item.id) === String(colorId));
                return Math.max(0, Number(color?.available || 0));
            }
            return entries.reduce((minPacks, [size, perPack]) => {
                const variant = product.variants.find(item => String(item.color_id) === String(colorId) && item.size === size);
                const available = Math.floor(Number(variant?.available || 0) / perPack);
                return Math.min(minPacks, available);
            }, 500);
        },
        totalPacks() { return Object.values(this.packCounts).reduce((sum, count) => sum + Number(count || 0), 0); },
        totalPackPieces() {
            return Object.entries(this.packCounts).reduce((sum, [colorId, count]) => sum + Number(count || 0) * this.packPiecesFor(colorId), 0);
        },
        activePackTier(packs = this.totalPacks()) {
            return [...(this.selectedProduct?.pack_prices || [])].reverse().find(item => item.packs <= packs) || null;
        },
        togglePackColor(color) {
            if (!color || Number(color.available) < 1) return;
            const colorId = String(color.id);
            const current = Number(this.packCounts[colorId] || 0);
            this.detailImageFailed = false;
            if (current < 1) {
                const max = this.maxPacksFor(colorId);
                if (max < 1) { this.notify(`${color.name} has insufficient stock for a full pack.`); return; }
                this.packCounts = { ...this.packCounts, [colorId]: 1 };
                this.selectedWholesaleColorId = colorId;
                return;
            }
            if (this.selectedWholesaleColorId !== colorId) {
                this.selectedWholesaleColorId = colorId;
                return;
            }
            this.stepPack(colorId, -current);
        },
        stepPack(colorId, delta) {
            const max = Math.max(0, this.maxPacksFor(colorId));
            const next = Math.min(max, Math.max(0, Number(this.packCounts[colorId] || 0) + delta));
            const updated = { ...this.packCounts, [colorId]: next };
            if (next < 1) delete updated[colorId];
            this.packCounts = updated;
            if (next > 0) {
                this.selectedWholesaleColorId = String(colorId);
                this.detailImageFailed = false;
            } else if (this.selectedWholesaleColorId === String(colorId)) {
                const fallbackId = Object.keys(updated).find(id => Number(updated[id]) > 0) || '';
                this.selectedWholesaleColorId = fallbackId;
            }
        },
        setPackCount(colorId, value) {
            const parsed = Math.max(0, Math.floor(Number(value || 0)));
            const current = Number(this.packCounts[colorId] || 0);
            this.stepPack(colorId, parsed - current);
        },
        packTotal(product, packs) {
            const row = [...(product?.pack_prices || [])].reverse().find(item => item.packs <= packs);
            return row ? (Number(row.price) / row.packs) * packs : 0;
        },
        packCartCountForProduct(product = this.selectedProduct) {
            if (!product) return 0;
            return this.groups
                .filter(group => group.mode === 'wholesale' && group.packs && group.product.id === product.id)
                .reduce((sum, group) => sum + Number(group.packs || 0), 0);
        },
        packSelectionError() {
            const product = this.selectedProduct;
            if (!product) return '';
            if (!this.totalPacks()) return 'Select at least one color pack.';
            const combined = this.totalPacks() + this.packCartCountForProduct(product);
            const minPacks = Math.max(1, Number(product.min_packs || 1));
            if (combined < minPacks) return `Add ${minPacks - combined} more pack${minPacks - combined === 1 ? '' : 's'} to reach the ${minPacks}-pack minimum.`;
            return '';
        },
        canAddPackSelection() { return this.totalPacks() > 0 && this.packSelectionError() === ''; },
        addPackSelection() {
            const product = this.selectedProduct;
            const error = this.packSelectionError();
            if (error) { this.notify(error); return; }
            for (const [colorId, count] of Object.entries(this.packCounts)) {
                if (Number(count) < 1) continue;
                const existing = this.groups.find(group => group.mode === 'wholesale' && group.packs && group.product.id === product.id && String(group.color_id) === String(colorId));
                if (existing) existing.packs += Number(count);
                else this.groups.push({ key: crypto.randomUUID(), product, mode: 'wholesale', color_id: Number(colorId), packs: Number(count), discount: '' });
            }
            this.invalidate();
            this.$refs.posProductModal.close();
        },

        // ---- Wholesale: custom size quantities & inline pack builder -------------------------
        baseWholesalePack(colorId) {
            const product = this.selectedProduct;
            const id = String(colorId);
            const variants = (product?.variants || []).filter(variant => String(variant.color_id) === id);
            const configured = product?.ratios?.[colorId] || product?.ratios?.[id] || {};
            const multiplier = Math.max(1, Number(product?.multiplier || 1));
            const hasConfigured = Object.values(configured).some(qty => Number(qty || 0) > 0);
            if (hasConfigured) {
                return Object.fromEntries(variants.map(variant => [
                    variant.size,
                    Number(variant.available) > 0 ? Math.min(Number(variant.available), Math.max(0, Number(configured[variant.size] || 0) * multiplier)) : 0,
                ]));
            }
            const minSizes = Math.max(1, Number(product?.minimum_sizes || 1));
            const colorMin = Math.max(1, Number(product?.color_minimum || 1));
            let remainingSizes = minSizes;
            return Object.fromEntries(variants.map(variant => {
                if (Number(variant.available) > 0 && remainingSizes > 0) {
                    remainingSizes--;
                    return [variant.size, Math.min(Number(variant.available), colorMin)];
                }
                return [variant.size, 0];
            }));
        },
        ensureWholesaleColorQuantities(colorId) {
            const id = String(colorId);
            if (!this.wholesaleSizeQuantities[id]) {
                this.wholesaleSizeQuantities[id] = this.baseWholesalePack(id);
                this.wholesalePackCounts[id] = 1;
            }
        },
        openWholesaleColor(color, trigger) {
            if (!color || !this.selectedProduct) return;
            const colorId = String(color.id);
            this.wholesaleColorTrigger = trigger;
            this.ensureWholesaleColorQuantities(colorId);
            if (!this.selectedWholesaleColorIds.includes(colorId)) {
                this.selectedWholesaleColorIds = [...this.selectedWholesaleColorIds, colorId];
            }
            this.selectedWholesaleColorId = colorId;
            this.detailImageFailed = false;
        },
        focusWholesaleColor(colorId) {
            const id = String(colorId);
            this.ensureWholesaleColorQuantities(id);
            this.selectedWholesaleColorId = id;
            this.detailImageFailed = false;
        },
        toggleWholesaleColor(color) {
            if (!color || Number(color.available) < 1) return;
            const colorId = String(color.id);
            const isSelected = this.selectedWholesaleColorIds.includes(colorId);
            if (!isSelected) {
                this.openWholesaleColor(color, null);
                return;
            }
            if (this.selectedWholesaleColorId !== colorId) {
                this.focusWholesaleColor(colorId);
                return;
            }
            this.removeWholesaleColor(colorId);
        },
        closeWholesaleColor() { this.wholesaleColorTrigger?.focus(); },
        wholesaleRemainingStock(color) {
            const colorId = String(color.id);
            if (!this.selectedWholesaleColorIds.includes(colorId)) return Number(color.available);
            const reserved = Object.values(this.wholesaleSizeQuantities[colorId] || {}).reduce((sum, quantity) => sum + Number(quantity || 0), 0);
            return Math.max(0, Number(color.available) - reserved);
        },
        wholesaleColorName(colorId = this.selectedWholesaleColorId) {
            return this.selectedProduct?.colors.find(color => String(color.id) === String(colorId))?.name || '';
        },
        wholesaleColorPieces(colorId = this.selectedWholesaleColorId) {
            return Object.values(this.wholesaleSizeQuantities[String(colorId)] || {}).reduce((sum, quantity) => sum + Number(quantity || 0), 0);
        },
        wholesalePackCount(colorId = this.selectedWholesaleColorId) {
            return Math.max(1, Number(this.wholesalePackCounts[String(colorId)] || 1));
        },
        stepWholesaleColorPack(colorId, delta) {
            const id = String(colorId);
            const base = this.baseWholesalePack(id);
            const current = this.wholesaleSizeQuantities[id] || { ...base };
            const currentPacks = this.wholesalePackCount(id);
            if (delta < 0 && currentPacks <= 1) return;
            const nextQuantities = { ...current };
            for (const variant of (this.selectedProduct?.variants || []).filter(item => String(item.color_id) === id)) {
                const step = Number(base[variant.size] || 0);
                if (step < 1) continue;
                nextQuantities[variant.size] = Math.min(Number(variant.available), Math.max(0, Number(current[variant.size] || 0) + delta * step));
            }
            const nextTotal = Object.values(nextQuantities).reduce((sum, q) => sum + Number(q || 0), 0);
            if (delta < 0 && nextTotal < 1) return;
            this.wholesaleSizeQuantities[id] = nextQuantities;
            this.wholesalePackCounts[id] = Math.max(1, currentPacks + delta);
        },
        stepWholesaleSize(colorId, size, delta) {
            const id = String(colorId);
            const variant = this.selectedProduct?.variants?.find(item => String(item.color_id) === id && item.size === size);
            const max = Number(variant?.available || 0);
            const current = Number(this.wholesaleSizeQuantities[id]?.[size] || 0);
            const next = Math.min(max, Math.max(0, current + delta));
            this.wholesaleSizeQuantities[id] = { ...(this.wholesaleSizeQuantities[id] || {}), [size]: next };
        },
        clampWholesaleSize(colorId, size) {
            const id = String(colorId);
            const variant = this.selectedProduct?.variants?.find(item => String(item.color_id) === id && item.size === size);
            const max = Number(variant?.available || 0);
            const parsed = Math.floor(Number(this.wholesaleSizeQuantities[id]?.[size] || 0));
            this.wholesaleSizeQuantities[id] = { ...(this.wholesaleSizeQuantities[id] || {}), [size]: Math.min(max, Math.max(0, Number.isFinite(parsed) ? parsed : 0)) };
        },
        wholesaleColorSummary(colorId) {
            return Object.entries(this.wholesaleSizeQuantities[String(colorId)] || {})
                .filter(([, q]) => Number(q) > 0)
                .map(([s, q]) => `${s}: ${q}`)
                .join(' · ');
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
        },
        removeWholesaleColor(colorId) {
            const id = String(colorId);
            this.selectedWholesaleColorIds = this.selectedWholesaleColorIds.filter(existing => existing !== id);
            if (this.selectedWholesaleColorId === id) {
                this.selectedWholesaleColorId = this.selectedWholesaleColorIds[0] || '';
                this.detailImageFailed = false;
            }
        },
        wholesaleCartPiecesForProduct(product = this.selectedProduct) {
            if (!product) return 0;
            return this.groups
                .filter(group => group.mode === 'wholesale' && group.ratio && group.product.id === product.id)
                .reduce((sum, group) => sum + Object.values(group.ratio).reduce((total, qty) => total + Number(qty), 0), 0);
        },
        wholesaleStats() {
            const product = this.selectedProduct;
            if (!product) return { totalPieces: 0, target: 1, progress: 0, moqMet: false, estimatedTotal: 0, unit: 'pcs', currentCount: 0 };
            if (product.pack_pricing) {
                const selected = this.totalPacks();
                const inCart = this.packCartCountForProduct(product);
                const target = Math.max(1, Number(product.min_packs || 1));
                const combined = selected + inCart;
                return {
                    currentCount: selected,
                    totalPieces: this.totalPackPieces(),
                    combined,
                    target,
                    progress: Math.min(100, Math.round((combined / target) * 100)),
                    moqMet: combined >= target && selected > 0,
                    estimatedTotal: this.packTotal(product, selected),
                    unit: 'packs',
                };
            }
            const selectedPieces = this.selectedWholesaleColorIds.reduce((sum, id) => sum + this.wholesaleColorPieces(id), 0);
            const inCart = this.wholesaleCartPiecesForProduct(product);
            const target = Math.max(1, Number(product.minimum || 1));
            const combined = selectedPieces + inCart;
            return {
                currentCount: selectedPieces,
                totalPieces: selectedPieces,
                combined,
                target,
                progress: Math.min(100, Math.round((combined / target) * 100)),
                moqMet: combined >= target && selectedPieces > 0,
                estimatedTotal: Number(product.wholesale_price || 0) * selectedPieces,
                unit: 'pcs',
            };
        },
        wholesaleSelectionError() {
            if (!this.selectedWholesaleColorIds.length) return 'Select at least one color.';
            const product = this.selectedProduct;
            let orderPieces = this.wholesaleCartPiecesForProduct(product);
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
        removeProductGroup(productId) {
            this.groups = this.groups.filter(group => !(group.mode === 'wholesale' && Number(group.product?.id) === Number(productId)));
            this.invalidate();
        },
        clearCart() {
            this.groups = [];
            this.expandedPackDetails = {};
            this.discountValue = '';
            this.invalidate();
        },
        cartSections() {
            const sections = [];
            const wholesaleByProduct = new Map();
            this.groups.forEach((group, index) => {
                if (group.mode !== 'wholesale' || !group.product?.id) {
                    sections.push({ type: 'retail', key: group.key, group, index });
                    return;
                }
                const pid = Number(group.product.id);
                let bucket = wholesaleByProduct.get(pid);
                if (!bucket) {
                    bucket = { type: 'wholesale', key: `ws-${pid}`, product: group.product, entries: [] };
                    wholesaleByProduct.set(pid, bucket);
                    sections.push(bucket);
                }
                bucket.entries.push({ group, index });
            });
            return sections;
        },
        togglePackDetails(productId) {
            const key = String(productId);
            this.expandedPackDetails = { ...this.expandedPackDetails, [key]: !this.expandedPackDetails[key] };
        },
        groupColor(group) {
            return group.product?.colors?.find(color => String(color.id) === String(group.color_id)) || null;
        },
        colorDot(group) {
            return this.colorHex(this.groupColor(group));
        },
        colorThumbUrl(group) {
            const color = this.groupColor(group);
            if (color && Array.isArray(group.product?.gallery)) {
                const scoped = this.colorGallery(group.product, color);
                if (scoped[0]?.url) return scoped[0].url;
            }
            return color?.swatch_image_url || group.product?.image_url || null;
        },
        groupPieces(group) {
            if (group.mode === 'retail') return Number(group.count || 0);
            if (group.packs) return Number(group.packs || 0) * this.packPieces(group);
            return Object.values(group.ratio || {}).reduce((total, qty) => total + Number(qty || 0), 0);
        },
        groupSizeSummary(group) {
            if (group.ratio) {
                return Object.entries(group.ratio)
                    .filter(([, qty]) => Number(qty || 0) > 0)
                    .map(([size, qty]) => `${size}: ${qty}`)
                    .join(', ');
            }
            if (group.packs) {
                const ratio = group.product?.ratios?.[group.color_id] || group.product?.ratios?.[String(group.color_id)] || {};
                const multiplier = Math.max(1, Number(group.product?.multiplier || 1));
                return Object.entries(ratio)
                    .map(([size, qty]) => [size, Number(qty || 0) * multiplier])
                    .filter(([, qty]) => qty > 0)
                    .map(([size, qty]) => `${size}: ${qty}`)
                    .join(', ');
            }
            return '';
        },
        maxPacksForGroup(group) {
            const ratio = group.product?.ratios?.[group.color_id] || group.product?.ratios?.[String(group.color_id)] || {};
            const multiplier = Math.max(1, Number(group.product?.multiplier || 1));
            const entries = Object.entries(ratio)
                .map(([size, qty]) => [size, Number(qty || 0) * multiplier])
                .filter(([, qty]) => qty > 0);
            if (!entries.length || !Array.isArray(group.product?.variants)) return 500;
            return entries.reduce((minPacks, [size, perPack]) => {
                const variant = group.product.variants.find(item => String(item.color_id) === String(group.color_id) && item.size === size);
                return Math.min(minPacks, Math.floor(Number(variant?.available || 0) / perPack));
            }, 500);
        },
        stepGroupPacks(group, delta) {
            const next = Number(group.packs || 0) + delta;
            if (next < 1) { this.remove(group.key); return; }
            const max = Math.max(1, this.maxPacksForGroup(group));
            group.packs = Math.min(next, max);
            this.invalidate();
        },
        sectionPieces(section) {
            return section.entries.reduce((sum, entry) => sum + this.groupPieces(entry.group), 0);
        },
        sectionTotal(section) {
            let sum = 0;
            for (const entry of section.entries) {
                const total = this.lineTotal(entry.index);
                if (total === null) return null;
                sum += Number(total);
            }
            return sum;
        },
        unitPiecePrice(entry) {
            const total = this.lineTotal(entry.index);
            const pieces = this.groupPieces(entry.group);
            if (total === null || pieces < 1) return null;
            return Number(total) / pieces;
        },
        itemCount() {
            return this.groups.reduce((sum, group) => sum + this.groupPieces(group), 0);
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
            this.savedAddresses = contact.addresses || [];
            this.balances = [];
            const preferred = this.savedAddresses.find(item => item.is_default) || this.savedAddresses[0];
            if (preferred) this.useAddress(preferred);
            else this.clearAddress({ name: contact.name || '', phone: contact.phone || '' });
            if (this.$refs.posCustomerModal.open) this.$refs.posCustomerModal.close();
            if (this.fulfillment === 'delivery') this.openAddressModal();
            try {
                const result = await this.request(this.config.balance.replace('__CONTACT__', contact.id));
                if (this.contactId === String(contact.id)) this.balances = result.data;
            } catch { /* balance is informational */ }
        },
        clearCustomer() {
            this.customerType = 'walk_in';
            this.contactId = '';
            this.contactLabel = '';
            this.savedAddresses = [];
            this.clearAddress();
            if (this.fulfillment === 'delivery') this.fulfillment = 'pickup';
        },
        clearAddress(values = {}) {
            this.addressId = '';
            this.address = { name: '', phone: '', line1: '', line2: '', city: '', state: '', postal_code: '', country: 'US', ...values };
        },
        useAddress(saved) {
            this.addressId = String(saved.id);
            this.address = { name: saved.name || '', phone: saved.phone || '', line1: saved.line1 || '', line2: saved.line2 || '', city: saved.city || '', state: saved.state || '', postal_code: saved.postal_code || '', country: saved.country || 'US' };
        },
        addressSummary(address = this.address) {
            return [address.line1, address.line2, [address.city, [address.state, address.postal_code].filter(Boolean).join(' ')].filter(Boolean).join(', '), address.country && address.country !== 'US' ? address.country : ''].filter(Boolean).join(', ');
        },
        initials(name) {
            return (name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map(word => word[0].toUpperCase()).join('');
        },

        // ---- Customer modal ------------------------------------------------------------------
        openCustomerModal(view = 'search') {
            this.formError = '';
            this.customerView = view;
            this.customerForm = { name: '', phone: '', email: '' };
            this.customerQuery = '';
            this.$refs.posCustomerModal.showModal();
            this.searchCustomers();
            this.$nextTick(() => (view === 'search' ? this.$refs.posCustomerSearch : this.$refs.posCustomerName)?.focus());
        },
        showCustomerForm() {
            this.formError = '';
            this.customerView = 'create';
            this.customerForm = { name: this.customerQuery.trim() && !/\d/.test(this.customerQuery) ? this.customerQuery.trim() : '', phone: '', email: '' };
            this.$nextTick(() => this.$refs.posCustomerName?.focus());
        },
        async saveCustomer() {
            const form = this.customerForm;
            if (!form.name.trim() || !form.phone.trim()) { this.formError = 'Enter the customer name and phone.'; return; }
            this.savingCustomer = true;
            try {
                const result = await this.request(this.config.customerStore, { name: form.name.trim(), phone: form.phone.trim(), email: form.email.trim() || null });
                await this.pickCustomer(result.data);
                this.notify('Customer saved.', 'success');
            } catch (error) { this.formError = error.message; }
            finally { this.savingCustomer = false; }
        },

        // ---- Shipping address modal ----------------------------------------------------------
        // mode: 'auto' (pick from saved addresses, or add the first one), 'create' (blank form), 'edit' (current address).
        openAddressModal(mode = 'auto') {
            if (!this.contactId) { this.openCustomerModal(); return; }
            this.formError = '';
            if (mode === 'edit' && this.address.line1) this.startAddressForm({ ...this.address });
            else if (mode === 'create' || !this.savedAddresses.length) this.startAddressForm();
            else this.addressView = 'choose';
            this.$refs.posAddressModal.showModal();
        },
        startAddressForm(values = null) {
            this.formError = '';
            this.postalStatus = '';
            this.suggestions = [];
            this.countryTouched = false;
            const [name, phone] = this.contactLabel.split(' · ');
            this.addressForm = values || { name: name || '', phone: phone || '', line1: '', line2: '', city: '', state: '', postal_code: '', country: 'US' };
            this.addressView = 'create';
        },
        chooseAddress(saved) {
            this.useAddress(saved);
            this.$refs.posAddressModal.close();
        },
        async saveAddress() {
            const form = this.addressForm;
            const missing = [['name', 'the recipient name'], ['phone', 'the delivery phone'], ['line1', 'the street address'], ['city', 'the city']].filter(([field]) => !String(form[field] || '').trim());
            if (missing.length) { this.formError = `Enter ${missing.map(([, label]) => label).join(', ')}.`; return; }
            this.savingAddress = true;
            try {
                const result = await this.request(this.config.addressStore.replace('__CONTACT__', this.contactId), form);
                this.savedAddresses = [result.data, ...this.savedAddresses.filter(item => item.id !== result.data.id)];
                this.useAddress(result.data);
                this.$refs.posAddressModal.close();
            } catch (error) { this.formError = error.message; }
            finally { this.savingAddress = false; }
        },

        // ---- Address autocomplete ------------------------------------------------------------
        queueSuggestions() {
            clearTimeout(this.suggestTimer);
            const query = this.addressForm.line1.trim();
            if (query.length < 3) { this.suggestions = []; return; }
            this.suggestTimer = setTimeout(() => this.loadSuggestions(query), 250);
        },
        async loadSuggestions(query) {
            const version = ++this.suggestVersion;
            try {
                const result = await this.request(`${this.config.addressSuggestions}?q=${encodeURIComponent(query)}${this.countryTouched ? `&country=${this.addressForm.country}` : ''}`);
                if (version === this.suggestVersion) { this.suggestions = result.data; this.suggestIndex = -1; }
            } catch { if (version === this.suggestVersion) this.suggestions = []; }
        },
        moveSuggestion(delta) {
            if (!this.suggestions.length) return;
            this.suggestIndex = (this.suggestIndex + delta + this.suggestions.length) % this.suggestions.length;
        },
        chooseSuggestion(suggestion = this.suggestions[this.suggestIndex]) {
            if (!suggestion) return;
            Object.assign(this.addressForm, { line1: suggestion.line1, city: suggestion.city || this.addressForm.city, state: suggestion.state || this.addressForm.state, postal_code: suggestion.postal_code || this.addressForm.postal_code, country: suggestion.country || this.addressForm.country });
            this.suggestVersion++;
            this.suggestions = [];
            this.suggestIndex = -1;
        },
        // ---- Postal code lookup (US and Bangladesh) ------------------------------------------
        countryChanged() {
            this.countryTouched = true;
            this.addressForm.state = '';
            this.suggestions = [];
            this.postalStatus = '';
            this.lookupPostal();
        },
        async lookupPostal() {
            const patterns = { US: /^\d{5}(-\d{4})?$/, BD: /^\d{4}$/ };
            const code = String(this.addressForm.postal_code || '').trim();
            // Until the owner picks a country by hand, the ZIP format decides it (4 digits: Bangladesh, 5 digits: US).
            if (!this.countryTouched && !patterns[this.addressForm.country]?.test(code)) {
                const detected = Object.keys(patterns).find(key => patterns[key].test(code));
                if (detected) { this.addressForm.country = detected; this.addressForm.state = ''; }
            }
            const country = this.addressForm.country;
            const pattern = patterns[country];
            if (!pattern || !pattern.test(code)) { this.postalStatus = ''; return; }
            const version = ++this.postalVersion;
            this.postalStatus = 'loading';
            try {
                const result = await this.request(`${this.config.postalLookup}?country=${country}&postal_code=${encodeURIComponent(code)}`);
                if (version !== this.postalVersion) return;
                if (!result.data) { this.postalStatus = 'missing'; return; }
                Object.assign(this.addressForm, { city: result.data.city, state: result.data.state, postal_code: result.data.postal_code });
                if (result.data.area && !this.addressForm.line2) this.addressForm.line2 = result.data.area;
                this.postalStatus = 'found';
                this.$nextTick(() => document.getElementById('pos-address-line1')?.focus());
            } catch { if (version === this.postalVersion) this.postalStatus = 'missing'; }
        },
        highlight(label) {
            const typed = this.addressForm.line1.trim();
            const index = typed ? label.toLowerCase().indexOf(typed.toLowerCase()) : -1;
            return index < 0 ? { before: label, match: '', after: '' } : { before: label.slice(0, index), match: label.slice(index, index + typed.length), after: label.slice(index + typed.length) };
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
                await this.request(this.config.held, { label: this.contactLabel || '', payload: { lines, customerType: this.customerType, contactId: this.contactId, contactLabel: this.contactLabel, fulfillment: this.fulfillment, address: this.address, savedAddresses: this.savedAddresses, addressId: this.addressId, discountType: this.discountType, discountValue: this.discountValue } });
                this.notify('Sale held.', 'success');
                this.newSale();
                this.loadHeld();
            } catch (error) { this.notify(error.message); }
        },
        async resumeHeld(sale) {
            if (this.groups.length && !window.confirm('Replace the current cart with this held sale?')) return;
            const payload = sale.payload;
            this.groups = payload.lines.map(line => ({ ...line, key: crypto.randomUUID() }));
            this.customerType = payload.customerType === 'existing' ? 'existing' : 'walk_in';
            this.contactId = payload.contactId || '';
            this.contactLabel = payload.contactLabel || '';
            this.savedAddresses = payload.savedAddresses || [];
            this.addressId = payload.addressId || '';
            this.address = payload.address || this.address;
            this.fulfillment = payload.fulfillment || 'pickup';
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
            const keys = { F2: () => this.$refs.posSearch?.focus(), F4: () => this.openCustomerModal(), F8: () => this.holdSale(), F9: () => this.checkout() };
            if (keys[event.key]) { event.preventDefault(); keys[event.key](); }
        },
    };
});
