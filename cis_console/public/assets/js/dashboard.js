document.addEventListener('DOMContentLoaded', function () {
  const base = window.CIS_BASE || '';
  const token = (document.cookie.match(/(?:^|; )admin_token=([^;]+)/)||[])[1] || '';
  if (!token) {
    console.warn('admin_token cookie missing; set it to enable dashboard fetches');
  }
  const headers = token ? { 'X-Admin-Token': token } : {};
  const healthEl = document.getElementById('health-json');
  if (healthEl) {
    fetch(base + '?endpoint=admin/health/checks', { headers })
      .then(r => r.json())
      .then(j => healthEl.textContent = JSON.stringify(j, null, 2))
      .catch(e => healthEl.textContent = 'Error: ' + e);
  }
});
