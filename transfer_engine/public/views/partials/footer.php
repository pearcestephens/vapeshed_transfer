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
                    <div class="d-flex justify-content-between">
                        <span>Last Updated:</span>
                        <span class="text-muted" id="last-updated"><?php echo date('H:i'); ?></span>
                    </div>
                </div>
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
