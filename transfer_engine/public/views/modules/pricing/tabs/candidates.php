<?php
/**
 * Pricing Candidates Tab
 * Shows products eligible for pricing changes
 */

use Unified\Persistence\ReadModels\PricingReadModel;
use Unified\Support\UiKernel;

$logger = UiKernel::logger();
$pricingModel = new PricingReadModel($logger);

// Get recent pricing candidates
$candidates = $pricingModel->recent(25);
?>

<div class="pricing-candidates">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5><i class="fas fa-search me-2"></i>Pricing Candidates</h5>
        <div class="btn-group btn-group-sm">
            <button class="btn btn-info" onclick="pricing.scanProducts()">
                <i class="fas fa-search"></i> Scan Products
            </button>
            <button class="btn btn-outline-light" onclick="pricing.refreshCandidates()">
                <i class="fas fa-sync"></i> Refresh
            </button>
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="fas fa-filter"></i></span>
                <select class="form-select" id="bandFilter" onchange="pricing.filterByBand(this.value)">
                    <option value="">All Impact Bands</option>
                    <option value="low">Low Impact</option>
                    <option value="medium">Medium Impact</option>
                    <option value="high">High Impact</option>
                </select>
            </div>
        </div>
        <div class="col-md-6">
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control" placeholder="Search products..." 
                       id="productSearch" onkeyup="pricing.searchProducts(this.value)">
            </div>
        </div>
    </div>
    
    <?php if (empty($candidates)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            No pricing candidates found. Run a product scan to find opportunities.
        </div>
    <?php else: ?>
        <div class="candidates-list">
            <?php foreach ($candidates as $candidate): ?>
                <div class="pricing-rule pricing-band-<?= strtolower($candidate['band'] ?? 'medium') ?>" 
                     data-band="<?= $candidate['band'] ?? 'medium' ?>" 
                     data-product="<?= htmlspecialchars($candidate['product_name'] ?? '') ?>">
                    
                    <div class="pricing-rule-info">
                        <div class="fw-bold">
                            <?= htmlspecialchars($candidate['product_name'] ?? 'Unknown Product') ?>
                            <span class="badge bg-secondary ms-2"><?= ucfirst($candidate['band'] ?? 'Medium') ?></span>
                        </div>
                        
                        <div class="pricing-impact">
                            <span class="text-muted">Current: $<?= number_format($candidate['current_price'] ?? 0, 2) ?></span>
                            <i class="fas fa-arrow-right mx-2"></i>
                            <span class="text-success">Proposed: $<?= number_format($candidate['proposed_price'] ?? 0, 2) ?></span>
                            
                            <?php 
                            $change = ($candidate['proposed_price'] ?? 0) - ($candidate['current_price'] ?? 0);
                            $changePercent = ($candidate['current_price'] ?? 0) > 0 ? 
                                ($change / $candidate['current_price']) * 100 : 0;
                            ?>
                            
                            <span class="ms-3 <?= $change >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= $change >= 0 ? '+' : '' ?>$<?= number_format($change, 2) ?> 
                                (<?= $changePercent >= 0 ? '+' : '' ?><?= number_format($changePercent, 1) ?>%)
                            </span>
                        </div>
                        
                        <?php if (!empty($candidate['rule_reason'])): ?>
                            <div class="text-muted small mt-1">
                                <i class="fas fa-lightbulb me-1"></i>
                                <?= htmlspecialchars($candidate['rule_reason']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="text-muted small mt-1">
                            <i class="fas fa-clock me-1"></i>
                            Found: <?= date('M j, g:i A', strtotime($candidate['created_at'])) ?>
                            
                            <?php if (!empty($candidate['competitor_price'])): ?>
                                <span class="ms-3">
                                    <i class="fas fa-store me-1"></i>
                                    Competitor: $<?= number_format($candidate['competitor_price'], 2) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="pricing-rule-actions">
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-success" 
                                    onclick="pricing.applyCandidate('<?= $candidate['id'] ?>')" 
                                    title="Apply This Pricing">
                                <i class="fas fa-check"></i>
                            </button>
                            
                            <button class="btn btn-warning" 
                                    onclick="pricing.reviewCandidate('<?= $candidate['id'] ?>')" 
                                    title="Mark for Review">
                                <i class="fas fa-eye"></i>
                            </button>
                            
                            <button class="btn btn-danger" 
                                    onclick="pricing.discardCandidate('<?= $candidate['id'] ?>')" 
                                    title="Discard Candidate">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-3">
            <button class="btn btn-outline-light" onclick="pricing.loadMoreCandidates()">
                <i class="fas fa-chevron-down me-1"></i>Load More Candidates
            </button>
        </div>
    <?php endif; ?>
</div>