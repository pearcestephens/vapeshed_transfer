<footer class="footer bg-dark text-light py-4 mt-5">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3">
                <h5><?php echo config('app.name'); ?></h5>
                <p class="text-muted small">
                    Intelligent stock transfer and pricing system for The Vape Shed retail chain.
                </p>
                <p class="small mb-0">
                    <strong>Version:</strong> <?php echo config('app.version'); ?><br>
                    <strong>Environment:</strong> <?php echo config('app.debug') ? 'Development' : 'Production'; ?>
                </p>
            </div>
            
            <div class="col-md-3">
                <h6>Quick Links</h6>
                <ul class="list-unstyled small">
                    <li><a href="<?php echo url('/dashboard'); ?>" class="text-muted">Dashboard</a></li>
                    <li><a href="<?php echo url('/modules/transfer'); ?>" class="text-muted">Transfer Engine</a></li>
                    <li><a href="<?php echo url('/modules/pricing'); ?>" class="text-muted">Pricing Intelligence</a></li>
                    <li><a href="<?php echo url('/modules/config'); ?>" class="text-muted">Configuration</a></li>
                    <li><a href="<?php echo url('/modules/health'); ?>" class="text-muted">System Health</a></li>
                </ul>
            </div>
            
            <div class="col-md-3">
                <h6>Resources</h6>
                <ul class="list-unstyled small">
                    <li><a href="<?php echo url('/docs'); ?>" class="text-muted">Documentation</a></li>
                    <li><a href="<?php echo url('/docs/api'); ?>" class="text-muted">API Reference</a></li>
                    <li><a href="<?php echo url('/support'); ?>" class="text-muted">Support</a></li>
                    <li><a href="<?php echo url('/changelog'); ?>" class="text-muted">Changelog</a></li>
                </ul>
            </div>
            
            <div class="col-md-3">
                <h6>System Status</h6>
                <div class="small">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Database:</span>
                        <span class="badge badge-success" id="db-status">Online</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>SSE:</span>
                        <span class="badge badge-secondary" id="sse-status">Connecting...</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-1" id="smoke-row" style="display:none;">
                        <span>Smoke:</span>
                        <div>
                            <a href="/api/smoke_summary.php" class="text-muted small mr-2" target="_blank" rel="noopener noreferrer" title="Open JSON summary">View</a>
                            <span class="badge badge-secondary" id="smoke-status">—</span>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Last Updated:</span>
                        <span class="text-muted" id="last-updated"><?php echo date('H:i'); ?></span>
                    </div>
                </div>
                <?php $__footerProposals = (bool) (config('neuro.unified.ui.footer_proposals_enabled', false)); ?>
                <?php if ($__footerProposals): ?>
                <div class="small mt-2" id="footer-proposals">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span>Proposals Today:</span>
                        <div>
                            <span class="badge badge-info mr-1" id="transfers-today" title="Transfers today">T: 0</span>
                            <span class="badge badge-info" id="pricing-today" title="Pricing today">P: 0</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <hr class="bg-secondary my-3">
        
        <div class="row">
            <div class="col-md-6 small text-muted">
                &copy; <?php echo date('Y'); ?> Ecigdis Limited / The Vape Shed. All rights reserved.
            </div>
            <div class="col-md-6 text-md-right small">
                <a href="<?php echo url('/privacy'); ?>" class="text-muted">Privacy Policy</a>
                <span class="mx-2">|</span>
                <a href="<?php echo url('/terms'); ?>" class="text-muted">Terms of Service</a>
            </div>
        </div>
        <?php if ((bool) (config('neuro.unified.ui.show_diagnostics', false))): ?>
            <div class="mt-3 p-2 bg-secondary rounded small">
                <div class="d-flex flex-wrap align-items-center text-light">
                    <span class="mr-3">CID: <code><?php echo e(correlationId()); ?></code></span>
                    <span class="mr-3">CSRF: <code><?php echo substr((string)($_SESSION['_csrf'] ?? ''),0,8); ?>…</code></span>
                    <span class="mr-3">SSE Caps: <code>G=<?php echo (int)config('neuro.unified.sse.max_global',200); ?>/IP=<?php echo (int)config('neuro.unified.sse.max_per_ip',3); ?></code></span>
                    <span class="mr-3">SSE Cadence: <code>S=<?php echo (int)config('neuro.unified.sse.status_period_sec',5); ?>/H=<?php echo (int)config('neuro.unified.sse.heartbeat_period_sec',15); ?></code></span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</footer>

<!-- SSE Manager & Correlation ID -->
<script>
// Set correlation ID globally
window.__correlation_id = '<?php echo correlationId(); ?>';

// Simple SSE Manager
class SSEManager {
    constructor() {
        this.eventSource = null;
        this.subscribers = new Map();
        this.reconnectDelay = 1000;
        this.maxReconnectDelay = 30000;
        this.connect();
    }

    connect() {
        try {
            // Selectively subscribe to topics to reduce server work
            const path = window.location.pathname || '';
            const topics = ['status','heartbeat'];
            if (path.includes('/modules/transfer')) topics.push('transfer');
            if (path.includes('/modules/pricing')) topics.push('pricing');
            const url = '/sse.php?cid=' + encodeURIComponent(window.__correlation_id) + '&topics=' + encodeURIComponent(topics.join(','));
            this.eventSource = new EventSource(url);
            
            this.eventSource.onopen = () => {
                console.log('[SSE] Connected');
                $('#sse-status').removeClass('badge-secondary badge-danger').addClass('badge-success').text('Connected');
                this.reconnectDelay = 1000;
            };
            
            this.eventSource.onmessage = (event) => {
                this.handleEvent('message', event.data);
            };

            // Listen for named events (status, transfer, pricing, heartbeat, error, system)
            ['status','transfer','pricing','heartbeat','error','system'].forEach(evt => {
                this.eventSource.addEventListener(evt, (e) => this.handleEvent(evt, e.data));
            });
            
            this.eventSource.onerror = () => {
                console.warn('[SSE] Connection error, reconnecting...');
                $('#sse-status').removeClass('badge-success badge-secondary').addClass('badge-danger').text('Error');
                this.eventSource.close();
                setTimeout(() => this.connect(), this.reconnectDelay);
                this.reconnectDelay = Math.min(this.reconnectDelay * 2, this.maxReconnectDelay);
            };
        } catch (e) {
            console.error('[SSE] Failed to connect:', e);
        }
    }

    handleEvent(eventName, raw) {
        try {
            const data = typeof raw === 'string' ? JSON.parse(raw) : raw;
            const channel = data.channel || eventName || 'default';
            // Handle server over-capacity advisory by increasing backoff
            if (eventName === 'system' && data && data.type === 'over_capacity') {
                const retry = Number(data.retry_ms || 3000);
                this.reconnectDelay = Math.max(this.reconnectDelay, retry);
                $('#sse-status').removeClass('badge-success badge-secondary').addClass('badge-warning').text('Busy');
            }
            this.dispatch(channel, data);
            $('#last-updated').text(new Date().toLocaleTimeString());
        } catch (e) {
            console.error('[SSE] Parse error:', e, raw);
        }
    }

    subscribe(channel, callback) {
        if (!this.subscribers.has(channel)) {
            this.subscribers.set(channel, []);
        }
        this.subscribers.get(channel).push(callback);
    }

    dispatch(channel, data) {
        const callbacks = this.subscribers.get(channel) || [];
        callbacks.forEach(callback => {
            try {
                callback(data);
            } catch (e) {
                console.error('[SSE] Callback error:', e);
            }
        });
    }

    close() {
        if (this.eventSource) {
            this.eventSource.close();
        }
    }
}

// Initialize SSE Manager
$(document).ready(() => {
    window.SSEManager = new SSEManager();
    
    // Cleanup on page unload
    $(window).on('beforeunload', () => {
        if (window.SSEManager) {
            window.SSEManager.close();
        }
    });
});
</script>

<?php $__smokeEnabled = (bool) (config('neuro.unified.ui.smoke_summary_enabled', false)); ?>
<?php if ($__smokeEnabled): ?>
<script>
(function(){
    const row = document.getElementById('smoke-row');
    const badge = document.getElementById('smoke-status');
    if (!row || !badge) return;
    row.style.display = '';

    function setBadge(state, meta){
        badge.classList.remove('badge-success','badge-danger','badge-warning','badge-secondary');
        let cls = 'badge-secondary';
        if (state === 'GREEN') cls = 'badge-success';
        else if (state === 'RED') cls = 'badge-danger';
        else if (state === 'SKIPPED') cls = 'badge-warning';
        badge.classList.add(cls);
        badge.textContent = state;
        if (meta && meta.counts){
            badge.title = `G:${meta.counts.GREEN||0} R:${meta.counts.RED||0} S:${meta.counts.SKIPPED||0}`;
        }
    }

    async function fetchSmoke(){
        try{
            const res = await fetch('/api/smoke_summary.php', { headers: { 'Accept':'application/json' } });
            if (!res.ok){ setBadge('SKIPPED'); return; }
            const json = await res.json();
            if (!json || json.success !== true){ setBadge('SKIPPED'); return; }
            const last = (json.data && json.data.last) ? json.data.last : null;
            const status = (last && typeof last.status === 'string') ? last.status.toUpperCase() : 'SKIPPED';
            setBadge(status, json.data || {});
        }catch(e){ setBadge('SKIPPED'); }
    }

    fetchSmoke();
    setInterval(fetchSmoke, 60000);
})();
</script>
<?php endif; ?>

<?php $__sseHealthPoll = (bool) (config('neuro.unified.ui.sse_health_poll_enabled', false)); ?>
<?php if ($__sseHealthPoll): ?>
<script>
(function(){
    const badge = document.getElementById('sse-status');
    if (!badge) return;

    function setSseBadge(state){
        // Do not override hard error state
        if (badge.classList.contains('badge-danger') && badge.textContent === 'Error') return;
        badge.classList.remove('badge-success','badge-warning','badge-danger','badge-secondary');
        if (state === 'green') { badge.classList.add('badge-success'); badge.textContent = 'Connected'; }
        else if (state === 'yellow') { badge.classList.add('badge-warning'); badge.textContent = 'Busy'; }
        else if (state === 'red') { badge.classList.add('badge-danger'); badge.textContent = 'Busy'; }
        else { badge.classList.add('badge-secondary'); }
    }

    async function poll(){
        try {
            const res = await fetch('/health_sse.php', { headers: { 'Accept':'application/json' }, cache: 'no-store' });
            if (!res.ok) return;
            const json = await res.json();
            if (!json || json.success !== true || !json.data) return;
            const status = json.data.status || 'green';
            setSseBadge(status);
        } catch (e) { /* silent */ }
    }

    // Initial and periodic poll (every 90s)
    poll();
    setInterval(poll, 90000);
})();
</script>
<?php endif; ?>

<?php if ($__footerProposals): ?>
<script>
(function(){
    const elT = document.getElementById('transfers-today');
    const elP = document.getElementById('pricing-today');
    if (!elT || !elP) return;

    async function poll(){
        try {
            const [tr, pr] = await Promise.all([
                fetch('/api/transfer.php?action=status', { headers: { 'Accept':'application/json' }, cache:'no-store' }),
                fetch('/api/pricing.php?action=status', { headers: { 'Accept':'application/json' }, cache:'no-store' })
            ]);
            if (tr && tr.ok){
                const j = await tr.json();
                const t = (j && j.stats && typeof j.stats.today === 'number') ? j.stats.today : 0;
                elT.textContent = 'T: ' + t;
            }
            if (pr && pr.ok){
                const j2 = await pr.json();
                const p = (j2 && j2.stats && typeof j2.stats.today === 'number') ? j2.stats.today : 0;
                elP.textContent = 'P: ' + p;
            }
        } catch(e) { /* silent */ }
    }
    poll();
    setInterval(poll, 120000);
})();
</script>
<?php endif; ?>
