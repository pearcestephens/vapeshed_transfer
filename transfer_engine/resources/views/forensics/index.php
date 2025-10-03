<?php /** @var array $recent_accepts,$category_dist,$flags,$syn_candidates */ ?>
<div class="container mt-4">
  <h1 class="h3 mb-3">Match Forensics Dashboard</h1>
  <div class="row g-3">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">Recent Auto-Accepts</div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0 table-striped">
            <thead><tr><th>Match</th><th>Candidate</th><th>SKU</th><th>Conf</th><th>Time</th></tr></thead>
            <tbody>
              <?php foreach($recent_accepts as $r): ?>
                <tr>
                  <td><?=htmlspecialchars($r['match_id'])?></td>
                  <td><?=htmlspecialchars($r['candidate_id'])?></td>
                  <td><?=htmlspecialchars($r['sku_id'])?></td>
                  <td><?=number_format((float)$r['confidence'],3)?></td>
                  <td><?=htmlspecialchars($r['created_at'])?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">Feature Flags</div>
        <div class="card-body">
          <ul class="list-unstyled mb-0">
            <?php foreach($flags as $k=>$v): ?>
              <li><span class="badge bg-<?= $v?'success':'secondary' ?>"><?= $v?'ON':'OFF' ?></span> <?= htmlspecialchars($k) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card mt-3">
        <div class="card-header">Category Distribution (recent)</div>
        <div class="card-body p-0" style="max-height:260px;overflow:auto;">
          <table class="table table-sm mb-0 table-striped">
            <thead><tr><th>Date</th><th>Category</th><th>Events</th></tr></thead>
            <tbody>
              <?php foreach($category_dist as $r): ?>
                <tr>
                  <td><?=htmlspecialchars($r['d'])?></td>
                  <td><?=htmlspecialchars($r['cat']??'')?></td>
                  <td><?=htmlspecialchars($r['c'])?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card mt-3">
        <div class="card-header">Top Synonym Candidates</div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0 table-striped">
            <thead><tr><th>Token</th><th>Occurrences</th></tr></thead>
            <tbody>
              <?php foreach($syn_candidates as $s): ?>
                <tr>
                  <td><?=htmlspecialchars($s['token'])?></td>
                  <td><?=htmlspecialchars($s['occurrences'])?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="row g-3 mt-2">
    <div class="col-md-4">
      <div class="card mt-3">
        <div class="card-header">Rejection Reasons (7d)</div>
        <div class="card-body"><canvas id="rejReasons" height="180"></canvas></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card mt-3">
        <div class="card-header">Acceptance Paths</div>
        <div class="card-body"><canvas id="acceptPaths" height="180"></canvas></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card mt-3">
        <div class="card-header">Cluster Stats</div>
        <div class="card-body">
          <div class="small text-muted" id="clusterStats">Loading...</div>
          <div class="mt-2">
            <canvas id="driftChart" height="140"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" integrity="sha384-dyn"></script>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  fetch('/api/dashboard/metrics').then(r=>r.json()).then(d=>{ if(!d.success) return; const m=d.metrics; buildRej(m.rejection_reason_dist); buildAccept(m.acceptance_path_distribution); setClusters(m.cluster_stats); buildDrift(m.recent_drift); });
  function buildRej(obj){ const ctx=document.getElementById('rejReasons'); if(!ctx) return; new Chart(ctx,{type:'doughnut',data:{labels:Object.keys(obj),datasets:[{data:Object.values(obj)}]},options:{plugins:{legend:{position:'bottom'}}}}); }
  function buildAccept(obj){ const ctx=document.getElementById('acceptPaths'); if(!ctx) return; new Chart(ctx,{type:'bar',data:{labels:Object.keys(obj),datasets:[{label:'Count',data:Object.values(obj),backgroundColor:'#0d6efd'}]},options:{scales:{x:{ticks:{autoSkip:false}}}}); }
  function setClusters(s){ const el=document.getElementById('clusterStats'); if(el) el.textContent = 'Clusters: '+s.total_clusters+' | Members: '+s.total_members; }
  function buildDrift(rows){ const ctx=document.getElementById('driftChart'); if(!ctx) return; const labels=rows.map(r=>r.created_at); const prim=rows.map(r=>r.delta_primary); const sec=rows.map(r=>r.delta_secondary); new Chart(ctx,{type:'line',data:{labels,datasets:[{label:'Δ Primary',data:prim,borderColor:'#198754',tension:.3},{label:'Δ Secondary',data:sec,borderColor:'#dc3545',tension:.3}]},options:{responsive:true,plugins:{legend:{position:'bottom'}}}); }
});
</script>
