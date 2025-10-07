<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo $pageTitle ?? config('app.name'); ?></title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['_csrf'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Dashboard CSS -->
    <link rel="stylesheet" href="<?php echo asset('css/dashboard.css'); ?>">
    
    <?php if (isset($additionalCSS)): ?>
        <?php echo $additionalCSS; ?>
    <?php endif; ?>
</head>
<body>
    
    <!-- Navigation -->
    <?php include __DIR__ . '/../partials/navbar.php'; ?>
    
    <!-- Main Content -->
    <main class="py-4">
        <?php echo $content ?? ''; ?>
    </main>
    
    <!-- Footer -->
    <?php include __DIR__ . '/../partials/footer.php'; ?>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Dashboard JS -->
    <script src="<?php echo asset('js/dashboard.js'); ?>"></script>
        <script>
        // Minimal helper to add CSRF header on POST fetch() automatically
            (function(){
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const cid = (window.__correlation_id || '');
            const _fetch = window.fetch;
            window.fetch = function(input, init){
                init = init || {};
                const method = (init.method || 'GET').toUpperCase();
                    const extra = {};
                    if (method === 'POST' && token) { extra['X-CSRF-Token'] = token; }
                    if (cid) { extra['X-Correlation-ID'] = cid; }
                    if (Object.keys(extra).length) { init.headers = Object.assign({}, init.headers || {}, extra); }
                return _fetch(input, init);
            };
        })();
        </script>
    
    <?php if (isset($additionalJS)): ?>
        <?php echo $additionalJS; ?>
    <?php endif; ?>
    
    <?php if (isset($inlineScripts)): ?>
        <script><?php echo $inlineScripts; ?></script>
    <?php endif; ?>
    
</body>
</html>
