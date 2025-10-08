(function () {
  'use strict';

  const sidebar = document.getElementById('sidebar');
  const toggle = document.getElementById('sidebarToggle');
  if (toggle && sidebar) {
    toggle.addEventListener('click', () => {
      const open = sidebar.classList.toggle('open');
      toggle.setAttribute('aria-expanded', String(open));
      if (open) {
        const focusable = sidebar.querySelector('a, button');
        if (focusable) {
          focusable.focus();
        }
      }
    });
  }

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && sidebar && sidebar.classList.contains('open')) {
      sidebar.classList.remove('open');
      if (toggle) {
        toggle.setAttribute('aria-expanded', 'false');
        toggle.focus();
      }
    }
  });

  const probeOutput = document.querySelector('.probe-output');
  const probeButton = document.querySelector('button[data-action="probe"]');
  if (probeButton && probeOutput) {
    probeButton.addEventListener('click', async () => {
      probeOutput.textContent = 'Probing…';
      try {
        const response = await fetch('?endpoint=admin/assets/probe', {
          headers: { 'X-Requested-With': 'fetch' },
        });
        const payload = await response.json();
        probeOutput.textContent = JSON.stringify(payload, null, 2);
      } catch (error) {
        const message = error && error.message ? error.message : String(error);
        probeOutput.textContent = 'Probe failed: ' + message;
      }
    });
  }
})();
