import Alpine from 'alpinejs';

const DEFAULT_ISO = 'US';

Alpine.data('phoneInput', () => {
    'use strict';

    return {
        countries: [], iso: DEFAULT_ISO, national: '', e164: '', open: false, query: '', active: 0, last: '', defaultIso: DEFAULT_ISO,
        init() {
            'use strict';
            this.countries = JSON.parse(this.$el.dataset.countries || '[]').map(([iso, name, dial]) => ({ iso, name, dial }));
            this.defaultIso = this.$el.dataset.default || DEFAULT_ISO;
            this.iso = this.defaultIso;
            this.$watch('e164', value => this.fromE164(value));
            this.$watch('national', () => this.toE164());
            this.$watch('iso', () => this.toE164());
            if (this.e164) this.fromE164(this.e164);
        },
        current() { return this.countries.find(country => country.iso === this.iso) || this.countries[0] || { iso: DEFAULT_ISO, name: '', dial: '1' }; },
        flag(iso) { return iso.toUpperCase().replace(/./g, char => String.fromCodePoint(127397 + char.charCodeAt(0))); },
        // Shared dial codes (+1, +7 ...) keep the selected country when it already owns the code, otherwise the first match.
        fromE164(value) {
            if (value === this.last) return;
            this.last = value;
            const digits = String(value || '').replace(/\D/g, '');
            if (!digits) { this.national = ''; this.iso = this.defaultIso; return; }
            if (!String(value).trim().startsWith('+')) { this.national = digits; return; }
            const owns = [1, 2, 3].map(length => digits.slice(0, length)).some(code => this.current().dial === code);
            const match = owns ? this.current() : [3, 2, 1].map(length => this.countries.find(country => country.dial === digits.slice(0, length))).find(Boolean);
            if (!match) { this.national = digits; return; }
            this.iso = match.iso;
            this.national = digits.slice(match.dial.length);
        },
        toE164() {
            const dial = this.current().dial;
            let digits = this.national.replace(/\D/g, '');
            if (this.national.trim().startsWith('+') && digits.startsWith(dial)) digits = digits.slice(dial.length);
            if (dial !== '39') digits = digits.replace(/^0+/, '');
            const value = digits ? `+${dial}${digits}` : '';
            if (value === this.e164) return;
            this.last = value;
            this.e164 = value;
        },
        filtered() {
            const query = this.query.trim().toLowerCase().replace(/^\+/, '');
            if (!query) return this.countries;
            return this.countries.filter(country => country.name.toLowerCase().includes(query) || country.iso.toLowerCase() === query || country.dial.startsWith(query));
        },
        toggle() {
            this.open = !this.open;
            this.query = '';
            this.active = 0;
            if (this.open) this.$nextTick(() => this.$refs.search?.focus());
        },
        choose(country) {
            this.iso = country.iso;
            this.open = false;
            this.$nextTick(() => this.$refs.number?.focus());
        },
        move(delta) {
            const total = this.filtered().length;
            if (total) this.active = (this.active + delta + total) % total;
        },
        pick() { const country = this.filtered()[this.active]; if (country) this.choose(country); },
    };
});
