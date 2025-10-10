<main class="container" style="padding:16px;">
  <h2>Traffic & Monitoring</h2>
  <section class="card">
    <h3>Metrics</h3>
    <pre id="metrics-json">Loading...</pre>
  </section>
  <section class="card">
    <h3>Alerts</h3>
    <pre id="alerts-json">Loading...</pre>
  </section>
</main>
<script>
(function(){
  const base = window.CIS_BASE; // Derived from dynamic base URL prepared in header
  const token = (document.cookie.match(/(?:^|; )admin_token=([^;]+)/)||[])[1] || '';
  const headers = token ? { 'X-Admin-Token': token } : {};
  fetch(base + '?endpoint=admin/traffic/metrics', { headers }).then(r=>r.json()).then(j=>{
    document.getElementById('metrics-json').textContent = JSON.stringify(j, null, 2);
  }).catch(e=>{ document.getElementById('metrics-json').textContent='Error: '+e; });
  fetch(base + '?endpoint=admin/traffic/alerts', { headers }).then(r=>r.json()).then(j=>{
    document.getElementById('alerts-json').textContent = JSON.stringify(j, null, 2);
  }).catch(e=>{ document.getElementById('alerts-json').textContent='Error: '+e; });
})();
</script>
