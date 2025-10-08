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

  // Live traffic metrics SSE
  const trafficBox = document.getElementById('trafficMetrics');
  if (trafficBox) {
    try {
      const eventSource = new EventSource('?endpoint=admin/metrics/stream');
      eventSource.addEventListener('tick', (event) => {
        try {
          const data = JSON.parse(event.data);
          const recent = data.recent || {};
          const errors = data.errors || [];
          
          let output = `Last 5s ➜ hits: ${recent.hits || 0}, errs: ${recent.errs || 0}, avg: ${recent.avg_ms || 0}ms\n`;
          
          if (errors.length > 0) {
            output += '\nTop errors:\n';
            errors.forEach(err => {
              output += `  ${err.endpoint}: ${err.count}\n`;
            });
          }
          
          trafficBox.textContent = output;
        } catch (parseError) {
          trafficBox.textContent = 'Parse error: ' + parseError.message;
        }
      });
      
      eventSource.addEventListener('error', () => {
        trafficBox.textContent = 'SSE connection failed';
      });
    } catch (sseError) {
      trafficBox.textContent = 'SSE not supported';
    }
  }
})();
