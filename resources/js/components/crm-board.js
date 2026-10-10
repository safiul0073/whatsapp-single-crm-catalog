'use strict';

import Alpine from 'alpinejs';

Alpine.data('crmBoard', (config) => ({
  error: '',
  message: '',
  savingLabel: config.savingLabel || 'Saving stage change...',
  isSaving: false,
  draggedLeadId: null,
  originalStageId: null,
  originalParent: null,
  originalNextSibling: null,

  startDrag(leadId, stageId, event) {
    if (!config.canManage || this.isSaving) {
      event.preventDefault();
      return;
    }

    this.draggedLeadId = leadId;
    this.originalStageId = stageId;
    this.originalParent = event.currentTarget.parentElement;
    this.originalNextSibling = event.currentTarget.nextElementSibling;
    this.error = '';
    this.message = '';
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(leadId));
  },

  dragOverStage(column) {
    if (config.canManage && this.draggedLeadId && !this.isSaving) {
      document.querySelectorAll('.pipeline-col.is-drop-target').forEach((target) => target.classList.remove('is-drop-target'));
      column.classList.add('is-drop-target');
    }
  },

  leaveStage(column, event) {
    if (!event.relatedTarget || !column.contains(event.relatedTarget)) {
      column.classList.remove('is-drop-target');
    }
  },

  finishDrag() {
    this.draggedLeadId = null;
    document.querySelectorAll('.pipeline-col.is-drop-target').forEach((target) => target.classList.remove('is-drop-target'));
  },

  async dropLead(stageId, event) {
    if (!config.canManage || this.isSaving) {
      return;
    }

    const leadId = Number(event.dataTransfer.getData('text/plain') || this.draggedLeadId);
    if (!leadId || stageId === this.originalStageId) {
      this.finishDrag();
      return;
    }

    const card = document.querySelector(`[data-lead-id="${leadId}"]`);
    const target = event.currentTarget.querySelector('[data-stage-cards]');
    if (!card || !target) {
      this.finishDrag();
      return;
    }

    const originalParent = this.originalParent;
    const originalNextSibling = this.originalNextSibling;
    const stageName = event.currentTarget.querySelector('.pipeline-col__title')?.textContent?.trim() || '';
    target.querySelector('[data-empty-stage]')?.remove();
    target.prepend(card);
    card.classList.add('is-moving');
    card.setAttribute('aria-busy', 'true');
    event.currentTarget.classList.remove('is-drop-target');
    this.updateCounts();
    this.error = '';
    this.message = '';
    this.isSaving = true;

    try {
      const url = config.moveUrl.replace('__LEAD__', String(leadId));
      await window.axios.patch(url, { stage_id: stageId }, {
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
      });
      this.originalStageId = stageId;
      this.message = (config.movedLabel || 'Lead moved to :stage.').replace(':stage', stageName);
    } catch (error) {
      if (originalNextSibling && originalNextSibling.parentElement === originalParent) {
        originalParent.insertBefore(card, originalNextSibling);
      } else {
        originalParent?.append(card);
      }
      this.error = error.response?.data?.message || Object.values(error.response?.data?.errors || {})[0]?.[0] || 'Unable to move this lead.';
      this.updateCounts();
    } finally {
      this.isSaving = false;
      card.classList.remove('is-moving');
      card.removeAttribute('aria-busy');
      this.finishDrag();
    }
  },

  updateCounts() {
    document.querySelectorAll('[data-stage-id]').forEach((column) => {
      const count = column.querySelectorAll('[data-lead-id]').length;
      const badge = column.querySelector('.pipeline-col__count');
      const body = column.querySelector('[data-stage-cards]');
      if (badge) {
        badge.textContent = String(count);
      }
      if (config.hasLeads && body && count === 0 && !body.querySelector('[data-empty-stage]')) {
        const empty = document.createElement('p');
        empty.className = 'rounded-md border border-dashed border-neutral-300 p-4 text-center text-xs text-body';
        empty.dataset.emptyStage = '';
        empty.textContent = config.emptyLabel || 'Drop leads here';
        body.append(empty);
      }
      if (body && count > 0) {
        body.querySelector('[data-empty-stage]')?.remove();
      }
    });
  },
}));
