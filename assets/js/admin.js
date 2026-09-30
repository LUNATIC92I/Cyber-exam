/* CYBER EXAM — back-office helpers */
(() => {
  'use strict';
  const btn = document.querySelector('[data-admin-menu]');
  const side = document.querySelector('[data-admin-sidebar]');
  if (btn && side) {
    btn.addEventListener('click', () => side.classList.toggle('is-open'));
    document.addEventListener('click', (e) => {
      if (side.classList.contains('is-open') && !side.contains(e.target) && !btn.contains(e.target)) side.classList.remove('is-open');
    });
  }
  // Confirmation for destructive actions
  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', async (e) => {
      if (form.dataset.confirmed) return;
      e.preventDefault();
      const ok = await window.CyberExam.confirmModal({ title: 'Please confirm', text: form.dataset.confirm, confirmLabel: 'Confirm' });
      if (ok) { form.dataset.confirmed = '1'; form.submit(); }
    });
  });
  // Auto-submit filter selects
  document.querySelectorAll('[data-autosubmit]').forEach((el) => el.addEventListener('change', () => el.form.submit()));
})();
