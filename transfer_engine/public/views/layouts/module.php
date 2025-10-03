<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? config('app.name'); ?></title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Dashboard CSS -->
    <link rel="stylesheet" href="<?php echo asset('css/dashboard.css'); ?>">
    
    <?php if (isset($moduleCSS)): ?>
        <link rel="stylesheet" href="<?php echo asset("css/modules/{$moduleCSS}.css"); ?>">
    <?php endif; ?>
</head>
<body>
    
    <!-- Navigation -->
    <?php include __DIR__ . '/../partials/navbar.php'; ?>
    
    <!-- Module Content Container -->
    <div class="container-fluid module-container" style="max-width: 1600px; padding: 24px;">
        
        <!-- Module Header -->
        <div class="module-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">
                    <i class="fas fa-<?php echo $moduleIcon ?? 'cube'; ?>" style="color: <?php echo $moduleColor ?? '#6366f1'; ?>;"></i>
                    <?php echo $pageTitle ?? 'Module'; ?>
                </h2>
                <p class="text-muted mb-0"><?php echo $moduleDescription ?? ''; ?></p>
            </div>
            <?php if (isset($moduleActions)): ?>
            <div class="module-actions">
                <?php echo $moduleActions; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Module Content -->
        <?php echo $content ?? ''; ?>
        
    </div>
    
    <!-- Footer -->
    <?php include __DIR__ . '/../partials/footer.php'; ?>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Dashboard JS -->
    <script src="<?php echo asset('js/dashboard.js'); ?>"></script>
    
    <?php if (isset($moduleJS)): ?>
        <script src="<?php echo asset("js/modules/{$moduleJS}.js"); ?>"></script>
    <?php endif; ?>
    
    <?php if (isset($inlineScripts)): ?>
        <script><?php echo $inlineScripts; ?></script>
    <?php endif; ?>
    
</body>
</html>
