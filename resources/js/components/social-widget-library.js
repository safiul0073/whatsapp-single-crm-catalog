import Alpine from 'alpinejs';

const PREVIEW_OFFSET = 18;

Alpine.data('socialWidgetLibrary', () => ({
  query: '',
  previewKey: null,

  get normalizedQuery() {
    return this.query.trim().toLowerCase();
  },

  get visibleCount() {
    return Array.from(this.$root.querySelectorAll('[data-widget-item]')).filter((item) => this.matches(item)).length;
  },

  matches(item) {
    return this.normalizedQuery === '' || (item.dataset.search || '').includes(this.normalizedQuery);
  },

  sectionHasMatches(section) {
    return Array.from(section.querySelectorAll('[data-widget-item]')).some((item) => this.matches(item));
  },

  focusSearchOnShortcut(event) {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      this.$refs.search.focus();
    }
  },

  showPreview(event, key) {
    if (window.matchMedia('(hover: none)').matches) return;
    this.previewKey = key;
    this.movePreview(event);
  },

  movePreview(event) {
    if (!this.previewKey) return;
    const preview = this.$refs.preview;
    const width = preview.offsetWidth;
    const height = preview.offsetHeight;
    const fitsAbove = event.clientY - height - PREVIEW_OFFSET > 8;
    const left = Math.min(Math.max(8, event.clientX - width / 2), window.innerWidth - width - 8);
    const top = fitsAbove ? event.clientY - height - PREVIEW_OFFSET : event.clientY + PREVIEW_OFFSET;
    preview.style.setProperty('transform', `translate(${left}px, ${top}px)`);
  },

  hidePreview() {
    this.previewKey = null;
  },
}));
