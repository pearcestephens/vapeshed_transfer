<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-balance-scale"></i> DSR Calculator</span>
    <button class="btn btn-sm btn-outline-secondary" id="recalcDSR"><i class="fas fa-sync"></i> Recalculate</button>
  </div>
  <div class="card-body">
    <p class="text-muted mb-2">Baseline placeholder. This tab will integrate unified Transfer DSR calculations (M14 parity) using orchestrator outputs rather than ad-hoc SQL. Future: allow SKU input & scenario simulation.</p>
    <form id="dsrCalcForm" class="form-inline mb-3">
      <div class="form-group mr-2">
        <label for="sku" class="sr-only">SKU</label>
        <input type="text" class="form-control" id="sku" placeholder="SKU">
      </div>
      <div class="form-group mr-2">
        <label for="stock" class="sr-only">Stock</label>
        <input type="number" class="form-control" id="stock" placeholder="Stock On Hand">
      </div>
      <div class="form-group mr-2">
        <label for="avgDemand" class="sr-only">Avg Demand</label>
        <input type="number" class="form-control" id="avgDemand" placeholder="Avg Daily Demand">
      </div>
      <button type="submit" class="btn btn-primary"><i class="fas fa-calculator"></i> Compute</button>
    </form>
    <div id="dsrResult" class="alert alert-info d-none"></div>
  </div>
</div>
