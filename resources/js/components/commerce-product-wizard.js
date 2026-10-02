import Alpine from 'alpinejs';

Alpine.data('commerceProductWizard', (config = {}) => ({
  gallery: config.gallery || [],
  options: config.options || [],
  variants: config.variants || [],
  variantPresets: config.variantPresets || [],
  colors: config.colors || [],
  sizes: config.sizes || [],
  
  // Modal State
  showSizeModal: false,
  modalSelectedPresets: [],
  modalNewSize: { name: '', weight: '', weight_unit: 'kg' },
  modalSaving: false,
  modalError: '',
  
  tierPrices: config.tierPrices || [],
  featureHighlights: config.featureHighlights || [
    { label: 'Premium Tech Fleece', icon: 'ph-t-shirt' },
    { label: 'Full-Zip 2-Piece Set', icon: 'ph-arrows-out-line-vertical' },
    { label: 'Kids to Older Kids', icon: 'ph-users-three' },
    { label: 'Multiple Colors', icon: 'ph-palette' },
    { label: 'USA True Size', icon: 'ph-ruler' },
  ],
  shippingCountries: config.shippingCountries || ['USA', 'Canada'],
  countryInput: '',
  showCountryDropdown: false,
  availableCountries: ['Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola', 'Antigua and Barbuda', 'Argentina', 'Armenia', 'Australia', 'Austria', 'Azerbaijan', 'Bahamas', 'Bahrain', 'Bangladesh', 'Barbados', 'Belarus', 'Belgium', 'Belize', 'Benin', 'Bhutan', 'Bolivia', 'Bosnia and Herzegovina', 'Botswana', 'Brazil', 'Brunei', 'Bulgaria', 'Burkina Faso', 'Burundi', 'Côte d\'Ivoire', 'Cabo Verde', 'Cambodia', 'Cameroon', 'Canada', 'Central African Republic', 'Chad', 'Chile', 'China', 'Colombia', 'Comoros', 'Congo (Congo-Brazzaville)', 'Costa Rica', 'Croatia', 'Cuba', 'Cyprus', 'Czechia (Czech Republic)', 'Democratic Republic of the Congo', 'Denmark', 'Djibouti', 'Dominica', 'Dominican Republic', 'Ecuador', 'Egypt', 'El Salvador', 'Equatorial Guinea', 'Eritrea', 'Estonia', 'Eswatini (fmr. "Swaziland")', 'Ethiopia', 'Fiji', 'Finland', 'France', 'Gabon', 'Gambia', 'Georgia', 'Germany', 'Ghana', 'Greece', 'Grenada', 'Guatemala', 'Guinea', 'Guinea-Bissau', 'Guyana', 'Haiti', 'Holy See', 'Honduras', 'Hungary', 'Iceland', 'India', 'Indonesia', 'Iran', 'Iraq', 'Ireland', 'Israel', 'Italy', 'Jamaica', 'Japan', 'Jordan', 'Kazakhstan', 'Kenya', 'Kiribati', 'Kuwait', 'Kyrgyzstan', 'Laos', 'Latvia', 'Lebanon', 'Lesotho', 'Liberia', 'Libya', 'Liechtenstein', 'Lithuania', 'Luxembourg', 'Madagascar', 'Malawi', 'Malaysia', 'Maldives', 'Mali', 'Malta', 'Marshall Islands', 'Mauritania', 'Mauritius', 'Mexico', 'Micronesia', 'Moldova', 'Monaco', 'Mongolia', 'Montenegro', 'Morocco', 'Mozambique', 'Myanmar', 'Namibia', 'Nauru', 'Nepal', 'Netherlands', 'New Zealand', 'Nicaragua', 'Niger', 'Nigeria', 'North Korea', 'North Macedonia', 'Norway', 'Oman', 'Pakistan', 'Palau', 'Palestine State', 'Panama', 'Papua New Guinea', 'Paraguay', 'Peru', 'Philippines', 'Poland', 'Portugal', 'Qatar', 'Romania', 'Russia', 'Rwanda', 'Saint Kitts and Nevis', 'Saint Lucia', 'Saint Vincent and the Grenadines', 'Samoa', 'San Marino', 'Sao Tome and Principe', 'Saudi Arabia', 'Senegal', 'Serbia', 'Seychelles', 'Sierra Leone', 'Singapore', 'Slovakia', 'Slovenia', 'Solomon Islands', 'Somalia', 'South Africa', 'South Korea', 'South Sudan', 'Spain', 'Sri Lanka', 'Sudan', 'Suriname', 'Sweden', 'Switzerland', 'Syria', 'Tajikistan', 'Tanzania', 'Thailand', 'Timor-Leste', 'Togo', 'Tonga', 'Trinidad and Tobago', 'Tunisia', 'Turkey', 'Turkmenistan', 'Tuvalu', 'Uganda', 'UK', 'Ukraine', 'United Arab Emirates', 'Uruguay', 'USA', 'Uzbekistan', 'Vanuatu', 'Venezuela', 'Vietnam', 'Yemen', 'Zambia', 'Zimbabwe', 'Worldwide'],

  get filteredCountries() {
    if (!this.countryInput) return [];
    const search = this.countryInput.toLowerCase();
    return this.availableCountries.filter(c => 
      c.toLowerCase().includes(search) && !this.shippingCountries.includes(c)
    ).slice(0, 6);
  },
  specifications: config.specifications || [
    { attribute: 'Material', value: 'Tech Fleece (Premium Quality)' },
    { attribute: 'Fit', value: 'USA True-to-Size' },
    { attribute: 'Set Includes', value: 'Hoodie + Jogger Pants' },
    { attribute: 'Gender', value: 'Unisex (Boys & Girls)' },
    { attribute: 'Season', value: 'All Season' },
    { attribute: 'MOQ', value: '40 Set' },
    { attribute: 'Shipping', value: 'USA & Canada (6-10 Working Days)' },
  ],
  basePrice: config.basePrice || 0,
  productSlug: config.productSlug || 'PROD',
  customSize: '',
  previewUrl: config.previewUrl || null,
  dirty: false,
  loadingVariants: false,

  // Track which color index we're picking media for (null = general gallery)
  _colorPickerIndex: null,

  // Track which color's image management modal is open
  editingColorIndex: null,

  // Gallery Preview & Color Picker State
  selectedGalleryPreviewId: null,
  galleryColorPickerEnabled: false,
  colorModalEyedropperEnabled: false,
  activeEyedropperRow: null,
  activeInspectorImage: null,
  extractedPalette: [],
  paletteLoading: false,
  autoCopyOnHover: false,
  inspectAnyImageMode: false,

  init() {
    'use strict';
    window.addEventListener('beforeunload', (event) => {
      if (!this.dirty) return;
      event.preventDefault();
      event.returnValue = '';
    });

    // Listen for media-picker:selected events from the bridge element
    const bridge = document.getElementById('commerceMediaPickerBridge');
    if (bridge) {
      bridge.addEventListener('media-picker:selected', (e) => {
        const media = e.detail?.media;
        if (!media) return;
        const items = Array.isArray(media) ? media : [media];

        if (this._colorPickerIndex !== null && this._colorPickerIndex !== undefined) {
          this.addMediaToColor(this._colorPickerIndex, items);
          this._colorPickerIndex = null;
        } else {
          this.addMedia(items);
        }
      });
    }

    // Set initial inspector image from gallery if available
    if (this.gallery.length > 0) {
      const firstImg = this.gallery.find((g) => g.type === 'image') || this.gallery[0];
      if (firstImg && firstImg.url) {
        this.selectInspectorImage(firstImg.url);
      }
    }

    // Listen for custom color-picked event (from loupe or screen eyedropper)
    window.addEventListener('color-picked', (e) => {
      const { hex, name } = e.detail || {};
      if (hex) {
        this.applyPickedColor(hex, name);
      }
      // Automatically disable color selection
      this.galleryColorPickerEnabled = false;
      this.colorModalEyedropperEnabled = false;
      this.activeEyedropperRow = null;
      if (window.ImageColorPicker?.loupe) {
        window.ImageColorPicker.loupe.hide();
      }
      // Automatically close all modals
      this.editingColorIndex = null;
      if (typeof window.closeAllModals === 'function') {
        window.closeAllModals();
      }
    });
  },

  openMediaPicker() {
    'use strict';
    this._colorPickerIndex = null;
    const trigger = document.querySelector('#commerceMediaPickerBridge [data-media-picker-trigger]');
    if (trigger) {
      trigger.click();
    }
  },

  openMediaPickerForColor(colorIndex) {
    'use strict';
    this._colorPickerIndex = colorIndex;
    const trigger = document.querySelector('#commerceMediaPickerBridge [data-media-picker-trigger]');
    if (trigger) {
      trigger.click();
    }
  },

  getColorSwatchUrl(col) {
    'use strict';
    if (col.swatch_image_url) return col.swatch_image_url;
    if (col.swatch_media_id) {
      const match = this.gallery.find((g) => String(g.id) === String(col.swatch_media_id));
      if (match) return match.url;
    }
    return null;
  },


  addTierPrice() {
    this.tierPrices.push({ min_quantity: '', max_quantity: '', unit_price: '', discount_percentage: '' });
    this.dirty = true;
  },

  removeTierPrice(index) {
    this.tierPrices.splice(index, 1);
    this.dirty = true;
  },

  addFeatureHighlight() {
    this.featureHighlights.push({ label: 'New Feature', icon: 'ph-check-circle' });
    this.dirty = true;
  },

  removeFeatureHighlight(index) {
    this.featureHighlights.splice(index, 1);
    this.dirty = true;
  },

  addShippingCountry(name) {
    const c = (typeof name === 'string' ? name : this.countryInput).trim();
    if (!c) return;
    
    // Check if what they typed exactly matches an available country (case-insensitive)
    const exactMatch = this.availableCountries.find(ac => ac.toLowerCase() === c.toLowerCase());
    
    if (exactMatch && !this.shippingCountries.includes(exactMatch)) {
      this.shippingCountries.push(exactMatch);
      this.dirty = true;
    }
    
    this.countryInput = '';
    this.showCountryDropdown = false;
  },

  removeShippingCountry(index) {
    this.shippingCountries.splice(index, 1);
    this.dirty = true;
  },

  addSpecification() {
    this.specifications.push({ attribute: '', value: '' });
    this.dirty = true;
  },

  removeSpecification(index) {
    this.specifications.splice(index, 1);
    this.dirty = true;
  },

  addMedia(items, defaultColorId = null) {
    if (!items || !items.length) return;

    items.forEach((item) => {
      const cId = defaultColorId !== null && defaultColorId !== undefined ? defaultColorId : (item.color_id || null);
      
      // Check if this exact combination of media + color already exists
      const exists = this.gallery.some(g => String(g.id) === String(item.id) && String(g.color_id || '') === String(cId || ''));
      if (exists) {
        return;
      }

      // If assigning to a color, and this item is already in gallery unassigned, assign it
      if (cId) {
        const generalItem = this.gallery.find(g => String(g.id) === String(item.id) && !g.color_id);
        if (generalItem) {
          generalItem.color_id = cId;
          return;
        }
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
        color_id: cId,
        is_primary: !isVideo && !this.gallery.some((entry) => entry.is_primary),
      });
    });
    this.dirty = true;
  },

  addMediaToColor(colorIndex, items) {
    if (!items || !items.length || !this.colors[colorIndex]) return;
    const colorObj = this.colors[colorIndex];
    const colorIdentifier = colorObj.id ? String(colorObj.id) : `idx_${colorIndex}`;
    const colorHex = colorObj.hex_code || '';
    const colorName = colorObj.name || colorHex;

    this.addMedia(items, colorIdentifier);

    // Set first image as swatch hero if not already assigned
    if (!colorObj.swatch_media_id && items[0]) {
      colorObj.swatch_media_id = items[0].id;
    }

    // Auto-update variants matching this color
    this.variants.forEach((v) => {
      if (
        (v.color_id && String(v.color_id) === String(colorObj.id)) ||
        (v.attributes?.color && String(v.attributes.color).toLowerCase() === String(colorHex).toLowerCase()) ||
        (v.attributes?.color && colorName && String(v.attributes.color).toLowerCase() === String(colorName).toLowerCase())
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
    const colorHex = colorObj.hex_code || '';
    const colorName = colorObj.name || colorHex;

    // Auto-update variants matching this color
    this.variants.forEach((v) => {
      if (
        (v.color_id && String(v.color_id) === String(colorObj.id)) ||
        (v.attributes?.color && String(v.attributes.color).toLowerCase() === String(colorHex).toLowerCase()) ||
        (v.attributes?.color && colorName && String(v.attributes.color).toLowerCase() === String(colorName).toLowerCase())
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

  removeMediaFromColor(mediaId, colorIndex) {
    const colorObj = this.colors[colorIndex];
    if (!colorObj) return;
    const colorIdentifier = colorObj.id ? String(colorObj.id) : `idx_${colorIndex}`;

    const idx = this.gallery.findIndex((item) => String(item.id) === String(mediaId) && String(item.color_id || '') === String(colorIdentifier));
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
    if (this.activeEyedropperRow === index) {
      this.cancelRowEyedropper();
    }
    this.colors.splice(index, 1);
    this.dirty = true;
  },

  getSelectedGalleryImage() {
    if (this.selectedGalleryPreviewId) {
      const found = this.gallery.find((g) => String(g.id) === String(this.selectedGalleryPreviewId));
      if (found) return found;
    }
    return this.gallery.find((g) => g.is_primary) || this.gallery[0] || null;
  },

  toggleGalleryColorPicker() {
    this.galleryColorPickerEnabled = !this.galleryColorPickerEnabled;
    if (!this.galleryColorPickerEnabled && window.ImageColorPicker?.loupe) {
      window.ImageColorPicker.loupe.hide();
    }
    if (window.showToast) {
      window.showToast(
        this.galleryColorPickerEnabled ? 'Color Picker Enabled' : 'Color Picker Disabled',
        this.galleryColorPickerEnabled ? 'Hover over any image and click to copy its color code!' : 'Color picker disabled.',
        this.galleryColorPickerEnabled ? 'success' : 'info'
      );
    }
  },

  startRowEyedropper(index) {
    if (this.activeEyedropperRow === index) {
      this.cancelRowEyedropper();
      return;
    }
    this.activeEyedropperRow = index;
    // Activate global image color inspection so clicking any image applies to this row
    if (window.ImageColorPicker) {
      window.ImageColorPicker.setGlobalInspectMode(true);
    }
    if (window.showToast) {
      const rowName = this.colors[index]?.name || `Color #${index + 1}`;
      window.showToast('Pick Color for ' + rowName, 'Hover over any image below or on this page and click to select the color!', 'info');
    }
  },

  cancelRowEyedropper() {
    this.activeEyedropperRow = null;
    if (!this.inspectAnyImageMode && window.ImageColorPicker) {
      window.ImageColorPicker.setGlobalInspectMode(false);
    }
  },

  applyPickedColor(hex, name = '') {
    if (this.activeEyedropperRow !== null && this.colors[this.activeEyedropperRow]) {
      const col = this.colors[this.activeEyedropperRow];
      col.hex_code = hex;
      if (!col.name || col.name.trim() === '' || col.name.startsWith('Color #') || col.name === 'Light Blue' || col.name === 'New Color') {
        col.name = name;
      }
      this.dirty = true;
      if (window.showToast) {
        window.showToast('Color Assigned', `Updated ${col.name || 'row'} to ${hex}!`, 'success');
      }
      this.cancelRowEyedropper();
    } else if (this.editingColorIndex !== null && this.colors[this.editingColorIndex]) {
      const col = this.colors[this.editingColorIndex];
      col.hex_code = hex;
      if (!col.name || col.name.trim() === '' || col.name.startsWith('Color #') || col.name === 'Light Blue' || col.name === 'New Color') {
        col.name = name;
      }
      this.dirty = true;
    }
  },

  async selectInspectorImage(url) {
    this.activeInspectorImage = url;
    this.paletteLoading = true;
    this.extractedPalette = [];

    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.src = url;

    try {
      if (window.ImageColorPicker?.extractImagePalette) {
        const palette = await window.ImageColorPicker.extractImagePalette(img, 6);
        this.extractedPalette = palette;
      }
    } catch (e) {
      console.warn('Failed to extract palette:', e);
    } finally {
      this.paletteLoading = false;
    }
  },

  toggleAutoCopyOnHover() {
    this.autoCopyOnHover = !this.autoCopyOnHover;
    window.__autoCopyColorOnHover = this.autoCopyOnHover;
    if (window.showToast) {
      window.showToast(
        this.autoCopyOnHover ? 'Auto-Copy on Hover: ON' : 'Auto-Copy on Hover: OFF',
        this.autoCopyOnHover ? 'Hovering on any image will automatically copy the color code (no need to click)!' : 'Now click to copy color codes.',
        'info'
      );
    }
  },

  toggleInspectAnyImage() {
    this.inspectAnyImageMode = !this.inspectAnyImageMode;
    if (window.ImageColorPicker) {
      window.ImageColorPicker.setGlobalInspectMode(this.inspectAnyImageMode);
    }
  },

  openScreenEyeDropper(rowIndex = null) {
    if (rowIndex !== null) {
      this.activeEyedropperRow = rowIndex;
    }
    if (window.ImageColorPicker?.openNativeEyeDropper) {
      window.ImageColorPicker.openNativeEyeDropper().then((res) => {
        if (res && res.hex) {
          this.applyPickedColor(res.hex, res.name);
        }
      });
    }
  },

  addColorFromExtracted(hex, name = '') {
    this.colors.push({
      id: '',
      name: name || 'Extracted Shade',
      hex_code: hex,
      color_family: '',
      swatch_media_id: ''
    });
    this.dirty = true;
    if (window.showToast) {
      window.showToast('Color Added', `Added ${name || hex} to your product colors!`, 'success');
    }
  },

  openSizeModal() {
    this.modalSelectedPresets = [];
    this.modalNewSize = { name: '', weight: '', weight_unit: 'kg' };
    this.modalError = '';
    this.showSizeModal = true;
  },

  togglePresetSelection(preset) {
    const index = this.modalSelectedPresets.findIndex(p => 
      (p.id != null && preset.id != null) ? p.id === preset.id : p.name === preset.name
    );
    if (index > -1) {
      this.modalSelectedPresets.splice(index, 1);
    } else {
      this.modalSelectedPresets.push(preset);
    }
  },

  isPresetSelected(preset) {
    return this.modalSelectedPresets.some(p => 
      (p.id != null && preset.id != null) ? p.id === preset.id : p.name === preset.name
    );
  },

  applyModalSelection() {
    this.modalSelectedPresets.forEach(preset => {
      let values = preset.values;
      if (typeof values === 'string') {
        try { values = JSON.parse(values); } catch (e) { values = null; }
      }
      const valuesToAdd = (Array.isArray(values) && values.length > 1)
        ? values
        : [preset.name];

      valuesToAdd.forEach(val => {
        if (val && !this.sizes.some(s => s.value.toLowerCase() === String(val).toLowerCase())) {
          this.sizes.push({
            value: String(val),
            weight: preset.weight != null && preset.weight !== '' ? parseFloat(preset.weight) : null,
            weight_unit: preset.weight_unit || 'kg'
          });
        }
      });
    });
    this.showSizeModal = false;
    this.dirty = true;
  },

  async createPresetFromModal() {
    if (!this.modalNewSize.name.trim()) {
      this.modalError = 'Size name is required.';
      return;
    }
    
    this.modalSaving = true;
    this.modalError = '';
    
    try {
      const response = await window.axios.post('/user/commerce/variant-presets', {
        name: this.modalNewSize.name,
        type: 'size',
        weight: this.modalNewSize.weight ? parseFloat(this.modalNewSize.weight) : null,
        weight_unit: this.modalNewSize.weight_unit
      }, {
        headers: { Accept: 'application/json' }
      });
      
      const newPreset = response.data.preset;
      
      // Add to presets list
      this.variantPresets.push(newPreset);
      
      // Auto-select it
      if (!this.isPresetSelected(newPreset)) {
        this.modalSelectedPresets.push(newPreset);
      }
      
      // Reset form
      this.modalNewSize = { name: '', weight: '', weight_unit: 'kg' };
      
    } catch (err) {
      this.modalError = err.response?.data?.message || 'Failed to create preset. Check name uniqueness.';
    } finally {
      this.modalSaving = false;
    }
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

  addVariant(colorObj = null) {
    const defaultOption = this.variantPresets[0] ? (this.variantPresets[0].sku_suffix || this.variantPresets[0].name) : (this.sizes[0]?.value || 'M');
    const colorVal = colorObj ? (colorObj.hex_code || colorObj.name || '') : '';
    const colorId = colorObj?.id || null;
    const mediaId = colorObj?.swatch_media_id || null;
    const price = (parseFloat(this.basePrice) || 0) + (this.variantPresets[0] ? parseFloat(this.variantPresets[0].price_delta || 0) : 0);
    const sku = `${this.productSlug ? this.productSlug.toUpperCase() : 'PROD'}-${defaultOption}`;

    this.variants.push({
      id: null,
      color_id: colorId,
      size: defaultOption,
      media_id: mediaId,
      sku: sku,
      stock_quantity: 0,
      price: price > 0 ? price.toFixed(2) : '0.00',
      status: 'active',
      attributes: {
        ...(colorVal ? { color: colorVal } : {}),
        size: defaultOption,
      },
    });
    this.dirty = true;
  },

  removeVariant(index) {
    this.variants.splice(index, 1);
    this.dirty = true;
  },

  onVariantOptionSelect(variantIndex, selectedVal) {
    const variant = this.variants[variantIndex];
    if (!variant) return;

    variant.size = selectedVal;
    if (!variant.attributes) {
      variant.attributes = {};
    }
    variant.attributes.size = selectedVal;

    // Find preset to calculate price and SKU suffix
    const preset = this.variantPresets.find((p) => (p.sku_suffix && p.sku_suffix.toLowerCase() === selectedVal.toLowerCase()) || p.name.toLowerCase() === selectedVal.toLowerCase());
    const suffix = preset?.sku_suffix || selectedVal;

    // Update SKU if matching pattern
    const baseSkuPrefix = this.productSlug ? this.productSlug.toUpperCase() : 'PROD';
    variant.sku = `${baseSkuPrefix}-${suffix}`;

    // Apply price delta if not manually customized
    if (preset && parseFloat(preset.price_delta) !== 0) {
      const calculated = (parseFloat(this.basePrice) || 0) + parseFloat(preset.price_delta || 0);
      if (calculated > 0) {
        variant.price = calculated.toFixed(2);
      }
    }

    // Apply weight from preset
    if (preset && preset.weight !== null && preset.weight !== undefined) {
      let weightInKg = parseFloat(preset.weight);
      const unit = (preset.weight_unit || 'kg').toLowerCase();
      
      if (unit === 'g') {
        weightInKg = weightInKg / 1000;
      } else if (unit === 'lb') {
        weightInKg = weightInKg * 0.453592;
      } else if (unit === 'oz') {
        weightInKg = weightInKg * 0.0283495;
      }
      
      variant.weight_kg = weightInKg.toFixed(3);
    }

    this.dirty = true;
  },

  markSaved() {
    this.dirty = false;
  },
}));
