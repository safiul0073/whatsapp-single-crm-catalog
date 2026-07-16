'use strict';

import Alpine from 'alpinejs';

Alpine.data('crmBoard', (config) => ({
  error: '',
  draggedLeadId: null,
  originalStageId: null,
  originalParent: null,
  originalNextSibling: null,

  startDrag(leadId, stageId, event) {
    this.draggedLeadId = leadId;
    this.originalStageId = stageId;
    this.originalParent = event.currentTarget.parentElement;
    this.originalNextSibling = event.currentTarget.nextElementSibling;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(leadId));
  },

  async dropLead(stageId, event) {
    const leadId = Number(event.dataTransfer.getData('text/plain') || this.draggedLeadId);
    if (!leadId || stageId === this.originalStageId) {
      return;
    }

    const card = document.querySelector(`[data-lead-id="${leadId}"]`);
    const target = event.currentTarget.querySelector('[data-stage-cards]');
    if (!card || !target) {
      return;
    }

    target.querySelector('[data-empty-stage]')?.remove();
    target.prepend(card);
    this.updateCounts();
    this.error = '';

    try {
      const url = config.moveUrl.replace('__LEAD__', String(leadId));
      await window.axios.patch(url, { stage_id: stageId }, {
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
      });
      this.originalStageId = stageId;
    } catch (error) {
      if (this.originalNextSibling && this.originalNextSibling.parentElement === this.originalParent) {
        this.originalParent.insertBefore(card, this.originalNextSibling);
      } else {
        this.originalParent?.append(card);
      }
      this.error = error.response?.data?.message || Object.values(error.response?.data?.errors || {})[0]?.[0] || 'Unable to move this lead.';
      this.updateCounts();
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
      if (body && count === 0 && !body.querySelector('[data-empty-stage]')) {
        const empty = document.createElement('p');
        empty.className = 'rounded-lg border border-dashed border-neutral-300 p-4 text-center text-xs text-body';
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
