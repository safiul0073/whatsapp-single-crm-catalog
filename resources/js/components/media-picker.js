/**
 * Media Picker Component
 * Handles the media library modal: browse, search, upload, select.
 */
import { openModal, closeModal } from './modal.js';

document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('mediaLibraryModal');
  if (!modal) return;

  const grid = modal.querySelector('[data-media-grid]');
  const searchInput = modal.querySelector('[data-media-search]');
  const typeTabs = modal.querySelectorAll('[data-media-type]');
  const uploadZone = modal.querySelector('[data-media-upload-zone]');
  const uploadInput = modal.querySelector('[data-media-upload-input]');
  const uploadProgress = modal.querySelector('[data-media-upload-progress]');
  const uploadBar = modal.querySelector('[data-media-upload-bar]');
  const uploadError = modal.querySelector('[data-media-upload-error]');
  const selectBtn = modal.querySelector('[data-media-select-btn]');
  const emptyState = modal.querySelector('[data-media-empty]');
  const loadingState = modal.querySelector('[data-media-loading]');
  const loadMoreWrap = modal.querySelector('[data-media-load-more]');
  const loadMoreBtn = loadMoreWrap ? loadMoreWrap.querySelector('button') : null;
  const manageToggle = modal.querySelector('[data-media-manage-toggle]');
  const manageBar = modal.querySelector('[data-media-manage-bar]');
  const manageSelect = modal.querySelector('[data-media-manage-select]');
  const manageConfirm = modal.querySelector('[data-media-manage-confirm]');
  const manageCount = modal.querySelector('[data-media-manage-count]');
  const manageAllBtn = modal.querySelector('[data-media-manage-all]');
  const manageDeleteBtn = modal.querySelector('[data-media-manage-delete]');
  const confirmText = modal.querySelector('[data-media-confirm-text]');
  const confirmYes = modal.querySelector('[data-media-confirm-yes]');
  const confirmNo = modal.querySelector('[data-media-confirm-no]');

  const browseUrl = modal.dataset.browseUrl;
  const uploadUrl = modal.dataset.uploadUrl;
  const deleteUrl = modal.dataset.deleteUrl;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

  // State
  let activePicker = null;    // The picker element that opened the modal
  let selectedMedia = null;   // Selected media object, or an array in multi-select mode
  let currentType = '';       // Type filter
  let currentSearch = '';     // Search query
  let currentPage = 1;
  let hasMore = false;
  let isLoading = false;
  let loadController = null;  // Aborts the previous browse request when filters change
  let loadSeq = 0;            // Only the newest browse response may render
  let galleryColorPickerActive = false;
  let customCallback = null;
  let manageMode = false;     // Checkbox mode for bulk delete
  let pendingDelete = null;   // Ids awaiting inline confirmation

  window.openMediaLibrary = function (callback, options = {}) {
    activePicker = null;
    customCallback = callback;
    const accept = options.type || 'image';
    currentType = accept === 'all' ? '' : accept;
    typeTabs.forEach(function (tab) {
      tab.classList.toggle('active', tab.dataset.mediaType === currentType);
    });
    currentPage = 1;
    if (searchInput) searchInput.value = '';
    currentSearch = '';
    selectedMedia = null;
    resetManage();
    updateSelectBtn();
    openModal('mediaLibraryModal');
    loadMedia(false);
  };

  const colorPickerToggleBtn = modal.querySelector('[data-media-color-picker-toggle]');

  function updateColorPickerToggle() {
    if (!colorPickerToggleBtn) return;
    const statusSpan = colorPickerToggleBtn.querySelector('.media-color-picker-status');
    if (galleryColorPickerActive) {
      colorPickerToggleBtn.classList.remove('bg-white', 'text-neutral-700', 'border-neutral-300', 'hover:bg-neutral-50');
      colorPickerToggleBtn.classList.add('bg-primary', 'text-white', 'border-primary', 'shadow-xs');
      if (statusSpan) {
        statusSpan.textContent = 'Enabled';
        statusSpan.className = 'media-color-picker-status uppercase text-[10px] font-extrabold px-1.5 py-0.5 rounded bg-white/25 text-white';
      }
      grid.classList.add('gallery-color-picker-mode');
    } else {
      colorPickerToggleBtn.classList.remove('bg-primary', 'text-white', 'border-primary', 'shadow-xs');
      colorPickerToggleBtn.classList.add('bg-white', 'text-neutral-700', 'border-neutral-300', 'hover:bg-neutral-50');
      if (statusSpan) {
        statusSpan.textContent = 'Disabled';
        statusSpan.className = 'media-color-picker-status uppercase text-[10px] font-extrabold px-1.5 py-0.5 rounded bg-neutral-100 text-neutral-600';
      }
      grid.classList.remove('gallery-color-picker-mode');
      if (window.ImageColorPicker?.loupe) {
        window.ImageColorPicker.loupe.hide();
      }
    }
  }

  if (colorPickerToggleBtn) {
    colorPickerToggleBtn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      galleryColorPickerActive = !galleryColorPickerActive;
      updateColorPickerToggle();
      if (window.showToast) {
        window.showToast(
          galleryColorPickerActive ? 'Color Picker Enabled' : 'Color Picker Disabled',
          galleryColorPickerActive ? 'Hover on any image to view color code, and click to copy!' : 'Normal media selection enabled.',
          galleryColorPickerActive ? 'success' : 'info'
        );
      }
    });
  }

  // ── Open Modal ──
  document.addEventListener('click', function (e) {
    const trigger = e.target.closest('[data-media-picker-trigger]');
    if (!trigger) return;

    e.preventDefault();
    activePicker = trigger.closest('[data-media-picker]');
    const multiple = activePicker?.dataset.mediaMultiple === 'true';
    selectedMedia = multiple ? [] : null;

    // Set accept filter from picker
    const accept = activePicker?.dataset.mediaAccept || '';
    if (accept && accept !== 'all') {
      currentType = accept;
      typeTabs.forEach(function (tab) {
        tab.classList.toggle('active', tab.dataset.mediaType === accept);
      });
    } else {
      currentType = '';
      typeTabs.forEach(function (tab) {
        tab.classList.toggle('active', tab.dataset.mediaType === '');
      });
    }

    // Get current value to pre-select
    const input = activePicker?.querySelector('[data-media-picker-input]');
    const currentValue = input ? input.value : '';

    resetManage();
    updateSelectBtn();
    currentPage = 1;
    if (searchInput) searchInput.value = '';
    currentSearch = '';

    openModal('mediaLibraryModal');
    loadMedia(false, currentValue);
  });

  // ── Remove Media ──
  document.addEventListener('click', function (e) {
    const removeBtn = e.target.closest('[data-media-picker-remove]');
    if (!removeBtn) return;

    e.preventDefault();
    const picker = removeBtn.closest('[data-media-picker]');
    if (!picker) return;

    const input = picker.querySelector('[data-media-picker-input]');
    const preview = picker.querySelector('[data-media-picker-preview]');

    if (input) {
      input.value = '';
      input.dispatchEvent(new Event('input', { bubbles: true }));
    }
    if (preview) preview.innerHTML = '';

    // Update actions
    updateBrowseButton(picker, false);
    removeBtn.remove();
  });

  // ── Type Tabs ──
  typeTabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      typeTabs.forEach(function (t) { t.classList.remove('active'); });
      this.classList.add('active');
      currentType = this.dataset.mediaType;
      currentPage = 1;
      loadMedia(false);
    });
  });

  // ── Search ──
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(function () {
        currentSearch = searchInput.value.trim();
        currentPage = 1;
        loadMedia(false);
      }, 300);
    });
  }

  // ── Load More ──
  if (loadMoreBtn) {
    loadMoreBtn.addEventListener('click', function () {
      if (hasMore && !isLoading) {
        currentPage++;
        loadMedia(true);
      }
    });
  }

  // Auto load more when scrolling near bottom of gallery
  const modalBody = modal.querySelector('.media-modal-body');
  if (modalBody) {
    modalBody.addEventListener('scroll', function () {
      if (hasMore && !isLoading) {
        if (modalBody.scrollTop + modalBody.clientHeight >= modalBody.scrollHeight - 120) {
          currentPage++;
          loadMedia(true);
        }
      }
    });
  }

  // ── Hover Color Inspection on Grid Images ──
  grid.addEventListener('mousemove', function (e) {
    if (!galleryColorPickerActive) return;
    const img = e.target.closest('img') || e.target.closest('[data-media-item]')?.querySelector('img');
    if (!img) {
      if (window.ImageColorPicker?.loupe) window.ImageColorPicker.loupe.hide();
      return;
    }
    if (window.ImageColorPicker?.loupe) {
      window.ImageColorPicker.loupe.show(e.clientX, e.clientY);
      window.ImageColorPicker.loupe.renderPixel(img, e, false);
    }
  });

  grid.addEventListener('mouseleave', function () {
    if (window.ImageColorPicker?.loupe) {
      window.ImageColorPicker.loupe.hide();
    }
  });

  // ── Select Item (delegated) ──
  grid.addEventListener('click', function (e) {
    // If color picker mode is active on gallery, copy color and do NOT select item
    if (galleryColorPickerActive) {
      e.preventDefault();
      e.stopPropagation();
      const img = e.target.closest('img') || e.target.closest('[data-media-item]')?.querySelector('img');
      if (img && window.ImageColorPicker?.loupe) {
        const sample = window.ImageColorPicker.loupe.renderPixel(img, e, false);
        if (sample && sample.hex) {
          window.ImageColorPicker.copyColorToClipboard(sample.hex, sample.colorName, true);
        }
      }
      // Disable color selection & hide loupe
      galleryColorPickerActive = false;
      updateColorPickerToggle();
      if (window.ImageColorPicker?.loupe) {
        window.ImageColorPicker.loupe.hide();
      }
      // Automatically close all modals
      closeModal('mediaLibraryModal');
      if (typeof window.closeAllModals === 'function') {
        window.closeAllModals();
      }
      return;
    }

    const item = e.target.closest('[data-media-item]');
    if (!item) return;

    if (e.target.closest('.media-grid-item-delete')) {
      e.stopPropagation();
      askDelete([String(JSON.parse(item.dataset.mediaItem).id)]);
      return;
    }

    if (manageMode) {
      item.classList.toggle('checked');
      renderManageBar();
      return;
    }

    const media = JSON.parse(item.dataset.mediaItem);
    if (activePicker?.dataset.mediaMultiple === 'true') {
      const existingIndex = selectedMedia.findIndex(function (selected) { return String(selected.id) === String(media.id); });
      if (existingIndex >= 0) {
        selectedMedia.splice(existingIndex, 1);
        item.classList.remove('selected');
      } else {
        selectedMedia.push(media);
        item.classList.add('selected');
      }
    } else {
      grid.querySelectorAll('[data-media-item]').forEach(function (el) {
        el.classList.remove('selected');
      });
      item.classList.add('selected');
      selectedMedia = media;
    }
    updateSelectBtn();
  });

  // ── Double Click Item to Select Immediately ──
  grid.addEventListener('dblclick', function (e) {
    if (galleryColorPickerActive || manageMode) return;
    const item = e.target.closest('[data-media-item]');
    if (!item) return;
    if (activePicker?.dataset.mediaMultiple === 'true') return;

    try {
      const media = JSON.parse(item.dataset.mediaItem);
      selectedMedia = media;
      if (selectBtn) {
        selectBtn.disabled = false;
        selectBtn.click();
      }
    } catch (err) {
      console.error(err);
    }
  });

  // ── Confirm Select ──
  selectBtn.addEventListener('click', function () {
    if (!selectedMedia || (Array.isArray(selectedMedia) && selectedMedia.length === 0)) return;

    if (typeof customCallback === 'function') {
      const cb = customCallback;
      customCallback = null;
      cb(selectedMedia);
      closeModal('mediaLibraryModal');
      return;
    }

    if (!activePicker) return;

    if (activePicker.dataset.mediaMultiple === 'true') {
      activePicker.dispatchEvent(new CustomEvent('media-picker:selected', {
        bubbles: true,
        detail: { media: selectedMedia }
      }));
      closeModal('mediaLibraryModal');
      return;
    }

    const input = activePicker.querySelector('[data-media-picker-input]');
    const preview = activePicker.querySelector('[data-media-picker-preview]');

    if (input) {
      const valType = activePicker.dataset.mediaValueType || 'id';
      input.value = valType === 'url' ? selectedMedia.url : selectedMedia.id;
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    if (preview) {
      preview.innerHTML = '';
      if (selectedMedia.type === 'image') {
        var img = document.createElement('img');
        img.src = selectedMedia.url;
        img.alt = selectedMedia.name || '';
        preview.appendChild(img);
      } else if (selectedMedia.type === 'video') {
        var video = document.createElement('video');
        video.src = selectedMedia.url;
        video.controls = true;
        video.muted = true;
        video.preload = 'metadata';
        preview.appendChild(video);
      } else {
        var file = document.createElement('div');
        file.className = 'media-picker-file-icon';
        var icon = document.createElement('i');
        icon.className = getFileIcon(selectedMedia.extension);
        var label = document.createElement('span');
        label.textContent = selectedMedia.original_name || '';
        file.appendChild(icon);
        file.appendChild(label);
        preview.appendChild(file);
      }
    }

    activePicker.dispatchEvent(new CustomEvent('media-picker:selected', {
      bubbles: true,
      detail: { media: selectedMedia }
    }));

    updateBrowseButton(activePicker, true);

    // Ensure remove button exists
    const actions = activePicker.querySelector('.media-picker-actions');
    if (actions && !actions.querySelector('[data-media-picker-remove]')) {
      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'media-picker-remove';
      removeBtn.setAttribute('data-media-picker-remove', '');
      removeBtn.innerHTML = '<i class="ph ph-x"></i> Remove';
      actions.appendChild(removeBtn);
    }

    closeModal('mediaLibraryModal');
  });

  // ── Upload Zone ──
  if (uploadZone && uploadInput) {
    // Click to browse
    uploadZone.addEventListener('click', function (e) {
      if (e.target.closest('input')) return;
      uploadInput.click();
    });

    // Drag and drop
    uploadZone.addEventListener('dragover', function (e) {
      e.preventDefault();
      this.classList.add('drag-active');
    });

    uploadZone.addEventListener('dragleave', function () {
      this.classList.remove('drag-active');
    });

    uploadZone.addEventListener('drop', function (e) {
      e.preventDefault();
      this.classList.remove('drag-active');
      if (e.dataTransfer.files.length) {
        uploadFiles(e.dataTransfer.files);
      }
    });

    // File input change
    uploadInput.addEventListener('change', function () {
      if (this.files.length) {
        uploadFiles(this.files);
        this.value = '';
      }
    });
  }

  // ── Load Media via AJAX ──
  function loadMedia(append, preSelectId) {
    if (append && isLoading) return;
    if (loadController) loadController.abort();
    loadController = new AbortController();
    var seq = ++loadSeq;
    isLoading = true;

    if (!append) {
      grid.innerHTML = '';
      showLoading(true);
    }

    var params = new URLSearchParams();
    params.set('page', currentPage);
    if (currentType) params.set('type', currentType);
    if (currentSearch) params.set('search', currentSearch);

    fetch(browseUrl + '?' + params.toString(), {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      signal: loadController.signal
    })
    .then(function (res) {
      if (!res.ok) throw new Error(res.status);
      return res.json();
    })
    .then(function (json) {
      if (seq !== loadSeq) return;
      isLoading = false;
      showLoading(false);

      if (!json.success) {
        showEmpty(true);
        showLoadMore(false);
        return;
      }

      var items = json.data.items;
      hasMore = json.data.has_more;

      if (items.length === 0 && !append) {
        showEmpty(true);
        showLoadMore(false);
        return;
      }

      showEmpty(false);
      showLoadMore(hasMore);

      items.forEach(function (item) {
        var el = createGridItem(item);
        if (preSelectId && String(item.id) === String(preSelectId)) {
          el.classList.add('selected');
          selectedMedia = item;
          updateSelectBtn();
        }
        grid.appendChild(el);
      });
      renderManageBar();
    })
    .catch(function (err) {
      if (seq !== loadSeq || err?.name === 'AbortError') return;
      isLoading = false;
      showLoading(false);
      showEmpty(true);
      showLoadMore(false);
    });
  }

  // ── Upload Files ──
  function uploadFiles(files) {
    var errors = [];
    files = Array.from(files).filter(function (file) {
      var problem = validateUpload(file);
      if (problem) errors.push(file.name + ': ' + problem);
      return !problem;
    });
    showUploadError(errors);
    if (files.length === 0) return;

    var total = files.length;
    var completed = 0;

    showUploadProgress(true);
    updateUploadBar(0);

    Array.from(files).forEach(function (file) {
      var formData = new FormData();
      formData.append('file', file);

      var xhr = new XMLHttpRequest();
      xhr.open('POST', uploadUrl);
      xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

      xhr.upload.addEventListener('progress', function (e) {
        if (e.lengthComputable) {
          var fileProgress = (e.loaded / e.total) * 100;
          var totalProgress = ((completed * 100) + fileProgress) / total;
          updateUploadBar(totalProgress);
        }
      });

      xhr.addEventListener('load', function () {
        completed++;
        updateUploadBar((completed / total) * 100);

        if (xhr.status === 201 || xhr.status === 200) {
          var json = JSON.parse(xhr.responseText);
          if (json.success && json.data) {
            var el = createGridItem(json.data);
            grid.insertBefore(el, grid.firstChild);
            showEmpty(false);

            // Auto-select the last uploaded file
            if (completed === total && activePicker?.dataset.mediaMultiple !== 'true') {
              grid.querySelectorAll('[data-media-item]').forEach(function (item) {
                item.classList.remove('selected');
              });
              el.classList.add('selected');
              selectedMedia = json.data;
              updateSelectBtn();
            } else if (activePicker?.dataset.mediaMultiple === 'true') {
              selectedMedia.push(json.data);
              el.classList.add('selected');
              updateSelectBtn();
            }
          }
        } else {
          errors.push(file.name + ': ' + uploadErrorMessage(xhr));
          showUploadError(errors);
        }

        if (completed === total) {
          setTimeout(function () { showUploadProgress(false); }, 500);
        }
      });

      xhr.addEventListener('error', function () {
        errors.push(file.name + ': Upload failed. Check your connection and try again.');
        showUploadError(errors);
        completed++;
        if (completed === total) {
          setTimeout(function () { showUploadProgress(false); }, 500);
        }
      });

      xhr.send(formData);
    });
  }

  // ── Upload Validation ──
  var UPLOAD_LIMITS_MB = { image: 10, video: 200, document: 25 };
  var UPLOAD_KINDS = {
    image: ['jpg', 'jpeg', 'png', 'gif', 'webp', 'ico'],
    video: ['mp4', 'mov', 'webm', 'm4v'],
    document: ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'ppt', 'pptx', 'zip']
  };

  function uploadKind(file) {
    var ext = (file.name.split('.').pop() || '').toLowerCase();
    return Object.keys(UPLOAD_KINDS).find(function (kind) { return UPLOAD_KINDS[kind].includes(ext); }) || null;
  }

  function validateUpload(file) {
    var kind = uploadKind(file);
    if (!kind) return 'This file type is not supported.';
    if (file.size > UPLOAD_LIMITS_MB[kind] * 1024 * 1024) return 'Too large (max ' + UPLOAD_LIMITS_MB[kind] + ' MB for ' + kind + 's).';
    return null;
  }

  function uploadErrorMessage(xhr) {
    if (xhr.status === 413) return 'File is larger than the server upload limit.';
    try {
      var json = JSON.parse(xhr.responseText);
      return json.errors?.file?.[0] || json.message || 'Upload failed.';
    } catch (e) {
      return 'Upload failed (' + xhr.status + ').';
    }
  }

  function showUploadError(errors) {
    if (!uploadError) return;
    uploadError.textContent = errors.join(' ');
    uploadError.classList.toggle('hidden', errors.length === 0);
  }

  if (uploadInput) {
    uploadInput.setAttribute('accept', Object.values(UPLOAD_KINDS).flat().map(function (ext) { return '.' + ext; }).join(','));
  }

  // ── Create Grid Item ──
  function createGridItem(item) {
    var div = document.createElement('div');
    div.className = 'media-grid-item';
    div.setAttribute('data-media-item', JSON.stringify(item));

    if (item.type === 'image') {
      var img = document.createElement('img');
      img.src = item.thumbnail_url;
      img.alt = item.name || '';
      img.loading = 'lazy';
      img.crossOrigin = 'anonymous';
      div.appendChild(img);
    } else if (item.type === 'video') {
      var wrap = document.createElement('div');
      wrap.className = 'media-grid-item-video';
      var video = document.createElement('video');
      video.src = item.url + '#t=0.5';
      video.muted = true;
      video.playsInline = true;
      video.preload = 'metadata';
      var badge = document.createElement('i');
      badge.className = 'ph-fill ph-play-circle media-grid-item-play';
      wrap.appendChild(video);
      wrap.appendChild(badge);
      div.appendChild(wrap);
    } else {
      var iconWrap = document.createElement('div');
      iconWrap.className = 'media-grid-item-icon';
      var icon = document.createElement('i');
      icon.className = getFileIcon(item.extension);
      iconWrap.appendChild(icon);
      div.appendChild(iconWrap);
    }

    var check = document.createElement('span');
    check.className = 'media-grid-item-check';
    check.innerHTML = '<i class="ph-bold ph-check text-xs"></i>';
    div.appendChild(check);

    var del = document.createElement('button');
    del.type = 'button';
    del.className = 'media-grid-item-delete';
    del.title = 'Delete';
    del.setAttribute('aria-label', 'Delete ' + (item.name || 'file'));
    del.innerHTML = '<i class="ph ph-trash text-sm"></i>';
    div.appendChild(del);

    var info = document.createElement('div');
    info.className = 'media-grid-item-info';
    var name = document.createElement('span');
    name.textContent = item.type === 'image' ? (item.name || '') : (item.name || '') + '.' + item.extension;
    var size = document.createElement('span');
    size.className = 'text-neutral-400';
    size.textContent = item.human_size || '';
    info.appendChild(name);
    info.appendChild(size);
    div.appendChild(info);

    return div;
  }

  // ── File Icon ──
  function getFileIcon(ext) {
    var map = {
      pdf: 'ph ph-file-pdf', doc: 'ph ph-file-doc', docx: 'ph ph-file-doc',
      xls: 'ph ph-file-xls', xlsx: 'ph ph-file-xls', csv: 'ph ph-file-csv',
      ppt: 'ph ph-file-ppt', pptx: 'ph ph-file-ppt',
      zip: 'ph ph-file-zip', rar: 'ph ph-file-zip', tar: 'ph ph-file-zip',
      mp4: 'ph ph-file-video', avi: 'ph ph-file-video', mov: 'ph ph-file-video',
      mp3: 'ph ph-file-audio', wav: 'ph ph-file-audio',
      svg: 'ph ph-file-svg', txt: 'ph ph-file-text'
    };
    return map[ext] || 'ph ph-file';
  }

  // ── UI Helpers ──
  // ── Manage / Delete ──
  function checkedIds() {
    return Array.from(grid.querySelectorAll('[data-media-item].checked')).map(function (el) {
      return String(JSON.parse(el.dataset.mediaItem).id);
    });
  }

  function renderManageBar() {
    if (!manageBar) return;
    var show = manageMode || pendingDelete !== null;
    manageBar.classList.toggle('hidden', !show);
    manageSelect.classList.toggle('hidden', pendingDelete !== null);
    manageConfirm.classList.toggle('hidden', pendingDelete === null);
    var count = checkedIds().length;
    manageCount.textContent = count;
    manageDeleteBtn.disabled = count === 0;
    if (pendingDelete !== null) {
      confirmText.textContent = confirmText.dataset.template.replace(':count', pendingDelete.length);
    }
  }

  function resetManage() {
    manageMode = false;
    pendingDelete = null;
    grid.classList.remove('is-managing');
    if (manageToggle) manageToggle.setAttribute('aria-pressed', 'false');
    grid.querySelectorAll('[data-media-item].checked').forEach(function (el) { el.classList.remove('checked'); });
    renderManageBar();
    updateSelectBtn();
  }

  function askDelete(ids) {
    pendingDelete = ids;
    renderManageBar();
  }

  function removeDeleted(ids) {
    var gone = new Set(ids.map(String));
    grid.querySelectorAll('[data-media-item]').forEach(function (el) {
      if (gone.has(String(JSON.parse(el.dataset.mediaItem).id))) el.remove();
    });
    if (Array.isArray(selectedMedia)) {
      selectedMedia = selectedMedia.filter(function (m) { return !gone.has(String(m.id)); });
    } else if (selectedMedia && gone.has(String(selectedMedia.id))) {
      selectedMedia = null;
    }
    showEmpty(grid.querySelectorAll('[data-media-item]').length === 0);
  }

  function performDelete() {
    var ids = pendingDelete || [];
    if (!ids.length || !deleteUrl) return;
    confirmYes.disabled = true;

    fetch(deleteUrl, {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ ids: ids })
    })
    .then(function (res) {
      if (!res.ok) throw new Error(res.status);
      return res.json();
    })
    .then(function () {
      showUploadError([]);
      removeDeleted(ids);
      pendingDelete = null;
      renderManageBar();
      updateSelectBtn();
    })
    .catch(function () {
      showUploadError(['Could not delete the selected files. Please try again.']);
      pendingDelete = null;
      renderManageBar();
    })
    .finally(function () {
      confirmYes.disabled = false;
    });
  }

  if (manageToggle) {
    manageToggle.addEventListener('click', function () {
      var next = !manageMode;
      resetManage();
      manageMode = next;
      grid.classList.toggle('is-managing', manageMode);
      manageToggle.setAttribute('aria-pressed', manageMode ? 'true' : 'false');
      renderManageBar();
      updateSelectBtn();
    });
    manageAllBtn.addEventListener('click', function () {
      var items = grid.querySelectorAll('[data-media-item]');
      var allChecked = items.length > 0 && checkedIds().length === items.length;
      items.forEach(function (el) { el.classList.toggle('checked', !allChecked); });
      renderManageBar();
    });
    manageDeleteBtn.addEventListener('click', function () {
      var ids = checkedIds();
      if (ids.length) askDelete(ids);
    });
    confirmYes.addEventListener('click', performDelete);
    confirmNo.addEventListener('click', function () {
      pendingDelete = null;
      renderManageBar();
    });
  }

  function updateSelectBtn() {
    if (selectBtn) selectBtn.disabled = manageMode || (Array.isArray(selectedMedia) ? selectedMedia.length === 0 : !selectedMedia);
  }

  function updateBrowseButton(picker, hasSelection) {
    const browseBtn = picker?.querySelector('[data-media-picker-trigger]');
    if (!browseBtn) return;

    browseBtn.innerHTML = '<i class="ph ph-folder-open"></i> ' + (hasSelection ? 'Change' : 'Browse Media');
  }

  function showLoading(show) {
    if (loadingState) loadingState.classList.toggle('hidden', !show);
  }

  function showEmpty(show) {
    if (emptyState) emptyState.classList.toggle('hidden', !show);
  }

  function showLoadMore(show) {
    if (loadMoreWrap) loadMoreWrap.classList.toggle('hidden', !show);
  }

  function showUploadProgress(show) {
    if (uploadProgress) uploadProgress.classList.toggle('hidden', !show);
    var content = uploadZone?.querySelector('.media-upload-zone-content');
    if (content) content.classList.toggle('hidden', show);
  }

  function updateUploadBar(percent) {
    if (uploadBar) uploadBar.style.width = Math.round(percent) + '%';
  }

  // Reset color picker when media modal is closed
  document.addEventListener('modal:closed', function (e) {
    if (e.detail?.id === 'mediaLibraryModal') {
      customCallback = null;
      galleryColorPickerActive = false;
      updateColorPickerToggle();
      if (window.ImageColorPicker?.loupe) {
        window.ImageColorPicker.loupe.hide();
      }
    }
  });

  // Automatically close media modal and disable color picker whenever any color is picked
  window.addEventListener('color-picked', function () {
    if (galleryColorPickerActive) {
      galleryColorPickerActive = false;
      updateColorPickerToggle();
    }
    if (window.ImageColorPicker?.loupe) {
      window.ImageColorPicker.loupe.hide();
    }
    closeModal('mediaLibraryModal');
  });
});
