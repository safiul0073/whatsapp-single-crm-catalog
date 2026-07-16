export function initFileUpload() {
  const inputs = document.querySelectorAll(
    'input[type="file"][data-upload="true"]',
  );

  inputs.forEach((input) => {
    // Prevent double init
    if (input.dataset.initialized === "true") return;
    input.dataset.initialized = "true";

    const label = input.closest("label");
    if (!label) return;

    input.addEventListener("change", function (e) {
      const file = e.target.files[0];
      if (!file) return;

      const variant = input.dataset.variant || "standard";
      const isImage = file.type.startsWith("image/");

      if (isImage) {
        const reader = new FileReader();
        reader.onload = function (event) {
          renderPreview(label, input, file, variant, true, event.target.result);
        };
        reader.readAsDataURL(file);
      } else {
        renderPreview(label, input, file, variant, false, null);
      }
    });
  });
}

function renderPreview(label, input, file, variant, isImage, hiddenSrc) {
  // Clear children safely to keep input attached
  Array.from(label.children).forEach((child) => {
    if (child !== input) {
      label.removeChild(child);
    }
  });

  let previewHtml = "";
  let containerClass = "pointer-events-none";

  if (variant === "avatar") {
    containerClass +=
      " absolute inset-0 flex items-center justify-center rounded-full overflow-hidden bg-neutral-100";

    if (hiddenSrc) {
      previewHtml = `
                <div class="relative w-full h-full group">
                    <img src="${hiddenSrc}" class="w-full h-full object-cover" alt="Avatar">
                    <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                        <i class="ph-bold ph-camera text-white text-xl"></i>
                    </div>
                </div>
             `;
    } else {
      previewHtml = `
                <div class="flex flex-col items-center justify-center text-neutral-400">
                    <i class="ph ph-file-text text-2xl"></i>
                    <span class="text-[10px] mt-1 truncate max-w-[60px]">${file.name}</span>
                </div>
             `;
    }
  } else if (variant === "simple") {
    containerClass += " flex items-center gap-3 w-full"; // Removed px-4 to avoid double padding

    const iconClass = isImage ? "ph-image" : "ph-file-text";
    previewHtml = `
            <div class="bg-neutral-100 p-2 rounded-lg text-neutral-500">
                <i class="ph ${iconClass} text-xl"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-neutral-900 truncate">${file.name}</p>
                <p class="text-xs text-neutral-500">${(file.size / 1024).toFixed(0)} KB</p>
            </div>
            <div class="text-success">
                <i class="ph-fill ph-check-circle text-xl"></i>
            </div>
        `;
  } else {
    // Standard / Dropzone
    containerClass +=
      " flex flex-col items-center justify-center py-6 text-center w-full h-full"; // Changed pt-5 pb-6 to py-6 and added h-full

    if (isImage && hiddenSrc) {
      previewHtml = `<img src="${hiddenSrc}" class="h-24 w-auto rounded-lg object-contain mb-3 shadow-sm border border-neutral-200" alt="Preview">`;
    } else {
      const iconClass = isImage ? "ph-image" : "ph-file-text";
      previewHtml = `<i class="ph ${iconClass} text-4xl text-neutral-400 mb-2"></i>`;
    }

    previewHtml += `
            <p class="mb-1 text-sm font-semibold text-neutral-900 truncate max-w-[200px]">${file.name}</p>
            <p class="text-xs text-neutral-500">Click to replace</p>
        `;
  }

  // Initialize container
  const previewContainer = document.createElement("div");
  previewContainer.className = containerClass;
  previewContainer.innerHTML = previewHtml;

  // Insert before input
  label.insertBefore(previewContainer, input);
}

// Auto init on load
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initFileUpload);
} else {
  initFileUpload();
}
