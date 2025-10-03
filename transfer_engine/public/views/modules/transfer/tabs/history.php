<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-history"></i> Transfer History (Recent)</span>
    <button class="btn btn-sm btn-outline-secondary" id="refreshHistory"><i class="fas fa-sync"></i></button>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0" id="transferHistoryTable">
        <thead class="thead-light">
          <tr>
            <th>ID</th>
            <th>SKU</th>
            <th>From</th>
            <th>To</th>
            <th>Qty</th>
            <th>Status</th>
            <th>Band</th>
            <th>Created</th>
          </tr>
        </thead>
        <tbody>
          <tr><td colspan="8" class="text-center py-4 text-muted">History view placeholder. Will utilize a future read model joining guardrail_traces and proposal_log for enriched timeline.</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
