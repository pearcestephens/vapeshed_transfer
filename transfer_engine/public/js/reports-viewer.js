(function(){
  function $(s){ return document.querySelector(s); }
  function esc(t){ const d=document.createElement('div'); d.textContent=String(t); return d.innerHTML; }
  function fmt(n, d){ if(typeof n!=='number'){ n=parseFloat(n)||0; } return n.toLocaleString(undefined,{maximumFractionDigits:d??0}); }

  async function runDemo(){
    const count = parseInt($('#rvProducts').value||'60');
    const outlets = parseInt($('#rvOutlets').value||'8');
    const reserve = parseFloat($('#rvReserve').value||'0.20');
    const maxPer = parseInt($('#rvMaxPer').value||'40');
    const method = $('#rvMethod').value||'power';
    const gamma = parseFloat($('#rvGamma').value||'1.8');
    const minCap = parseInt($('#rvMinCap').value||'10');
    const onlyCodes = ($('#rvOnlyCodes').value||'').trim();
    const compare = ($('#rvCompare').value||'').trim();
    const dynK = $('#rvDynamicK').checked ? 1 : 0;

    const base = (window.VapeshedTransfer && window.VapeshedTransfer.baseUrl) ? window.VapeshedTransfer.baseUrl : '';
    const p = new URLSearchParams();
    p.set('demo_products','1');
    p.set('count', String(count));
    p.set('outlets', String(outlets));
    p.set('reserve', String(reserve));
    p.set('max_per', String(maxPer));
    p.set('method', method);
    p.set('gamma', String(gamma));
    p.set('min_cap', String(minCap));
    if (onlyCodes) p.set('only_codes', onlyCodes);
    if (compare) p.set('compare_codes', compare);
    if (dynK) p.set('dynamic_k', '1');

  const url = base.replace(/\/$/, '') + '/api/transfer/test?' + p.toString();
  const csrf = document.querySelector('meta[name="csrf-token"]');
  const r = await fetch(url, { headers: { 'Accept':'application/json', 'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : '' }});
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const json = await r.json();
    if (!json.success) throw new Error(json.error || 'Run failed');
    render(json.data, json.meta||{});
  }

  function render(data, meta){
    // Build summary + fairness
    const trace = data.decision_trace || {};
    let lines=0, units=0, prods=Object.keys(trace).length;
    const perOutlet = new Map();
    Object.values(trace).forEach(t => {
      (t.outlets||[]).forEach(row => {
        if (row.reason === 'allocated') {
          lines++;
          const q = parseInt(row.allocated_qty||0);
          units += q;
          const oid = String(row.outlet_id||'');
          if (oid) perOutlet.set(oid, (perOutlet.get(oid)||0)+q);
        }
      })
    });
    const outletsAffected = Array.from(perOutlet.values()).filter(v=>v>0).length;
    const fairness = computeFairness(Array.from(perOutlet.values()));

    // Update summary cards
    $('#rvSummary').style.display='flex';
    $('#rvLines').textContent = fmt(lines);
    $('#rvUnits').textContent = fmt(units);
    $('#rvProductsN').textContent = fmt(prods);
    $('#rvOutletsN').textContent = fmt(outletsAffected);
    $('#rvFair').textContent = fairness.toFixed(3);

    // Build top units and hotspots
    const outPerf = Array.isArray(data.outlet_perf) ? data.outlet_perf : [];
    const sortedUnits = [...outPerf].sort((a,b)=> (b.allocation_qty - a.allocation_qty) || (b.allocation_lines - a.allocation_lines)).slice(0,8);
    renderBars('#rvTopUnits', sortedUnits.map(r=>({
      label: (r.store_code||'')+' '+(r.name||''),
      value: r.allocation_qty||0,
      sub: r.allocation_lines||0
    }));

    const sortedTime = [...outPerf].sort((a,b)=> (b.demand_ms - a.demand_ms)).slice(0,8);
    renderBars('#rvHotspots', sortedTime.map(r=>({
      label: (r.store_code||'')+' '+(r.name||''),
      value: r.demand_ms||0,
      sub: r.demand_calls||0,
      unit:' ms'
    })));

    // Matrix
    const tbody = document.querySelector('#rvTable tbody');
    tbody.innerHTML = '';
    Object.entries(trace).forEach(([pid, t]) => {
      const pmeta = t.product||{}; const wh = parseInt(pmeta.warehouse_stock||0);
      (t.outlets||[]).forEach(row => {
        const flags = [];
        if (row.reason === 'allocated') { flags.push('<span class="rv-badge rv-alloc">allocated</span>'); if (row.capped) flags.push('<span class="rv-badge rv-cap">capped</span>'); }
        else if (row.reason === 'below_min_cap') { flags.push('<span class="rv-badge rv-near">'+(row.near_miss?'near-miss':'below-min-cap')+'</span>'); }
        else if (row.reason === 'filtered_top_k') { flags.push('<span class="rv-badge rv-fk">filtered-top-k</span>'); }
        else if (row.reason === 'zero_demand') { flags.push('<span class="rv-badge rv-zero">zero-demand</span>'); }

        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td><code>${esc(pid)}</code></td>
          <td>${esc((row.store_code||'')+' '+(row.name||''))}</td>
          <td class="text-end">${fmt(wh)}</td>
          <td class="text-end">${fmt(parseInt(row.dest_stock||0))}</td>
          <td class="text-end">${fmt(parseFloat(row.demand||0),2)}</td>
          <td class="text-end">${fmt(parseInt(row.allocated_qty||0))}</td>
          <td>${flags.join(' ')}</td>
        `;
        tbody.appendChild(tr);
      });
    });
  }

  function renderBars(containerSel, items){
    const el = document.querySelector(containerSel);
    el.innerHTML = '';
    if (!items || !items.length) { el.textContent = 'No data'; return; }
    const max = Math.max(...items.map(i=>i.value||0), 1);
    items.forEach(i => {
      const row = document.createElement('div');
      row.innerHTML = `
        <div class="rv-kv">
          <div>${esc(i.label)}</div>
          <div><strong>${fmt(i.value)}</strong>${i.unit||''} <span class="text-muted">(${fmt(i.sub)})</span></div>
        </div>
        <div class="rv-bar"><span style="width:${Math.round((i.value/max)*100)}%"></span></div>
      `;
      el.appendChild(row);
    });
  }

  function computeFairness(values){
    const v = values.filter(x=>x>0).sort((a,b)=>a-b);
    const n = v.length; if (!n) return 0;
    const sum = v.reduce((a,b)=>a+b,0); if (!sum) return 0;
    let cum = 0; for (let i=0;i<n;i++){ cum += (i+1)*v[i]; }
    const gini = ((n+1) - 2*(cum/sum)) / n; // bound to [0,1]
    return Math.max(0, Math.min(1, 1 - gini));
  }

  document.addEventListener('DOMContentLoaded', function(){
    const btn = document.getElementById('btnRunDemo');
    btn.addEventListener('click', async function(){
      btn.disabled = true; btn.textContent = 'Running…';
      try { await runDemo(); } catch(e){ alert('Run failed: '+e.message); } finally { btn.disabled = false; btn.textContent = 'Run Demo'; }
    });

    const best = document.getElementById('btnBestSpread');
    best.addEventListener('click', async function(){
      best.disabled = true; best.textContent = 'Selecting…';
      try {
        const count = parseInt($('#rvProducts').value||'60');
        const outlets = parseInt($('#rvOutlets').value||'8');
        const reserve = parseFloat($('#rvReserve').value||'0.20');
        const maxPer = parseInt($('#rvMaxPer').value||'40');
        const method = $('#rvMethod').value||'power';
        const gamma = parseFloat($('#rvGamma').value||'1.8');
        const dynK = $('#rvDynamicK').checked ? 1 : 0;

        const body = {
          outlets,
          bands: { low: Math.max(0, Math.round(count*0.34)), medium: Math.max(0, Math.round(count*0.33)), high: Math.max(0, count - Math.round(count*0.34) - Math.round(count*0.33)) },
          sweep: {
            reserves: [reserve],
            max_per: [maxPer],
            method: [method],
            gamma: [gamma],
            dynamic_k: [dynK],
            max_runs: 12
          },
          objective: { outlet_weight: 0.5, product_weight: 0.5, units_weight: 0.0 }
        };
        const base = (window.VapeshedTransfer && window.VapeshedTransfer.baseUrl) ? window.VapeshedTransfer.baseUrl : '';
        const csrf = document.querySelector('meta[name="csrf-token"]');
        const r = await fetch(base.replace(/\/$/, '') + '/api/transfer/best-spread', {
          method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : '' },
          body: JSON.stringify(body)
        });
        if (!r.ok) throw new Error('HTTP ' + r.status);
        const json = await r.json();
        if (!json.success) throw new Error(json.error || 'No result');
        const b = json.data && json.data.best;
        if (!b) throw new Error('Missing best result');
        const p = b.params || {}; const m = b.metrics || {};
        const el = document.getElementById('rvBestBody');
        el.innerHTML = `
          <div>Params: reserve=<strong>${esc(p.reserve)}</strong>, max_per=<strong>${esc(p.max_per)}</strong>, method=<strong>${esc(p.method)}</strong>, gamma=<strong>${esc(p.gamma)}</strong>, dynamic_k=<strong>${esc(p.dynamic_k)}</strong></div>
          <div>Metrics: fairness_outlet=<strong>${fmt(m.fairness_outlet,3)}</strong>, fairness_product_avg=<strong>${fmt(m.fairness_product_avg,3)}</strong>, lines=<strong>${fmt(m.lines)}</strong>, units=<strong>${fmt(m.units)}</strong>, U/L=<strong>${fmt(m.units_per_line,2)}</strong>, outlets=<strong>${fmt(m.outlets_affected)}</strong></div>
        `;
        document.getElementById('rvBestPanel').style.display = 'block';
      } catch(e) {
        alert('Best-spread failed: ' + e.message);
      } finally {
        best.disabled = false; best.textContent = 'Best Spread';
      }
    });
  });
})();
