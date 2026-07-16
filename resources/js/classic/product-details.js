import { renderIcons } from "./icons.js";
import Swiper from "swiper";
import { Navigation, Pagination } from "swiper/modules";
import "swiper/css";

const onReady = (cb) => {
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", cb);
  else cb();
};

onReady(function () {
  renderIcons();

  // ── TAB SWITCHING ──
  const tabBtns = document.querySelectorAll(".pd-tab-btn");
  const tabIds = ["overview", "features", "screenshots", "reviews", "faq"];

  tabBtns.forEach(function (btn) {
    btn.addEventListener("click", function () {
      const target = btn.dataset.tab;
      tabBtns.forEach((b) => {
        b.classList.remove("is-active", "text-brand-blue");
        b.classList.add("text-text-muted");
      });
      btn.classList.add("is-active", "text-brand-blue");
      btn.classList.remove("text-text-muted");
      tabIds.forEach((id) => {
        const el = document.getElementById("tab-" + id);
        if (el) el.classList.toggle("hidden", id !== target);
      });

      // Init Swiper when screenshots tab is first shown
      if (target === "screenshots") initSwipers();
    });
  });

  // ── SCREENSHOT SWIPERS ──
  let swipersInit = false;

  function initSwipers() {
    if (swipersInit) return;
    swipersInit = true;

    const shotsSwiper = new Swiper(".pd-shots-swiper", {
      modules: [Navigation],
      slidesPerView: 1,
      spaceBetween: 16,
      breakpoints: {
        640:  { slidesPerView: 2 },
        1024: { slidesPerView: 3 },
      },
    });

    const prev = document.getElementById("pd-prev");
    const next = document.getElementById("pd-next");
    if (prev) prev.addEventListener("click", () => shotsSwiper.slidePrev());
    if (next) next.addEventListener("click", () => shotsSwiper.slideNext());

    new Swiper(".pd-mobile-shots-swiper", {
      slidesPerView: "auto",
      spaceBetween: 12,
      freeMode: true,
    });
  }
});
