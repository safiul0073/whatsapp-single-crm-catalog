/**
 * Public embed bootstrap, appended after feed-renderer.js by the loader route.
 * Endpoints are derived from this script's own URL so the snippet needs no configuration.
 */
(function bootSocialFeedEmbed(global) {
  'use strict';

  const script = document.currentScript;
  if (!script || !global.SocialFeedRenderer) return;

  const loaderUrl = new URL(script.src);
  const configUrl = loaderUrl.href.replace(/\/loader\.js(\?.*)?$/, '');
  const token = configUrl.split('/').pop();
  const stylesUrl = loaderUrl.origin + '/widgets/feed/styles.css';

  function mount() {
    const hosts = document.querySelectorAll('[data-social-feed="' + token + '"]');
    if (!hosts.length) return;

    fetch(configUrl, { headers: { Accept: 'application/json' } })
      .then((response) => (response.ok ? response.json() : Promise.reject(response.status)))
      .then((payload) => {
        const data = payload.data || payload;
        hosts.forEach((host) => global.SocialFeedRenderer.renderFeed(host, data, { stylesUrl }));
      })
      .catch(() => {});
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
  } else {
    mount();
  }
})(window);
