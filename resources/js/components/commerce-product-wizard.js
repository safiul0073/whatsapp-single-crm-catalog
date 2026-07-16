import Alpine from 'alpinejs';

Alpine.data('commerceProductWizard', (config = {}) => ({
  gallery: config.gallery || [],
  options: config.options || [],
  variants: config.variants || [],
  previewUrl: config.previewUrl || null,
  dirty: false,
  loadingVariants: false,

  init() {
    window.addEventListener('beforeunload', (event) => {
      if (!this.dirty) return;
      event.preventDefault();
      event.returnValue = '';
    });
  },

  addMedia(items) {
    const existing = new Set(this.gallery.map((item) => String(item.id)));
    items.forEach((item) => {
      if (existing.has(String(item.id))) return;
      const isVideo = item.type === 'video';
      if (isVideo && this.gallery.some((entry) => entry.type === 'video')) return;
      if (!isVideo && this.gallery.filter((entry) => entry.type === 'image').length >= 10) return;
      this.gallery.push({
        id: item.id,
        name: item.name,
        url: item.url,
        type: item.type,
        alt_text: item.name || '',
        is_primary: !isVideo && !this.gallery.some((entry) => entry.is_primary),
      });
      existing.add(String(item.id));
    });
    this.dirty = true;
  },

  removeMedia(index) {
    const removed = this.gallery[index];
    this.gallery.splice(index, 1);
    if (removed?.is_primary) {
      const nextImage = this.gallery.find((item) => item.type === 'image');
      if (nextImage) nextImage.is_primary = true;
    }
    this.dirty = true;
  },

  setPrimary(index) {
    if (this.gallery[index]?.type !== 'image') return;
    this.gallery.forEach((item, itemIndex) => { item.is_primary = itemIndex === index; });
    this.dirty = true;
  },

  moveMedia(index, direction) {
    const target = index + direction;
    if (target < 0 || target >= this.gallery.length) return;
    const item = this.gallery.splice(index, 1)[0];
    this.gallery.splice(target, 0, item);
    this.dirty = true;
  },

  addOption() {
    if (this.options.length >= 5) return;
    this.options.push({ name: '', code: '', values_csv: '' });
    this.dirty = true;
  },

  async regenerateVariants() {
    if (!this.previewUrl || this.loadingVariants) return;
    this.loadingVariants = true;
    try {
      const response = await window.axios.get(this.previewUrl, { headers: { Accept: 'application/json' } });
      this.variants = response.data.variants || [];
      this.dirty = true;
    } finally {
      this.loadingVariants = false;
    }
  },

  markSaved() {
    this.dirty = false;
  },
}));
