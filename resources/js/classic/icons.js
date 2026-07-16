// Lucide icons — loaded from the official CDN as a UMD bundle.
// The script tag is in index.html; here we just call createIcons() once
// the global is available, and re-export a renderer for dynamic content.

function render() {
  if (typeof window !== "undefined" && window.lucide?.createIcons) {
    window.lucide.createIcons();
  }
}

function whenReady(cb) {
  if (window.lucide?.createIcons) { cb(); return; }
  // Wait for the CDN script to finish parsing.
  let tries = 0;
  const id = setInterval(() => {
    if (window.lucide?.createIcons || tries++ > 50) {
      clearInterval(id);
      cb();
    }
  }, 40);
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => whenReady(render));
} else {
  whenReady(render);
}

export { render as renderIcons };
