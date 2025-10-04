/**
 * Transfer Module JavaScript
 * Handles transfer-specific UI interactions, SSE subscriptions, and API calls
 */
class TransferModule {
    constructor() {
        this.correlationId = window.__correlation_id || 'unknown';
        this.sseConnection = null;
        this.init();
    }

    init() {
        this.bindEvents();
        this.initSSE();
        // Disable execute until a selection is made
        $('#executeQueue').prop('disabled', true);
        console.log('[Transfer] Module initialized', { cid: this.correlationId });
    }

    bindEvents() {
        // Refresh data button
        $('#refreshData').on('click', () => this.refreshData());
        
        // Calculate transfers button
        $('#calculateTransfers').on('click', () => this.calculateTransfers());
        
        // Execute queue button
        $('#executeQueue').on('click', () => this.executeQueue());
        
        // DSR Calculator form
        $('#dsrCalcForm').on('submit', (e) => {
            e.preventDefault();
            this.calculateDSR();
        });
        
        // Queue selection
        $('#selectAllQueue').on('change', (e) => this.toggleQueueSelection(e.target.checked));
    }

    initSSE() {
        if (typeof window.SSEManager !== 'undefined') {
            window.SSEManager.subscribe('transfer', (data) => this.handleSSEUpdate(data));
        }
    }

    async refreshData() {
        try {
            const response = await fetch('/api/transfer.php?action=status', {
                headers: { 'X-Correlation-ID': this.correlationId }
            });
            const data = await response.json();
            const stats = data.data || data.stats || data;
            this.updateStats(stats);
        } catch (error) {
            console.error('[Transfer] Refresh failed:', error);
        }
    }

    async calculateTransfers() {
        try {
            const response = await fetch('/api/transfer.php?action=calculate', {
                headers: { 'X-Correlation-ID': this.correlationId }
            });
            const result = await response.json();
            console.log('[Transfer] Calculate result:', result);
        } catch (error) {
            console.error('[Transfer] Calculate failed:', error);
        }
    }

    async executeQueue() {
        const selected = $('.queue-checkbox:checked').length;
        if (selected === 0) {
            alert('Please select transfers to execute');
            return;
        }
        
        if (!confirm(`Execute ${selected} transfers?`)) return;
        
        try {
            const response = await fetch('/api/transfer.php?action=execute', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Correlation-ID': this.correlationId
                },
                body: JSON.stringify({ ids: this.getSelectedIds() })
            });
            const result = await response.json();
            console.log('[Transfer] Execute result:', result);
        } catch (error) {
            console.error('[Transfer] Execute failed:', error);
        }
    }

    calculateDSR() {
        const sku = $('#sku').val();
        const stock = parseFloat($('#stock').val()) || 0;
        const avgDemand = parseFloat($('#avgDemand').val()) || 1;
        
        const dsr = avgDemand > 0 ? (stock / avgDemand).toFixed(1) : 'N/A';
        
        $('#dsrResult')
            .removeClass('d-none alert-warning alert-success')
            .addClass(dsr > 10 ? 'alert-success' : 'alert-warning')
            .html(`<strong>${sku || 'Product'}:</strong> ${dsr} days supply`);
    }

    toggleQueueSelection(checked) {
        $('.queue-checkbox').prop('checked', checked);
        const anyChecked = $('.queue-checkbox:checked').length > 0;
        $('#executeQueue').prop('disabled', !anyChecked);
    }

    getSelectedIds() {
        return $('.queue-checkbox:checked').map((i, el) => $(el).data('id')).get();
    }

    updateStats(stats) {
        if (!stats) return;
        // Map incoming keys to UI keys when needed
        const mapped = {
            pending: stats.pending ?? stats.queue_size ?? 0,
            today: stats.today ?? stats.completed_today ?? 0,
            failed: stats.failed ?? 0,
            total: stats.total ?? stats.seven_day_total ?? 0,
        };
        Object.keys(mapped).forEach(key => {
            $(`.stat-value[data-stat="${key}"]`).text(this.formatNumber(mapped[key]));
        });
    }

    handleSSEUpdate(data) {
        console.log('[Transfer] SSE update:', data);
        // Handle named status event structure from backend footer SSE
        if (data && (data.queue || data.engine)) {
            const stats = {
                pending: (data.queue && data.queue.transfer_pending) ? data.queue.transfer_pending : 0,
                today: 0,
                failed: 0,
                total: 0,
            };
            this.updateStats(stats);
        }
    }

    formatNumber(num) {
        return new Intl.NumberFormat().format(num);
    }

    // ---- View action handlers used by tabs (stubs) ----
    refreshHistory() { console.log('[Transfer] refreshHistory'); }
    exportHistory() { console.log('[Transfer] exportHistory'); }
    loadMoreHistory() { console.log('[Transfer] loadMoreHistory'); }
    viewDetails(id) { console.log('[Transfer] viewDetails', id); }
    viewGuardrails(id) { console.log('[Transfer] viewGuardrails', id); }
    retry(id) { console.log('[Transfer] retry', id); }
}

// Initialize when DOM ready
$(document).ready(() => {
    if (window.location.pathname.includes('/modules/transfer')) {
        window.transferModule = new TransferModule();
        // Back-compat alias used in PHP view onclick handlers
        window.transfer = window.transferModule;
    }
});