<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-stream"></i> Transfer Queue (Proposed)</span>
    <div>
      <button class="btn btn-sm btn-outline-primary" id="refreshQueue"><i class="fas fa-sync"></i></button>
      <button class="btn btn-sm btn-success" id="applySelected" disabled><i class="fas fa-check"></i> Apply Selected</button>
    </div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-striped mb-0" id="transferQueueTable">
        <thead class="thead-light">
          <tr>
            <th style="width:32px"><input type="checkbox" id="selectAllQueue"></th>
            <th>ID</th>
            <th>SKU</th>
            <th>From → To</th>
            <th>Qty</th>
            <th>Score</th>
            <th>Band</th>
            <th>Created</th>
          </tr>
        </thead>
        <tbody>
          <tr><td colspan="8" class="text-center py-4 text-muted">Queue integration pending repository wiring (will read from proposal_log where proposal_type='transfer' AND band IN ('propose','auto')).</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
