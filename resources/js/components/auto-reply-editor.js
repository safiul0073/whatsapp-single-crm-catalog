function headline(value) {
  return String(value || '')
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function selectedValue(form, name) {
  return form.querySelector(`[name="${name}"]:checked`)?.value || '';
}

function setHidden(element, hidden) {
  if (!element) {
    return;
  }

  element.classList.toggle('hidden', hidden);
}

function updateAutoReplyEditor(form) {
  const triggerType = selectedValue(form, 'trigger_type');
  const replyType = selectedValue(form, 'reply_type');
  const replyText = form.querySelector('[data-preview-text]');
  const mediaCaption = form.querySelector('[data-preview-caption]');
  const activeInput = form.querySelector('[data-summary-status-input]');
  const priorityInput = form.querySelector('[data-summary-priority-input]');
  const previewContent = form.querySelector('[data-preview-content]');
  const charCount = form.querySelector('[data-char-count]');
  const summaryTrigger = form.querySelector('[data-summary-trigger]');
  const summaryReply = form.querySelector('[data-summary-reply]');
  const summaryPriority = form.querySelector('[data-summary-priority]');
  const summaryStatus = form.querySelector('[data-summary-status]');

  setHidden(form.querySelector('[data-keyword-fields]'), triggerType !== 'keyword');
  setHidden(form.querySelector('[data-template-fields]'), replyType !== 'template');
  setHidden(form.querySelector('[data-media-fields]'), replyType !== 'media');

  if (previewContent && replyText) {
    const fallback = replyType === 'template'
      ? 'Approved WhatsApp template will be sent.'
      : replyType === 'media'
        ? 'Media reply will be sent.'
        : 'Hi! Thanks for reaching out. How can we help you today?';
    previewContent.textContent = replyText.value || mediaCaption?.value || fallback;
  }

  if (charCount && replyText) {
    charCount.textContent = replyText.value.length;
  }

  if (summaryTrigger) {
    summaryTrigger.textContent = headline(triggerType);
  }

  if (summaryReply) {
    summaryReply.textContent = headline(replyType);
  }

  if (summaryPriority && priorityInput) {
    summaryPriority.textContent = priorityInput.value || '10';
  }

  if (summaryStatus && activeInput) {
    summaryStatus.textContent = activeInput.checked ? 'Enabled' : 'Disabled';
    summaryStatus.classList.toggle('badge-success', activeInput.checked);
    summaryStatus.classList.toggle('badge-warning', !activeInput.checked);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-auto-reply-editor]').forEach((form) => {
    updateAutoReplyEditor(form);

    form.addEventListener('input', () => updateAutoReplyEditor(form));
    form.addEventListener('change', () => updateAutoReplyEditor(form));
  });
});
