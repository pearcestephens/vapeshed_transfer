<nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo url('/dashboard'); ?>">
            <i class="fas fa-chart-network"></i>
            <?php echo config('app.name'); ?>
        </a>
        
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo url('/dashboard'); ?>">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="modulesDropdown" role="button" data-toggle="dropdown">
                        <i class="fas fa-cubes"></i> Modules
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                        <?php 
                        $modules = config('modules', []);
                        foreach ($modules as $key => $module): 
                            if ($module['status'] === 'active' || $module['status'] === 'beta'):
                        ?>
                        <a class="dropdown-item" href="<?php echo url("/modules/{$key}"); ?>">
                            <i class="fas fa-<?php echo $module['icon']; ?>"></i>
                            <?php echo $module['name']; ?>
                            <?php if ($module['status'] === 'beta'): ?>
                                <span class="badge badge-warning ml-1">Beta</span>
                            <?php endif; ?>
                        </a>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown">
                        <i class="fas fa-user-circle"></i>
                        <?php echo e(auth()->user()['name'] ?? 'User'); ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="<?php echo url('/profile'); ?>">
                            <i class="fas fa-user"></i> Profile
                        </a>
                        <a class="dropdown-item" href="<?php echo url('/settings'); ?>">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="<?php echo url('/docs'); ?>">
                            <i class="fas fa-book"></i> Documentation
                        </a>
                        <a class="dropdown-item" href="<?php echo url('/support'); ?>">
                            <i class="fas fa-life-ring"></i> Support
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="<?php echo url('/logout'); ?>">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</nav>

<?php if (isset($breadcrumbs) && !empty($breadcrumbs)): ?>
<nav aria-label="breadcrumb" class="bg-light">
    <div class="container-fluid">
        <ol class="breadcrumb mb-0 py-2">
            <li class="breadcrumb-item"><a href="<?php echo url('/dashboard'); ?>">Home</a></li>
            <?php foreach ($breadcrumbs as $title => $link): ?>
                <?php if ($link): ?>
                    <li class="breadcrumb-item"><a href="<?php echo $link; ?>"><?php echo e($title); ?></a></li>
                <?php else: ?>
                    <li class="breadcrumb-item active"><?php echo e($title); ?></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ol>
    </div>
</nav>
<?php endif; ?>
