import Alpine from 'alpinejs';
import axios from 'axios';

const STEPS = ['source', 'template', 'posts', 'style'];

Alpine.data('socialWidgetEditor', () => ({
  name: '',
  layout: 'default',
  settings: {},
  feed: { profile: {}, posts: [] },
  themes: {},
  urls: {},
  step: 0,
  visitedSteps: [0],
  device: 'desktop',
  openSection: null,
  saveState: 'saved',
  sourceUsername: '',
  sourceMessage: '',
  isSearching: false,
  isPublished: false,
  isEmbedOpen: false,
  embedCode: '',
  hasCopied: false,
  saveTimer: null,

  init() {
    const config = JSON.parse(this.$el.dataset.config || '{}');
    this.name = config.name;
    this.layout = config.layout;
    this.settings = config.settings;
    this.feed = config.feed;
    this.themes = config.themes;
    this.urls = config.urls;
    this.isPublished = config.isPublished;
    this.embedCode = config.embedCode;
    this.sourceUsername = this.settings.source.username || '';
    this.openSection = this.defaultSectionFor(0);
    this.$root.querySelectorAll('[data-swatch]').forEach((swatch) => {
      swatch.style.setProperty('background-color', swatch.dataset.swatch);
    });

    this.$watch('settings', () => this.changed(), { deep: true });
    this.$watch('layout', () => this.changed());
    this.$watch('name', () => this.changed());
    this.$watch('device', () => this.renderPreview());

    if (window.SocialFeedRenderer) {
      this.$nextTick(() => this.renderPreview());
    } else {
      window.addEventListener('social-feed-renderer:ready', () => this.renderPreview(), { once: true });
    }
  },

  get steps() {
    return STEPS;
  },

  get isLastStep() {
    return this.step === STEPS.length - 1;
  },

  defaultSectionFor(step) {
    return { 0: 'source', 1: 'layout', 2: 'post-elements', 3: 'theme' }[step];
  },

  goToStep(step) {
    this.step = step;
    if (!this.visitedSteps.includes(step)) this.visitedSteps.push(step);
    this.openSection = this.defaultSectionFor(step);
  },

  isStepDone(step) {
    return this.visitedSteps.includes(step) && step !== this.step;
  },

  nextStep() {
    if (this.isLastStep) {
      this.publish();
      return;
    }
    this.goToStep(this.step + 1);
  },

  previousStep() {
    if (this.step > 0) this.goToStep(this.step - 1);
  },

  toggleSection(section) {
    this.openSection = this.openSection === section ? null : section;
  },

  applyTheme(theme) {
    this.settings.style.theme = theme;
    this.settings.style.colors = { ...this.themes[theme] };
  },

  changed() {
    this.renderPreview();
    this.saveState = 'pending';
    clearTimeout(this.saveTimer);
    this.saveTimer = setTimeout(() => this.save(), 600);
  },

  renderPreview() {
    if (!window.SocialFeedRenderer || !this.$refs.preview) return;
    window.SocialFeedRenderer.renderFeed(
      this.$refs.preview,
      { layout: this.layout, settings: JSON.parse(JSON.stringify(this.settings)), profile: this.feed.profile, posts: this.feed.posts },
      { stylesUrl: this.urls.styles, device: this.device },
    );
  },

  async save() {
    this.saveState = 'saving';
    try {
      await axios.put(this.urls.update, { name: this.name, layout: this.layout, settings: this.settings });
      this.saveState = 'saved';
    } catch (error) {
      this.saveState = 'error';
      window.showToast?.('Not saved', error.response?.data?.message || 'Could not save the widget.', 'error');
    }
  },

  async searchSource() {
    this.isSearching = true;
    this.sourceMessage = '';
    try {
      const { data } = await axios.post(this.urls.source, { username: this.sourceUsername });
      this.feed = data.feed;
      this.settings.source.username = this.sourceUsername.replace(/^@/, '') || null;
      this.sourceMessage = data.message || '';
      this.renderPreview();
    } catch (error) {
      this.sourceMessage = error.response?.data?.message || 'Could not load that account.';
    } finally {
      this.isSearching = false;
    }
  },

  async publish() {
    clearTimeout(this.saveTimer);
    await this.save();
    if (this.saveState !== 'saved') return;
    const { data } = await axios.post(this.urls.publish);
    this.isPublished = true;
    this.embedCode = data.embed_code;
    this.isEmbedOpen = true;
  },

  async copyEmbed() {
    await navigator.clipboard.writeText(this.embedCode);
    this.hasCopied = true;
    setTimeout(() => {
      this.hasCopied = false;
    }, 2000);
  },
}));
