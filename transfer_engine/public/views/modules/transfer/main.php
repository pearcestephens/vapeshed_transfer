<!-- Stats Row -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-details">
                <div class="stat-label">Pending Transfers</div>
                <div class="stat-value"><?php echo formatNumber($stats['pending']); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-details">
                <div class="stat-label">Executed Today</div>
                <div class="stat-value"><?php echo formatNumber($stats['today']); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-details">
                <div class="stat-label">Failed Transfers</div>
                <div class="stat-value"><?php echo formatNumber($stats['failed']); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                <i class="fas fa-list"></i>
            </div>
            <div class="stat-details">
                <div class="stat-label">Total (7d)</div>
                <div class="stat-value"><?php echo formatNumber($stats['total']); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Module Tabs -->
<ul class="nav nav-tabs mb-4" id="transferTabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" data-toggle="tab" href="#calculator" role="tab">
            <i class="fas fa-calculator"></i> DSR Calculator
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-toggle="tab" href="#queue" role="tab">
            <i class="fas fa-list"></i> Transfer Queue
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-toggle="tab" href="#history" role="tab">
            <i class="fas fa-history"></i> History
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-toggle="tab" href="#settings" role="tab">
            <i class="fas fa-cog"></i> Settings
        </a>
    </li>
</ul>

<!-- Tab Content -->
<div class="tab-content">
    
    <!-- DSR Calculator Tab -->
    <div class="tab-pane fade show active" id="calculator" role="tabpanel">
        <?php include __DIR__ . '/tabs/calculator.php'; ?>
    </div>
    
    <!-- Queue Tab -->
    <div class="tab-pane fade" id="queue" role="tabpanel">
        <?php include __DIR__ . '/tabs/queue.php'; ?>
    </div>
    
    <!-- History Tab -->
    <div class="tab-pane fade" id="history" role="tabpanel">
        <?php include __DIR__ . '/tabs/history.php'; ?>
    </div>
    
    <!-- Settings Tab -->
    <div class="tab-pane fade" id="settings" role="tabpanel">
        <?php include __DIR__ . '/tabs/settings.php'; ?>
    </div>
    
</div>
