<?php
/**
 * Pricing Settings Tab
 * Configure pricing engine settings
 */
?>

<div class="pricing-settings">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5><i class="fas fa-sliders-h me-2"></i>Pricing Settings</h5>
        <div class="btn-group btn-group-sm">
            <button class="btn btn-success" onclick="pricing.saveSettings()">
                <i class="fas fa-save"></i> Save Changes
            </button>
            <button class="btn btn-outline-secondary" onclick="pricing.resetSettings()">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-cogs me-2"></i>Engine Configuration</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Auto-Apply Mode</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="autoApply" value="disabled" id="autoDisabled">
                            <label class="form-check-label" for="autoDisabled">
                                Disabled - All proposals require manual review
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="autoApply" value="low" id="autoLow" checked>
                            <label class="form-check-label" for="autoLow">
                                Low Impact Only - Auto-apply low-risk changes
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="autoApply" value="all" id="autoAll">
                            <label class="form-check-label" for="autoAll">
                                All Bands - Auto-apply all pricing proposals
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" for="scanFrequency">Scan Frequency</label>
                        <select class="form-select" id="scanFrequency">
                            <option value="hourly">Hourly</option>
                            <option value="daily" selected>Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="manual">Manual Only</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" for="maxProposals">Max Proposals Per Run</label>
                        <input type="number" class="form-control" id="maxProposals" value="100" min="10" max="1000">
                        <div class="form-text">Limit the number of proposals generated in each scan</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Guardrails</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="maxPriceIncrease">Max Price Increase (%)</label>
                        <input type="number" class="form-control" id="maxPriceIncrease" value="25" min="1" max="100" step="0.1">
                        <div class="form-text">Maximum allowed price increase in a single change</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" for="maxPriceDecrease">Max Price Decrease (%)</label>
                        <input type="number" class="form-control" id="maxPriceDecrease" value="40" min="1" max="100" step="0.1">
                        <div class="form-text">Maximum allowed price decrease in a single change</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" for="minMargin">Minimum Margin (%)</label>
                        <input type="number" class="form-control" id="minMargin" value="15" min="0" max="100" step="0.1">
                        <div class="form-text">Never price below this margin threshold</div>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="competitorCheck" checked>
                        <label class="form-check-label" for="competitorCheck">
                            Require competitor price verification
                        </label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="inventoryCheck" checked>
                        <label class="form-check-label" for="inventoryCheck">
                            Check inventory levels before pricing
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Impact Bands</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Low Impact Threshold (%)</label>
                        <input type="number" class="form-control" value="5" min="1" max="50" step="0.1">
                        <div class="form-text">Price changes below this threshold are considered low impact</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">High Impact Threshold (%)</label>
                        <input type="number" class="form-control" value="15" min="5" max="100" step="0.1">
                        <div class="form-text">Price changes above this threshold are considered high impact</div>
                    </div>
                    
                    <div class="alert alert-info small">
                        <i class="fas fa-info-circle me-1"></i>
                        Medium impact is automatically calculated as between low and high thresholds.
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-bell me-2"></i>Notifications</h6>
                </div>
                <div class="card-body">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="emailNotifications" checked>
                        <label class="form-check-label" for="emailNotifications">
                            Email notifications for high-impact changes
                        </label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="dailyReport" checked>
                        <label class="form-check-label" for="dailyReport">
                            Daily pricing activity report
                        </label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="errorAlerts">
                        <label class="form-check-label" for="errorAlerts">
                            Alert on pricing engine errors
                        </label>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" for="notificationEmail">Notification Email</label>
                        <input type="email" class="form-control" id="notificationEmail" 
                               value="pricing@vapeshed.co.nz" placeholder="Enter email address">
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Important:</strong> Changes to pricing settings will affect future proposals. 
        Existing proposals will not be modified.
    </div>
</div>