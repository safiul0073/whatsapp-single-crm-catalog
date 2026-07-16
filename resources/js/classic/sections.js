// Sections behavior — reveals, accordions, toggles, parallax.
import { renderIcons } from "./icons.js";

const onReady = (cb) => {
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", cb);
  else cb();
};

/* ============ Generic IntersectionObserver reveal ============ */
function initRevealOnScroll(selector, threshold = 0.2) {
  const els = document.querySelectorAll(selector);
  if (!els.length) return;
  const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  if (reduce || typeof IntersectionObserver === "undefined") {
    els.forEach((el) => el.classList.add("is-visible"));
    return;
  }
  const io = new IntersectionObserver((entries) => {
    entries.forEach((e) => {
      if (e.isIntersecting) {
        e.target.classList.add("is-visible");
        io.unobserve(e.target);
      }
    });
  }, { threshold });
  els.forEach((el) => io.observe(el));
}

/* ============ Stats odometer + parallax ============ */
function initStatsOdometer() {
  const cards = document.querySelectorAll("[data-odometer]");
  if (!cards.length) return;
  const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  cards.forEach((card) => {
    const target = parseFloat(card.dataset.target || "0");
    const formatThousands = card.dataset.formatThousands === "true";
    const out = card.querySelector("[data-odometer-text]");
    if (!out) return;

    const fmt = (v) => {
      let val = v;
      if (formatThousands) val = val / 1000;
      return Math.round(val).toString();
    };

    if (reduce || typeof IntersectionObserver === "undefined") {
      out.textContent = fmt(target);
      return;
    }

    const io = new IntersectionObserver((entries) => {
      entries.forEach((e) => {
        if (!e.isIntersecting) return;
        const t0 = performance.now();
        const dur = 1800;
        const tick = (now) => {
          const t = Math.min(1, (now - t0) / dur);
          const ease = 1 - Math.pow(1 - t, 5);
          out.textContent = fmt(target * ease);
          if (t < 1) requestAnimationFrame(tick);
          else out.textContent = fmt(target);
        };
        requestAnimationFrame(tick);
        io.unobserve(card);
      });
    }, { threshold: 0.4 });
    io.observe(card);
  });
}

function initStatsParallax() {
  const layer = document.querySelector("[data-stats-parallax]");
  if (!layer) return;
  if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
  const parent = layer.parentElement;
  if (!parent) return;

  let raf = 0, target = 0, current = 0;
  const factor = 0.22;
  const update = () => {
    const r = parent.getBoundingClientRect();
    const vh = window.innerHeight || 1;
    target = -((r.top + r.height / 2) - vh / 2) * factor;
    if (!raf) raf = requestAnimationFrame(tick);
  };
  const tick = () => {
    current += (target - current) * 0.12;
    layer.style.transform = `translate3d(0, ${current.toFixed(2)}px, 0)`;
    if (Math.abs(target - current) > 0.1) raf = requestAnimationFrame(tick);
    else raf = 0;
  };
  update();
  window.addEventListener("scroll", update, { passive: true });
  window.addEventListener("resize", update);
}

/* ============ Services accordion + cursor preview ============ */
function initServices() {
  const stack = document.querySelector("[data-srv-stack]");
  if (!stack) return;
  const cards = Array.from(stack.querySelectorAll("[data-srv-card]"));
  if (!cards.length) return;

  let openCard = cards.find((c) => c.classList.contains("is-open")) || null;
  cards.forEach((card) => {
    const toggle = card.querySelector("[data-srv-toggle]");
    if (!toggle) return;
    toggle.addEventListener("click", () => {
      const willOpen = openCard !== card;
      cards.forEach((c) => {
        const isOpen = c === card && willOpen;
        c.classList.toggle("is-open", isOpen);
        const t = c.querySelector("[data-srv-toggle]");
        if (t) t.setAttribute("aria-expanded", String(isOpen));
      });
      openCard = willOpen ? card : null;
    });
  });

  // Cursor-tracking preview overlay
  const section = document.querySelector("[data-srv]");
  const preview = document.querySelector("[data-srv-preview]");
  if (!section || !preview) return;
  const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  if (reduce) return;

  let raf = 0, tx = 0, ty = 0, cx = 0, cy = 0;
  const OFFSET_X = 28;
  const OFFSET_Y = 28;
  const tick = () => {
    cx += (tx - cx) * 0.18;
    cy += (ty - cy) * 0.18;
    preview.style.transform = `translate3d(${cx}px, ${cy}px, 0)`;
    if (Math.abs(tx - cx) > 0.4 || Math.abs(ty - cy) > 0.4) raf = requestAnimationFrame(tick);
    else raf = 0;
  };
  section.addEventListener("mousemove", (e) => {
    const r = section.getBoundingClientRect();
    const pw = preview.offsetWidth;
    const ph = preview.offsetHeight;
    let px = e.clientX - r.left + OFFSET_X;
    let py = e.clientY - r.top + OFFSET_Y;
    if (px + pw > r.width - 8) px = e.clientX - r.left - pw - OFFSET_X;
    if (py + ph > r.height - 8) py = e.clientY - r.top - ph - OFFSET_Y;
    if (px < 8) px = 8;
    if (py < 8) py = 8;
    tx = px;
    ty = py;
    if (!raf) raf = requestAnimationFrame(tick);
  }, { passive: true });

  const tagNum = preview.querySelector(".srv3-preview-tag-num");
  const tagLabel = preview.querySelector(".srv3-preview-tag-label");

  stack.querySelectorAll("[data-srv-card]").forEach((card, i) => {
    card.addEventListener("mouseenter", () => {
      section.dataset.previewActive = "true";
      preview.classList.add("is-visible");
      const id = card.dataset.srvId;
      preview.querySelectorAll(".srv3-preview-img").forEach((img) => {
        img.classList.toggle("is-active", img.dataset.srvPreviewImg === id);
      });
      if (tagNum) tagNum.textContent = String(i + 1).padStart(2, "0");
      if (tagLabel) tagLabel.textContent = id;
    });
  });
  stack.addEventListener("mouseleave", () => {
    section.dataset.previewActive = "false";
    preview.classList.remove("is-visible");
  });
}

/* ============ Process card stack ============ */
function initProcess() {
  const stack = document.querySelector("[data-proc-stack]");
  if (!stack) return;
  const cards = Array.from(stack.querySelectorAll("[data-proc-card]"));
  const pips = Array.from(document.querySelectorAll("[data-proc-pip]"));
  const counter = document.querySelector("[data-proc-counter]");
  const prev = document.querySelector("[data-proc-prev]");
  const next = document.querySelector("[data-proc-next]");
  if (!cards.length) return;

  let active = 0;
  const tilts = [0, 2, -2.5, 3, -2, 2.5];

  const apply = () => {
    cards.forEach((card, i) => {
      const offset = i - active;
      const abs = Math.abs(offset);
      let tx = 0, ty = 0, rot = 0, sc = 1, op = 1, z = 100 - abs, pe = "auto";
      if (offset === 0) { /* active */ }
      else if (offset > 0) {
        ty = -18 * abs; tx = 14 * abs;
        rot = (tilts[i] || 0);
        sc = 1 - 0.03 * abs;
        op = Math.max(0.55, 1 - 0.18 * abs);
      } else {
        tx = -120; rot = -8; sc = 0.92; op = 0; z = 1; pe = "none";
      }
      card.style.transform = `translate3d(${tx}px, ${ty}px, 0) rotate(${rot}deg) scale(${sc})`;
      card.style.opacity = String(op);
      card.style.zIndex = String(z);
      card.style.pointerEvents = pe;
      card.classList.toggle("is-active", offset === 0);
      card.classList.toggle("is-peek", offset > 0);
      card.classList.toggle("is-passed", offset < 0);
    });
    pips.forEach((p, i) => {
      p.classList.toggle("is-active", i === active);
      p.classList.toggle("is-done", i < active);
    });
    if (counter) counter.textContent = String(active + 1).padStart(2, "0");
    if (prev) prev.disabled = active === 0;
    if (next) next.disabled = active === cards.length - 1;
  };

  apply();

  cards.forEach((card, i) => {
    card.addEventListener("click", () => {
      if (i > active) { active = i; apply(); }
    });
  });
  pips.forEach((p, i) => p.addEventListener("click", () => { active = i; apply(); }));
  prev?.addEventListener("click", () => { if (active > 0) { active--; apply(); } });
  next?.addEventListener("click", () => { if (active < cards.length - 1) { active++; apply(); } });

  document.addEventListener("keydown", (e) => {
    if (e.key === "ArrowLeft" || e.key === "ArrowUp") { if (active > 0) { active--; apply(); } }
    if (e.key === "ArrowRight" || e.key === "ArrowDown") { if (active < cards.length - 1) { active++; apply(); } }
  });

  // Scroll-jack: lock page while advancing cards, release when done
  let locked = false;
  let touchStartY = 0;
  // Throttle to avoid skipping cards on fast scroll
  let lastAdvance = 0;
  const THROTTLE = 420; // ms between card steps

  const stackFullyVisible = () => {
    const r = stack.getBoundingClientRect();
    return r.top >= 0 && r.bottom <= window.innerHeight;
  };

  const tryAdvance = (dir) => {
    const now = Date.now();
    if (now - lastAdvance < THROTTLE) return;
    lastAdvance = now;
    if (dir > 0 && active < cards.length - 1) { active++; apply(); return; }
    if (dir < 0 && active > 0) { active--; apply(); return; }
    // No more cards to advance — unlock
    locked = false;
  };

  const onWheel = (e) => {
    if (!stackFullyVisible()) { locked = false; return; }
    // Engage lock when stack enters full view and there are cards to show
    if (!locked) {
      if (e.deltaY > 0 && active < cards.length - 1) { locked = true; }
      else if (e.deltaY < 0 && active > 0) { locked = true; }
      else { return; }
    }
    e.preventDefault();
    tryAdvance(e.deltaY > 0 ? 1 : -1);
  };

  const onTouchStart = (e) => { touchStartY = e.touches[0].clientY; };
  const onTouchMove = (e) => {
    if (!stackFullyVisible()) { locked = false; return; }
    const dy = touchStartY - e.touches[0].clientY;
    if (Math.abs(dy) < 12) return;
    if (!locked) {
      if (dy > 0 && active < cards.length - 1) { locked = true; }
      else if (dy < 0 && active > 0) { locked = true; }
      else { return; }
    }
    e.preventDefault();
    tryAdvance(dy > 0 ? 1 : -1);
    touchStartY = e.touches[0].clientY;
  };

  window.addEventListener("wheel", onWheel, { passive: false });
  window.addEventListener("touchstart", onTouchStart, { passive: true });
  window.addEventListener("touchmove", onTouchMove, { passive: false });
}

/* ============ Stack toggle + grid ============ */
function initStack() {
  const toggle = document.querySelector("[data-stk-toggle]");
  const pill = document.querySelector("[data-stk-pill]");
  if (!toggle) return;

  const buttons = Array.from(toggle.querySelectorAll("[data-stk-cat]"));
  const panels = Array.from(document.querySelectorAll("[data-stk-panel]"));
  const cats = buttons.map((b) => b.dataset.stkCat);

  const movePill = (btn) => {
    if (!pill) return;
    pill.style.left = btn.offsetLeft + "px";
    pill.style.width = btn.offsetWidth + "px";
  };

  const setActive = (cat) => {
    const idx = cats.indexOf(cat);
    if (idx < 0) return;
    buttons.forEach((b) => b.classList.toggle("is-active", b.dataset.stkCat === cat));
    movePill(buttons[idx]);
    panels.forEach((p) => {
      const show = p.dataset.stkPanel === cat;
      p.style.display = show ? "" : "none";
      if (show) {
        p.querySelectorAll(".stk2-tool-card").forEach((c) => {
          c.style.animation = "none";
          c.offsetHeight; // reflow
          c.style.animation = "";
        });
      }
    });
  };

  buttons.forEach((b) => b.addEventListener("click", () => setActive(b.dataset.stkCat)));
  setActive(cats[0]);

  if (typeof ResizeObserver !== "undefined") {
    new ResizeObserver(() => {
      const active = buttons.find((b) => b.classList.contains("is-active"));
      if (active) movePill(active);
    }).observe(toggle);
  }

  // Constellation
  document.querySelectorAll("[data-stk-constellation]").forEach((el) => {
    const dots = [];
    for (let col = 0; col < 8; col++) {
      for (let row = 0; row < 16; row++) {
        const x = col * 14 + Math.sin(row * 0.42) * 9;
        const y = row * 20 + col * 4;
        const baseOpacity = 0.14 + col * 0.05;
        const pulses = (col * row) % 5 === 0;
        const pulseDelay = ((col * 7) + row) % 11;
        dots.push({ x, y, baseOpacity, pulses, pulseDelay });
      }
    }
    const svg = `<svg viewBox="0 0 130 360" preserveAspectRatio="none" width="100%" height="100%">${dots.map((d) => `<circle cx="${d.x}" cy="${d.y}" r="${d.pulses ? 1.6 : 1.2}" fill="${d.pulses ? '#16C784' : '#2148FF'}" opacity="${d.baseOpacity}"${d.pulses ? ` class="stk2-dot-pulse" style="animation-delay:${d.pulseDelay * 220}ms"` : ''}/>`).join("")}</svg>`;
    el.innerHTML = svg;
  });
}

/* ============ Testimonials marquee data ============ */
function initTestimonials() {
  document.querySelectorAll("[data-tst-track]").forEach((track) => {
    const cards = track.querySelector("[data-tst-cards]");
    if (!cards) return;
    const clone = cards.cloneNode(true);
    clone.setAttribute("aria-hidden", "true");
    track.appendChild(clone);
  });
}

/* ============ FAQ accordion ============ */
function initFaq() {
  const list = document.querySelector("[data-faq-list]");
  if (!list) return;
  const items = list.querySelectorAll(".faq2-item");
  items.forEach((item) => {
    const trigger = item.querySelector("[data-faq-trigger]");
    if (!trigger) return;
    trigger.addEventListener("click", () => {
      const willOpen = !item.classList.contains("is-open");
      items.forEach((other) => {
        other.classList.toggle("is-open", other === item && willOpen);
        const t = other.querySelector("[data-faq-trigger]");
        if (t) t.setAttribute("aria-expanded", String(other.classList.contains("is-open")));
      });
    });
  });
}

/* ============ Footer newsletter + year ============ */
function initFooter() {
  const yearEl = document.querySelector("[data-footer-year]");
  if (yearEl) yearEl.textContent = new Date().getFullYear();

  const form = document.querySelector("[data-footer-newsletter]");
  if (form) {
    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const input = form.querySelector("input[type=email]");
      if (!input || !input.value.trim()) return;
      const submit = form.querySelector("[data-footer-submit]");
      const text = form.querySelector("[data-footer-submit-text]");
      const icon = form.querySelector("[data-footer-submit-icon]");
      const email = input.value.trim();

      input.disabled = true;
      if (submit) submit.disabled = true;
      if (text) text.textContent = "Subscribing...";

      const csrfToken = form.querySelector('input[name="_token"]')?.value || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

      fetch("/newsletter/subscribe", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
          "X-CSRF-TOKEN": csrfToken
        },
        body: JSON.stringify({ email })
      })
        .then(response => {
          if (!response.ok) {
            throw new Error("Subscription failed");
          }
          return response.json();
        })
        .then(data => {
          if (text) text.textContent = "Subscribed";
          if (icon) icon.style.display = "none";
        })
        .catch(err => {
          input.disabled = false;
          if (submit) submit.disabled = false;
          if (text) text.textContent = "Try Again";
          console.error(err);
        });
    });
  }

  const blogForm = document.querySelector("[data-blog-newsletter]");
  if (blogForm) {
    blogForm.addEventListener("submit", (e) => {
      e.preventDefault();
      const input = blogForm.querySelector("input[type=email]");
      if (!input || !input.value.trim()) return;
      const submit = blogForm.querySelector("[data-blog-submit]");
      const text = blogForm.querySelector("[data-blog-submit-text]");
      const icon = blogForm.querySelector("[data-blog-submit-icon]");
      const email = input.value.trim();

      input.disabled = true;
      if (submit) submit.disabled = true;
      if (text) text.textContent = "Subscribing...";

      const csrfToken = blogForm.querySelector('input[name="_token"]')?.value || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

      fetch("/newsletter/subscribe", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
          "X-CSRF-TOKEN": csrfToken
        },
        body: JSON.stringify({ email })
      })
        .then(response => {
          if (!response.ok) {
            throw new Error("Subscription failed");
          }
          return response.json();
        })
        .then(data => {
          if (text) text.textContent = "Subscribed";
          if (icon) icon.style.display = "none";
        })
        .catch(err => {
          input.disabled = false;
          if (submit) submit.disabled = false;
          if (text) text.textContent = "Try Again";
          console.error(err);
        });
    });
  }

  const archiveForm = document.querySelector("[data-archive-newsletter]");
  if (archiveForm) {
    archiveForm.addEventListener("submit", (e) => {
      e.preventDefault();
      const input = archiveForm.querySelector("input[type=email]");
      if (!input || !input.value.trim()) return;
      const submit = archiveForm.querySelector("[data-archive-submit]");
      const text = archiveForm.querySelector("[data-archive-submit-text]");
      const icon = archiveForm.querySelector("[data-archive-submit-icon]");
      const email = input.value.trim();

      input.disabled = true;
      if (submit) submit.disabled = true;
      if (text) text.textContent = "Subscribing...";

      const csrfToken = archiveForm.querySelector('input[name="_token"]')?.value || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

      fetch("/newsletter/subscribe", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
          "X-CSRF-TOKEN": csrfToken
        },
        body: JSON.stringify({ email })
      })
        .then(response => {
          if (!response.ok) {
            throw new Error("Subscription failed");
          }
          return response.json();
        })
        .then(data => {
          if (text) text.textContent = "Subscribed";
          if (icon) icon.style.display = "none";
        })
        .catch(err => {
          input.disabled = false;
          if (submit) submit.disabled = false;
          if (text) text.textContent = "Try Again";
          console.error(err);
        });
    });
  }

  const blogSignupForm = document.querySelector("[data-blog-signup-newsletter]");
  if (blogSignupForm) {
    blogSignupForm.addEventListener("submit", (e) => {
      e.preventDefault();
      const input = blogSignupForm.querySelector("input[type=email]");
      if (!input || !input.value.trim()) return;
      const submit = blogSignupForm.querySelector("[data-blog-signup-submit]");
      const text = blogSignupForm.querySelector("[data-blog-signup-submit-text]");
      const icon = blogSignupForm.querySelector("[data-blog-signup-submit-icon]");
      const email = input.value.trim();

      input.disabled = true;
      if (submit) submit.disabled = true;
      if (text) text.textContent = "Subscribing...";

      const csrfToken = blogSignupForm.querySelector('input[name="_token"]')?.value || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

      fetch("/newsletter/subscribe", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
          "X-CSRF-TOKEN": csrfToken
        },
        body: JSON.stringify({ email })
      })
        .then(response => {
          if (!response.ok) {
            throw new Error("Subscription failed");
          }
          return response.json();
        })
        .then(data => {
          if (text) text.textContent = "Subscribed";
          if (icon) icon.style.display = "none";
        })
        .catch(err => {
          input.disabled = false;
          if (submit) submit.disabled = false;
          if (text) text.textContent = "Try Again";
          console.error(err);
        });
    });
  }
}

/* ============ Video modal ============ */
function initVideoModal() {
  const modal = document.querySelector("[data-video-modal]");
  if (!modal) return;
  const iframe = modal.querySelector("[data-video-iframe]");
  const triggers = document.querySelectorAll("[data-video-trigger]");
  const closers = modal.querySelectorAll("[data-video-close], [data-video-backdrop]");

  const toEmbedUrl = (url) => {
    if (!url) return "";
    if (url.includes("youtube.com/embed/")) {
      if (url.includes("autoplay")) return url;
      const sep = url.includes("?") ? "&" : "?";
      return url + sep + "autoplay=1&rel=0&modestbranding=1";
    }
    const short = url.match(/youtu\.be\/([^?&]+)/);
    if (short) return `https://www.youtube.com/embed/${short[1]}?autoplay=1&rel=0&modestbranding=1`;
    const watch = url.match(/[?&]v=([^?&]+)/);
    if (watch) return `https://www.youtube.com/embed/${watch[1]}?autoplay=1&rel=0&modestbranding=1`;
    return url;
  };

  const open = (btn) => {
    const src = toEmbedUrl(btn.dataset.videoSrc || "");
    if (!src) return;
    if (iframe) iframe.src = src;
    modal.classList.remove("hidden");
    modal.classList.add("flex");
    document.body.style.overflow = "hidden";
  };
  const close = () => {
    modal.classList.add("hidden");
    modal.classList.remove("flex");
    if (iframe) iframe.src = "";
    document.body.style.overflow = "";
  };

  triggers.forEach((btn) => btn.addEventListener("click", () => open(btn)));
  closers.forEach((el) => el.addEventListener("click", close));
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && !modal.classList.contains("hidden")) close();
  });
}

/* ============ Init ============ */
onReady(() => {
  initRevealOnScroll("[data-sol-reveal]");
  initRevealOnScroll("[data-why-reveal]");
  initStatsOdometer();
  initStatsParallax();
  initServices();
  initProcess();
  initStack();
  initTestimonials();
  initFaq();
  initFooter();
  initVideoModal();
  renderIcons();
});
