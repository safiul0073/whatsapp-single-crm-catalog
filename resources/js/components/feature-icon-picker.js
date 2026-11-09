import Alpine from 'alpinejs';

/**
 * Shared "More icons…" modal. Open it from anywhere with:
 *   window.dispatchEvent(new CustomEvent('feature-icon-picker:open', { detail: { value, apply(icon) {} } }))
 */
Alpine.data('featureIconPicker', (config = {}) => ({
  iconsUrl: config.iconsUrl || '',
  open: false,
  query: '',
  icons: [],
  loaded: false,
  loading: false,
  value: '',
  apply: null,

  get filteredIcons() {
    const q = this.query.trim().toLowerCase();
    const list = q ? this.icons.filter((icon) => icon.includes(q)) : this.icons;
    return list.slice(0, 240);
  },

  async load() {
    if (this.loaded || this.loading || !this.iconsUrl) {
      return;
    }
    this.loading = true;
    try {
      const response = await fetch(this.iconsUrl, { headers: { Accept: 'application/json' } });
      this.icons = response.ok ? await response.json() : [];
      this.loaded = response.ok;
    } finally {
      this.loading = false;
    }
  },

  show(detail) {
    this.value = detail?.value || '';
    this.apply = typeof detail?.apply === 'function' ? detail.apply : null;
    this.query = '';
    this.open = true;
    this.load();
  },

  choose(icon) {
    if (this.apply) {
      this.apply(icon);
    }
    this.open = false;
  },
}));

Alpine.data('featureIconPresets', (config = {}) => ({
  presets: Array.isArray(config.presets) ? config.presets.map((p) => ({ icon: p.icon, label: p.label })) : [],
  defaults: Array.isArray(config.defaults) ? config.defaults : [],

  add() {
    this.presets.push({ icon: 'ph-check-circle', label: '' });
  },

  remove(index) {
    this.presets.splice(index, 1);
  },

  reset() {
    this.presets = this.defaults.map((p) => ({ icon: p.icon, label: p.label }));
  },

  pick(index) {
    window.dispatchEvent(new CustomEvent('feature-icon-picker:open', {
      detail: {
        value: this.presets[index].icon,
        apply: (icon) => {
          this.presets[index].icon = icon;
        },
      },
    }));
  },
}));
