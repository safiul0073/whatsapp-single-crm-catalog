import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

function gallery() {
    let factory;
    const source = readFileSync(new URL('../../resources/js/components/commerce-product-gallery.js', import.meta.url), 'utf8');
    vm.runInNewContext(source.replace("import Alpine from 'alpinejs';", ''), {
        Alpine: { data(_name, callback) { factory = callback; } },
    });
    const state = factory();
    state.$refs = { productViewer: { showModal() { state.opened = true; } } };
    return state;
}

test('opens the selected product, wraps navigation, and restores thumbnail focus', () => {
    const state = gallery();
    const trigger = { focus() { this.focused = true; } };
    const photos = [{ url: 'front.png' }, { url: 'back.png' }];
    state.openProduct(photos, trigger);
    assert.equal(state.opened, true);
    assert.equal(state.activeIndex, 0);
    state.move(-1);
    assert.equal(state.activeIndex, 1);
    state.move(1);
    assert.equal(state.activeIndex, 0);
    state.restoreFocus();
    assert.equal(trigger.focused, true);
});

test('empty galleries stay closed and single photos do not navigate', () => {
    const state = gallery();
    state.openProduct([], null);
    assert.equal(state.opened, undefined);
    state.move(1);
    assert.equal(state.activeIndex, 0);
    state.openProduct([{ url: 'only.png' }], null);
    state.move(-1);
    assert.equal(state.activeIndex, 0);
    state.restoreFocus();
});

test('switching products resets the index and keeps failures scoped to image urls', () => {
    const state = gallery();
    state.openProduct([{ url: 'broken.png' }, { url: 'good.png' }], null);
    state.failedImages['broken.png'] = true;
    state.move(1);
    state.openProduct([{ url: 'other.png' }], null);
    assert.equal(state.activeIndex, 0);
    assert.equal(state.failedImages['other.png'], undefined);
    assert.equal(state.failedImages['broken.png'], true);
});
