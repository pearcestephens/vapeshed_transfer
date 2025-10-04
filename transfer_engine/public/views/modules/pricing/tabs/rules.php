<?php
/**
 * Pricing Rules Tab
 * Configure and manage pricing rules
 */
?>

<div class="pricing-rules">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5><i class="fas fa-cogs me-2"></i>Pricing Rules</h5>
        <div class="btn-group btn-group-sm">
            <button class="btn btn-primary" onclick="pricing.createRule()">
                <i class="fas fa-plus"></i> New Rule
            </button>
            <button class="btn btn-outline-light" onclick="pricing.refreshRules()">
                <i class="fas fa-sync"></i> Refresh
            </button>
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-md-4">
            <?= statCard('Active Rules', 8, 'fas fa-play-circle', 'success') ?>
        </div>
        <div class="col-md-4">
            <?= statCard('Inactive Rules', 3, 'fas fa-pause-circle', 'warning') ?>
        </div>
        <div class="col-md-4">
            <?= statCard('Auto Apply', 5, 'fas fa-magic', 'info') ?>
        </div>
    </div>
    
    <div class="rules-list">
        <!-- Competitor Price Match Rule -->
        <div class="pricing-rule pricing-band-medium">
            <div class="pricing-rule-info">
                <div class="fw-bold">
                    Competitor Price Match
                    <?= statusBadge('active', ['active' => 'success', 'inactive' => 'secondary']) ?>
                    <span class="badge bg-info ms-2">Auto Apply</span>
                </div>
                
                <div class="pricing-impact">
                    <span class="text-muted">Matches competitor prices when we're 5% or more higher</span>
                </div>
                
                <div class="text-muted small mt-1">
                    <i class="fas fa-chart-line me-1"></i>
                    Impact: Medium • Products: 45 • Frequency: Daily
                </div>
                
                <div class="text-muted small">
                    <i class="fas fa-clock me-1"></i>
                    Last Run: <?= date('M j, g:i A', strtotime('-2 hours')) ?> • 
                    Next Run: <?= date('M j, g:i A', strtotime('+10 hours')) ?>
                </div>
            </div>
            
            <div class="pricing-rule-actions">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-light" 
                            onclick="pricing.editRule('competitor_match')" 
                            title="Edit Rule">
                        <i class="fas fa-edit"></i>
                    </button>
                    
                    <button class="btn btn-outline-info" 
                            onclick="pricing.runRule('competitor_match')" 
                            title="Run Now">
                        <i class="fas fa-play"></i>
                    </button>
                    
                    <button class="btn btn-outline-warning" 
                            onclick="pricing.toggleRule('competitor_match')" 
                            title="Pause Rule">
                        <i class="fas fa-pause"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Slow Moving Stock Rule -->
        <div class="pricing-rule pricing-band-high">
            <div class="pricing-rule-info">
                <div class="fw-bold">
                    Slow Moving Stock Discount
                    <?= statusBadge('active', ['active' => 'success', 'inactive' => 'secondary']) ?>
                    <span class="badge bg-warning ms-2">Manual Review</span>
                </div>
                
                <div class="pricing-impact">
                    <span class="text-muted">Applies discounts to products with low turn rates (< 2 sales/month)</span>
                </div>
                
                <div class="text-muted small mt-1">
                    <i class="fas fa-chart-line me-1"></i>
                    Impact: High • Products: 23 • Frequency: Weekly
                </div>
                
                <div class="text-muted small">
                    <i class="fas fa-clock me-1"></i>
                    Last Run: <?= date('M j, g:i A', strtotime('-1 day')) ?> • 
                    Next Run: <?= date('M j, g:i A', strtotime('+6 days')) ?>
                </div>
            </div>
            
            <div class="pricing-rule-actions">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-light" 
                            onclick="pricing.editRule('slow_moving')" 
                            title="Edit Rule">
                        <i class="fas fa-edit"></i>
                    </button>
                    
                    <button class="btn btn-outline-info" 
                            onclick="pricing.runRule('slow_moving')" 
                            title="Run Now">
                        <i class="fas fa-play"></i>
                    </button>
                    
                    <button class="btn btn-outline-warning" 
                            onclick="pricing.toggleRule('slow_moving')" 
                            title="Pause Rule">
                        <i class="fas fa-pause"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Promotional Pricing Rule -->
        <div class="pricing-rule pricing-band-low">
            <div class="pricing-rule-info">
                <div class="fw-bold">
                    Promotional End-of-Line
                    <?= statusBadge('inactive', ['active' => 'success', 'inactive' => 'secondary']) ?>
                    <span class="badge bg-danger ms-2">Disabled</span>
                </div>
                
                <div class="pricing-impact">
                    <span class="text-muted">Automatically marks discontinued products for promotional pricing</span>
                </div>
                
                <div class="text-muted small mt-1">
                    <i class="fas fa-chart-line me-1"></i>
                    Impact: Low • Products: 12 • Frequency: As Needed
                </div>
                
                <div class="text-muted small">
                    <i class="fas fa-clock me-1"></i>
                    Last Run: <?= date('M j, g:i A', strtotime('-1 week')) ?> • 
                    Next Run: Disabled
                </div>
            </div>
            
            <div class="pricing-rule-actions">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-light" 
                            onclick="pricing.editRule('promotional')" 
                            title="Edit Rule">
                        <i class="fas fa-edit"></i>
                    </button>
                    
                    <button class="btn btn-outline-success" 
                            onclick="pricing.toggleRule('promotional')" 
                            title="Enable Rule">
                        <i class="fas fa-play"></i>
                    </button>
                    
                    <button class="btn btn-outline-danger" 
                            onclick="pricing.deleteRule('promotional')" 
                            title="Delete Rule">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="text-center mt-3">
        <p class="text-muted">Rules are evaluated automatically based on their frequency settings. 
           Use "Run Now" to execute rules immediately.</p>
    </div>
</div>