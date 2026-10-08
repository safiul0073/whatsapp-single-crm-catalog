/**
 * Social feed renderer shared by the editor preview and the public embed loader.
 * Import-free on purpose: the loader route concatenates this file into a classic script.
 */
(function registerSocialFeedRenderer(global) {
  'use strict';

  const FONT_STACKS = {
    sans: 'Inter, system-ui, -apple-system, "Segoe UI", sans-serif',
    serif: 'Georgia, "Times New Roman", serif',
    mono: '"JetBrains Mono", ui-monospace, SFMono-Regular, Menlo, monospace',
  };

  const COLOR_VARIABLES = {
    canvasBackground: '--swf-canvas-bg',
    headerBackground: '--swf-header-bg',
    headerText: '--swf-header-text',
    headerButton: '--swf-header-button',
    postBackground: '--swf-post-bg',
    postText: '--swf-post-text',
    postBorder: '--swf-post-border',
    popupBackground: '--swf-popup-bg',
    popupText: '--swf-popup-text',
    loadMoreBackground: '--swf-more-bg',
    loadMoreText: '--swf-more-text',
    loadMoreBorder: '--swf-more-border',
  };

  const ICONS = {
    heart: 'M12 21s-7.5-4.6-9.6-9.2C.9 8.4 2.7 4.5 6.4 4.1c2-.2 3.9.8 5.6 2.9 1.7-2.1 3.6-3.1 5.6-2.9 3.7.4 5.5 4.3 4 7.7C19.5 16.4 12 21 12 21z',
    comment: 'M20 12a8 8 0 0 1-11.6 7.1L4 20l1-4.1A8 8 0 1 1 20 12z',
    play: 'M8 5v14l11-7z',
    verified: 'M12 2l2.4 2.1 3.2-.3.8 3.1 2.8 1.6-1.2 3 1.2 3-2.8 1.6-.8 3.1-3.2-.3L12 22l-2.4-2.1-3.2.3-.8-3.1-2.8-1.6 1.2-3-1.2-3 2.8-1.6.8-3.1 3.2.3z',
    close: 'M6 6l12 12M18 6L6 18',
    prev: 'M15 5l-7 7 7 7',
    next: 'M9 5l7 7-7 7',
    share: 'M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7M12 3v13M7 8l5-5 5 5',
  };

  function element(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = String(text);
    return node;
  }

  function icon(name, className) {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('class', 'swf-icon ' + (className || ''));
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', ICONS[name]);
    svg.appendChild(path);
    return svg;
  }

  function compactNumber(value) {
    const number = Number(value) || 0;
    if (number >= 1000000) return (number / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
    if (number >= 1000) return (number / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
    return String(number);
  }

  function timeAgo(timestamp) {
    if (!timestamp) return '';
    const days = Math.max(1, Math.round((Date.now() - new Date(timestamp).getTime()) / 86400000));
    if (days < 7) return days + 'D AGO';
    if (days < 60) return Math.round(days / 7) + 'W AGO';
    return Math.round(days / 30) + 'MO AGO';
  }

  function safeUrl(url) {
    return typeof url === 'string' && /^https?:\/\//i.test(url) ? url : '';
  }

  function image(url, alt, className) {
    const img = element('img', className);
    const source = safeUrl(url);
    if (source) img.src = source;
    img.alt = alt || '';
    img.loading = 'lazy';
    img.decoding = 'async';
    return img;
  }

  function stats(post, settings, className) {
    const row = element('div', className || 'swf-stats');
    if (settings.post.likes) {
      const likes = element('span', 'swf-stat');
      likes.append(icon('heart'), element('span', null, compactNumber(post.likes)));
      row.appendChild(likes);
    }
    if (settings.post.comments) {
      const comments = element('span', 'swf-stat');
      comments.append(icon('comment'), element('span', null, compactNumber(post.comments)));
      row.appendChild(comments);
    }
    return row;
  }

  function profileUrl(profile) {
    return profile.username ? 'https://www.instagram.com/' + encodeURIComponent(profile.username) + '/' : 'https://www.instagram.com/';
  }

  function followButton(profile, className) {
    const link = element('a', className || 'swf-follow', 'Follow');
    link.href = profileUrl(profile);
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    return link;
  }

  function avatar(profile, className) {
    const ring = element('span', className || 'swf-avatar');
    ring.appendChild(image(profile.avatar, profile.username));
    return ring;
  }

  function renderHeader(profile, settings, layout) {
    const elements = settings.header.elements;
    const header = element('header', 'swf-header' + (layout === 'tiktok' ? ' swf-header--tiktok' : ''));

    if (elements.profilePicture) header.appendChild(avatar(profile));

    const body = element('div', 'swf-header-body');
    const titleRow = element('div', 'swf-header-title');
    if (elements.username) titleRow.appendChild(element('strong', 'swf-username', '@' + profile.username));
    if (elements.verifiedBadge && profile.verified) titleRow.appendChild(icon('verified', 'swf-verified'));
    if (elements.followButton && layout !== 'tiktok') titleRow.appendChild(followButton(profile));
    body.appendChild(titleRow);

    if (elements.fullName && profile.name) body.appendChild(element('p', 'swf-fullname', profile.name));

    if (elements.stats) {
      const statsRow = element('p', 'swf-header-stats');
      const counts = layout === 'tiktok'
        ? [[profile.following, 'Following'], [profile.followers, 'Followers'], [profile.posts, 'Posts']]
        : [[profile.followers, 'followers'], [profile.following, 'following']];
      counts.forEach(([value, label]) => {
        const item = element('span', 'swf-header-stat');
        item.append(element('strong', null, compactNumber(value)), document.createTextNode(' ' + label));
        statsRow.appendChild(item);
      });
      body.appendChild(statsRow);
    }

    header.appendChild(body);
    if (elements.followButton && layout === 'tiktok') header.appendChild(followButton(profile, 'swf-follow swf-follow--wide'));

    return header;
  }

  function mediaBadge(post) {
    if (post.type !== 'video') return null;
    const badge = element('span', 'swf-badge');
    badge.appendChild(icon('play'));
    return badge;
  }

  function hoverOverlay(post, settings) {
    const overlay = element('span', 'swf-overlay');
    overlay.appendChild(stats(post, settings, 'swf-overlay-stats'));
    return overlay;
  }

  function tile(post, settings, context, variant) {
    const item = element('button', 'swf-tile' + (variant ? ' swf-tile--' + variant : ''));
    item.type = 'button';
    item.setAttribute('aria-label', post.caption ? post.caption.slice(0, 80) : 'Open post');
    const media = element('span', 'swf-media');
    media.appendChild(image(post.image, post.caption));
    const badge = mediaBadge(post);
    if (badge) media.appendChild(badge);
    media.appendChild(hoverOverlay(post, settings));
    item.appendChild(media);
    item.addEventListener('click', () => context.open(post));
    return item;
  }

  function card(post, settings, context, variant) {
    const item = element('button', 'swf-card' + (variant ? ' swf-card--' + variant : ''));
    item.type = 'button';
    const media = element('span', 'swf-media');
    media.appendChild(image(post.image, post.caption));
    const badge = mediaBadge(post);
    if (badge) media.appendChild(badge);
    item.appendChild(media);
    if (settings.post.likes || settings.post.comments || (settings.post.caption && post.caption)) {
      const body = element('span', 'swf-card-body');
      if (settings.post.likes || settings.post.comments) body.appendChild(stats(post, settings));
      if (settings.post.caption && post.caption) body.appendChild(element('span', 'swf-caption', post.caption));
      item.appendChild(body);
    }
    item.addEventListener('click', () => context.open(post));
    return item;
  }

  const LAYOUTS = {
    default(posts, settings, context) {
      const grid = element('div', 'swf-grid swf-grid--tiles');
      posts.forEach((post) => grid.appendChild(tile(post, settings, context)));
      return grid;
    },
    grid(posts, settings, context) {
      const grid = element('div', 'swf-grid swf-grid--cards');
      posts.forEach((post) => grid.appendChild(card(post, settings, context)));
      return grid;
    },
    slider(posts, settings, context) {
      const wrapper = element('div', 'swf-slider');
      const track = element('div', 'swf-slider-track');
      posts.forEach((post) => track.appendChild(card(post, settings, context, 'slide')));
      const previous = element('button', 'swf-slider-nav swf-slider-nav--prev');
      previous.type = 'button';
      previous.setAttribute('aria-label', 'Previous posts');
      previous.appendChild(icon('prev'));
      const next = element('button', 'swf-slider-nav swf-slider-nav--next');
      next.type = 'button';
      next.setAttribute('aria-label', 'Next posts');
      next.appendChild(icon('next'));
      previous.addEventListener('click', () => track.scrollBy({ left: -track.clientWidth * 0.8, behavior: 'smooth' }));
      next.addEventListener('click', () => track.scrollBy({ left: track.clientWidth * 0.8, behavior: 'smooth' }));
      wrapper.append(track, previous, next);
      return wrapper;
    },
    masonry(posts, settings, context) {
      const masonry = element('div', 'swf-masonry');
      posts.forEach((post, index) => masonry.appendChild(card(post, settings, context, index % 3 === 0 ? 'tall' : index % 3 === 1 ? 'short' : 'square')));
      return masonry;
    },
    highlight(posts, settings, context) {
      const layout = element('div', 'swf-highlight');
      const [featured, ...rest] = posts;
      if (featured) layout.appendChild(tile(featured, settings, context, 'featured'));
      const side = element('div', 'swf-highlight-side');
      rest.slice(0, 4).forEach((post) => side.appendChild(tile(post, settings, context)));
      layout.appendChild(side);
      if (rest.length > 4) {
        const more = element('div', 'swf-grid swf-grid--tiles swf-highlight-more');
        rest.slice(4).forEach((post) => more.appendChild(tile(post, settings, context)));
        layout.appendChild(more);
      }
      return layout;
    },
    bento(posts, settings, context) {
      const bento = element('div', 'swf-bento');
      const pattern = ['big', 'tall', 'square', 'square', 'square', 'square', 'square', 'square'];
      posts.forEach((post, index) => bento.appendChild(tile(post, settings, context, 'bento-' + pattern[index % pattern.length])));
      return bento;
    },
    polaroid(posts, settings, context) {
      const board = element('div', 'swf-polaroid');
      posts.forEach((post, index) => {
        const frame = element('button', 'swf-polaroid-frame swf-tilt-' + (index % 5));
        frame.type = 'button';
        frame.appendChild(image(post.image, post.caption, 'swf-polaroid-photo'));
        if (settings.post.caption && post.caption) frame.appendChild(element('span', 'swf-polaroid-caption', post.caption));
        frame.addEventListener('click', () => context.open(post));
        board.appendChild(frame);
      });
      return board;
    },
    filmstrip(posts, settings, context) {
      const strip = element('div', 'swf-filmstrip');
      const track = element('div', 'swf-filmstrip-track');
      posts.forEach((post) => {
        const frame = element('button', 'swf-filmstrip-frame');
        frame.type = 'button';
        frame.appendChild(image(post.image, post.caption));
        frame.addEventListener('click', () => context.open(post));
        track.appendChild(frame);
      });
      strip.appendChild(track);
      return strip;
    },
    shape(posts, settings, context) {
      const honeycomb = element('div', 'swf-shape');
      posts.forEach((post) => honeycomb.appendChild(tile(post, settings, context, 'hexagon')));
      return honeycomb;
    },
    neon(posts, settings, context) {
      const grid = element('div', 'swf-neon');
      posts.forEach((post) => {
        const item = element('button', 'swf-neon-card');
        item.type = 'button';
        item.appendChild(image(post.image, post.caption));
        if (settings.post.caption && post.caption) item.appendChild(element('span', 'swf-neon-caption', post.caption));
        item.addEventListener('click', () => context.open(post));
        grid.appendChild(item);
      });
      return grid;
    },
    tiktok(posts, settings, context) {
      const grid = element('div', 'swf-tiktok');
      posts.forEach((post) => {
        const item = element('button', 'swf-tiktok-tile');
        item.type = 'button';
        item.appendChild(image(post.image, post.caption));
        const views = element('span', 'swf-tiktok-views');
        views.append(icon('play'), element('span', null, compactNumber((post.likes || 0) * 11)));
        item.appendChild(views);
        item.appendChild(stats(post, settings, 'swf-tiktok-actions'));
        const footer = element('span', 'swf-tiktok-footer');
        footer.appendChild(element('strong', null, '@' + context.profile.username));
        if (settings.post.caption && post.caption) footer.appendChild(element('span', null, post.caption));
        item.appendChild(footer);
        item.addEventListener('click', () => context.open(post));
        grid.appendChild(item);
      });
      return grid;
    },
  };

  function renderPopup(shadow, root, profile, posts, settings, startIndex) {
    const popupSettings = settings.click.popup;
    let index = startIndex;
    const backdrop = element('div', 'swf-popup-backdrop');
    backdrop.setAttribute('role', 'dialog');
    backdrop.setAttribute('aria-modal', 'true');
    const dialog = element('div', 'swf-popup');
    const close = element('button', 'swf-popup-close');
    close.type = 'button';
    close.setAttribute('aria-label', 'Close');
    close.appendChild(icon('close'));

    function closePopup() {
      backdrop.remove();
      document.removeEventListener('keydown', onKeydown);
    }

    function show() {
      const post = posts[index];
      dialog.replaceChildren();
      const media = element('div', 'swf-popup-media');
      if (post.type === 'video' && safeUrl(post.video)) {
        const video = element('video');
        video.src = safeUrl(post.video);
        video.controls = true;
        video.playsInline = true;
        media.appendChild(video);
      } else {
        media.appendChild(image(post.image, post.caption));
      }
      const side = element('div', 'swf-popup-side');
      const top = element('div', 'swf-popup-author');
      top.append(avatar(profile, 'swf-avatar swf-avatar--small'), element('strong', null, profile.username));
      if (popupSettings.followButton) top.appendChild(followButton(profile));
      side.appendChild(top);
      if (popupSettings.header && profile.biography) side.appendChild(element('p', 'swf-popup-bio', profile.biography));
      if (popupSettings.caption && post.caption) side.appendChild(element('p', 'swf-popup-caption', post.caption));
      if (popupSettings.comments) {
        const placeholder = element('div', 'swf-popup-comments');
        for (let line = 0; line < 3; line += 1) placeholder.appendChild(element('span'));
        side.appendChild(placeholder);
      }
      const footer = element('div', 'swf-popup-footer');
      if (popupSettings.counts) footer.appendChild(stats(post, { post: { likes: true, comments: true } }));
      if (popupSettings.shareButton && safeUrl(post.permalink)) {
        const share = element('a', 'swf-popup-share');
        share.href = safeUrl(post.permalink);
        share.target = '_blank';
        share.rel = 'noopener noreferrer';
        share.append(icon('share'), element('span', null, 'Share'));
        footer.appendChild(share);
      }
      footer.appendChild(element('time', 'swf-popup-date', timeAgo(post.timestamp)));
      side.appendChild(footer);
      dialog.append(media, side);

      if (settings.click.swipe && posts.length > 1) {
        const previous = element('button', 'swf-popup-nav swf-popup-nav--prev');
        previous.type = 'button';
        previous.setAttribute('aria-label', 'Previous post');
        previous.appendChild(icon('prev'));
        previous.addEventListener('click', () => step(-1));
        const next = element('button', 'swf-popup-nav swf-popup-nav--next');
        next.type = 'button';
        next.setAttribute('aria-label', 'Next post');
        next.appendChild(icon('next'));
        next.addEventListener('click', () => step(1));
        dialog.append(previous, next);
      }
    }

    function step(direction) {
      index = (index + direction + posts.length) % posts.length;
      show();
    }

    function onKeydown(event) {
      if (event.key === 'Escape') closePopup();
      if (settings.click.swipe && event.key === 'ArrowLeft') step(-1);
      if (settings.click.swipe && event.key === 'ArrowRight') step(1);
    }

    close.addEventListener('click', closePopup);
    backdrop.addEventListener('click', (event) => {
      if (event.target === backdrop) closePopup();
    });
    document.addEventListener('keydown', onKeydown);
    backdrop.append(close, dialog);
    root.appendChild(backdrop);
    show();
  }

  function applyTheme(root, settings) {
    const style = settings.style;
    Object.entries(COLOR_VARIABLES).forEach(([key, variable]) => {
      if (style.colors && style.colors[key]) root.style.setProperty(variable, style.colors[key]);
    });
    root.style.setProperty('--swf-radius', (Number(style.radius) || 0) + 'px');
    root.style.setProperty('--swf-border-width', (Number(style.borderWidth) || 0) + 'px');
    root.style.setProperty('--swf-more-border-width', (Number(style.loadMoreBorderWidth) || 0) + 'px');
    root.style.setProperty('--swf-more-radius', (Number(style.loadMoreRadius) || 0) + 'px');
    root.style.setProperty('--swf-font', FONT_STACKS[style.font] || FONT_STACKS.sans);
    root.style.setProperty('--swf-gap', (Number(settings.columns.gap) || 0) + 'px');
    root.style.setProperty('--swf-header-gap', (Number(settings.header.gap) || 0) + 'px');
    root.style.setProperty('--swf-max-width', (Number(settings.columns.width) || 1200) + 'px');
    root.style.setProperty('--swf-columns', String(Number(settings.columns.count) || 3));
  }

  /**
   * Renders into `host`'s shadow root so storefront CSS cannot leak in. Safe to call repeatedly.
   */
  function renderFeed(host, data, options) {
    const config = options || {};
    const shadow = host.shadowRoot || host.attachShadow({ mode: 'open' });
    const settings = data.settings;
    const layout = LAYOUTS[data.layout] ? data.layout : 'default';
    const pageSize = Number(settings.post.limit) || 12;
    const allPosts = data.posts || [];
    let visibleCount = Math.min(pageSize, allPosts.length);
    const posts = allPosts.slice(0, visibleCount);
    const profile = data.profile || {};

    shadow.replaceChildren();
    if (config.stylesUrl) {
      const link = element('link');
      link.rel = 'stylesheet';
      link.href = config.stylesUrl;
      shadow.appendChild(link);
    }

    const root = element('div', 'swf swf--' + layout + ' swf-columns--' + settings.columns.mode + (config.device === 'mobile' ? ' swf--mobile' : ''));
    applyTheme(root, settings);

    if (settings.title.show && settings.title.text) root.appendChild(element('h2', 'swf-title', settings.title.text));
    if (settings.header.show) root.appendChild(renderHeader(profile, settings, layout));

    const context = {
      profile,
      open(post) {
        if (settings.click.action === 'link') {
          const url = safeUrl(post.permalink);
          if (url) window.open(url, '_blank', 'noopener');
          return;
        }
        renderPopup(shadow, root, profile, posts, settings, posts.indexOf(post));
      },
    };

    let feedBody = LAYOUTS[layout](posts, settings, context);
    root.appendChild(feedBody);

    if (allPosts.length > visibleCount) {
      const loadMore = element('button', 'swf-load-more', 'Load more');
      loadMore.type = 'button';
      loadMore.addEventListener('click', () => {
        visibleCount = Math.min(visibleCount + pageSize, allPosts.length);
        posts.splice(0, posts.length, ...allPosts.slice(0, visibleCount));
        const nextBody = LAYOUTS[layout](posts, settings, context);
        root.replaceChild(nextBody, feedBody);
        feedBody = nextBody;
        if (visibleCount >= allPosts.length) loadMore.remove();
      });
      root.appendChild(loadMore);
    }

    shadow.appendChild(root);
  }

  global.SocialFeedRenderer = { renderFeed, layouts: Object.keys(LAYOUTS) };
  global.dispatchEvent(new CustomEvent('social-feed-renderer:ready'));
})(window);
