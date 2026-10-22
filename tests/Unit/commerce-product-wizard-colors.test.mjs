import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

function createWizard(config = {}) {
    let factory;
    const source = readFileSync(new URL('../../resources/js/components/commerce-product-wizard.js', import.meta.url), 'utf8');
    vm.runInNewContext(source.replace("import Alpine from 'alpinejs';", ''), {
        Alpine: { data(_name, callback) { factory = callback; } },
        document: { getElementById() { return null; }, querySelector() { return null; } },
        window: { addEventListener() {} },
        console,
    });
    return factory(config);
}

test('addColor adds new color rows to the top (index 0)', () => {
    const state = createWizard({
        colors: [
            { id: 10, name: 'Navy Blue', hex_code: '#1A2033' },
            { id: 11, name: 'Khaki', hex_code: '#CDCBBB' },
        ],
    });

    assert.equal(state.colors.length, 2);
    assert.equal(state.colors[0].name, 'Navy Blue');

    state.addColor();

    assert.equal(state.colors.length, 3);
    assert.equal(state.colors[0].id, '');
    assert.equal(state.colors[0].hex_code, '#2563EB');
    assert.equal(state.colors[1].name, 'Navy Blue');
    assert.equal(state.colors[2].name, 'Khaki');

    // Add another color - should also prepend to top
    state.addColor();
    assert.equal(state.colors.length, 4);
    assert.equal(state.colors[0].id, '');
    assert.equal(state.colors[1].id, '');
    assert.equal(state.colors[2].name, 'Navy Blue');
    assert.equal(state.colors[3].name, 'Khaki');
});

test('media assignments remain linked to correct colors when new colors are unshifted to top', () => {
    const state = createWizard({
        colors: [
            { id: 10, name: 'Navy Blue', hex_code: '#1A2033' },
        ],
        gallery: [
            { id: 501, name: 'navy.jpg', color_id: '10' },
        ],
    });

    // Add a new color row at top
    state.addColor();
    state.colors[0].name = 'Crimson';

    // Assign an image to the newly added color at top
    state.addMediaToColor(0, [{ id: 601, name: 'crimson.jpg' }]);

    assert.equal(state.getColorMediaList(0).length, 1);
    assert.equal(state.getColorMediaList(0)[0].id, 601);
    assert.equal(state.getColorMediaList(1).length, 1);
    assert.equal(state.getColorMediaList(1)[0].id, 501);

    // Add another color at top
    state.addColor();
    state.colors[0].name = 'Emerald';

    // Crimson is now at index 1, Navy Blue is now at index 2
    assert.equal(state.getColorMediaList(0).length, 0);
    assert.equal(state.getColorMediaList(1).length, 1);
    assert.equal(state.getColorMediaList(1)[0].id, 601);
    assert.equal(state.getColorMediaList(2).length, 1);
    assert.equal(state.getColorMediaList(2)[0].id, 501);

    // Verify submission IDs match actual positions
    const crimsonMedia = state.gallery.find(g => g.id === 601);
    const navyMedia = state.gallery.find(g => g.id === 501);
    assert.equal(state.getMediaColorSubmissionId(crimsonMedia), 'idx_1');
    assert.equal(state.getMediaColorSubmissionId(navyMedia), '10');
});

test('normalizeHex properly formats hex codes with or without hash and expands 3-char shorthand', () => {
    const state = createWizard();

    assert.equal(state.normalizeHex('1A2033'), '#1A2033');
    assert.equal(state.normalizeHex('#1a2033'), '#1A2033');
    assert.equal(state.normalizeHex('fff'), '#FFFFFF');
    assert.equal(state.normalizeHex('#abc'), '#AABBCC');
    assert.equal(state.normalizeHex(''), '#2563EB');
});

test('removeColor removes the color and unlinks associated gallery media', () => {
    const state = createWizard({
        colors: [
            { id: 10, name: 'Navy Blue', hex_code: '#1A2033' },
            { id: 11, name: 'Khaki', hex_code: '#CDCBBB' },
        ],
        gallery: [
            { id: 501, name: 'navy.jpg', color_id: '10' },
            { id: 502, name: 'khaki.jpg', color_id: '11' },
        ],
    });

    state.removeColor(0);
    assert.equal(state.colors.length, 1);
    assert.equal(state.colors[0].name, 'Khaki');
    assert.equal(state.gallery.find(g => g.id === 501).color_id, null);
    assert.equal(state.gallery.find(g => g.id === 502).color_id, '11');
});
