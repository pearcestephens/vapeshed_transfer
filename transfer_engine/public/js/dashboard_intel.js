/* Dashboard Intelligence Auto Loader
 * Fetches KPIs, transfer suggestions, crawler summary, insights.
 * Lightweight, dependency-free.
 */
(function(){
  const ENDPOINTS = {
    kpis: '/api/dashboard/kpis',
    transfers: '/api/dashboard/transfer/suggestions',
    crawler: '/api/dashboard/crawler/summary',
    insights: '/api/dashboard/assistant/insights',
    neuro: '/api/dashboard/neuro-state'
  };
  function $(sel){ return document.querySelector(sel); }
  function createContainer(){
    let wrap = document.getElementById('intel-panels');
    if(!wrap){ wrap = document.createElement('div'); wrap.id='intel-panels'; wrap.style.display='grid'; wrap.style.gridTemplateColumns='repeat(auto-fit,minmax(300px,1fr))'; wrap.style.gap='16px'; document.body.prepend(wrap);} 
    return wrap;
  }
  function panel(id,title){
    let el=document.getElementById(id); if(!el){ el=document.createElement('div'); el.id=id; el.className='intel-panel card shadow-sm'; el.innerHTML='<div class="card-header"><strong>'+title+'</strong></div><div class="card-body"><div class="intel-body">Loading...</div></div>'; createContainer().appendChild(el);} return el.querySelector('.intel-body');
  }
  async function fetchJSON(url){ const r=await fetch(url,{credentials:'same-origin'}); if(!r.ok) throw new Error(url+': '+r.status); return r.json(); }
  function safe(v){ return (v===null||v===undefined)?'—':v; }
  function rel(sec){ if(sec===null||sec===undefined) return '—'; if(sec<60) return sec+'s'; const h=(sec/3600).toFixed(1); return h+'h'; }

  async function loadKPIs(){
    try { const data=await fetchJSON(ENDPOINTS.kpis); const body=panel('panel-kpis','KPIs'); if(!data.success){ body.textContent='Unavailable'; return; }
      const k=data.data.kpis; body.innerHTML='<ul class="list-unstyled mb-0">'
        +'<li><strong>Transfers 7d:</strong> '+safe(k.transfers_7d?.transfers)+' ('+safe(k.transfers_7d?.units)+' units)</li>'
        +'<li><strong>Directive Freshness:</strong> '+rel(k.directive_freshness_seconds)+'</li>'
        +'<li><strong>Low Stock Items:</strong> '+safe(k.low_stock_items)+'</li>'
        +'<li><strong>Last Crawl:</strong> '+rel(k.last_crawl_seconds)+'</li>'
  +'<li><strong>Agent Success:</strong> '+(k.agent_success_rate? k.agent_success_rate+'%':'—')+'</li>'
  +'<li><strong>Insight Acceptance:</strong> '+(k.insight_acceptance_rate? k.insight_acceptance_rate+'%':'—')+'</li>'
  + (k.directive_weight_adjustment_hint? '<li><strong>Directive Hint:</strong> '+k.directive_weight_adjustment_hint+'</li>':'')
        +'</ul>';
    } catch(e){ panel('panel-kpis','KPIs').textContent='Error'; }
  }

  async function loadTransfers(){
    try { const data=await fetchJSON(ENDPOINTS.transfers); const body=panel('panel-transfers','Transfer Suggestions'); if(!data.success){ body.textContent='Unavailable'; return; }
      const list=data.data.suggestions||[]; if(!list.length){ body.textContent='No rebalancing required'; return; }
      body.innerHTML='<table class="table table-sm table-striped"><thead><tr><th>Product</th><th>From</th><th>To</th><th>Qty</th><th>Sev</th></tr></thead><tbody>'
        + list.slice(0,15).map(r=>'<tr><td>'+r.product_id+'</td><td>'+r.from_outlet+'</td><td>'+r.to_outlet+'</td><td>'+r.quantity+'</td><td>'+r.severity+'</td></tr>').join('') + '</tbody></table>';
    } catch(e){ panel('panel-transfers','Transfer Suggestions').textContent='Error'; }
  }

  async function loadCrawler(){
    try { const data=await fetchJSON(ENDPOINTS.crawler); const body=panel('panel-crawler','Crawler Summary'); if(!data.success){ body.textContent='Unavailable'; return; }
      const s=data.data.summary||{}; const agg=s.aggregate||{}; body.innerHTML='<div><strong>Crawls 24h:</strong> '+safe(agg.crawl_count)+' | Products: '+safe(agg.products_found)+' | Competitors: '+safe(agg.competitors)+'</div>'
        +'<div class="mt-2"><strong>Recent:</strong><ul class="small mb-0">'+(s.recent||[]).slice(0,5).map(r=>'<li>'+r.competitor_name+': '+r.products_found+' products</li>').join('')+'</ul></div>'
        +(s.top_threats? '<div class="mt-2"><strong>Threats:</strong><ul class="small mb-0">'+s.top_threats.slice(0,5).map(t=>'<li>'+t.our_product_id+' '+t.price_difference_percent+'%</li>').join('')+'</ul></div>':'');
    } catch(e){ panel('panel-crawler','Crawler Summary').textContent='Error'; }
  }

  async function loadInsights(){
    try { const data=await fetchJSON(ENDPOINTS.insights); const body=panel('panel-insights','Assistant Insights'); if(!data.success){ body.textContent='Unavailable'; return; }
      const list=data.data.insights||[]; if(!list.length){ body.textContent='No insights'; return; }
      body.innerHTML='<ul class="small mb-0">'+list.slice(0,10).map(i=>'<li data-insight="'+i.insight_id+'"><strong>'+i.title+'</strong> '+i.summary+' <span class="ms-1 text-nowrap">'
        +' <button class="btn btn-xs btn-outline-success btn-sm py-0 px-1" data-react="up">↑</button>'
        +' <button class="btn btn-xs btn-outline-danger btn-sm py-0 px-1" data-react="down">↓</button>'
        +'</span></li>').join('')+'</ul>';
      body.querySelectorAll('button[data-react]').forEach(btn=>{
        btn.addEventListener('click', async (e)=>{
          const li=e.target.closest('li'); const id=li.getAttribute('data-insight'); const reaction=e.target.getAttribute('data-react');
          try { await fetch('/api/assistant/insight/feedback',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({insight_id:id,user_id:1,reaction:reaction,csrf_token:window.CSRF_TOKEN||''})});
            li.style.opacity='0.5';
          } catch(_){}
        });
      });
    } catch(e){ panel('panel-insights','Assistant Insights').textContent='Error'; }
  }

  async function loadNeuro(){
    try { const data=await fetchJSON(ENDPOINTS.neuro); const body=panel('panel-neuro','Neuro State'); if(!data.success){ body.textContent='Unavailable'; return; }
      const d=data.data; body.innerHTML='<div><strong>Directives:</strong></div><ul class="small mb-2">'+(d.directives||[]).slice(0,6).map(dr=>'<li>'+dr.directive_type+' ('+dr.weight+')</li>').join('')+'</ul>'
        +'<div class="text-muted small">Freshness: '+(d.freshness_seconds? rel(d.freshness_seconds):'—')+'</div>';
    } catch(e){ panel('panel-neuro','Neuro State').textContent='Error'; }
  }

  function cycle(){ loadKPIs(); loadTransfers(); loadCrawler(); loadInsights(); loadNeuro(); }
  cycle(); setInterval(cycle, 60000); // refresh every 60s
})();
