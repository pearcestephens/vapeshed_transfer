<?php
/**
 * Pricing Intelligence Module Dashboard
 * 
 * Advanced competitor price monitoring, rule management, and price proposal system.
 * Displays real-time competitor pricing, margin analysis, and automated pricing rules.
 * 
 * @package VapeshedTransfer
 * @subpackage Dashboard
 */
declare(strict_types=1);

require_once __DIR__ . '/../../../config/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/template.php';

requireAuth();

$pageTitle = 'Pricing Intelligence';
$currentModule = 'pricing';
$currentUser = getCurrentUser();
$breadcrumbs = [
    'Dashboard' => '/dashboard/',
    'Pricing Intelligence' => null
];

// Fetch pricing statistics
try {
    $db = new PDO("mysql:host=localhost;dbname=transfer_engine", "root", "");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get proposal statistics
    $stmt = $db->query("SELECT 
        COUNT(*) as total_proposals,
        AVG(final_price) as avg_price,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        AVG(margin_pct) as avg_margin
    FROM proposal_log 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $pricingStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent proposals
    $stmt = $db->query("SELECT * FROM proposal_log WHERE proposal_type = 'pricing' ORDER BY created_at DESC LIMIT 50");
    $recentProposals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Development fallback
    $pricingStats = ['total_proposals' => 0, 'avg_price' => 0, 'approved_count' => 0, 'avg_margin' => 0];
    $recentProposals = [];
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid pricing-module" style="max-width: 1600px; padding: 24px;">
    
    <!-- Module Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">
                <i class="fas fa-tags" style="color: #ec4899;"></i>
                Pricing Intelligence
            </h2>
            <p class="text-muted mb-0">Competitive pricing engine with real-time market monitoring</p>
        </div>
        <div>
            <button class="btn btn-outline-primary" id="refreshPricing">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <button class="btn btn-primary" id="scanCompetitors">
                <i class="fas fa-search"></i> Scan Competitors
            </button>
            <button class="btn btn-success" id="generateProposals">
                <i class="fas fa-magic"></i> Generate Proposals
            </button>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);">
                    <i class="fas fa-lightbulb"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-label">Active Proposals</div>
                    <div class="stat-value"><?php echo formatNumber($pricingStats['total_proposals']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-label">Approved (7d)</div>
                    <div class="stat-value"><?php echo formatNumber($pricingStats['approved_count']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-label">Avg Margin</div>
                    <div class="stat-value"><?php echo formatPercent($pricingStats['avg_margin'] / 100); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-label">Avg Price</div>
                    <div class="stat-value"><?php echo formatCurrency($pricingStats['avg_price']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Tabs -->
    <ul class="nav nav-tabs mb-4" id="pricingTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="comparison-tab" data-toggle="tab" href="#comparison" role="tab">
                <i class="fas fa-chart-line"></i> Price Comparison
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="proposals-tab" data-toggle="tab" href="#proposals" role="tab">
                <i class="fas fa-lightbulb"></i> Proposals
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="rules-tab" data-toggle="tab" href="#rules" role="tab">
                <i class="fas fa-shield-alt"></i> Pricing Rules
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="competitors-tab" data-toggle="tab" href="#competitors" role="tab">
                <i class="fas fa-store"></i> Competitors
            </a>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="pricingTabContent">
        
        <!-- Price Comparison Tab -->
        <div class="tab-pane fade show active" id="comparison" role="tabpanel">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line text-primary"></i>
                        Competitor Price Comparison Grid
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Search and Filters -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" id="searchProduct" placeholder="Search by product name or SKU...">
                        </div>
                        <div class="col-md-3">
                            <select class="form-control" id="filterCategory">
                                <option value="">All Categories</option>
                                <option value="devices">Devices</option>
                                <option value="liquids">E-Liquids</option>
                                <option value="accessories">Accessories</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-control" id="filterPriceStatus">
                                <option value="">All Price Status</option>
                                <option value="competitive">Competitive</option>
                                <option value="higher">Higher than Market</option>
                                <option value="lower">Lower than Market</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-primary btn-block" id="applyComparisonFilters">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                    </div>

                    <!-- Comparison Table -->
                    <div class="table-responsive">
                        <table class="table table-hover table-sm" id="comparisonTable">
                            <thead class="thead-light">
                                <tr>
                                    <th width="200">Product</th>
                                    <th>Our Price</th>
                                    <th>Comp A</th>
                                    <th>Comp B</th>
                                    <th>Comp C</th>
                                    <th>Market Min</th>
                                    <th>Market Avg</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Sample Data Row -->
                                <tr>
                                    <td><strong>JUUL Device Kit</strong><br><small class="text-muted">SKU: JUUL-001</small></td>
                                    <td><strong class="text-primary">$49.99</strong></td>
                                    <td>$52.00</td>
                                    <td>$48.50</td>
                                    <td>$51.99</td>
                                    <td>$48.50</td>
                                    <td>$50.83</td>
                                    <td><span class="badge badge-success">Competitive</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary view-details" data-sku="JUUL-001">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Vaporesso XROS 3</strong><br><small class="text-muted">SKU: VAPO-003</small></td>
                                    <td><strong class="text-danger">$65.00</strong></td>
                                    <td>$58.00</td>
                                    <td>$59.99</td>
                                    <td>$57.50</td>
                                    <td>$57.50</td>
                                    <td>$58.50</td>
                                    <td><span class="badge badge-danger">Higher</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning propose-price" data-sku="VAPO-003">
                                            <i class="fas fa-magic"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Nic Salt 50mg 30ml</strong><br><small class="text-muted">SKU: SALT-030</small></td>
                                    <td><strong class="text-success">$24.99</strong></td>
                                    <td>$28.00</td>
                                    <td>$27.50</td>
                                    <td>$29.99</td>
                                    <td>$24.99</td>
                                    <td>$28.50</td>
                                    <td><span class="badge badge-info">Market Leader</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary view-details" data-sku="SALT-030">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <nav>
                        <ul class="pagination justify-content-center">
                            <li class="page-item disabled"><a class="page-link" href="#">Previous</a></li>
                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                            <li class="page-item"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                            <li class="page-item"><a class="page-link" href="#">Next</a></li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Proposals Tab -->
        <div class="tab-pane fade" id="proposals" role="tabpanel">
            <div class="card">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-lightbulb text-primary"></i>
                        Active Price Proposals
                    </h5>
                    <button class="btn btn-sm btn-success" id="approveSelected">
                        <i class="fas fa-check"></i> Approve Selected
                    </button>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th width="50"><input type="checkbox" id="selectAllProposals"></th>
                                <th>Product</th>
                                <th>Current Price</th>
                                <th>Proposed Price</th>
                                <th>Change</th>
                                <th>Margin Impact</th>
                                <th>Confidence</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentProposals)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-2"></i>
                                    <p>No active price proposals</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach (array_slice($recentProposals, 0, 10) as $proposal): ?>
                            <tr>
                                <td><input type="checkbox" class="proposal-checkbox" value="<?php echo $proposal['id']; ?>"></td>
                                <td><?php echo htmlspecialchars($proposal['product_sku'] ?? 'N/A'); ?></td>
                                <td><?php echo formatCurrency($proposal['current_price'] ?? 0); ?></td>
                                <td><strong><?php echo formatCurrency($proposal['final_price'] ?? 0); ?></strong></td>
                                <td>
                                    <?php 
                                    $change = (($proposal['final_price'] ?? 0) - ($proposal['current_price'] ?? 0)) / ($proposal['current_price'] ?? 1) * 100;
                                    $changeClass = $change > 0 ? 'text-success' : 'text-danger';
                                    echo "<span class='$changeClass'>" . formatPercent($change / 100) . "</span>";
                                    ?>
                                </td>
                                <td><?php echo formatPercent(($proposal['margin_pct'] ?? 0) / 100); ?></td>
                                <td><span class="badge badge-success">High</span></td>
                                <td>
                                    <button class="btn btn-sm btn-success approve-proposal" data-id="<?php echo $proposal['id']; ?>">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger reject-proposal" data-id="<?php echo $proposal['id']; ?>">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pricing Rules Tab -->
        <div class="tab-pane fade" id="rules" role="tabpanel">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-shield-alt text-primary"></i>
                        Automated Pricing Rules
                    </h5>
                </div>
                <div class="card-body">
                    
                    <!-- Add New Rule Button -->
                    <button class="btn btn-primary mb-3" data-toggle="modal" data-target="#addRuleModal">
                        <i class="fas fa-plus"></i> Add New Rule
                    </button>

                    <!-- Rules List -->
                    <div class="accordion" id="rulesAccordion">
                        
                        <!-- Rule 1: Margin Floor -->
                        <div class="card">
                            <div class="card-header" id="ruleOne">
                                <h6 class="mb-0">
                                    <button class="btn btn-link" data-toggle="collapse" data-target="#collapseOne">
                                        <i class="fas fa-chevron-down"></i>
                                        <strong>Margin Floor Rule</strong>
                                        <span class="badge badge-success ml-2">Active</span>
                                    </button>
                                </h6>
                            </div>
                            <div id="collapseOne" class="collapse show" data-parent="#rulesAccordion">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <p><strong>Description:</strong> Ensures all pricing proposals maintain minimum 25% margin</p>
                                            <p><strong>Applies to:</strong> All products</p>
                                            <p><strong>Priority:</strong> Critical</p>
                                            <p><strong>Last triggered:</strong> 2 hours ago</p>
                                        </div>
                                        <div class="col-md-4 text-right">
                                            <button class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-danger">
                                                <i class="fas fa-toggle-on"></i> Disable
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Rule 2: Competitive Matching -->
                        <div class="card">
                            <div class="card-header" id="ruleTwo">
                                <h6 class="mb-0">
                                    <button class="btn btn-link collapsed" data-toggle="collapse" data-target="#collapseTwo">
                                        <i class="fas fa-chevron-down"></i>
                                        <strong>Competitive Price Matching</strong>
                                        <span class="badge badge-success ml-2">Active</span>
                                    </button>
                                </h6>
                            </div>
                            <div id="collapseTwo" class="collapse" data-parent="#rulesAccordion">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <p><strong>Description:</strong> Match lowest competitor price when within 5% of our price</p>
                                            <p><strong>Applies to:</strong> Category: Devices</p>
                                            <p><strong>Priority:</strong> High</p>
                                            <p><strong>Last triggered:</strong> 1 day ago</p>
                                        </div>
                                        <div class="col-md-4 text-right">
                                            <button class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-danger">
                                                <i class="fas fa-toggle-on"></i> Disable
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Rule 3: Price War Protection -->
                        <div class="card">
                            <div class="card-header" id="ruleThree">
                                <h6 class="mb-0">
                                    <button class="btn btn-link collapsed" data-toggle="collapse" data-target="#collapseThree">
                                        <i class="fas fa-chevron-down"></i>
                                        <strong>Price War Protection</strong>
                                        <span class="badge badge-warning ml-2">Monitoring</span>
                                    </button>
                                </h6>
                            </div>
                            <div id="collapseThree" class="collapse" data-parent="#rulesAccordion">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <p><strong>Description:</strong> Prevent price drops greater than 15% within 7 days</p>
                                            <p><strong>Applies to:</strong> All products</p>
                                            <p><strong>Priority:</strong> Critical</p>
                                            <p><strong>Last triggered:</strong> Never</p>
                                        </div>
                                        <div class="col-md-4 text-right">
                                            <button class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-success">
                                                <i class="fas fa-toggle-off"></i> Enable
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- Competitors Tab -->
        <div class="tab-pane fade" id="competitors" role="tabpanel">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-store text-primary"></i>
                        Competitor Monitoring
                    </h5>
                </div>
                <div class="card-body">
                    <button class="btn btn-primary mb-3" data-toggle="modal" data-target="#addCompetitorModal">
                        <i class="fas fa-plus"></i> Add Competitor
                    </button>

                    <div class="row">
                        <!-- Competitor Card 1 -->
                        <div class="col-md-4 mb-3">
                            <div class="card border-primary">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-store text-primary"></i> VapeStore NZ
                                    </h5>
                                    <p class="card-text">
                                        <strong>URL:</strong> vapestore.co.nz<br>
                                        <strong>Products Tracked:</strong> 245<br>
                                        <strong>Last Scan:</strong> 2 hours ago<br>
                                        <strong>Status:</strong> <span class="badge badge-success">Active</span>
                                    </p>
                                    <button class="btn btn-sm btn-primary">
                                        <i class="fas fa-sync"></i> Scan Now
                                    </button>
                                    <button class="btn btn-sm btn-warning">
                                        <i class="fas fa-cog"></i> Configure
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Competitor Card 2 -->
                        <div class="col-md-4 mb-3">
                            <div class="card border-success">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-store text-success"></i> VapeNZ
                                    </h5>
                                    <p class="card-text">
                                        <strong>URL:</strong> vapenz.co.nz<br>
                                        <strong>Products Tracked:</strong> 180<br>
                                        <strong>Last Scan:</strong> 30 minutes ago<br>
                                        <strong>Status:</strong> <span class="badge badge-success">Active</span>
                                    </p>
                                    <button class="btn btn-sm btn-primary">
                                        <i class="fas fa-sync"></i> Scan Now
                                    </button>
                                    <button class="btn btn-sm btn-warning">
                                        <i class="fas fa-cog"></i> Configure
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Competitor Card 3 -->
                        <div class="col-md-4 mb-3">
                            <div class="card border-warning">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-store text-warning"></i> Cosmic Vapes
                                    </h5>
                                    <p class="card-text">
                                        <strong>URL:</strong> cosmicvapes.co.nz<br>
                                        <strong>Products Tracked:</strong> 120<br>
                                        <strong>Last Scan:</strong> 5 hours ago<br>
                                        <strong>Status:</strong> <span class="badge badge-warning">Slow</span>
                                    </p>
                                    <button class="btn btn-sm btn-primary">
                                        <i class="fas fa-sync"></i> Scan Now
                                    </button>
                                    <button class="btn btn-sm btn-warning">
                                        <i class="fas fa-cog"></i> Configure
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

<?php
$additionalJS = <<<'JAVASCRIPT'
<script>
// Pricing Module JavaScript
(function($) {
    'use strict';

    const PricingModule = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#scanCompetitors').on('click', this.scanCompetitors.bind(this));
            $('#generateProposals').on('click', this.generateProposals.bind(this));
            $('#refreshPricing').on('click', this.refreshAll.bind(this));
            $('.view-details').on('click', this.viewDetails.bind(this));
            $('.propose-price').on('click', this.proposePrice.bind(this));
            $('.approve-proposal').on('click', this.approveProposal.bind(this));
            $('.reject-proposal').on('click', this.rejectProposal.bind(this));
            $('#approveSelected').on('click', this.approveSelected.bind(this));
            $('#selectAllProposals').on('change', this.toggleSelectAll.bind(this));
        },

        scanCompetitors: function(e) {
            e.preventDefault();
            if (confirm('Start competitor price scan? This may take several minutes.')) {
                alert('Competitor scan initiated. You will be notified when complete.');
            }
        },

        generateProposals: function(e) {
            e.preventDefault();
            alert('Generating pricing proposals based on current market data...');
        },

        viewDetails: function(e) {
            const sku = $(e.currentTarget).data('sku');
            alert(`Viewing detailed pricing history for ${sku}`);
        },

        proposePrice: function(e) {
            const sku = $(e.currentTarget).data('sku');
            alert(`Generating competitive price proposal for ${sku}`);
        },

        approveProposal: function(e) {
            const id = $(e.currentTarget).data('id');
            if (confirm(`Approve price proposal #${id}?`)) {
                alert(`Proposal #${id} approved and queued for execution.`);
            }
        },

        rejectProposal: function(e) {
            const id = $(e.currentTarget).data('id');
            if (confirm(`Reject price proposal #${id}?`)) {
                alert(`Proposal #${id} rejected.`);
            }
        },

        approveSelected: function(e) {
            e.preventDefault();
            const selected = $('.proposal-checkbox:checked').length;
            if (selected === 0) {
                alert('No proposals selected.');
                return;
            }
            if (confirm(`Approve ${selected} selected proposal(s)?`)) {
                alert(`${selected} proposals approved.`);
            }
        },

        toggleSelectAll: function(e) {
            $('.proposal-checkbox').prop('checked', $(e.currentTarget).is(':checked'));
        },

        refreshAll: function() {
            location.reload();
        }
    };

    $(document).ready(function() {
        PricingModule.init();
    });

})(jQuery);
</script>
JAVASCRIPT;

include __DIR__ . '/../../templates/footer.php';
?>
