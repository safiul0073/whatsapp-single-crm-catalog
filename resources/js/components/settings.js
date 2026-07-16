/**
 * Settings Page Component
 * Handles tab navigation, sidebar search, and mobile bottom sheet.
 */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  const navItems = document.querySelectorAll('[data-settings-nav]');
  if (!navItems.length) return; // Not on settings page

  const sections = document.querySelectorAll('[data-settings-group]');
  const searchInput = document.getElementById('settingsNavSearch');
  const activeTabInput = document.getElementById('activeTab');
  const settingsForm = document.getElementById('settingsForm');

  // Mobile elements
  const mobileBtn = document.getElementById('settingsMobileNavBtn');
  const sheet = document.getElementById('settingsSheet');
  const sheetOverlay = document.getElementById('settingsSheetOverlay');
  const sheetItems = document.querySelectorAll('[data-sheet-nav]');
  const sheetSearch = document.getElementById('settingsSheetSearch');

  function getSettingFieldValue(key) {
    const input = document.querySelector(`[name="settings[${key}]"]`);
    if (!input) return null;

    if (input.type === 'checkbox') {
      return input.checked ? (input.value || '1') : '0';
    }

    return String(input.value ?? '');
  }

  function setConditionalContainerState(container, visible) {
    container.classList.toggle('is-hidden', !visible);
    container.style.display = visible ? '' : 'none';

    container.querySelectorAll('[name]').forEach(function (field) {
      if (!field.dataset.initialDisabled) {
        field.dataset.initialDisabled = field.disabled ? '1' : '0';
      }

      if (visible) {
        field.disabled = field.dataset.initialDisabled === '1';
      } else {
        field.disabled = true;
      }
    });
  }

  function applyConditionalSettings() {
    const conditionalItems = document.querySelectorAll('[data-visible-if]');
    if (!conditionalItems.length) return;

    conditionalItems.forEach(function (item) {
      let conditions = null;

      try {
        conditions = JSON.parse(item.dataset.visibleIf || '{}');
      } catch (err) {
        conditions = null;
      }

      if (!conditions || typeof conditions !== 'object') return;

      const isVisible = Object.entries(conditions).every(function ([sourceKey, expected]) {
        const currentValue = getSettingFieldValue(sourceKey);
        const expectedValues = Array.isArray(expected) ? expected : [expected];

        return expectedValues.map(String).includes(String(currentValue ?? ''));
      });

      setConditionalContainerState(item, isVisible);
    });
  }

  // ── Tab Switching ──
  function switchTab(group) {
    navItems.forEach(function (n) { n.classList.toggle('active', n.dataset.settingsNav === group); });
    sheetItems.forEach(function (n) { n.classList.toggle('active', n.dataset.sheetNav === group); });
    sections.forEach(function (s) {
      const isActive = s.dataset.settingsGroup === group;

      s.classList.toggle('is-hidden', !isActive);
      s.style.display = isActive ? '' : 'none';
    });
    applyConditionalSettings();
    if (activeTabInput) activeTabInput.value = group;
    window.location.hash = group;
  }

  // Restore active tab from URL hash
  var hash = window.location.hash.replace('#', '');
  if (hash && document.querySelector('[data-settings-group="' + hash + '"]')) {
    switchTab(hash);
  }

  applyConditionalSettings();

  document.querySelectorAll('[data-tile-select]').forEach(function (group) {
    const input = group.querySelector('[data-tile-select-input]');

    group.querySelectorAll('[data-tile-select-option]').forEach(function (option) {
      option.addEventListener('click', function () {
        if (!input) return;

        input.value = option.dataset.value || '';
        group.querySelectorAll('[data-tile-select-option]').forEach(function (item) {
          item.classList.toggle('active', item === option);
        });
        input.dispatchEvent(new Event('change', { bubbles: true }));
        applyConditionalSettings();
      });
    });
  });

  document.querySelectorAll('.setting-color-field').forEach(function (field) {
    const swatch = field.querySelector('[data-color-source]');
    const input = field.querySelector('[data-color-input]');

    if (!swatch || !input) return;

    swatch.addEventListener('input', function () {
      input.value = swatch.value;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });

    input.addEventListener('input', function () {
      swatch.value = input.value;
    });
  });

  document.querySelectorAll('[data-copy-button]').forEach(function (button) {
    button.addEventListener('click', async function () {
      const control = button.closest('.setting-copy-control');
      const input = control?.querySelector('[data-copy-source]');

      if (!input) return;

      try {
        await navigator.clipboard.writeText(input.value);
        button.classList.add('copied');
        button.querySelector('span').textContent = 'Copied';

        window.setTimeout(function () {
          button.classList.remove('copied');
          button.querySelector('span').textContent = 'Copy';
        }, 1600);
      } catch (err) {
        input.select();
        document.execCommand('copy');
      }
    });
  });

  // Desktop nav click
  navItems.forEach(function (item) {
    item.addEventListener('click', function (e) {
      e.preventDefault();
      switchTab(this.dataset.settingsNav);
      var scroll = document.getElementById('settingsScroll');
      if (scroll) scroll.scrollTop = 0;
    });
  });

  // ── Sidebar Search ──
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      var q = this.value.toLowerCase().trim();
      navItems.forEach(function (item) {
        var label = item.dataset.searchLabel || '';
        item.style.display = (!q || label.indexOf(q) !== -1) ? '' : 'none';
      });
    });
  }

  // ── Mobile Bottom Sheet ──
  function openSheet() {
    if (!sheet) return;
    sheet.classList.add('active');
    if (sheetOverlay) sheetOverlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeSheet() {
    if (!sheet) return;
    sheet.classList.remove('active');
    if (sheetOverlay) sheetOverlay.classList.remove('active');
    document.body.style.overflow = '';
    if (sheetSearch) {
      sheetSearch.value = '';
      sheetItems.forEach(function (item) { item.style.display = ''; });
    }
  }

  if (mobileBtn) {
    mobileBtn.addEventListener('click', openSheet);
  }

  if (sheetOverlay) {
    sheetOverlay.addEventListener('click', closeSheet);
  }

  // Sheet nav item click
  sheetItems.forEach(function (item) {
    item.addEventListener('click', function (e) {
      e.preventDefault();
      switchTab(this.dataset.sheetNav);
      closeSheet();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  });

  // Sheet search
  if (sheetSearch) {
    sheetSearch.addEventListener('input', function () {
      var q = this.value.toLowerCase().trim();
      sheetItems.forEach(function (item) {
        var label = item.dataset.searchLabel || '';
        item.style.display = (!q || label.indexOf(q) !== -1) ? '' : 'none';
      });
    });
  }

  if (settingsForm) {
    settingsForm.addEventListener('change', function (e) {
      if (e.target && e.target.name && e.target.name.startsWith('settings[')) {
        applyConditionalSettings();
      }
    });
  }

  // ── Swipe-down to close sheet ──
  var touchStartY = 0;
  var touchCurrentY = 0;
  var isDragging = false;

  if (sheet) {
    sheet.addEventListener('touchstart', function (e) {
      var sheetList = document.getElementById('settingsSheetList');
      if (sheetList && sheetList.scrollTop > 0) return;
      touchStartY = e.touches[0].clientY;
      isDragging = true;
    }, { passive: true });

    sheet.addEventListener('touchmove', function (e) {
      if (!isDragging) return;
      touchCurrentY = e.touches[0].clientY;
      var diff = touchCurrentY - touchStartY;
      if (diff > 0) {
        sheet.style.transform = 'translateY(' + diff + 'px)';
        sheet.style.transition = 'none';
      }
    }, { passive: true });

    sheet.addEventListener('touchend', function () {
      if (!isDragging) return;
      isDragging = false;
      var diff = touchCurrentY - touchStartY;
      sheet.style.transition = '';
      sheet.style.transform = '';
      if (diff > 80) {
        closeSheet();
      }
    }, { passive: true });
  }
});
