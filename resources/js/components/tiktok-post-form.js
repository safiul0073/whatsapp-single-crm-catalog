'use strict';

import Alpine from 'alpinejs';

// "Post to TikTok" form. Follows TikTok's Direct Post rules: no default privacy, interactions off by
// default, commercial content disclosure with its labels, a video preview and a duration check.
Alpine.data('tiktokPostForm', (config) => ({
  mode: config.mode || 'draft',
  privacy: '',
  disclose: false,
  brandOrganic: false,
  brandContent: false,
  previewUrl: '',
  durationError: '',

  pickVideo(event) {
    const file = event.target.files?.[0];
    this.durationError = '';

    if (this.previewUrl) {
      URL.revokeObjectURL(this.previewUrl);
    }

    this.previewUrl = file ? URL.createObjectURL(file) : '';
  },

  checkDuration(event) {
    const seconds = Math.floor(event.target.duration || 0);
    const max = Number(config.maxDuration || 0);
    this.durationError = this.mode === 'direct' && max > 0 && seconds > max ? config.durationLabel.replace(':max', max) : '';
  },

  get disclosureLabel() {
    if (!this.disclose) {
      return '';
    }

    if (this.brandContent) {
      return config.paidPartnershipLabel;
    }

    return this.brandOrganic ? config.promotionalLabel : '';
  },

  get disclosureMissing() {
    return this.mode === 'direct' && this.disclose && !this.brandOrganic && !this.brandContent;
  },

  get canSubmit() {
    if (!this.previewUrl || this.durationError) {
      return false;
    }

    return this.mode === 'draft' || (this.privacy !== '' && !this.disclosureMissing);
  },

  choosePrivacy(value) {
    if (value === 'SELF_ONLY' && this.brandContent) {
      return;
    }

    this.privacy = value;
  },
}));
