/**
 * Pricing Module JavaScript
 * Handles pricing-specific UI interactions and real-time updates
 */
class PricingModule {
    constructor() {
        this.correlationId = window.__correlation_id || 'unknown';
        this.sseConnection = null;
        this.init();
    }

    init() {
        this.bindEvents();
        this.initSSE();
        console.log('[Pricing] Module initialized', { cid: this.correlationId });
    }

    bindEvents() {
        $('#refreshPricing').on('click', () => this.refreshData());
        $('#runPricing').on('click', () => this.runPricing());
        $('#applyAutoPricing').on('click', () => this.applyAuto());
        $('#refreshPricingCandidates').on('click', () => this.refreshCandidates());
    }

    initSSE() {
        if (typeof window.SSEManager !== 'undefined') {
            window.SSEManager.subscribe('pricing', (data) => this.handleSSEUpdate(data));
        }
    }

    async refreshData() {
        try {
            const response = await fetch('/api/pricing.php?action=status', {
                headers: { 'X-Correlation-ID': this.correlationId }
            });
            const data = await response.json();
            const stats = data.data || data.stats || data;
            this.updateStats(stats);
        } catch (error) {
            console.error('[Pricing] Refresh failed:', error);
        }
    }

    async runPricing() {
        try {
            const response = await fetch('/api/pricing.php?action=scan', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Correlation-ID': this.correlationId
                }
            });
            const result = await response.json();
            console.log('[Pricing] Run result:', result);
            this.refreshCandidates();
        } catch (error) {
            console.error('[Pricing] Run failed:', error);
        }
    }

    async applyAuto() {
        if (!confirm('Apply auto-pricing proposals?')) return;
        
        try {
            const response = await fetch('/api/pricing.php?action=apply', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Correlation-ID': this.correlationId
                },
                body: JSON.stringify({ apply_all: true })
            });
            const result = await response.json();
            console.log('[Pricing] Auto-apply result:', result);
        } catch (error) {
            console.error('[Pricing] Auto-apply failed:', error);
        }
    }

    async refreshCandidates() {
        try {
            const response = await fetch('/api/pricing.php?action=candidates', {
                headers: { 'X-Correlation-ID': this.correlationId }
            });
            const data = await response.json();
            const candidates = (data.data && data.data.candidates) || data.candidates || [];
            this.updateCandidatesTable(candidates);
        } catch (error) {
            console.error('[Pricing] Candidates refresh failed:', error);
        }
    }

    updateStats(stats) {
        if (!stats) return;
        Object.keys(stats).forEach(key => {
            $(`.stat-value[data-stat="${key}"]`).text(this.formatNumber(stats[key]));
        });
        // Enable/disable auto apply button based on available auto proposals
        const auto = parseInt(stats.auto || 0, 10);
        $('#applyAutoPricing').prop('disabled', !(auto > 0));
    }

    updateCandidatesTable(candidates) {
        const tbody = $('#pricingCandidatesTable tbody');
        tbody.empty();
        
        if (!candidates || candidates.length === 0) {
            tbody.append('<tr><td colspan="5" class="text-center py-4 text-muted">No candidates available</td></tr>');
            return;
        }
        
        candidates.forEach(candidate => {
            tbody.append(`
                <tr>
                    <td>${candidate.id}</td>
                    <td><span class="badge badge-${this.getBandColor(candidate.band)}">${candidate.band}</span></td>
                    <td>${parseFloat(candidate.score).toFixed(3)}</td>
                    <td>${candidate.blocked_by || '—'}</td>
                    <td>${this.formatDateTime(candidate.created_at)}</td>
                </tr>
            `);
        });
    }

    // ---- View action handlers used by tabs ----
    scanProducts() {
        return this.runPricing();
    }

    filterByBand(band) {
        const items = document.querySelectorAll('.pricing-candidates .pricing-rule');
        items.forEach(el => {
            const match = !band || (el.dataset.band || '').toLowerCase() === band.toLowerCase();
            el.style.display = match ? '' : 'none';
        });
    }

    searchProducts(query) {
        const q = (query || '').toLowerCase();
        const items = document.querySelectorAll('.pricing-candidates .pricing-rule');
        items.forEach(el => {
            const name = (el.dataset.product || '').toLowerCase();
            el.style.display = name.includes(q) ? '' : 'none';
        });
    }

    loadMoreCandidates() {
        console.log('[Pricing] loadMoreCandidates not implemented yet');
    }

    applyCandidate(id) {
        return fetch('/api/pricing.php?action=apply', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Correlation-ID': this.correlationId
            },
            body: JSON.stringify({ proposal_ids: [id] })
        }).then(r => r.json()).then(res => {
            console.log('[Pricing] applyCandidate', id, res);
            this.refreshCandidates();
        }).catch(err => console.error('[Pricing] applyCandidate failed', err));
    }

    reviewCandidate(id) {
        console.log('[Pricing] reviewCandidate', id);
        alert('Marked for review: ' + id);
    }

    discardCandidate(id) {
        console.log('[Pricing] discardCandidate', id);
        alert('Discarded candidate: ' + id);
    }

    // History and rule actions (stubs for now)
    refreshHistory() { console.log('[Pricing] refreshHistory'); }
    exportHistory() { console.log('[Pricing] exportHistory'); }
    loadMoreHistory() { console.log('[Pricing] loadMoreHistory'); }
    viewDetails(id) { console.log('[Pricing] viewDetails', id); }
    viewGuardrails(id) { console.log('[Pricing] viewGuardrails', id); }
    reapply(id) { console.log('[Pricing] reapply', id); }
    rollback(id) { console.log('[Pricing] rollback', id); }

    createRule() { console.log('[Pricing] createRule'); }
    editRule(id) { console.log('[Pricing] editRule', id); }
    runRule(id) { console.log('[Pricing] runRule', id); }
    toggleRule(id) { console.log('[Pricing] toggleRule', id); }
    deleteRule(id) { console.log('[Pricing] deleteRule', id); }
    saveSettings() { console.log('[Pricing] saveSettings'); }
    resetSettings() { console.log('[Pricing] resetSettings'); }

    getBandColor(band) {
        const colors = { auto: 'success', propose: 'info', discard: 'secondary', blocked: 'danger' };
        return colors[band] || 'secondary';
    }

    handleSSEUpdate(data) {
        console.log('[Pricing] SSE update:', data);
        // Backend emits named 'pricing' events; system 'status' has engine/queue too
        if (data && data.type === 'pricing_proposal') {
            this.refreshCandidates();
        } else if (data && (data.engine || data.queue)) {
            // If future status payloads include pricing stats, map when available
            // For now, just trigger a lightweight refresh occasionally
            // no-op to avoid spamming
        }
    }

    formatNumber(num) {
        return new Intl.NumberFormat().format(num);
    }

    formatDateTime(dt) {
        return new Date(dt).toLocaleString();
    }
}

// Initialize when DOM ready
$(document).ready(() => {
    if (window.location.pathname.includes('/modules/pricing')) {
        window.pricingModule = new PricingModule();
        // Back-compat alias used in PHP view onclick handlers
        window.pricing = window.pricingModule;
    }
});