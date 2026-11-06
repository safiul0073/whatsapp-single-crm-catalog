'use strict';

import Alpine from 'alpinejs';

// "Create ad" page: switches between promoting a product, boosting a Page post and running a
// creator's post as a partnership ad, and loads Page posts / creator posts from Meta on demand.
Alpine.data('metaAdBuilder', (config) => ({
  kind: config.kind || 'product',
  pageId: config.pageId || '',
  instagramId: config.instagramId || '',
  postId: config.postId || '',
  contentId: config.contentId || '',
  adCode: '',
  platform: 'INSTAGRAM',
  search: '',
  creatorUsername: '',
  posts: [],
  content: [],
  loading: false,
  error: '',
  notice: '',

  async loadPosts() {
    if (!this.pageId) {
      this.error = config.choosePageLabel;
      return;
    }

    await this.fetchList(config.postsUrl, { page_id: this.pageId }, (data) => {
      this.posts = data.posts || [];
    });
  },

  async loadContent() {
    if (!this.pageId) {
      this.error = config.choosePageLabel;
      return;
    }

    await this.fetchList(config.contentUrl, {
      page_id: this.pageId,
      instagram_account_id: this.instagramId,
      search: this.search,
    }, (data) => {
      this.content = data.content || [];
    });
  },

  pickContent(item) {
    this.contentId = item.content_id;
    this.adCode = item.ad_code || '';
    this.platform = item.platform || 'INSTAGRAM';
  },

  async askCreator() {
    this.error = '';
    this.notice = '';

    if (!this.instagramId || !this.creatorUsername) {
      this.error = config.creatorLabel;
      return;
    }

    try {
      const response = await window.axios.post(config.permissionUrl, {
        instagram_account_id: this.instagramId,
        creator_username: this.creatorUsername,
      }, { headers: { Accept: 'application/json' } });
      this.notice = response.data.message || '';
      this.creatorUsername = '';
    } catch (error) {
      this.error = this.messageFrom(error);
    }
  },

  async fetchList(url, params, apply) {
    this.loading = true;
    this.error = '';

    try {
      const response = await window.axios.get(url, { params, headers: { Accept: 'application/json' } });
      apply(response.data);
    } catch (error) {
      this.error = this.messageFrom(error);
    } finally {
      this.loading = false;
    }
  },

  messageFrom(error) {
    const data = error?.response?.data || {};
    const first = data.errors ? Object.values(data.errors)[0] : null;

    return (Array.isArray(first) ? first[0] : first) || data.message || config.failedLabel;
  },
}));
