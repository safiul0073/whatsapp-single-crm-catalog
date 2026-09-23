/**
 * Image Color Picker & Eyedropper Component
 * Allows hovering on any image to inspect pixel color with a real-time magnifying loupe,
 * copy HEX color codes (via click or hover), extract color palettes, and apply colors directly
 * to product color variations.
 *
 * @module components/image-color-picker
 */

// Color naming reference table for apparel & e-commerce
const COLOR_NAMES = [
  { name: 'Pure White', hex: '#FFFFFF', r: 255, g: 255, b: 255 },
  { name: 'Snow / Off White', hex: '#F8FAFC', r: 248, g: 250, b: 252 },
  { name: 'Ivory / Cream', hex: '#FFFFF0', r: 255, g: 255, b: 240 },
  { name: 'Beige / Sand', hex: '#F5F5DC', r: 245, g: 245, b: 220 },
  { name: 'Light Khaki', hex: '#F0E68C', r: 240, g: 230, b: 140 },
  { name: 'Khaki', hex: '#C3B091', r: 195, g: 176, b: 145 },
  { name: 'Tan / Camel', hex: '#D2B48C', r: 210, g: 180, b: 140 },
  { name: 'Light Grey', hex: '#E2E8F0', r: 226, g: 232, b: 240 },
  { name: 'Heather Grey', hex: '#94A3B8', r: 148, g: 163, b: 184 },
  { name: 'Charcoal', hex: '#334155', r: 51, g: 65, b: 85 },
  { name: 'Dark Slate', hex: '#1E293B', r: 30, g: 41, b: 59 },
  { name: 'Pitch Black', hex: '#0F172A', r: 15, g: 23, b: 42 },
  { name: 'True Black', hex: '#000000', r: 0, g: 0, b: 0 },
  { name: 'Raw Indigo', hex: '#1E293B', r: 30, g: 41, b: 59 },
  { name: 'Deep Indigo', hex: '#1E1B4B', r: 30, g: 27, b: 75 },
  { name: 'Navy Blue', hex: '#1E3A8A', r: 30, g: 58, b: 138 },
  { name: 'Medium Stone Wash', hex: '#3B82F6', r: 59, g: 130, b: 246 },
  { name: 'Royal Blue', hex: '#2563EB', r: 37, g: 99, b: 235 },
  { name: 'Denim Blue', hex: '#4361EE', r: 67, g: 97, b: 238 },
  { name: 'Light Blue', hex: '#87CEEB', r: 135, g: 206, b: 235 },
  { name: 'Sky Blue', hex: '#38BDF8', r: 56, g: 189, b: 248 },
  { name: 'Ice Blue', hex: '#E0F2FE', r: 224, g: 242, b: 254 },
  { name: 'Turquoise / Cyan', hex: '#06B6D4', r: 6, g: 182, b: 212 },
  { name: 'Teal', hex: '#0D9488', r: 13, g: 148, b: 136 },
  { name: 'Forest Green', hex: '#14532D', r: 20, g: 83, b: 45 },
  { name: 'Hunter Green', hex: '#166534', r: 22, g: 101, b: 52 },
  { name: 'Olive Green', hex: '#556B2F', r: 85, g: 107, b: 47 },
  { name: 'Sage Green', hex: '#84A98C', r: 132, g: 169, b: 140 },
  { name: 'Mint Green', hex: '#A7F3D0', r: 167, g: 243, b: 208 },
  { name: 'Burgundy / Maroon', hex: '#800020', r: 128, g: 0, b: 32 },
  { name: 'Wine Red', hex: '#722F37', r: 114, g: 47, b: 55 },
  { name: 'Crimson', hex: '#DC2626', r: 220, g: 38, b: 38 },
  { name: 'Ruby Red', hex: '#EF4444', r: 239, g: 68, b: 68 },
  { name: 'Coral / Salmon', hex: '#F87171', r: 248, g: 113, b: 113 },
  { name: 'Rust / Terracotta', hex: '#C2410C', r: 194, g: 65, b: 12 },
  { name: 'Burnt Orange', hex: '#EA580C', r: 234, g: 88, b: 12 },
  { name: 'Amber / Mustard', hex: '#D97706', r: 217, g: 119, b: 6 },
  { name: 'Golden Yellow', hex: '#EAB308', r: 234, g: 179, b: 8 },
  { name: 'Pastel Yellow', hex: '#FEF08A', r: 254, g: 240, b: 138 },
  { name: 'Plum / Eggplant', hex: '#4A044E', r: 74, g: 4, b: 78 },
  { name: 'Deep Purple', hex: '#581C87', r: 88, g: 28, b: 135 },
  { name: 'Violet', hex: '#7C3AED', r: 124, g: 58, b: 237 },
  { name: 'Lavender', hex: '#C084FC', r: 192, g: 132, b: 252 },
  { name: 'Dusty Rose', hex: '#BE185D', r: 190, g: 24, b: 93 },
  { name: 'Hot Pink', hex: '#EC4899', r: 236, g: 72, b: 153 },
  { name: 'Blush Pink', hex: '#F472B6', r: 244, g: 114, b: 182 },
  { name: 'Chocolate Brown', hex: '#3E2723', r: 62, g: 39, b: 35 },
  { name: 'Warm Brown', hex: '#78350F', r: 120, g: 53, b: 15 },
  { name: 'Mocha / Coffee', hex: '#5D4037', r: 93, g: 64, b: 55 },
];

/**
 * Find the closest color name by RGB Euclidean distance.
 */
export function getClosestColorName(r, g, b) {
  let minDistance = Infinity;
  let closestName = 'Custom Color';

  for (const c of COLOR_NAMES) {
    const dr = r - c.r;
    const dg = g - c.g;
    const db = b - c.b;
    const distance = dr * dr + dg * dg + db * db;
    if (distance < minDistance) {
      minDistance = distance;
      closestName = c.name;
    }
  }

  return closestName;
}

export function rgbToHex(r, g, b) {
  return '#' + [r, g, b].map(x => {
    const hex = Math.max(0, Math.min(255, Math.round(x))).toString(16);
    return hex.padStart(2, '0');
  }).join('').toUpperCase();
}

/**
 * WeakMap cache for offscreen image canvases to prevent redrawing on every mousemove.
 */
const imageCanvasCache = new WeakMap();

/**
 * Obtain an offscreen canvas and 2D context for an image.
 */
function getImageCanvas(img) {
  const currentSrc = img.currentSrc || img.src;
  if (imageCanvasCache.has(img)) {
    const entry = imageCanvasCache.get(img);
    if (entry && entry.src === currentSrc && entry.width === (img.naturalWidth || img.width)) {
      return entry;
    }
  }

  try {
    const canvas = document.createElement('canvas');
    canvas.width = img.naturalWidth || img.width || 300;
    canvas.height = img.naturalHeight || img.height || 300;
    if (canvas.width === 0 || canvas.height === 0) {
      return null;
    }
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    
    // Attempt drawing
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
    const entry = { canvas, ctx, width: canvas.width, height: canvas.height, src: currentSrc, valid: true };
    imageCanvasCache.set(img, entry);
    return entry;
  } catch (err) {
    console.warn('[ImageColorPicker] Canvas drawing restricted by CORS:', err);
    return null;
  }
}

/**
 * Extract dominant palette from an image element.
 */
export function extractImagePalette(img, maxColors = 6) {
  return new Promise((resolve) => {
    const doExtract = () => {
      const entry = getImageCanvas(img);
      if (!entry) {
        resolve([]);
        return;
      }

      const { ctx, width, height } = entry;
      // Sample a reduced grid (max 60x60)
      const sampleW = Math.min(60, width);
      const sampleH = Math.min(60, height);
      const stepX = Math.max(1, Math.floor(width / sampleW));
      const stepY = Math.max(1, Math.floor(height / sampleH));

      let imgData;
      try {
        imgData = ctx.getImageData(0, 0, width, height).data;
      } catch (e) {
        resolve([]);
        return;
      }

      const colorBuckets = new Map();

      for (let y = 0; y < height; y += stepY) {
        for (let x = 0; x < width; x += stepX) {
          const idx = (y * width + x) * 4;
          const a = imgData[idx + 3];
          if (a < 128) continue; // Skip transparent

          // Quantize to 16-step buckets to group close shades
          const qr = Math.round(imgData[idx] / 16) * 16;
          const qg = Math.round(imgData[idx + 1] / 16) * 16;
          const qb = Math.round(imgData[idx + 2] / 16) * 16;
          const key = `${qr},${qg},${qb}`;

          if (!colorBuckets.has(key)) {
            colorBuckets.set(key, { r: imgData[idx], g: imgData[idx + 1], b: imgData[idx + 2], count: 1 });
          } else {
            colorBuckets.get(key).count++;
          }
        }
      }

      // Sort by frequency
      const sorted = Array.from(colorBuckets.values())
        .sort((a, b) => b.count - a.count)
        .slice(0, maxColors * 2);

      // Filter distinct colors (ensure minimum perceptual difference)
      const distinct = [];
      for (const item of sorted) {
        const isDuplicate = distinct.some(d => {
          const dr = d.r - item.r;
          const dg = d.g - item.g;
          const db = d.b - item.b;
          return Math.sqrt(dr * dr + dg * dg + db * db) < 32;
        });

        if (!isDuplicate) {
          const hex = rgbToHex(item.r, item.g, item.b);
          distinct.push({
            hex,
            name: getClosestColorName(item.r, item.g, item.b),
            r: item.r,
            g: item.g,
            b: item.b,
          });
        }
        if (distinct.length >= maxColors) break;
      }

      resolve(distinct);
    };

    if (img.complete && img.naturalWidth > 0) {
      doExtract();
    } else {
      img.addEventListener('load', doExtract, { once: true });
    }
  });
}

/**
 * Singleton Loupe Overlay Manager
 */
class ImageColorLoupe {
  constructor() {
    this.el = null;
    this.canvas = null;
    this.ctx = null;
    this.swatchDot = null;
    this.hexLabel = null;
    this.nameLabel = null;
    this.hintLabel = null;
    this.currentHex = '#000000';
    this.currentColorName = '';
    this.activeImage = null;
    this.autoCopyDebounceTimer = null;
    this.lastCopiedHex = null;
    this.copyRippleEl = null;

    this.initDOM();
  }

  initDOM() {
    if (document.getElementById('imageColorLoupeOverlay')) {
      this.el = document.getElementById('imageColorLoupeOverlay');
      return;
    }

    const wrapper = document.createElement('div');
    wrapper.id = 'imageColorLoupeOverlay';
    wrapper.className = 'color-loupe-overlay pointer-events-none fixed z-[9999] opacity-0 transition-opacity duration-150';
    wrapper.innerHTML = `
      <div class="color-loupe-lens shadow-2xl rounded-full border-2 border-white ring-2 ring-black/20 overflow-hidden relative bg-neutral-900 w-[110px] h-[110px] flex items-center justify-center">
        <canvas class="color-loupe-canvas w-full h-full block" width="88" height="88"></canvas>
        <div class="color-loupe-crosshair absolute inset-0 flex items-center justify-center pointer-events-none">
          <div class="w-2.5 h-2.5 border border-white ring-1 ring-black/60 rounded-xs"></div>
        </div>
      </div>
      <div class="color-loupe-badge mt-2 bg-neutral-900/95 text-white backdrop-blur-md rounded-xl py-1.5 px-3 shadow-xl border border-white/20 text-center flex flex-col items-center gap-0.5 min-w-[130px] -translate-x-1/2 left-1/2 relative">
        <div class="flex items-center gap-2">
          <span class="color-loupe-swatch-dot w-3.5 h-3.5 rounded-full border border-white/40 shadow-xs shrink-0"></span>
          <span class="color-loupe-hex font-mono font-bold text-xs tracking-wider">#FFFFFF</span>
        </div>
        <span class="color-loupe-name text-[10px] font-medium text-neutral-300 truncate max-w-[140px]">Color Name</span>
        <span class="color-loupe-hint text-[9px] font-semibold text-emerald-400 mt-0.5 uppercase tracking-wider">Click to Copy</span>
      </div>
    `;

    document.body.appendChild(wrapper);

    this.el = wrapper;
    this.badgeEl = wrapper.querySelector('.color-loupe-badge');
    this.canvas = wrapper.querySelector('.color-loupe-canvas');
    this.ctx = this.canvas.getContext('2d', { willReadFrequently: true });
    this.swatchDot = wrapper.querySelector('.color-loupe-swatch-dot');
    this.hexLabel = wrapper.querySelector('.color-loupe-hex');
    this.nameLabel = wrapper.querySelector('.color-loupe-name');
    this.hintLabel = wrapper.querySelector('.color-loupe-hint');
    this.flashTimer = null;
  }

  flashCopied(hex) {
    if (!this.hintLabel) return;
    this.hintLabel.textContent = `✓ Copied ${hex || ''}!`;
    this.hintLabel.classList.remove('text-emerald-400');
    this.hintLabel.classList.add('text-emerald-300', 'font-bold');
    if (this.badgeEl) {
      this.badgeEl.classList.add('copied-pulse');
      setTimeout(() => {
        this.badgeEl?.classList.remove('copied-pulse');
      }, 350);
    }
    clearTimeout(this.flashTimer);
    this.flashTimer = setTimeout(() => {
      if (this.hintLabel) {
        this.hintLabel.textContent = 'Click to Copy';
        this.hintLabel.classList.remove('text-emerald-300', 'font-bold');
        this.hintLabel.classList.add('text-emerald-400');
      }
    }, 1500);
  }

  show(clientX, clientY) {
    if (!this.el) return;
    this.el.classList.remove('opacity-0');
    this.el.classList.add('opacity-100');
    this.move(clientX, clientY);
  }

  hide() {
    if (!this.el) return;
    this.el.classList.remove('opacity-100');
    this.el.classList.add('opacity-0');
    clearTimeout(this.autoCopyDebounceTimer);
  }

  move(clientX, clientY) {
    if (!this.el) return;
    const offset = 24;
    let left = clientX + offset;
    let top = clientY - 70;

    // Flip if near right or bottom edge
    const viewportW = window.innerWidth;
    const viewportH = window.innerHeight;

    if (left + 150 > viewportW) {
      left = clientX - 150 - offset;
    }
    if (top < 10) {
      top = clientY + offset;
    } else if (top + 180 > viewportH) {
      top = clientY - 180;
    }

    this.el.style.left = `${left}px`;
    this.el.style.top = `${top}px`;
  }

  renderPixel(img, e, autoCopy = false, onCopyCallback = null) {
    const entry = getImageCanvas(img);
    if (!entry) return null;

    const rect = img.getBoundingClientRect();
    const relX = e.clientX - rect.left;
    const relY = e.clientY - rect.top;

    const scaleX = entry.width / rect.width;
    const scaleY = entry.height / rect.height;

    const naturalX = Math.max(0, Math.min(entry.width - 1, Math.floor(relX * scaleX)));
    const naturalY = Math.max(0, Math.min(entry.height - 1, Math.floor(relY * scaleY)));

    // Sample center pixel
    let pixel;
    try {
      pixel = entry.ctx.getImageData(naturalX, naturalY, 1, 1).data;
    } catch (err) {
      return null;
    }

    const r = pixel[0], g = pixel[1], b = pixel[2];
    const hex = rgbToHex(r, g, b);
    const colorName = getClosestColorName(r, g, b);

    this.currentHex = hex;
    this.currentColorName = colorName;

    // Render 11x11 pixel magnified grid
    if (this.ctx && this.canvas) {
      this.ctx.imageSmoothingEnabled = false;
      this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

      const sampleSize = 11;
      const half = Math.floor(sampleSize / 2);
      const srcX = Math.max(0, naturalX - half);
      const srcY = Math.max(0, naturalY - half);

      this.ctx.drawImage(
        entry.canvas,
        srcX, srcY, sampleSize, sampleSize,
        0, 0, this.canvas.width, this.canvas.height
      );
    }

    // Update labels
    if (this.swatchDot) this.swatchDot.style.backgroundColor = hex;
    if (this.hexLabel) this.hexLabel.textContent = hex;
    if (this.nameLabel) this.nameLabel.textContent = colorName;

    if (autoCopy) {
      if (this.hintLabel) this.hintLabel.textContent = 'Auto-Copying...';
      // Debounce auto-copy on hover by 200ms
      clearTimeout(this.autoCopyDebounceTimer);
      this.autoCopyDebounceTimer = setTimeout(() => {
        if (this.currentHex && this.currentHex !== this.lastCopiedHex) {
          this.lastCopiedHex = this.currentHex;
          copyColorToClipboard(this.currentHex, this.currentColorName, false);
          if (this.hintLabel) {
            this.hintLabel.textContent = 'Copied to Clipboard!';
            this.hintLabel.classList.add('text-emerald-300');
          }
          if (onCopyCallback) {
            onCopyCallback(this.currentHex, this.currentColorName);
          }
        }
      }, 200);
    } else {
      if (this.hintLabel) this.hintLabel.textContent = 'Click to Copy';
    }

    this.move(e.clientX, e.clientY);
    return { hex, colorName, r, g, b };
  }
}

export const loupe = new ImageColorLoupe();

/**
 * Copy a hex code to the clipboard and show user feedback.
 */
export async function copyColorToClipboard(hex, name = '', showNotification = true) {
  if (!hex) return;

  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(hex);
    } else {
      const textarea = document.createElement('textarea');
      textarea.value = hex;
      textarea.style.position = 'fixed';
      textarea.style.opacity = '0';
      document.body.appendChild(textarea);
      textarea.select();
      document.execCommand('copy');
      textarea.remove();
    }

    loupe.flashCopied(hex);

    if (showNotification && window.showToast) {
      window.showToast(
        'Color Copied',
        `${hex} ${name ? '— ' + name : ''} copied to clipboard!`,
        'success'
      );
    }

    // Dispatch global custom event for any listeners (e.g. Alpine wizard)
    window.dispatchEvent(new CustomEvent('color-picked', {
      detail: { hex, name }
    }));

  } catch (err) {
    console.error('Failed to copy color:', err);
  }
}

/**
 * Native EyeDropper API support (samples entire screen)
 */
export async function openNativeEyeDropper() {
  if (!window.EyeDropper) {
    if (window.showToast) {
      window.showToast('EyeDropper Not Supported', 'Your browser does not support the native screen eyedropper. Hover over any image on this page to sample colors instead!', 'info');
    }
    return null;
  }

  try {
    const eyeDropper = new window.EyeDropper();
    const result = await eyeDropper.open();
    const hex = result.sRGBHex.toUpperCase();
    
    // Parse RGB from hex
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    const name = getClosestColorName(r, g, b);

    copyColorToClipboard(hex, name, true);
    return { hex, name };
  } catch (err) {
    // User cancelled
    return null;
  }
}

/**
 * Attach hover color inspection to any <img> element.
 */
export function attachColorPickerToImage(img, options = {}) {
  if (!img || img._colorPickerAttached) return;
  img._colorPickerAttached = true;

  const isEnabled = () => {
    if (typeof options.enabled === 'function') return options.enabled();
    return options.enabled !== false;
  };

  const isAutoCopy = () => {
    if (typeof options.autoCopy === 'function') return options.autoCopy();
    return Boolean(options.autoCopy);
  };

  img.addEventListener('mouseenter', (e) => {
    if (!isEnabled()) return;
    loupe.show(e.clientX, e.clientY);
  });

  img.addEventListener('mousemove', (e) => {
    if (!isEnabled()) {
      loupe.hide();
      return;
    }
    loupe.show(e.clientX, e.clientY);
    loupe.renderPixel(img, e, isAutoCopy(), options.onCopy);
  });

  img.addEventListener('mouseleave', () => {
    loupe.hide();
  });

  img.addEventListener('click', (e) => {
    if (!isEnabled()) return;
    e.preventDefault();
    e.stopPropagation();

    const sample = loupe.renderPixel(img, e, false);
    if (sample) {
      copyColorToClipboard(sample.hex, sample.colorName, true);
      if (options.onPick) {
        options.onPick(sample.hex, sample.colorName);
      }
    }
    loupe.hide();
    if (typeof window.closeAllModals === 'function') {
      window.closeAllModals();
    }
  });
}

/**
 * Global image observer to attach hover color picking to all images when active.
 */
let globalInspectActive = false;

export function setGlobalInspectMode(active) {
  globalInspectActive = Boolean(active);
  const images = document.querySelectorAll('img');
  images.forEach(img => {
    if (globalInspectActive) {
      attachColorPickerToImage(img, {
        enabled: () => globalInspectActive,
        autoCopy: () => window.__autoCopyColorOnHover || false,
        onPick: (hex, name) => {
          window.dispatchEvent(new CustomEvent('color-picked', { detail: { hex, name } }));
        }
      });
      img.classList.add('inspecting-color-target');
    } else {
      img.classList.remove('inspecting-color-target');
    }
  });

  if (window.showToast) {
    window.showToast(
      globalInspectActive ? 'Image Color Eyedropper: Active' : 'Image Color Eyedropper: Off',
      globalInspectActive ? 'Hover on ANY image in the page to inspect and copy color codes!' : 'Color picker deactivated.',
      globalInspectActive ? 'success' : 'info'
    );
  }
}

// Expose on window for easy Alpine / inline access
window.ImageColorPicker = {
  loupe,
  copyColorToClipboard,
  extractImagePalette,
  attachColorPickerToImage,
  setGlobalInspectMode,
  openNativeEyeDropper,
  getClosestColorName,
  rgbToHex,
};
