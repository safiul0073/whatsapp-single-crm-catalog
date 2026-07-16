/* ==========================================================================
   AUTH — Password toggle + form demo handlers
   ========================================================================== */

document.addEventListener('DOMContentLoaded', () => {

  // Password show/hide
  document.querySelectorAll('[data-password-toggle]').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = btn.parentElement.querySelector('input');
      if (!input) return;
      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';
      btn.innerHTML = `<i data-lucide="${isPassword ? 'eye' : 'eye-off'}" class="h-4 w-4" aria-hidden="true"></i>`;
      if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [btn] });
    });
  });

  // Form submit demo — show loading then success state
  document.querySelectorAll('[data-auth-submit]').forEach(btn => {
    const form = btn.closest('form') || btn.closest('div');
    if (!form) return;

    btn.addEventListener('click', () => {
      if (btn.disabled) return;
      const orig = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = `<svg class="h-4 w-4 animate-spin inline" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg> Signing in…`;
      setTimeout(() => {
        btn.innerHTML = `<i data-lucide="check" class="h-4 w-4 inline" aria-hidden="true"></i> Success`;
        if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [btn] });
        setTimeout(() => {
          btn.innerHTML = orig;
          btn.disabled = false;
          if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [btn] });
        }, 1500);
      }, 1800);
    });
  });

  // Utility pages — reload button
  document.querySelectorAll('[data-action="reload"]').forEach(btn => {
    btn.addEventListener('click', () => location.reload());
  });

});
