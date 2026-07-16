/* ==========================================================================
   COMPONENTS PAGE — Design-system showcase interactions
   ========================================================================== */

document.addEventListener('DOMContentLoaded', () => {

  // --- Modal ---
  document.querySelectorAll('[data-modal-trigger]').forEach(btn => {
    const id = btn.getAttribute('data-modal-trigger');
    const modal = document.querySelector(`[data-modal="${id}"]`);
    if (!modal) return;
    btn.addEventListener('click', () => { modal.classList.remove('hidden'); document.body.style.overflow = 'hidden'; });
    modal.querySelectorAll('[data-modal-close]').forEach(c => c.addEventListener('click', () => { modal.classList.add('hidden'); document.body.style.overflow = ''; }));
    modal.querySelectorAll('[data-modal-backdrop]').forEach(b => b.addEventListener('click', () => { modal.classList.add('hidden'); document.body.style.overflow = ''; }));
  });

  // --- Drawer ---
  document.querySelectorAll('[data-drawer-trigger]').forEach(btn => {
    const id = btn.getAttribute('data-drawer-trigger');
    const drawer = document.querySelector(`[data-drawer="${id}"]`);
    if (!drawer) return;
    const panel = drawer.querySelector('.translate-x-full, [class*="translate-x"]');
    btn.addEventListener('click', () => { drawer.classList.remove('hidden'); document.body.style.overflow = 'hidden'; requestAnimationFrame(() => { if(panel) panel.classList.remove('translate-x-full'); }); });
    const close = () => { if(panel) panel.classList.add('translate-x-full'); setTimeout(() => { drawer.classList.add('hidden'); document.body.style.overflow = ''; }, 300); };
    drawer.querySelectorAll('[data-drawer-close]').forEach(c => c.addEventListener('click', close));
    drawer.querySelectorAll('[data-drawer-backdrop]').forEach(b => b.addEventListener('click', close));
  });

  // --- Toast ---
  const toastMessages = {
    'toast-success': { icon: 'check-circle', color: 'success', bg: 'success-soft', msg: 'Action completed successfully!' },
    'toast-error':   { icon: 'x-circle',     color: 'danger',  bg: 'danger-soft',  msg: 'Something went wrong. Try again.' },
    'toast-info':    { icon: 'info',          color: 'brand-blue', bg: 'info-soft', msg: 'A new version is available.' },
  };
  document.querySelectorAll('[data-toast-trigger]').forEach(btn => {
    const id = btn.getAttribute('data-toast-trigger');
    const cfg = toastMessages[id]; if (!cfg) return;
    btn.addEventListener('click', () => {
      const container = document.getElementById('toast-container'); if (!container) return;
      const el = document.createElement('div');
      el.className = 'bg-bg-elevated shadow-lg rounded-lg px-4 py-3 flex items-center gap-3 max-w-sm animate-[nav-fadein_240ms_ease-out]';
      el.innerHTML = `<div class="w-6 h-6 rounded-pill bg-${cfg.bg} flex items-center justify-center flex-none"><i data-lucide="${cfg.icon}" class="w-3.5 h-3.5 text-${cfg.color}"></i></div><p class="font-body text-text-default text-body-sm flex-1">${cfg.msg}</p><button class="flex-none p-1 rounded-sm text-text-light hover:text-text-default transition-colors"><i data-lucide="x" class="w-3.5 h-3.5"></i></button>`;
      container.appendChild(el);
      if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [el] });
      el.querySelector('button').addEventListener('click', () => { el.style.opacity = '0'; el.style.transform = 'translateX(100%)'; el.style.transition = 'all 200ms'; setTimeout(() => el.remove(), 200); });
      setTimeout(() => { if (el.parentNode) { el.style.opacity = '0'; el.style.transform = 'translateX(100%)'; el.style.transition = 'all 200ms'; setTimeout(() => el.remove(), 200); } }, 4000);
    });
  });

  // --- Dropdown ---
  document.querySelectorAll('[data-dropdown-trigger]').forEach(btn => {
    const menu = btn.nextElementSibling; if (!menu) return;
    btn.addEventListener('click', (e) => { e.stopPropagation(); menu.classList.toggle('hidden'); });
    document.addEventListener('click', () => menu.classList.add('hidden'));
  });

  // --- Tabs ---
  document.querySelectorAll('[role="tablist"]').forEach(tablist => {
    const triggers = tablist.querySelectorAll('[data-tab-trigger]');
    const section = tablist.parentElement;
    const panels = section.querySelectorAll('[data-tab-panel]');
    triggers.forEach((trigger, i) => {
      trigger.addEventListener('click', () => {
        triggers.forEach((t, j) => { t.classList.toggle('border-brand-blue', j === i); t.classList.toggle('text-brand-blue', j === i); t.classList.toggle('border-transparent', j !== i); t.classList.toggle('text-text-muted', j !== i); t.setAttribute('aria-selected', j === i); });
        panels.forEach((p, j) => { p.classList.toggle('hidden', j !== i); p.classList.toggle('block', j === i); });
      });
    });
  });

  // --- Accordion ---
  document.querySelectorAll('[data-accordion-trigger]').forEach(trigger => {
    trigger.addEventListener('click', () => {
      const wrapper = trigger.nextElementSibling; if (!wrapper) return;
      const isOpen = trigger.getAttribute('aria-expanded') === 'true';
      trigger.setAttribute('aria-expanded', !isOpen);
      if (isOpen) { wrapper.style.gridTemplateRows = '0fr'; } else { wrapper.style.gridTemplateRows = '1fr'; }
    });
  });

  // --- Theme Switcher ---
  document.querySelectorAll('[data-theme]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('[data-theme]').forEach(b => { b.classList.remove('bg-brand-blue', 'text-white'); b.classList.add('text-text-muted'); });
      btn.classList.add('bg-brand-blue', 'text-white'); btn.classList.remove('text-text-muted');
    });
  });

  // --- Password Show/Hide ---
  document.querySelectorAll('[data-password-toggle]').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = btn.parentElement.querySelector('input');
      if (!input) return;
      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';
      btn.innerHTML = `<i data-lucide="${isPassword ? 'eye-off' : 'eye'}" class="w-4 h-4"></i>`;
      if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [btn] });
    });
  });

  // --- Copy to Clipboard ---
  document.querySelectorAll('[data-copy-trigger]').forEach(btn => {
    btn.addEventListener('click', () => {
      const code = btn.closest('div').parentElement.querySelector('[data-copy-target]');
      if (!code) return;
      navigator.clipboard.writeText(code.textContent).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i data-lucide="check" class="w-3.5 h-3.5"></i><span>Copied!</span>';
        if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [btn] });
        setTimeout(() => { btn.innerHTML = orig; if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [btn] }); }, 2000);
      });
    });
  });

  // --- Search Filter ---
  document.querySelectorAll('[data-search-input]').forEach(input => {
    const section = input.closest('.bg-bg-soft');
    if (!section) return;
    const items = section.querySelectorAll('[data-search-item]');
    input.addEventListener('input', () => {
      const q = input.value.toLowerCase();
      items.forEach(item => { const text = item.getAttribute('data-search-text') || item.textContent; item.classList.toggle('hidden', !text.toLowerCase().includes(q)); });
    });
  });

  // --- Pricing Toggle ---
  document.querySelectorAll('[data-pricing-toggle]').forEach(toggle => {
    let isYearly = false;
    toggle.addEventListener('click', () => {
      isYearly = !isYearly;
      toggle.setAttribute('aria-checked', isYearly);
      const knob = toggle.querySelector('span');
      if (knob) knob.style.transform = isYearly ? 'translateX(24px)' : 'translateX(0)';
      const section = toggle.closest('.bg-bg-soft'); if (!section) return;
      section.querySelectorAll('[data-pricing-monthly]').forEach(el => el.classList.toggle('hidden', isYearly));
      section.querySelectorAll('[data-pricing-yearly]').forEach(el => el.classList.toggle('hidden', !isYearly));
    });
  });

  // --- Counter Animation ---
  document.querySelectorAll('[data-counter-trigger]').forEach(btn => {
    btn.addEventListener('click', () => {
      const section = btn.closest('.bg-bg-soft'); if (!section) return;
      section.querySelectorAll('[data-counter-target]').forEach(el => {
        const target = parseInt(el.getAttribute('data-counter-target'), 10);
        const duration = 1500;
        const start = performance.now();
        const animate = (now) => {
          const progress = Math.min((now - start) / duration, 1);
          const eased = 1 - Math.pow(1 - progress, 3);
          el.textContent = Math.round(target * eased);
          if (progress < 1) requestAnimationFrame(animate);
        };
        requestAnimationFrame(animate);
      });
    });
  });

  // --- Range slider dynamic fill ---
  document.querySelectorAll('.comp-range').forEach(input => {
    const update = () => {
      const pct = ((input.value - input.min) / (input.max - input.min)) * 100;
      input.style.background = `linear-gradient(to right, #2148ff ${pct}%, #e5e7eb ${pct}%)`;
    };
    update();
    input.addEventListener('input', update);
  });

  // --- Dual-handle range slider ---
  document.querySelectorAll('[data-dual-range-min]').forEach(container => {
    const min = +container.dataset.dualRangeMin;
    const max = +container.dataset.dualRangeMax;
    const lower = container.querySelector('[data-dual-range-lower]');
    const upper = container.querySelector('[data-dual-range-upper]');
    const track = container.querySelector('[data-dual-range-track]');
    const thumbLo = container.querySelector('.comp-dual-thumb-lower');
    const thumbHi = container.querySelector('.comp-dual-thumb-upper');
    const minLabel = container.parentElement.querySelector('[data-dual-range-min-label]');
    const maxLabel = container.parentElement.querySelector('[data-dual-range-max-label]');
    if (!lower || !upper) return;

    const fmt = max > 1000 ? v => '$' + (+v).toLocaleString() : v => v;
    const pct = val => ((val - min) / (max - min)) * 100;

    const update = () => {
      let lo = +lower.value, hi = +upper.value;
      if (lo > hi) { const t = lo; lo = hi; hi = t; }
      const pLo = pct(lo);
      const pHi = pct(hi);
      if (track) { track.style.left = pLo + '%'; track.style.width = (pHi - pLo) + '%'; }
      if (thumbLo) thumbLo.style.left = pLo + '%';
      if (thumbHi) thumbHi.style.left = pHi + '%';
      if (minLabel) minLabel.textContent = fmt(lo);
      if (maxLabel) maxLabel.textContent = fmt(hi);
    };
    update();
    lower.addEventListener('input', update);
    upper.addEventListener('input', update);
  });

});
