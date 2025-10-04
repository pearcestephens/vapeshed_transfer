<?php
/**
 * Pricing History Tab - Enhanced with HistoryReadModel
 */

use Unified\Persistence\ReadModels\HistoryReadModel;
use Unified\Support\UiKernel;

$logger = UiKernel::logger();
$historyModel = new HistoryReadModel($logger);

// Get recent pricing history with guardrail traces
$history = $historyModel->enrichedHistory('pricing', 20);
?>

<div class="pricing-history">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5><i class="fas fa-history me-2"></i>Recent Pricing History</h5>
        <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-light" onclick="pricing.refreshHistory()">
                <i class="fas fa-sync"></i> Refresh
            </button>
            <button class="btn btn-outline-light" onclick="pricing.exportHistory()">
                <i class="fas fa-download"></i> Export
            </button>
        </div>
    </div>
    
    <?php if (empty($history)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            No pricing history available. Apply some pricing proposals to see them here.
        </div>
    <?php else: ?>
        <div class="history-timeline">
            <?php foreach ($history as $item): ?>
                <div class="history-item pricing-rule pricing-band-<?= strtolower($item['band'] ?? 'medium') ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="history-details flex-grow-1">
                            <div class="fw-bold">
                                Pricing #<?= htmlspecialchars($item['proposal_id']) ?>
                                <?= statusBadge($item['status'], [
                                    'applied' => 'success',
                                    'rejected' => 'danger', 
                                    'pending' => 'warning',
                                    'auto_applied' => 'info'
                                ]) ?>
                                
                                <?php if (!empty($item['band'])): ?>
                                    <span class="badge bg-secondary ms-2"><?= ucfirst($item['band']) ?> Impact</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="history-meta text-muted small mt-1">
                                <i class="fas fa-clock me-1"></i>
                                <?= date('M j, Y g:i A', strtotime($item['created_at'])) ?>
                                
                                <?php if (!empty($item['product_count'])): ?>
                                    <span class="ms-3">
                                        <i class="fas fa-tag me-1"></i>
                                        <?= number_format($item['product_count']) ?> products
                                    </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($item['price_change'])): ?>
                                    <span class="ms-3">
                                        <i class="fas fa-chart-line me-1"></i>
                                        <?= $item['price_change'] > 0 ? '+' : '' ?><?= number_format($item['price_change'], 1) ?>%
                                    </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($item['value_impact'])): ?>
                                    <span class="ms-3">
                                        <i class="fas fa-dollar-sign me-1"></i>
                                        $<?= number_format($item['value_impact']) ?> impact
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($item['guardrail_traces'])): ?>
                                <div class="guardrail-info mt-2">
                                    <small class="text-info">
                                        <i class="fas fa-shield-alt me-1"></i>
                                        Guardrails: <?= count($item['guardrail_traces']) ?> checks
                                        
                                        <?php 
                                        $passed = array_filter($item['guardrail_traces'], fn($t) => $t['result'] === 'pass');
                                        $failed = array_filter($item['guardrail_traces'], fn($t) => $t['result'] === 'fail');
                                        ?>
                                        
                                        <?php if (!empty($passed)): ?>
                                            <span class="badge bg-success ms-1"><?= count($passed) ?> passed</span>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($failed)): ?>
                                            <span class="badge bg-danger ms-1"><?= count($failed) ?> failed</span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($item['rule_name'])): ?>
                                <div class="pricing-rule-info mt-2">
                                    <small class="text-primary">
                                        <i class="fas fa-cogs me-1"></i>
                                        Rule: <?= htmlspecialchars($item['rule_name']) ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($item['notes'])): ?>
                                <div class="history-notes mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-sticky-note me-1"></i>
                                        <?= htmlspecialchars(substr($item['notes'], 0, 100)) ?>
                                        <?= strlen($item['notes']) > 100 ? '...' : '' ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="history-actions ms-3">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-light" 
                                        onclick="pricing.viewDetails('<?= $item['proposal_id'] ?>')" 
                                        title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                <?php if (!empty($item['guardrail_traces'])): ?>
                                    <button class="btn btn-outline-info" 
                                            onclick="pricing.viewGuardrails('<?= $item['proposal_id'] ?>')" 
                                            title="View Guardrail Results">
                                        <i class="fas fa-shield-alt"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($item['status'] === 'rejected' && !empty($item['rule_name'])): ?>
                                    <button class="btn btn-outline-warning" 
                                            onclick="pricing.reapply('<?= $item['proposal_id'] ?>')" 
                                            title="Reapply Pricing">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($item['status'] === 'applied'): ?>
                                    <button class="btn btn-outline-danger" 
                                            onclick="pricing.rollback('<?= $item['proposal_id'] ?>')" 
                                            title="Rollback Pricing">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-3">
            <button class="btn btn-outline-light" onclick="pricing.loadMoreHistory()">
                <i class="fas fa-chevron-down me-1"></i>Load More History
            </button>
        </div>
    <?php endif; ?>
</div>