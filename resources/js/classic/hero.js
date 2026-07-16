// Hero behaviors: rotating caption + mouse-parallax stage.
// No-ops gracefully if hero markup is absent or prefers-reduced-motion is set.

const ROTATING_WORDS = ['SaaS platforms', 'dashboards', 'mobile apps', 'MVPs', 'admin tools'];
const ROTATE_INTERVAL_MS = 2200;
const ROTATE_DURATION_MS = 460;

function initRotatingCaption() {
  const caption = document.querySelector('[data-hero-caption]');
  if (!caption) return;
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduceMotion) return;

  let index = 0;
  const slot = caption.querySelector('.hero-caption__clip');
  if (!slot) return;

  setInterval(() => {
    const current = slot.querySelector('[data-hero-caption-word]');
    if (!current) return;
    current.classList.remove('hero-caption__word--in');
    current.classList.add('hero-caption__word--out');

    setTimeout(() => {
      index = (index + 1) % ROTATING_WORDS.length;
      const next = document.createElement('span');
      next.className = 'hero-caption__word hero-caption__word--in';
      next.setAttribute('data-hero-caption-word', '');
      next.textContent = ROTATING_WORDS[index];
      slot.replaceChildren(next);
      caption.setAttribute('aria-label', `We ship ${ROTATING_WORDS[index]}.`);
    }, ROTATE_DURATION_MS);
  }, ROTATE_INTERVAL_MS);
}

function initStageParallax() {
  const stage = document.querySelector('[data-hero-stage]');
  if (!stage) return;
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  const rocket = stage.querySelector('[data-hero-rocket]');
  const ring   = stage.querySelector('[data-hero-ring]');
  const bg     = stage.querySelector('[data-hero-bg]');

  let raf = 0;
  let targetX = 0, targetY = 0;
  let curX = 0, curY = 0;

  const onMove = (e) => {
    const r = stage.getBoundingClientRect();
    const cx = r.left + r.width / 2;
    const cy = r.top + r.height / 2;
    targetX = Math.max(-1, Math.min(1, (e.clientX - cx) / (r.width / 2)));
    targetY = Math.max(-1, Math.min(1, (e.clientY - cy) / (r.height / 2)));
    if (!raf) raf = requestAnimationFrame(tick);
  };

  const tick = () => {
    curX += (targetX - curX) * 0.08;
    curY += (targetY - curY) * 0.08;

    if (rocket) {
      rocket.style.setProperty('--mx', `${curX * 14}px`);
      rocket.style.setProperty('--my', `${curY * 14}px`);
    }
    if (ring) {
      ring.style.setProperty('--rx', `${curX * -6}px`);
      ring.style.setProperty('--ry', `${curY * -6}px`);
    }
    if (bg) {
      bg.style.setProperty('--bx', `${curX * 4}px`);
      bg.style.setProperty('--by', `${curY * 4}px`);
    }

    if (Math.abs(targetX - curX) > 0.001 || Math.abs(targetY - curY) > 0.001) {
      raf = requestAnimationFrame(tick);
    } else {
      raf = 0;
    }
  };

  window.addEventListener('mousemove', onMove, { passive: true });
}

function init() {
  initRotatingCaption();
  initStageParallax();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
