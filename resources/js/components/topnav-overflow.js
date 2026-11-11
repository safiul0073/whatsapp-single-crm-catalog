/**
 * Topnav overflow ("priority+" navigation)
 * Hides module links that don't fit in the topbar and lists them under "More".
 */

function fitTopnav(nav) {
  'use strict';

  const container = nav.parentElement;
  const links = Array.from(nav.querySelectorAll('[data-topnav-link]'));
  const more = nav.querySelector('[data-topnav-more]');
  const moreTrigger = nav.querySelector('[data-topnav-more-trigger]');

  if (!container || !more || links.length === 0 || getComputedStyle(nav).display === 'none') {
    return;
  }

  links.forEach((link) => { link.hidden = false; });
  more.hidden = true;

  const overflowing = () => nav.scrollWidth > nav.clientWidth + 1;

  if (overflowing()) {
    more.hidden = false;

    for (let i = links.length - 1; i >= 0 && overflowing(); i--) {
      if (!links[i].classList.contains('is-active')) {
        links[i].hidden = true;
      }
    }
  }

  const hiddenIds = new Set(links.filter((link) => link.hidden).map((link) => link.dataset.topnavLink));

  nav.querySelectorAll('[data-topnav-more-item]').forEach((item) => {
    item.hidden = !hiddenIds.has(item.dataset.topnavMoreItem);
  });

  if (hiddenIds.size === 0) {
    more.hidden = true;
  }

  moreTrigger?.classList.toggle('is-active', links.some((link) => link.hidden && link.classList.contains('is-active')));
}

function initTopnav() {
  'use strict';

  document.querySelectorAll('[data-topnav]').forEach((nav) => {
    let frame = null;
    const schedule = () => {
      cancelAnimationFrame(frame);
      frame = requestAnimationFrame(() => fitTopnav(nav));
    };

    schedule();

    if (window.ResizeObserver && nav.closest('header')) {
      new ResizeObserver(schedule).observe(nav.closest('header'));
    } else {
      window.addEventListener('resize', schedule);
    }

    document.fonts?.ready.then(schedule);
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initTopnav);
} else {
  initTopnav();
}
