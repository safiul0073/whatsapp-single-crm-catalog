function bootFrontendMenuBuilder() {
  const form = document.getElementById('frontendMenuForm');
  const configElement = document.getElementById('frontendMenuBuilderConfig');

  if (!form || !configElement) {
    return;
  }

  const payloadInput = document.getElementById('menuItemsPayload');
  const builderList = document.getElementById('menuBuilderList');
  const emptyState = document.getElementById('menuBuilderEmpty');
  const inspectorEmpty = document.getElementById('menuInspectorEmpty');
  const inspectorFields = document.getElementById('menuInspectorFields');
  const labelInput = document.getElementById('inspectorLabel');
  const typeInput = document.getElementById('inspectorType');
  const parentInput = document.getElementById('inspectorParentKey');
  const parentHint = document.getElementById('inspectorParentHint');
  const pageWrap = document.getElementById('inspectorPageWrap');
  const pageInput = document.getElementById('inspectorPageId');
  const urlWrap = document.getElementById('inspectorUrlWrap');
  const urlInput = document.getElementById('inspectorUrl');
  const targetWrap = document.getElementById('inspectorTargetWrap');
  const targetInput = document.getElementById('inspectorTarget');
  const visibleInput = document.getElementById('inspectorVisible');
  const updateButton = document.getElementById('inspectorUpdate');
  const openAddModalButton = document.getElementById('openAddMenuItemModal');

  const modalForm = document.getElementById('addMenuItemForm');
  const modalType = document.getElementById('modalItemType');
  const modalParent = document.getElementById('modalParentKey');
  const modalPageFields = document.getElementById('modalPageFields');
  const modalPageId = document.getElementById('modalPageId');
  const modalPageLabel = document.getElementById('modalPageLabel');
  const modalExternalFields = document.getElementById('modalExternalFields');
  const modalExternalLabel = document.getElementById('modalExternalLabel');
  const modalExternalUrl = document.getElementById('modalExternalUrl');
  const modalExternalTarget = document.getElementById('modalExternalTarget');
  const modalGroupFields = document.getElementById('modalGroupFields');
  const modalGroupLabel = document.getElementById('modalGroupLabel');

  if (
    !payloadInput || !builderList || !emptyState || !inspectorEmpty || !inspectorFields ||
    !labelInput || !typeInput || !parentInput || !pageInput || !urlInput || !targetInput ||
    !visibleInput || !updateButton || !openAddModalButton || !modalForm || !modalType ||
    !modalParent || !modalPageFields || !modalPageId || !modalPageLabel ||
    !modalExternalFields || !modalExternalLabel || !modalExternalUrl ||
    !modalExternalTarget || !modalGroupFields || !modalGroupLabel
  ) {
    return;
  }

  let config = null;

  try {
    config = JSON.parse(configElement.textContent || '{}');
  } catch (error) {
    console.error('Failed to parse frontend menu builder config.', error);
    return;
  }

  const pageOptions = config.pageOptions || {};
  const pageLinkableType = config.pageLinkableType || '';
  const strings = {
    untitledItem: 'Untitled item',
    internalPage: 'Internal Page',
    customUrl: 'Custom URL',
    groupParent: 'Group Parent',
    linkedPage: 'Linked page',
    parentGroupDescription: 'Parent group for nested menu items',
    submenuItems: 'Submenu Items',
    item: 'item',
    items: 'items',
    hidden: 'Hidden',
    addChild: 'Add Child',
    parent: 'Parent',
    noSubmenuItems: 'No submenu items yet. Add a child item if this should open a submenu.',
    rootLevel: 'Root level',
    moveChildrenFirst: 'Move or remove submenu items first if you want to change this parent item to another level.',
    chooseParentOrRoot: 'Choose a parent root item to turn this into a submenu item, or keep it at the root level.',
    addAtRootLevel: 'Add at root level',
    ...config.strings,
  };

  const modalCloseButton = modalForm.querySelector('[data-modal-close="addMenuItemModal"]');
  let items = ensureStructure(config.bootItems || []);
  let selectedKey = null;
  let draggedRootKey = null;

  function clone(value) {
    return JSON.parse(JSON.stringify(value));
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function ensureStructure(list) {
    return (Array.isArray(list) ? list : []).map((item, index) => ({
      id: item.id || null,
      temp_key: item.temp_key || `menu-item-${index}-${Date.now()}`,
      item_type: item.item_type || 'external',
      label: item.label || '',
      linkable_type: item.linkable_type || null,
      linkable_id: item.linkable_id || null,
      url: item.url || '',
      target: item.target || '_self',
      is_visible: item.is_visible !== false,
      children: ensureStructure(item.children || []).slice(0, 50),
    }));
  }

  function persist() {
    payloadInput.value = JSON.stringify(items);
  }

  function findNode(key, collection, parentCollection) {
    for (let index = 0; index < collection.length; index += 1) {
      const item = collection[index];

      if (item.temp_key === key) {
        return { item, index, collection, parentCollection: parentCollection || null };
      }

      const nested = findNode(key, item.children || [], collection);
      if (nested) {
        return nested;
      }
    }

    return null;
  }

  function rootItemsForSelect(excludeKey) {
    return items
      .filter((item) => item.temp_key !== excludeKey)
      .map((item) => ({
        key: item.temp_key,
        label: item.label || strings.untitledItem,
      }));
  }

  function typeLabel(type) {
    return ({
      internal: strings.internalPage,
      external: strings.customUrl,
      group: strings.groupParent,
    })[type] || type;
  }

  function itemDescription(item) {
    if (item.item_type === 'internal') {
      return pageOptions[item.linkable_id] || strings.linkedPage;
    }

    if (item.item_type === 'external') {
      return item.url || strings.customUrl;
    }

    return strings.parentGroupDescription;
  }

  function replaceOptions(select, options, placeholder, selectedValue) {
    select.innerHTML = '';

    const placeholderOption = document.createElement('option');
    placeholderOption.value = '';
    placeholderOption.textContent = placeholder;
    select.appendChild(placeholderOption);

    options.forEach((option) => {
      const element = document.createElement('option');
      element.value = option.key;
      element.textContent = option.label;
      select.appendChild(element);
    });

    select.value = selectedValue || '';
  }

  function rootKeyForItem(targetKey) {
    const root = items.find((item) => {
      if (item.temp_key === targetKey) {
        return true;
      }

      return item.children.some((child) => child.temp_key === targetKey);
    });

    return root ? root.temp_key : '';
  }

  function renderBuilder() {
    builderList.innerHTML = '';
    emptyState.style.display = items.length ? 'none' : 'block';

    items.forEach((item) => {
      const rootCard = document.createElement('li');
      rootCard.className = 'rounded-[28px] border border-neutral-100 bg-neutral-0 p-5 shadow-sm transition';
      rootCard.draggable = true;
      rootCard.dataset.rootKey = item.temp_key;

      if (item.temp_key === selectedKey) {
        rootCard.classList.add('border-primary', 'ring-2', 'ring-primary/10');
      } else {
        rootCard.classList.add('border-neutral-200');
      }

      const childCount = item.children.length;
      const childMarkup = childCount > 0
        ? `
          <div class="mt-4 rounded-2xl border border-neutral-200 bg-neutral-50 p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
              <p class="text-xs font-bold uppercase tracking-wider text-neutral-400">${escapeHtml(strings.submenuItems)}</p>
              <span class="rounded-full bg-primary/10 px-2.5 py-1 text-[11px] font-semibold text-primary">${childCount} ${escapeHtml(childCount === 1 ? strings.item : strings.items)}</span>
            </div>
            <ul class="space-y-3">
              ${item.children.map((child) => `
                <li class="rounded-2xl border border-neutral-100 bg-neutral-0 px-4 py-3 ${child.temp_key === selectedKey ? 'ring-2 ring-primary/10 border-primary' : ''}" data-child-key="${escapeHtml(child.temp_key)}">
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <div class="flex flex-wrap items-center gap-2">
                        <p class="truncate font-medium text-neutral-900">${escapeHtml(child.label || strings.untitledItem)}</p>
                        <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-neutral-500">${escapeHtml(typeLabel(child.item_type))}</span>
                        ${child.is_visible ? '' : `<span class="rounded-full bg-error/10 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-error">${escapeHtml(strings.hidden)}</span>`}
                      </div>
                      <p class="mt-1 truncate text-sm text-neutral-500">${escapeHtml(itemDescription(child))}</p>
                    </div>
                    <button type="button" class="btn-icon h-9 w-9 text-error" data-remove-key="${escapeHtml(child.temp_key)}"><i class="ph ph-trash"></i></button>
                  </div>
                </li>
              `).join('')}
            </ul>
          </div>
        `
        : `
          <div class="mt-4 rounded-2xl border border-dashed border-neutral-300 px-4 py-4 text-sm text-neutral-400">
            ${escapeHtml(strings.noSubmenuItems)}
          </div>
        `;

      rootCard.innerHTML = `
        <div class="flex items-start justify-between gap-4">
          <div class="flex min-w-0 items-start gap-3">
            <div class="pt-1 text-lg text-neutral-400"><i class="ph ph-dots-six-vertical"></i></div>
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <p class="truncate font-medium text-neutral-900">${escapeHtml(item.label || strings.untitledItem)}</p>
                <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-neutral-500">${escapeHtml(typeLabel(item.item_type))}</span>
                <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-primary">${escapeHtml(strings.parent)}</span>
                ${item.is_visible ? '' : `<span class="rounded-full bg-error/10 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-error">${escapeHtml(strings.hidden)}</span>`}
              </div>
              <p class="mt-1 truncate text-sm text-neutral-500">${escapeHtml(itemDescription(item))}</p>
            </div>
          </div>
          <div class="flex shrink-0 flex-wrap items-center gap-2">
            <button type="button" class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-xl border border-neutral-200 px-3 py-2 text-xs font-semibold text-neutral-500 hover:border-primary hover:text-primary" data-add-child="${escapeHtml(item.temp_key)}">
              <i class="ph ph-plus"></i>
              ${escapeHtml(strings.addChild)}
            </button>
            <button type="button" class="btn-icon h-9 w-9 text-error" data-remove-key="${escapeHtml(item.temp_key)}">
              <i class="ph ph-trash"></i>
            </button>
          </div>
        </div>
        ${childMarkup}
      `;

      rootCard.addEventListener('click', (event) => {
        const removable = event.target.closest('[data-remove-key]');
        const addChild = event.target.closest('[data-add-child]');
        const childRow = event.target.closest('[data-child-key]');

        if (removable) {
          removeItem(removable.dataset.removeKey);
          return;
        }

        if (addChild) {
          openAddModalButton.dataset.parentKey = addChild.dataset.addChild;
          openAddModalButton.click();
          return;
        }

        if (childRow) {
          selectedKey = childRow.dataset.childKey;
          renderBuilder();
          renderInspector();
          return;
        }

        selectedKey = item.temp_key;
        renderBuilder();
        renderInspector();
      });

      rootCard.addEventListener('dragstart', (event) => {
        draggedRootKey = item.temp_key;
        rootCard.classList.add('opacity-60');

        if (event.dataTransfer) {
          event.dataTransfer.effectAllowed = 'move';
        }
      });

      rootCard.addEventListener('dragend', () => {
        draggedRootKey = null;
        rootCard.classList.remove('opacity-60');
      });

      rootCard.addEventListener('dragover', (event) => {
        event.preventDefault();
      });

      rootCard.addEventListener('drop', (event) => {
        event.preventDefault();

        if (!draggedRootKey || draggedRootKey === item.temp_key) {
          return;
        }

        const fromIndex = items.findIndex((entry) => entry.temp_key === draggedRootKey);
        const toIndex = items.findIndex((entry) => entry.temp_key === item.temp_key);

        if (fromIndex === -1 || toIndex === -1) {
          return;
        }

        const moved = items.splice(fromIndex, 1)[0];
        const bounds = rootCard.getBoundingClientRect();
        const insertAfter = event.clientY > bounds.top + (bounds.height / 2);
        const targetIndex = fromIndex < toIndex ? toIndex - 1 : toIndex;

        items.splice(insertAfter ? targetIndex + 1 : targetIndex, 0, moved);
        selectedKey = moved.temp_key;
        renderBuilder();
        renderInspector();
      });

      builderList.appendChild(rootCard);
    });

    persist();
  }

  function renderInspector() {
    const node = selectedKey ? findNode(selectedKey, items, null) : null;
    const hasSelection = Boolean(node);

    inspectorEmpty.classList.toggle('hidden', hasSelection);
    inspectorFields.classList.toggle('hidden', !hasSelection);

    if (!node) {
      return;
    }

    const selectedItem = node.item;
    const isRoot = node.collection === items;
    const hasChildren = (selectedItem.children || []).length > 0;

    labelInput.value = selectedItem.label || '';
    typeInput.value = typeLabel(selectedItem.item_type);
    visibleInput.checked = selectedItem.is_visible !== false;
    pageWrap.classList.toggle('hidden', selectedItem.item_type !== 'internal');
    urlWrap.classList.toggle('hidden', selectedItem.item_type !== 'external');
    targetWrap.classList.toggle('hidden', selectedItem.item_type === 'group');

    pageInput.value = selectedItem.linkable_id || '';
    urlInput.value = selectedItem.item_type === 'external' ? (selectedItem.url || '') : '';
    targetInput.value = selectedItem.target || '_self';

    const parentOptions = rootItemsForSelect(selectedItem.temp_key);
    replaceOptions(parentInput, parentOptions, strings.rootLevel, isRoot ? '' : rootKeyForItem(selectedItem.temp_key));
    parentInput.disabled = hasChildren;
    parentHint.textContent = hasChildren ? strings.moveChildrenFirst : strings.chooseParentOrRoot;
  }

  function removeItem(targetKey) {
    const node = findNode(targetKey, items, null);

    if (!node) {
      return;
    }

    node.collection.splice(node.index, 1);
    selectedKey = items[0] ? items[0].temp_key : null;
    renderBuilder();
    renderInspector();
  }

  function applyInspectorChanges() {
    const node = selectedKey ? findNode(selectedKey, items, null) : null;

    if (!node) {
      return;
    }

    const currentKey = node.item.temp_key;
    const isRoot = node.collection === items;
    const hasChildren = (node.item.children || []).length > 0;
    const targetParentKey = parentInput.disabled ? (isRoot ? '' : rootKeyForItem(currentKey)) : (parentInput.value || '');
    const currentParentKey = isRoot ? '' : rootKeyForItem(currentKey);

    node.item.label = labelInput.value.trim();
    node.item.is_visible = visibleInput.checked;

    if (node.item.item_type === 'internal') {
      node.item.linkable_id = pageInput.value ? Number(pageInput.value) : null;
      node.item.linkable_type = pageInput.value ? pageLinkableType : null;
      node.item.url = '';
      node.item.target = targetInput.value || '_self';
    } else if (node.item.item_type === 'external') {
      node.item.url = urlInput.value.trim();
      node.item.target = targetInput.value || '_self';
      node.item.linkable_id = null;
      node.item.linkable_type = null;
    } else {
      node.item.url = '';
      node.item.target = '_self';
      node.item.linkable_id = null;
      node.item.linkable_type = null;
    }

    if (!hasChildren && targetParentKey !== currentParentKey) {
      const moved = clone(node.item);
      node.collection.splice(node.index, 1);

      if (!targetParentKey) {
        moved.children = moved.children || [];
        items.push(moved);
      } else {
        const targetNode = findNode(targetParentKey, items, null);

        if (targetNode && targetNode.collection === items) {
          targetNode.item.children = targetNode.item.children || [];
          targetNode.item.children.push(moved);
        } else {
          items.push(moved);
        }
      }
    }

    selectedKey = currentKey;
    renderBuilder();
    renderInspector();
  }

  function createItemPayload(type, parentKey) {
    if (type === 'internal') {
      const pageId = modalPageId.value;

      if (!pageId) {
        return null;
      }

      const fallbackLabel = (pageOptions[pageId] || '').replace(/\s+\(.+\)$/, '');

      return {
        temp_key: `menu-item-${Date.now()}`,
        item_type: 'internal',
        label: modalPageLabel.value.trim() || fallbackLabel,
        linkable_type: pageLinkableType,
        linkable_id: Number(pageId),
        url: '',
        target: '_self',
        is_visible: true,
        children: [],
        parent_key: parentKey || '',
      };
    }

    if (type === 'external') {
      const label = modalExternalLabel.value.trim();
      const url = modalExternalUrl.value.trim();

      if (!label || !url) {
        return null;
      }

      return {
        temp_key: `menu-item-${Date.now()}`,
        item_type: 'external',
        label,
        linkable_type: null,
        linkable_id: null,
        url,
        target: modalExternalTarget.value || '_self',
        is_visible: true,
        children: [],
        parent_key: parentKey || '',
      };
    }

    const groupLabel = modalGroupLabel.value.trim();

    if (!groupLabel) {
      return null;
    }

    return {
      temp_key: `menu-item-${Date.now()}`,
      item_type: 'group',
      label: groupLabel,
      linkable_type: null,
      linkable_id: null,
      url: '',
      target: '_self',
      is_visible: true,
      children: [],
      parent_key: parentKey || '',
    };
  }

  function addItemToTree(item) {
    if (!item.parent_key) {
      delete item.parent_key;
      items.push(item);
      return;
    }

    const parentNode = findNode(item.parent_key, items, null);

    if (!parentNode || parentNode.collection !== items) {
      delete item.parent_key;
      items.push(item);
      return;
    }

    delete item.parent_key;
    parentNode.item.children = parentNode.item.children || [];
    parentNode.item.children.push(item);
  }

  function openAddItemModal(parentKey) {
    const options = rootItemsForSelect('');
    modalForm.reset();
    modalType.value = 'internal';
    modalExternalTarget.value = '_self';
    replaceOptions(modalParent, options, strings.addAtRootLevel, parentKey || '');
    refreshModalState();
  }

  function refreshModalState() {
    const type = modalType.value;
    const isChild = Boolean(modalParent.value);

    modalPageFields.classList.toggle('hidden', type !== 'internal');
    modalExternalFields.classList.toggle('hidden', type !== 'external');
    modalGroupFields.classList.toggle('hidden', type !== 'group');

    if (isChild && type === 'group') {
      modalType.value = 'external';
      modalPageFields.classList.add('hidden');
      modalExternalFields.classList.remove('hidden');
      modalGroupFields.classList.add('hidden');
    }
  }

  function flattenForSubmit(list) {
    return list.map((item) => ({
      id: item.id || null,
      temp_key: item.temp_key,
      item_type: item.item_type,
      label: item.label,
      linkable_type: item.linkable_type,
      linkable_id: item.linkable_id,
      url: item.url,
      target: item.target,
      is_visible: item.is_visible !== false,
      children: flattenForSubmit(item.children || []),
    }));
  }

  openAddModalButton.addEventListener('click', () => {
    const parentKey = openAddModalButton.dataset.parentKey || '';
    openAddItemModal(parentKey);
    openAddModalButton.dataset.parentKey = '';
  });

  modalType.addEventListener('change', refreshModalState);
  modalParent.addEventListener('change', refreshModalState);

  modalForm.addEventListener('submit', (event) => {
    event.preventDefault();

    const payload = createItemPayload(modalType.value, modalParent.value || '');

    if (!payload) {
      return;
    }

    addItemToTree(payload);
    selectedKey = payload.temp_key;
    renderBuilder();
    renderInspector();
    modalForm.reset();
    modalExternalTarget.value = '_self';

    if (modalCloseButton) {
      modalCloseButton.click();
    }
  });

  updateButton.addEventListener('click', () => {
    applyInspectorChanges();
  });

  form.addEventListener('submit', () => {
    applyInspectorChanges();
    payloadInput.value = JSON.stringify(flattenForSubmit(items));
  });

  selectedKey = items[0] ? items[0].temp_key : null;
  renderBuilder();
  renderInspector();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', bootFrontendMenuBuilder);
} else {
  bootFrontendMenuBuilder();
}
