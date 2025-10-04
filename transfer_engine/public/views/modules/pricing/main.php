<!-- Pricing Module Main View -->
<link rel="stylesheet" href="<?php echo asset('css/modules/pricing.css'); ?>">
<div class="row mb-4">
  <div class="col-md-2">
    <?= statCard('Total (7d)', formatNumber($stats['total']), 'layer-group', '#ec4899', 'total'); ?>
  </div>
  <div class="col-md-2">
    <?= statCard('Proposed', formatNumber($stats['propose']), 'lightbulb', '#6366f1', 'propose'); ?>
  </div>
  <div class="col-md-2">
    <?= statCard('Auto', formatNumber($stats['auto']), 'bolt', '#10b981', 'auto'); ?>
  </div>
  <div class="col-md-2">
    <?= statCard('Blocked', formatNumber($stats['blocked']), 'ban', '#f59e0b', 'blocked'); ?>
  </div>
  <div class="col-md-2">
    <?= statCard('Discarded', formatNumber($stats['discard']), 'times-circle', '#ef4444', 'discard'); ?>
  </div>
  <div class="col-md-2">
    <?= statCard('Today', formatNumber($stats['today']), 'calendar-day', '#3b82f6', 'today'); ?>
  </div>
  
</div>

<!-- Module JS -->
<script src="<?php echo asset('js/modules/pricing.js'); ?>"></script>

<ul class="nav nav-tabs mb-4" role="tablist">
  <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#pricing-overview" role="tab"><i class="fas fa-chart-line"></i> Overview</a></li>
  <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#pricing-candidates" role="tab"><i class="fas fa-list"></i> Candidates</a></li>
  <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#pricing-history" role="tab"><i class="fas fa-history"></i> History</a></li>
  <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#pricing-settings" role="tab"><i class="fas fa-cog"></i> Settings</a></li>
</ul>
<div class="tab-content">
  <div class="tab-pane fade show active" id="pricing-overview" role="tabpanel">
    <?php /* Overview content pending charts integration */ ?>
    <div class="card"><div class="card-body"><p class="text-muted mb-0">Overview dashboard – charts coming soon.</p></div></div>
  </div>
  <div class="tab-pane fade" id="pricing-candidates" role="tabpanel">
    <?php include __DIR__ . '/tabs/candidates.php'; ?>
  </div>
  <div class="tab-pane fade" id="pricing-history" role="tabpanel">
    <?php include __DIR__ . '/tabs/history.php'; ?>
  </div>
  <div class="tab-pane fade" id="pricing-settings" role="tabpanel">
    <?php include __DIR__ . '/tabs/settings.php'; ?>
  </div>
</div>
