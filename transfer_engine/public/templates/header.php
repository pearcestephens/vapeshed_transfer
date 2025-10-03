<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo $pageTitle ?? 'Unified Intelligence Platform'; ?> | The Vape Shed</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/img/favicon.ico">
    
    <!-- Bootstrap 4.6 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom Dashboard CSS -->
    <link rel="stylesheet" href="/assets/css/dashboard.css?v=<?php echo time(); ?>">
    
    <!-- Module-specific CSS -->
    <?php if (isset($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <link rel="stylesheet" href="<?php echo $css; ?>?v=<?php echo time(); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <style>
        /* Quick inline overrides if needed */
        body {
            background: #f9fafb;
        }
    </style>
</head>
<body>

<!-- Top Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%); box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
    <div class="container-fluid" style="max-width: 1400px;">
        
        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center" href="/dashboard/">
            <i class="fas fa-brain" style="font-size: 24px; margin-right: 12px;"></i>
            <div>
                <div style="font-size: 18px; font-weight: 700; line-height: 1;">
                    Unified Intelligence Platform
                </div>
                <div style="font-size: 11px; opacity: 0.8; text-transform: uppercase; letter-spacing: 1px;">
                    The Vape Shed
                </div>
            </div>
        </a>
        
        <!-- Mobile Toggle -->
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Navigation Links -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                
                <!-- Dashboard -->
                <li class="nav-item <?php echo ($currentModule ?? '') === 'dashboard' ? 'active' : ''; ?>">
                    <a class="nav-link" href="/dashboard/">
                        <i class="fas fa-th-large"></i> Dashboard
                    </a>
                </li>
                
                <!-- Transfer -->
                <li class="nav-item <?php echo ($currentModule ?? '') === 'transfer' ? 'active' : ''; ?>">
                    <a class="nav-link" href="/dashboard/transfer/">
                        <i class="fas fa-truck-loading"></i> Transfer
                    </a>
                </li>
                
                <!-- Pricing -->
                <li class="nav-item <?php echo ($currentModule ?? '') === 'pricing' ? 'active' : ''; ?>">
                    <a class="nav-link" href="/dashboard/pricing/">
                        <i class="fas fa-dollar-sign"></i> Pricing
                    </a>
                </li>
                
                <!-- Insights -->
                <li class="nav-item <?php echo ($currentModule ?? '') === 'insights' ? 'active' : ''; ?>">
                    <a class="nav-link" href="/dashboard/insights/">
                        <i class="fas fa-lightbulb"></i> Insights
                    </a>
                </li>
                
                <!-- Config -->
                <li class="nav-item <?php echo ($currentModule ?? '') === 'config' ? 'active' : ''; ?>">
                    <a class="nav-link" href="/dashboard/config/">
                        <i class="fas fa-cog"></i> Config
                    </a>
                </li>
                
                <!-- Health -->
                <li class="nav-item <?php echo ($currentModule ?? '') === 'health' ? 'active' : ''; ?>">
                    <a class="nav-link" href="/dashboard/health/">
                        <i class="fas fa-heartbeat"></i> Health
                    </a>
                </li>
                
                <!-- User Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown">
                        <i class="fas fa-user-circle"></i>
                        <?php echo htmlspecialchars($currentUser['name'] ?? 'User'); ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="/profile">
                            <i class="fas fa-user"></i> Profile
                        </a>
                        <a class="dropdown-item" href="/settings">
                            <i class="fas fa-sliders-h"></i> Settings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="/docs">
                            <i class="fas fa-book"></i> Documentation
                        </a>
                        <a class="dropdown-item" href="/support">
                            <i class="fas fa-life-ring"></i> Support
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-danger" href="/logout">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </li>
                
            </ul>
        </div>
        
    </div>
</nav>

<!-- Breadcrumb -->
<?php if (isset($breadcrumbs) && is_array($breadcrumbs)): ?>
<div class="container-fluid" style="max-width: 1400px; padding-top: 16px;">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb" style="background: transparent; padding: 0; margin: 0;">
            <li class="breadcrumb-item"><a href="/dashboard/"><i class="fas fa-home"></i> Home</a></li>
            <?php foreach ($breadcrumbs as $label => $url): ?>
                <?php if ($url): ?>
                    <li class="breadcrumb-item"><a href="<?php echo htmlspecialchars($url); ?>"><?php echo htmlspecialchars($label); ?></a></li>
                <?php else: ?>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($label); ?></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ol>
    </nav>
</div>
<?php endif; ?>

<!-- Main Content Starts Here -->
<main class="main-content">
