<footer class="footer bg-dark text-light py-4 mt-5">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3">
                <h5><?php echo config('app.name'); ?></h5>
                <p class="text-muted small">
                    Intelligent stock transfer and pricing system for The Vape Shed retail chain.
                </p>
                <p class="small mb-0">
                    <strong>Version:</strong> <?php echo config('app.version'); ?><br>
                    <strong>Environment:</strong> <?php echo config('app.debug') ? 'Development' : 'Production'; ?>
                </p>
            </div>
            
            <div class="col-md-3">
                <h6>Quick Links</h6>
                <ul class="list-unstyled small">
                    <li><a href="<?php echo url('/dashboard'); ?>" class="text-muted">Dashboard</a></li>
                    <li><a href="<?php echo url('/modules/transfer'); ?>" class="text-muted">Transfer Engine</a></li>
                    <li><a href="<?php echo url('/modules/pricing'); ?>" class="text-muted">Pricing Intelligence</a></li>
                    <li><a href="<?php echo url('/modules/config'); ?>" class="text-muted">Configuration</a></li>
                    <li><a href="<?php echo url('/modules/health'); ?>" class="text-muted">System Health</a></li>
                </ul>
            </div>
            
            <div class="col-md-3">
                <h6>Resources</h6>
                <ul class="list-unstyled small">
                    <li><a href="<?php echo url('/docs'); ?>" class="text-muted">Documentation</a></li>
                    <li><a href="<?php echo url('/docs/api'); ?>" class="text-muted">API Reference</a></li>
                    <li><a href="<?php echo url('/support'); ?>" class="text-muted">Support</a></li>
                    <li><a href="<?php echo url('/changelog'); ?>" class="text-muted">Changelog</a></li>
                </ul>
            </div>
            
            <div class="col-md-3">
                <h6>System Status</h6>
                <div class="small">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Database:</span>
                        <span class="badge badge-success">Online</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Engine:</span>
                        <span class="badge badge-success">Active</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Last Updated:</span>
                        <span class="text-muted"><?php echo date('H:i'); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <hr class="bg-secondary my-3">
        
        <div class="row">
            <div class="col-md-6 small text-muted">
                &copy; <?php echo date('Y'); ?> Ecigdis Limited / The Vape Shed. All rights reserved.
            </div>
            <div class="col-md-6 text-md-right small">
                <a href="<?php echo url('/privacy'); ?>" class="text-muted">Privacy Policy</a>
                <span class="mx-2">|</span>
                <a href="<?php echo url('/terms'); ?>" class="text-muted">Terms of Service</a>
            </div>
        </div>
    </div>
</footer>
