<main class="container" style="padding:16px;">
  <h2>Admin Dashboard</h2>
  <section id="health" class="card">
    <h3>Health Checks</h3>
    <pre id="health-json">Loading...</pre>
    <h4>Health Grid</h4>
    <pre id="health-grid">Loading...</pre>
    <h4>One-Click Health</h4>
    <pre id="health-oneclick">Loading...</pre>
  </section>
  <section id="links" class="card">
    <h3>Quick Links</h3>
    <ul>
  <?php $indexUrl = $this->app['urls']['index'] ?? (htmlspecialchars($baseUrl) . 'index.php'); ?>
  <li><a href="<?= $indexUrl ?>?endpoint=admin/traffic/live">Live Traffic (SSE)</a></li>
  <li><a href="<?= $indexUrl ?>?endpoint=admin/logs/apache-error-tail">Apache Error Tail</a></li>
    </ul>
  </section>
  <section id="redirects" class="card">
    <h3>Create Redirect</h3>
    <form id="redirect-form" onsubmit="return false;">
      <div class="form-group">
        <label for="redir-from">From (site path)</label>
        <input type="text" id="redir-from" class="form-control" placeholder="/old-path">
      </div>
      <div class="form-group">
        <label for="redir-to">To (absolute or site path)</label>
        <input type="text" id="redir-to" class="form-control" placeholder="/new-path or https://host/new-path">
      </div>
      <button id="redir-submit" class="btn btn-primary" type="button">Create Redirect</button>
      <pre id="redir-result" class="mt-2"></pre>
    </form>
  </section>
</main>
<script>
(function(){
  const base = window.CIS_BASE;
  const token = (document.cookie.match(/admin_token=([^;]+)/)||[])[1] || '';
  fetch(base + '?endpoint=admin/health/checks', { headers: { 'X-Admin-Token': token }})
    .then(r => r.json())
    .then(j => { document.getElementById('health-json').textContent = JSON.stringify(j, null, 2); })
    .catch(e => { document.getElementById('health-json').textContent = 'Error: ' + e; });
  fetch(base + '?endpoint=admin/health/grid', { headers: { 'X-Admin-Token': token }})
    .then(r => r.json())
    .then(j => { document.getElementById('health-grid').textContent = JSON.stringify(j, null, 2); })
    .catch(e => { document.getElementById('health-grid').textContent = 'Error: ' + e; });
  fetch(base + '?endpoint=admin/health/oneclick', { headers: { 'X-Admin-Token': token }})
    .then(r => r.json())
    .then(j => { document.getElementById('health-oneclick').textContent = JSON.stringify(j, null, 2); })
    .catch(e => { document.getElementById('health-oneclick').textContent = 'Error: ' + e; });

  const btn = document.getElementById('redir-submit');
  if (btn) {
    btn.addEventListener('click', function(){
      const from = document.getElementById('redir-from').value.trim();
      const to = document.getElementById('redir-to').value.trim();
      const url = base + '?endpoint=admin/errors/create-redirect&from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to);
      fetch(url, { headers: { 'X-Admin-Token': token }})
        .then(r => r.json())
        .then(j => { document.getElementById('redir-result').textContent = JSON.stringify(j, null, 2); })
        .catch(e => { document.getElementById('redir-result').textContent = 'Error: ' + e; });
    });
  }
})();
</script>
