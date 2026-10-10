import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

function pos() {
    let factory;
    let nextId = 1;
    const source = readFileSync(new URL('../../resources/js/components/commerce-pos.js', import.meta.url), 'utf8');
    vm.runInNewContext(source.replace("import Alpine from 'alpinejs';", ''), {
        Alpine: { data(_name, callback) { factory = callback; } },
        crypto: { randomUUID() { return `uuid-${nextId++}`; } },
    });
    const state = factory();
    state.$refs = {
        posGallery: { showModal() { state.opened = true; } },
        posProductModal: { showModal() { state.productModalOpened = true; }, close() { state.productModalOpened = false; }, querySelector() { return null; } },
        posWholesaleColorModal: { showModal() { state.colorModalOpened = true; }, close() { state.colorModalOpened = false; } },
    };
    return state;
}

test('color galleries include only images belonging to the selected color', () => {
    const state = pos();
    const product = {
        name: 'Shirt',
        image_url: 'primary.jpg',
        gallery: [
            { url: 'red-front.jpg', color_id: 1 },
            { url: 'red-back.jpg', color_id: 1 },
            { url: 'blue-front.jpg', color_id: 2 },
            { url: 'general.jpg', color_id: null },
        ],
    };

    assert.deepEqual(state.colorGallery(product, { id: 1 }).map(image => image.url), ['red-front.jpg', 'red-back.jpg']);
    assert.deepEqual(state.colorGallery(product, { id: 2 }).map(image => image.url), ['blue-front.jpg']);
});

test('color galleries fall back through swatch, general, then primary images', () => {
    const state = pos();
    const product = { name: 'Shirt', image_url: 'primary.jpg', gallery: [{ url: 'general.jpg', color_id: null }] };

    assert.deepEqual(Array.from(state.colorGallery(product, { id: 1, name: 'Red', swatch_image_url: 'swatch.jpg' }), image => image.url), ['swatch.jpg', 'general.jpg', 'primary.jpg']);
    assert.deepEqual(Array.from(state.colorGallery(product, { id: 1 }), image => image.url), ['general.jpg', 'primary.jpg']);
    assert.equal(state.colorGallery({ name: 'Shirt', gallery: [], image_url: null }, { id: 1 }).length, 0);
});

test('opening a color gallery keeps cart unchanged and supports navigation and focus restoration', () => {
    const state = pos();
    state.groups = [{ key: 'existing' }];
    const trigger = { focus() { this.focused = true; } };
    const product = { name: 'Shirt', image_url: null, gallery: [{ url: 'one.jpg', color_id: 1 }, { url: 'two.jpg', color_id: 1 }] };

    state.openColorGallery(product, { id: 1, name: 'Red' }, trigger);
    assert.equal(state.opened, true);
    assert.equal(state.galleryTitle, 'Shirt · Red');
    assert.equal(state.galleryIndex, 0);
    assert.equal(state.groups.length, 1);
    state.moveGallery(-1);
    assert.equal(state.galleryIndex, 1);
    state.moveGallery(1);
    assert.equal(state.galleryIndex, 0);
    state.restoreGalleryFocus();
    assert.equal(trigger.focused, true);
});

test('single-image galleries do not navigate', () => {
    const state = pos();
    state.openGallery([{ url: 'only.jpg' }], 'Shirt · Red', null);
    state.moveGallery(-1);
    assert.equal(state.galleryIndex, 0);
});

test('switching modes preserves independent retail and wholesale drafts', () => {
    const state = pos();
    const retail = { key: 'retail-line', mode: 'retail' };
    const wholesale = { key: 'pack-line', mode: 'wholesale', product: { id: 8 } };
    state.groups = [retail];
    state.switchMode('wholesale');
    assert.deepEqual(Array.from(state.groups), []);
    state.groups = [wholesale];
    state.wholesaleProductId = 8;
    state.switchMode('retail');
    assert.equal(state.groups[0].key, 'retail-line');
    state.switchMode('wholesale');
    assert.equal(state.groups[0].key, 'pack-line');
    assert.equal(state.wholesaleProductId, 8);
});

test('formats USD with a dollar symbol while retaining currency-code support', () => {
    const state = pos();
    state.config = { currency: 'USD', precision: 2 };
    assert.equal(state.currencySymbol(), '$');
    assert.match(state.money('9'), /\$.*9\.00|9\.00.*\$/);
    assert.notEqual(state.currencySymbol('BDT'), 'BDT');
});

test('retail product modal adds selected variants and quantities to the multi-item cart', () => {
    const state = pos();
    const product = {
        id: 12, name: 'Shirt', mode: 'both', wholesale_enabled: true, colors: [{ id: 4, available: 3 }],
        variants: [{ id: 91, color_id: 4, size: 'M', available: 3 }],
    };
    state.openProduct(product, null);
    assert.equal(state.selectedVariantId, '91');
    state.selectedQuantity = 2;
    state.addRetailSelection();
    assert.equal(state.groups.length, 1);
    assert.equal(state.groups[0].variant_id, 91);
    assert.equal(state.groups[0].count, 2);
    assert.deepEqual(JSON.parse(JSON.stringify(state.payload().groups)), [{ product_id: 12, mode: 'retail', variant_id: 91, quantity: 2 }]);
});

test('wholesale color rows open quantity details without opening the gallery', () => {
    const state = pos();
    state.mode = 'wholesale';
    const product = {
        id: 12, name: 'Shirt', mode: 'both', wholesale_enabled: true,
        colors: [{ id: 4, name: 'Red', available: 100 }, { id: 5, name: 'Blue', available: 100 }],
        variants: ['S', 'M', 'L'].flatMap((size, index) => [4, 5].map(colorId => ({ id: index * 2 + colorId, color_id: colorId, size, available: 100 }))),
        ratios: { 4: { M: 3 } }, multiplier: 1, minimum: 6, minimum_sizes: 3, color_minimum: 1,
    };
    state.selectedProduct = product;
    state.openWholesaleColor(product.colors[0], { focus() { this.focused = true; } });
    assert.equal(state.colorModalOpened, true);
    assert.equal(state.opened, undefined);
    assert.equal(state.selectedWholesaleColorId, '4');
    state.wholesaleSizeQuantities[4] = { S: 1, M: 2, L: 1 };
    state.saveWholesaleColor();
    assert.deepEqual(Array.from(state.selectedWholesaleColorIds), ['4']);
    state.openWholesaleColor(product.colors[1], null);
    state.wholesaleSizeQuantities[5] = { S: 1, M: 1, L: 1 };
    state.saveWholesaleColor();
    state.addWholesaleSelection();
    assert.equal(state.groups.length, 2);
    assert.equal(state.wholesaleProductId, 12);
    assert.equal(state.wholesaleSummary(), '7 pieces across 2 colors');
    assert.deepEqual(JSON.parse(JSON.stringify(state.payload().groups)), [
        { product_id: 12, mode: 'wholesale', color_id: 4, size_quantities: { S: 1, M: 2, L: 1 } },
        { product_id: 12, mode: 'wholesale', color_id: 5, size_quantities: { S: 1, M: 1, L: 1 } },
    ]);
    state.$refs.posProductModal.close();
    state.openProduct({ ...product, id: 13 }, null);
    assert.equal(state.productModalOpened, false);
    assert.match(state.error, /Clear the current wholesale color selections/);
    state.clearWholesaleDraft();
    assert.equal(state.wholesaleProductId, null);
    assert.equal(state.groups.length, 0);
});
