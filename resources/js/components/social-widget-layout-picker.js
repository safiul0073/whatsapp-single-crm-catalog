import Alpine from 'alpinejs';

Alpine.data('socialWidgetLayoutPicker', () => ({
  query: '',

  get normalizedQuery() {
    return this.query.trim().toLowerCase();
  },

  get visibleCount() {
    return Array.from(this.$root.querySelectorAll('[data-layout-card]')).filter((card) => this.matches(card)).length;
  },

  get visibleCountLabel() {
    return `${this.visibleCount} ${this.visibleCount === 1 ? 'layout' : 'layouts'}`;
  },

  matches(card) {
    return this.normalizedQuery === '' || (card.dataset.search || '').includes(this.normalizedQuery);
  },

  focusSearchOnShortcut(event) {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      this.$refs.search.focus();
    }
  },
}));
