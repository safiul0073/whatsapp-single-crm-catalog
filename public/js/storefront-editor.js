window.storefrontPageEditor = function (initial) {
    'use strict';
    return {sections: initial.map((s, i) => ({...s, key: i + ':' + Date.now()})), drag: null, error: '',
        add(type) { this.sections.push({key: crypto.randomUUID(), type, config: {title: '', source: 'new-arrivals', limit: 8, items: [], text: ''}, active: true, visibility: 'all', starts_at: null, ends_at: null}); },
        move(from, to) { if (to >= 0 && to < this.sections.length && from !== null) { this.sections.splice(to, 0, this.sections.splice(from, 1)[0]); } },
        parseItems(section, value) { try { const items = JSON.parse(value); if (!Array.isArray(items)) throw new Error(); section.config.items = items; this.error = ''; } catch { this.error = 'Enter a valid JSON array for items.'; } }
    };
};
