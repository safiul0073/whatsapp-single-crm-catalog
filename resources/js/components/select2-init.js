/**
 * Select2 Initialization
 *
 * This component initializes Select2 dropdowns for both basic and multi-select variants.
 * It assumes jQuery and Select2 JS are loaded via CDN or bundle.
 */

function initSelect2() {
  const $ = window.jQuery;
  if (!$) {
    console.warn("Select2: jQuery not found. Skipping initialization.");
    return;
  }

  // Get current direction
  const getDir = () => {
    return document.documentElement.dir || "ltr";
  };

  // Basic Searchable Select
  if ($(".select2-basic").length) {
    $(".select2-basic").select2({
      placeholder: "Select an option",
      allowClear: true,
      width: "100%",
      dir: getDir(),
    });
  }

  // Multi-select with Tags
  if ($(".select2-multi").length) {
    $(".select2-multi").select2({
      placeholder: "Select tags",
      allowClear: true,
      tags: true,
      width: "100%",
      dir: getDir(),
    });
  }
}

// Initialize on DOM load - REMOVED to allow manual init after RTL restoration in app.js
// document.addEventListener("DOMContentLoaded", () => {
//   initSelect2();
// });

// Re-initialize on RTL toggle
document.addEventListener("rtl-toggled", () => {
  // We need to destroy and re-init to update the 'dir' option
  if (window.jQuery && window.jQuery(".select2-basic, .select2-multi").length) {
    window.jQuery(".select2-basic, .select2-multi").select2("destroy");
    initSelect2();
  }
});

export { initSelect2 };
