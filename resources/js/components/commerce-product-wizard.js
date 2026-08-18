import Alpine from 'alpinejs';

Alpine.data('commerceProductWizard', (config = {}) => ({
  gallery: config.gallery || [],
  options: config.options || [],
  variants: config.variants || [],
  colors: config.colors || [],
  sizes: config.sizes || ['S', 'M', 'L', 'XL', 'XXL'],
  customSize: '',
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

  addMedia(items, defaultColorId = null) {
    if (!items || !items.length) return;
    const existing = new Map(this.gallery.map((item) => [String(item.id), item]));

    items.forEach((item) => {
      if (existing.has(String(item.id))) {
        const found = existing.get(String(item.id));
        if (defaultColorId !== null && defaultColorId !== undefined) {
          found.color_id = defaultColorId;
        }
        return;
      }
      const isVideo = item.type === 'video';
      if (isVideo && this.gallery.some((entry) => entry.type === 'video')) return;
      if (!isVideo && this.gallery.filter((entry) => entry.type === 'image').length >= 40) return;

      this.gallery.push({
        id: item.id,
        name: item.name,
        url: item.url,
        type: item.type,
        alt_text: item.name || '',
        color_id: defaultColorId !== null && defaultColorId !== undefined ? defaultColorId : (item.color_id || null),
        is_primary: !isVideo && !this.gallery.some((entry) => entry.is_primary),
      });
    });
    this.dirty = true;
  },

  addMediaToColor(colorIndex, items) {
    if (!items || !items.length || !this.colors[colorIndex]) return;
    const colorObj = this.colors[colorIndex];
    const colorIdentifier = colorObj.id ? String(colorObj.id) : `idx_${colorIndex}`;

    this.addMedia(items, colorIdentifier);

    // Set first image as swatch hero if not already assigned
    if (!colorObj.swatch_media_id && items[0]) {
      colorObj.swatch_media_id = items[0].id;
    }

    // Auto-update variants matching this color
    const colorName = colorObj.name || colorObj.hex_code;
    this.variants.forEach((v) => {
      if (
        (v.color_id && String(v.color_id) === String(colorObj.id)) ||
        (v.attributes?.color && String(v.attributes.color).toLowerCase() === String(colorName).toLowerCase())
      ) {
        if (!v.media_id) {
          v.media_id = items[0].id;
        }
      }
    });

    this.dirty = true;
  },

  getColorMediaList(colorIndex) {
    if (!this.colors[colorIndex]) return [];
    const colorObj = this.colors[colorIndex];
    const colorIdentifier = colorObj.id ? String(colorObj.id) : `idx_${colorIndex}`;

    return this.gallery.filter((item) => {
      if (!item.color_id) return false;
      return String(item.color_id) === colorIdentifier || (colorObj.id && String(item.color_id) === String(colorObj.id));
    });
  },

  getGeneralMediaList() {
    return this.gallery.filter((item) => !item.color_id);
  },

  setColorPrimary(colorIndex, mediaId) {
    if (!this.colors[colorIndex]) return;
    const colorObj = this.colors[colorIndex];
    colorObj.swatch_media_id = mediaId;

    // Auto-update variants matching this color
    const colorName = colorObj.name || colorObj.hex_code;
    this.variants.forEach((v) => {
      if (
        (v.color_id && String(v.color_id) === String(colorObj.id)) ||
        (v.attributes?.color && String(v.attributes.color).toLowerCase() === String(colorName).toLowerCase())
      ) {
        v.media_id = mediaId;
      }
    });

    this.dirty = true;
  },

  removeMediaById(mediaId) {
    const idx = this.gallery.findIndex((item) => String(item.id) === String(mediaId));
    if (idx !== -1) {
      this.removeMedia(idx);
    }
  },

  removeMedia(index) {
    const removed = this.gallery[index];
    this.gallery.splice(index, 1);
    if (removed?.is_primary) {
      const nextImage = this.gallery.find((item) => item.type === 'image');
      if (nextImage) nextImage.is_primary = true;
    }
    // Clean up color assignments if that media was removed
    if (removed?.id) {
      this.colors.forEach((c) => {
        if (String(c.swatch_media_id) === String(removed.id)) {
          // Re-assign next image of this color if available
          const remaining = this.gallery.find((g) => (c.id && String(g.color_id) === String(c.id)) && String(g.id) !== String(removed.id));
          c.swatch_media_id = remaining ? remaining.id : '';
        }
      });
    }
    this.dirty = true;
  },

  setPrimary(index) {
    if (this.gallery[index]?.type !== 'image') return;
    this.gallery.forEach((item, itemIndex) => { item.is_primary = itemIndex === index; });
    this.dirty = true;
  },

  setPrimaryById(mediaId) {
    this.gallery.forEach((item) => {
      item.is_primary = String(item.id) === String(mediaId);
    });
    this.dirty = true;
  },

  moveMedia(index, direction) {
    const target = index + direction;
    if (target < 0 || target >= this.gallery.length) return;
    const item = this.gallery.splice(index, 1)[0];
    this.gallery.splice(target, 0, item);
    this.dirty = true;
  },

  addColor() {
    this.colors.push({ id: '', name: '', hex_code: '#2563EB', color_family: '', swatch_media_id: '' });
    this.dirty = true;
  },

  removeColor(index) {
    const removed = this.colors[index];
    // Remove color_id tag from gallery images belonging to this color
    if (removed?.id) {
      this.gallery.forEach((g) => {
        if (String(g.color_id) === String(removed.id)) {
          g.color_id = null;
        }
      });
    }
    this.colors.splice(index, 1);
    this.dirty = true;
  },

  applyPreset(presetValues) {
    if (!presetValues) return;
    const list = Array.isArray(presetValues) ? presetValues : presetValues.split(',').map((s) => s.trim()).filter(Boolean);
    this.sizes = [...list];
    this.dirty = true;
  },

  toggleSize(sizeVal) {
    if (!sizeVal) return;
    const idx = this.sizes.indexOf(sizeVal);
    if (idx > -1) {
      this.sizes.splice(idx, 1);
    } else {
      this.sizes.push(sizeVal);
    }
    this.dirty = true;
  },

  hasSize(sizeVal) {
    return this.sizes.includes(sizeVal);
  },

  addCustomSize() {
    if (!this.customSize || !this.customSize.trim()) return;
    const parts = this.customSize.split(',').map((s) => s.trim()).filter(Boolean);
    parts.forEach((p) => {
      if (!this.sizes.includes(p)) {
        this.sizes.push(p);
      }
    });
    this.customSize = '';
    this.dirty = true;
  },

  removeSize(index) {
    this.sizes.splice(index, 1);
    this.dirty = true;
  },

  getColorVariants(colorName) {
    return this.variants.filter((v) => {
      const vColor = v.attributes?.color || '';
      return String(vColor).toLowerCase() === String(colorName).toLowerCase();
    });
  },

  getColorSubtotal(colorName) {
    const list = this.getColorVariants(colorName);
    return list.reduce((sum, v) => sum + Math.max(0, parseInt(v.stock_quantity || 0, 10)), 0);
  },

  getTotalStock() {
    return this.variants.reduce((sum, v) => sum + Math.max(0, parseInt(v.stock_quantity || 0, 10)), 0);
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
