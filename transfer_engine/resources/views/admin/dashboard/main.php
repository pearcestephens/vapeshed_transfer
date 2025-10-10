<!DOCTYPE html>
<html lang="en" data-theme="<?= $user_preferences['theme']['mode'] ?? 'auto' ?>" data-accent="<?= $user_preferences['theme']['accent'] ?? 'teal' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    
    <!-- Enhanced CSS Framework with Power User Controls -->
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/dashboard-power.css" rel="stylesheet">
    <link href="/assets/icons/feather/feather.css" rel="stylesheet">
    
    <!-- Theme Variables -->
    <style>
        :root {
            --font-scale: <?= $user_preferences['theme']['font_scale'] ?? 100 ?>%;
            --grid-columns: <?= $user_preferences['layout']['columns'] ?? 3 ?>;
            --card-density: <?= $user_preferences['layout']['density'] ?? 'standard' ?>;
            --refresh-interval: <?= $user_preferences['time']['refresh'] ?? 30 ?>s;
            --accent-color: var(--color-<?= $user_preferences['theme']['accent'] ?? 'teal' ?>);
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(var(--grid-columns), 1fr);
            gap: var(--spacing-<?= $user_preferences['layout']['density'] ?? 'standard' ?>);
        }
        
        .font-scale {
            font-size: calc(1rem * var(--font-scale) / 100);
        }
    </style>
</head>
<body class="dashboard-power font-scale" data-profile="<?= $profile ?>" data-live-mode="<?= $user_preferences['live_mode'] ?? false ? 'true' : 'false' ?>">

<!-- Dashboard Header with Power Controls -->
<header class="dashboard-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <!-- Left: Profile & Status -->
            <div class="header-left d-flex align-items-center">
                <div class="profile-selector">
                    <select id="profileSelect" class="form-select form-select-sm">
                        <?php foreach ($profiles as $key => $profileData): ?>
                            <option value="<?= $key ?>" <?= $key === $profile ? 'selected' : '' ?>>
                                <?= htmlspecialchars($profileData['name']) ?>
                                <span class="badge"><?= $profileData['settings_count'] ?></span>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="system-status ms-3">
                    <span class="status-indicator status-healthy" title="System Status: Healthy">
                        <i data-feather="check-circle"></i>
                    </span>
                    <span class="status-text">All Systems Operational</span>
                </div>
                
                <?php if ($user_preferences['live_mode'] ?? false): ?>
                <div class="live-indicator ms-3">
                    <span class="badge bg-success pulse">
                        <i data-feather="radio"></i> LIVE
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Center: Global Time Controls -->
            <div class="header-center">
                <div class="time-controls d-flex align-items-center">
                    <select id="timeWindow" class="form-select form-select-sm me-2">
                        <?php foreach ($global_controls['time_windows'] as $value => $label): ?>
                            <option value="<?= $value ?>" <?= ($user_preferences['time']['window'] ?? 'last_24h') === $value ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <div class="refresh-controls">
                        <button id="refreshAll" class="btn btn-outline-secondary btn-sm" title="Refresh All Modules">
                            <i data-feather="refresh-cw"></i>
                        </button>
                        
                        <div class="dropdown d-inline-block ms-1">
                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                <i data-feather="clock"></i>
                                <?= $user_preferences['time']['refresh'] ?? 30 ?>s
                            </button>
                            <ul class="dropdown-menu">
                                <?php foreach ($global_controls['refresh_intervals'] as $interval): ?>
                                    <li><a class="dropdown-item refresh-interval" data-interval="<?= $interval ?>" href="#"><?= $interval ?>s</a></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Quick Actions & Settings -->
            <div class="header-right d-flex align-items-center">
                <!-- Emergency Controls -->
                <div class="emergency-controls me-3">
                    <div class="btn-group" role="group">
                        <?php foreach ($quick_actions['emergency'] as $action): ?>
                            <button type="button" class="btn <?= $action['class'] ?> btn-sm emergency-action" 
                                    data-action="<?= $action['id'] ?>" 
                                    title="<?= htmlspecialchars($action['label']) ?>">
                                <i data-feather="<?= $action['icon'] ?>"></i>
                                <span class="d-none d-lg-inline"><?= htmlspecialchars($action['label']) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- System Actions -->
                <div class="system-actions me-3">
                    <div class="btn-group" role="group">
                        <?php foreach ($quick_actions['system'] as $action): ?>
                            <button type="button" class="btn <?= $action['class'] ?> btn-sm system-action" 
                                    data-action="<?= $action['id'] ?>" 
                                    title="<?= htmlspecialchars($action['label']) ?>">
                                <i data-feather="<?= $action['icon'] ?>"></i>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Settings Toggle -->
                <button id="settingsToggle" class="btn btn-outline-secondary btn-sm" title="Dashboard Settings">
                    <i data-feather="settings"></i>
                </button>
            </div>
        </div>
    </div>
</header>

<!-- Settings Panel (Collapsible) -->
<div id="settingsPanel" class="settings-panel collapsed">
    <div class="container-fluid">
        <div class="row">
            <!-- Global Settings -->
            <div class="col-md-3">
                <h6><i data-feather="globe"></i> Global Controls</h6>
                
                <!-- Theme Controls -->
                <div class="setting-group">
                    <label class="form-label">Theme</label>
                    <div class="btn-group d-flex" role="group">
                        <?php foreach ($global_controls['themes'] as $theme): ?>
                            <input type="radio" class="btn-check" name="theme" id="theme<?= ucfirst($theme) ?>" value="<?= $theme ?>" <?= ($user_preferences['theme']['mode'] ?? 'auto') === $theme ? 'checked' : '' ?>>
                            <label class="btn btn-outline-secondary btn-sm" for="theme<?= ucfirst($theme) ?>">
                                <i data-feather="<?= $theme === 'light' ? 'sun' : ($theme === 'dark' ? 'moon' : 'monitor') ?>"></i>
                                <?= ucfirst($theme) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Accent Color -->
                <div class="setting-group">
                    <label class="form-label">Accent Color</label>
                    <div class="accent-colors">
                        <?php foreach ($global_controls['accents'] as $accent): ?>
                            <input type="radio" class="accent-radio" name="accent" id="accent<?= ucfirst($accent) ?>" value="<?= $accent ?>" <?= ($user_preferences['theme']['accent'] ?? 'teal') === $accent ? 'checked' : '' ?>>
                            <label class="accent-color" for="accent<?= ucfirst($accent) ?>" style="background-color: var(--color-<?= $accent ?>)"></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Font Scale -->
                <div class="setting-group">
                    <label class="form-label">Font Scale: <span id="fontScaleValue"><?= $user_preferences['theme']['font_scale'] ?? 100 ?>%</span></label>
                    <input type="range" class="form-range" id="fontScale" min="80" max="130" step="5" value="<?= $user_preferences['theme']['font_scale'] ?? 100 ?>">
                </div>
            </div>

            <!-- Layout Settings -->
            <div class="col-md-3">
                <h6><i data-feather="layout"></i> Layout</h6>
                
                <!-- Grid Columns -->
                <div class="setting-group">
                    <label class="form-label">Grid Columns</label>
                    <div class="btn-group d-flex" role="group">
                        <?php foreach ($global_controls['layouts']['columns'] as $cols): ?>
                            <input type="radio" class="btn-check" name="columns" id="cols<?= $cols ?>" value="<?= $cols ?>" <?= ($user_preferences['layout']['columns'] ?? 3) == $cols ? 'checked' : '' ?>>
                            <label class="btn btn-outline-secondary btn-sm" for="cols<?= $cols ?>"><?= $cols ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Density -->
                <div class="setting-group">
                    <label class="form-label">Density</label>
                    <select id="densitySelect" class="form-select form-select-sm">
                        <?php foreach ($global_controls['layouts']['density'] as $density): ?>
                            <option value="<?= $density ?>" <?= ($user_preferences['layout']['density'] ?? 'standard') === $density ? 'selected' : '' ?>>
                                <?= ucfirst($density) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Snap to Grid -->
                <div class="setting-group">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="snapToGrid" <?= $user_preferences['layout']['snap_to_grid'] ?? true ? 'checked' : '' ?>>
                        <label class="form-check-label" for="snapToGrid">Snap to Grid</label>
                    </div>
                </div>
            </div>

            <!-- Privacy & Security -->
            <div class="col-md-3">
                <h6><i data-feather="shield"></i> Privacy & Security</h6>
                
                <?php foreach ($global_controls['privacy_options'] as $key => $label): ?>
                    <div class="form-check form-switch">
                        <input class="form-check-input privacy-option" type="checkbox" id="<?= $key ?>" <?= $user_preferences['privacy'][$key] ?? false ? 'checked' : '' ?>>
                        <label class="form-check-label" for="<?= $key ?>"><?= htmlspecialchars($label) ?></label>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Advanced Options -->
            <div class="col-md-3">
                <h6><i data-feather="sliders"></i> Advanced</h6>
                
                <!-- Live Mode -->
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="liveMode" <?= $user_preferences['live_mode'] ?? false ? 'checked' : '' ?>>
                    <label class="form-check-label" for="liveMode">Live Mode (5s refresh)</label>
                </div>

                <!-- War Room Mode -->
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="warRoomMode">
                    <label class="form-check-label" for="warRoomMode">War Room Mode</label>
                </div>

                <!-- Demo Mode -->
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="demoMode">
                    <label class="form-check-label" for="demoMode">Demo Mode</label>
                </div>

                <!-- Action Buttons -->
                <div class="mt-3">
                    <button id="resetLayout" class="btn btn-outline-warning btn-sm">
                        <i data-feather="rotate-ccw"></i> Reset Layout
                    </button>
                    <button id="exportConfig" class="btn btn-outline-info btn-sm">
                        <i data-feather="download"></i> Export Config
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Dashboard Content -->
<main class="dashboard-main">
    <div class="container-fluid">
        <!-- Module Grid -->
        <div class="dashboard-grid" id="dashboardGrid">
            <?php foreach ($modules as $moduleKey => $module): ?>
                <div class="dashboard-module" 
                     data-module="<?= $moduleKey ?>" 
                     data-category="<?= $module['category'] ?>"
                     id="module-<?= $moduleKey ?>">
                    
                    <!-- Module Header -->
                    <div class="module-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="module-title">
                                <i data-feather="<?= $module['icon'] ?>"></i>
                                <span><?= htmlspecialchars($module['name']) ?></span>
                                <span class="module-category badge bg-secondary ms-2"><?= $module['category'] ?></span>
                            </div>
                            
                            <div class="module-controls">
                                <!-- Module Settings -->
                                <div class="dropdown d-inline-block">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                        <i data-feather="more-horizontal"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item module-config" data-module="<?= $moduleKey ?>" href="#"><i data-feather="settings"></i> Configure</a></li>
                                        <li><a class="dropdown-item module-refresh" data-module="<?= $moduleKey ?>" href="#"><i data-feather="refresh-cw"></i> Refresh</a></li>
                                        <li><a class="dropdown-item module-fullscreen" data-module="<?= $moduleKey ?>" href="#"><i data-feather="maximize"></i> Fullscreen</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item module-hide" data-module="<?= $moduleKey ?>" href="#"><i data-feather="eye-off"></i> Hide</a></li>
                                    </ul>
                                </div>
                                
                                <!-- Status Indicator -->
                                <span class="module-status status-loading" title="Loading...">
                                    <i data-feather="loader"></i>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Module Content -->
                    <div class="module-content" id="content-<?= $moduleKey ?>">
                        <div class="loading-placeholder">
                            <div class="spinner-border spinner-border-sm" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <span class="ms-2">Loading <?= $module['name'] ?>...</span>
                        </div>
                    </div>

                    <!-- Module Settings Panel (Hidden by default) -->
                    <div class="module-settings-panel collapsed" id="settings-<?= $moduleKey ?>">
                        <div class="settings-header">
                            <h6><?= $module['name'] ?> Settings</h6>
                            <button class="btn btn-sm btn-outline-secondary close-settings">
                                <i data-feather="x"></i>
                            </button>
                        </div>
                        
                        <div class="settings-content">
                            <!-- Dynamic settings based on profile and module -->
                            <?php 
                            $profileLevel = $profile === 'minimal' ? 'minimal' : ($profile === 'power' ? 'power' : 'standard');
                            $settings = $module['settings'][$profileLevel] ?? [];
                            ?>
                            
                            <?php foreach ($settings as $setting): ?>
                                <div class="setting-item">
                                    <label class="form-label"><?= ucfirst(str_replace('_', ' ', $setting)) ?></label>
                                    
                                    <?php if (str_contains($setting, 'threshold') || str_contains($setting, 'slider')): ?>
                                        <input type="range" class="form-range module-setting" 
                                               data-setting="<?= $setting ?>" 
                                               min="0" max="100" step="1" value="50">
                                    <?php elseif (str_contains($setting, 'toggle') || str_contains($setting, 'enabled')): ?>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input module-setting" 
                                                   type="checkbox" 
                                                   data-setting="<?= $setting ?>" 
                                                   checked>
                                        </div>
                                    <?php else: ?>
                                        <select class="form-select form-select-sm module-setting" data-setting="<?= $setting ?>">
                                            <option value="default">Default</option>
                                            <option value="custom">Custom</option>
                                        </select>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="settings-actions mt-3">
                                <button class="btn btn-primary btn-sm save-module-settings" data-module="<?= $moduleKey ?>">
                                    <i data-feather="save"></i> Save
                                </button>
                                <button class="btn btn-outline-secondary btn-sm reset-module-settings" data-module="<?= $moduleKey ?>">
                                    <i data-feather="rotate-ccw"></i> Reset
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Add Module Button -->
        <div class="add-module-container">
            <button class="btn btn-outline-primary btn-lg add-module-btn" data-bs-toggle="modal" data-bs-target="#addModuleModal">
                <i data-feather="plus"></i>
                Add Module
            </button>
        </div>
    </div>
</main>

<!-- Footer Status Bar -->
<footer class="dashboard-footer">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-4">
                <div class="status-summary">
                    <span class="status-item">
                        <i data-feather="activity"></i>
                        <span id="systemLoad">CPU: 23%</span>
                    </span>
                    <span class="status-item ms-3">
                        <i data-feather="database"></i>
                        <span id="dbStatus">DB: 45ms</span>
                    </span>
                    <span class="status-item ms-3">
                        <i data-feather="wifi"></i>
                        <span id="apiStatus">API: Online</span>
                    </span>
                </div>
            </div>
            
            <div class="col-md-4 text-center">
                <div class="last-updated">
                    Last updated: <span id="lastUpdated"><?= date('H:i:s') ?></span>
                    <span class="auto-refresh-indicator ms-2" title="Auto-refresh enabled">
                        <i data-feather="refresh-cw" class="rotate"></i>
                    </span>
                </div>
            </div>
            
            <div class="col-md-4 text-end">
                <div class="footer-actions">
                    <button class="btn btn-sm btn-outline-secondary" title="Full Screen">
                        <i data-feather="maximize"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-secondary ms-1" title="Help">
                        <i data-feather="help-circle"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-secondary ms-1" title="Feedback">
                        <i data-feather="message-circle"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Modals -->

<!-- Add Module Modal -->
<div class="modal fade" id="addModuleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i data-feather="plus"></i>
                    Add Dashboard Module
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <?php 
                    $allModules = ['neuro_strategic', 'pipeline_throughput', 'high_confidence', 'opportunities', 'automation_success', 'gap_identification', 'agent_telemetry', 'system_health', 'queue_jobs', 'supplier_intelligence', 'competitive_analysis', 'financial_impact'];
                    foreach ($allModules as $moduleKey):
                        if (!isset($modules[$moduleKey])):
                            $moduleConfig = $this->moduleConfigs[$moduleKey] ?? [];
                    ?>
                        <div class="col-md-6 mb-3">
                            <div class="card module-option" data-module="<?= $moduleKey ?>">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i data-feather="<?= $moduleConfig['icon'] ?? 'box' ?>"></i>
                                        <?= $moduleConfig['name'] ?? ucfirst(str_replace('_', ' ', $moduleKey)) ?>
                                    </h6>
                                    <p class="card-text text-muted small">
                                        <?= $moduleConfig['category'] ?? 'General' ?> Module
                                    </p>
                                    <button class="btn btn-outline-primary btn-sm add-module" data-module="<?= $moduleKey ?>">
                                        <i data-feather="plus"></i> Add
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Emergency Confirmation Modal -->
<div class="modal fade" id="emergencyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i data-feather="alert-triangle"></i>
                    Emergency Action Confirmation
                </h5>
            </div>
            <div class="modal-body">
                <p>You are about to execute an emergency action:</p>
                <div class="alert alert-warning">
                    <strong id="emergencyActionName">Action Name</strong>
                    <p id="emergencyActionDescription" class="mb-0">Action description will appear here.</p>
                </div>
                <p>This action may affect system operations. Are you sure you want to proceed?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmEmergencyAction">
                    <i data-feather="alert-triangle"></i>
                    Execute Action
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="/assets/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/feather.min.js"></script>
<script src="/assets/js/dashboard-power.js"></script>

<script>
// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Feather icons
    feather.replace();
    
    // Initialize dashboard power controls
    DashboardPower.init({
        profile: '<?= $profile ?>',
        modules: <?= json_encode(array_keys($modules)) ?>,
        preferences: <?= json_encode($user_preferences) ?>,
        endpoints: {
            ajax: '/admin/dashboard/ajax',
            moduleData: '/admin/dashboard/module-data',
            preferences: '/admin/dashboard/preferences'
        }
    });
    
    // Auto-refresh setup
    <?php if ($user_preferences['time']['auto_refresh'] ?? true): ?>
    DashboardPower.startAutoRefresh(<?= $user_preferences['time']['refresh'] ?? 30 ?>);
    <?php endif; ?>
    
    // Live mode setup
    <?php if ($user_preferences['live_mode'] ?? false): ?>
    DashboardPower.enableLiveMode();
    <?php endif; ?>
});
</script>

</body>
</html>