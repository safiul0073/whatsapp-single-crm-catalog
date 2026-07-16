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
  const selectBtn = modal.querySelector('[data-media-select-btn]');
  const emptyState = modal.querySelector('[data-media-empty]');
  const loadingState = modal.querySelector('[data-media-loading]');
  const loadMoreWrap = modal.querySelector('[data-media-load-more]');
  const loadMoreBtn = loadMoreWrap ? loadMoreWrap.querySelector('button') : null;

  const browseUrl = modal.dataset.browseUrl;
  const uploadUrl = modal.dataset.uploadUrl;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

  // State
  let activePicker = null;    // The picker element that opened the modal
  let selectedMedia = null;   // Selected media object, or an array in multi-select mode
  let currentType = '';       // Type filter
  let currentSearch = '';     // Search query
  let currentPage = 1;
  let hasMore = false;
  let isLoading = false;
  let searchTimeout = null;

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

  // ── Select Item (delegated) ──
  grid.addEventListener('click', function (e) {
    const item = e.target.closest('[data-media-item]');
    if (!item) return;

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

  // ── Confirm Select ──
  selectBtn.addEventListener('click', function () {
    if (!selectedMedia || !activePicker || (Array.isArray(selectedMedia) && selectedMedia.length === 0)) return;

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
      input.value = selectedMedia.id;
      input.dispatchEvent(new Event('input', { bubbles: true }));
    }

    if (preview) {
      if (selectedMedia.type === 'image') {
        preview.innerHTML = '<img src="' + selectedMedia.url + '" alt="' + (selectedMedia.name || '') + '">';
      } else {
        preview.innerHTML = '<div class="media-picker-file-icon"><i class="ph ph-file-text"></i><span>' + selectedMedia.original_name + '</span></div>';
      }
    }

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
    if (isLoading) return;
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
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (res) {
      if (!res.ok) throw new Error(res.status);
      return res.json();
    })
    .then(function (json) {
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
    })
    .catch(function () {
      isLoading = false;
      showLoading(false);
      showEmpty(true);
      showLoadMore(false);
    });
  }

  // ── Upload Files ──
  function uploadFiles(files) {
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
        }

        if (completed === total) {
          setTimeout(function () { showUploadProgress(false); }, 500);
        }
      });

      xhr.addEventListener('error', function () {
        completed++;
        if (completed === total) {
          setTimeout(function () { showUploadProgress(false); }, 500);
        }
      });

      xhr.send(formData);
    });
  }

  // ── Create Grid Item ──
  function createGridItem(item) {
    var div = document.createElement('div');
    div.className = 'media-grid-item';
    div.setAttribute('data-media-item', JSON.stringify(item));

    if (item.type === 'image') {
      div.innerHTML = '<img src="' + item.thumbnail_url + '" alt="' + (item.name || '') + '" loading="lazy">' +
        '<div class="media-grid-item-info"><span>' + item.name + '</span><span class="text-neutral-400">' + item.human_size + '</span></div>';
    } else {
      var iconClass = getFileIcon(item.extension);
      div.innerHTML = '<div class="media-grid-item-icon"><i class="' + iconClass + '"></i></div>' +
        '<div class="media-grid-item-info"><span>' + item.name + '.' + item.extension + '</span><span class="text-neutral-400">' + item.human_size + '</span></div>';
    }

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
  function updateSelectBtn() {
    if (selectBtn) selectBtn.disabled = Array.isArray(selectedMedia) ? selectedMedia.length === 0 : !selectedMedia;
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
});
