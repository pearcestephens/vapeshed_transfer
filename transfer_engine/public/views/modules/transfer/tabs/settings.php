<div class="card">
  <div class="card-header"><i class="fas fa-cog"></i> Transfer Settings / Guardrails Snapshot</div>
  <div class="card-body">
    <p class="text-muted">Read-only snapshot of unified config (subset). Future: editable through Config Admin (Phase M16). Values below pulled from unified namespace if available.</p>
    <div class="row">
      <?php
        $configKeys = [
          'neuro.unified.balancer.daily_line_cap' => 'Daily Line Cap',
          'neuro.unified.policy.auto_apply_min' => 'Auto Apply Score Min',
          'neuro.unified.policy.propose_min' => 'Propose Score Min',
          'neuro.unified.pricing.delta_cap_pct' => 'Pricing Delta Cap %',
          'neuro.unified.matching.min_confidence' => 'Match Min Confidence',
          'neuro.unified.drift.psi_warn' => 'PSI Warn Threshold',
          'neuro.unified.drift.psi_critical' => 'PSI Critical Threshold'
        ];
      ?>
      <div class="col-md-8">
        <table class="table table-sm table-striped">
          <thead class="thead-light">
            <tr><th style="width:45%">Key</th><th>Value</th></tr>
          </thead>
          <tbody>
            <?php foreach ($configKeys as $k=>$label): ?>
              <tr>
                <td><?php echo e($label); ?><br><small class="text-muted"><?php echo e($k); ?></small></td>
                <td><code><?php echo e((string)(config($k) ?? '—')); ?></code></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="col-md-4">
        <div class="alert alert-info mb-3">
          <strong>Phase Alignment:</strong><br>
          This settings panel is informational only until M16 (Config Admin & Lint). All edits must occur via migration scripts or direct config_items updates.
        </div>
        <div class="alert alert-warning mb-0">
          <strong>Governance:</strong><br>
          Any future writable UI must enforce audit logging & CSRF + require elevated permission (config.edit).
        </div>
      </div>
    </div>
  </div>
</div>
