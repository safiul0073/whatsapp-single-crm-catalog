'use strict';

import Alpine from 'alpinejs';

// Facebook Page / Instagram post composer: product photos, AI caption, AI image and a live preview.
Alpine.data('metaPostComposer', () => ({
  instagram: false,
  schedule: false,
  pageName: '',
  productId: '',
  product: null,
  loadingProduct: false,
  message: '',
  link: '',
  mediaId: '',
  imageUrl: '',
  tab: 'product',
  tone: 'friendly',
  language: 'en',
  notes: '',
  emojis: true,
  hashtags: true,
  captionLoading: false,
  captionError: '',
  imagePrompt: '',
  imageStyle: 'studio',
  imageLoading: false,
  imageError: '',
  aiImages: [],
  config: {},

  init() {
    const data = this.$el.dataset;
    this.config = { ...data };
    this.instagram = data.instagram === '1';
    this.schedule = data.schedule === '1';
    this.productId = data.productId || '';
    this.mediaId = data.mediaId || '';
    this.imageUrl = data.imagePreview || '';
    this.message = this.$el.querySelector('#message')?.value || '';
    this.link = this.$el.querySelector('#link')?.value || '';
    this.pageName = this.$refs.page?.selectedOptions[0]?.text || '';

    if (this.productId) {
      this.$nextTick(() => {
        const select = this.$el.querySelector('#product_id');
        if (select) {
          select.value = this.productId;
        }
        this.loadProduct(true);
      });
    }
  },

  async request(url, options = {}) {
    const response = await fetch(url, {
      ...options,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });
    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
      const firstError = payload.errors ? Object.values(payload.errors)[0]?.[0] : null;
      throw new Error(firstError || payload.message || this.config.errorLabel);
    }

    return payload;
  },

  async loadProduct(keepFields = false) {
    this.product = null;

    if (!this.productId) {
      return;
    }

    this.loadingProduct = true;

    try {
      this.product = await this.request(this.config.assetsUrl.replace('__ID__', this.productId));

      if (!keepFields || !this.link) {
        this.link = this.product.url;
      }

      if (!this.imagePrompt) {
        this.imagePrompt = this.product.name + (this.product.short_description ? ' - ' + this.product.short_description : '');
      }

      if (this.product.images.length && (!keepFields || !this.imageUrl)) {
        this.pickImage(this.product.images[0].media_id, this.product.images[0].url);
      }

      this.tab = 'product';
    } catch (error) {
      this.captionError = error.message;
    } finally {
      this.loadingProduct = false;
    }
  },

  pickImage(id, url) {
    this.mediaId = id ? String(id) : '';
    this.imageUrl = url || '';
  },

  async generateCaption() {
    this.captionLoading = true;
    this.captionError = '';

    try {
      const payload = await this.request(this.config.captionUrl, {
        method: 'POST',
        body: JSON.stringify({
          product_id: this.productId || null,
          tone: this.tone,
          language: this.language,
          emojis: this.emojis,
          hashtags: this.hashtags,
          notes: this.notes || null,
        }),
      });
      this.message = payload.message;
    } catch (error) {
      this.captionError = error.message;
    } finally {
      this.captionLoading = false;
    }
  },

  async generateImage() {
    this.imageLoading = true;
    this.imageError = '';

    try {
      const payload = await this.request(this.config.imageUrl, {
        method: 'POST',
        body: JSON.stringify({ prompt: this.imagePrompt, style: this.imageStyle, product_id: this.productId || null }),
      });
      this.aiImages.unshift(payload);
      this.pickImage(payload.media_id, payload.url);
    } catch (error) {
      this.imageError = error.message;
    } finally {
      this.imageLoading = false;
    }
  },
}));
