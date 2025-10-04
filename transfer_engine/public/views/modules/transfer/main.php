<!-- Module CSS -->
<link rel="stylesheet" href="<?php echo asset('css/modules/transfer.css'); ?>">

<!-- Stats Row -->
<div class="row mb-4">
    <div class="col-md-3">
        <?= statCard('Pending Transfers', formatNumber($stats['pending']), 'clock', '#8b5cf6', 'pending'); ?>
    </div>
    <div class="col-md-3">
        <?= statCard('Executed Today', formatNumber($stats['today']), 'check-circle', '#10b981', 'today'); ?>
    </div>
    <div class="col-md-3">
        <?= statCard('Failed Transfers', formatNumber($stats['failed']), 'exclamation-triangle', '#f59e0b', 'failed'); ?>
    </div>
    <div class="col-md-3">
        <?= statCard('Total (7d)', formatNumber($stats['total']), 'list', '#3b82f6', 'total'); ?>
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

<!-- Module JS -->
<script src="<?php echo asset('js/modules/transfer.js'); ?>"></script>
