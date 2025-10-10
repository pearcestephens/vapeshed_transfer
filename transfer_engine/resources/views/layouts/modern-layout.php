<?php
/**
 * Modern Layout Component
 *
 * Enhanced layout template with:
 * - Responsive design system
 * - Component-based architecture
 * - Dynamic sidebar
 * - Advanced header
 * - Breadcrumb navigation
 * - Status indicators
 * - Theme switching
 * - Mobile optimization
 * - Accessibility features
 * - Progressive enhancement
 *
 * @category   View
 * @package    VapeshedTransfer
 * @subpackage Layout
 * @version    1.0.0
 */

class ModernLayout
{
    /**
     * Current page configuration
     *
     * @var array
     */
    private $config = [];

    /**
     * Navigation items
     *
     * @var array
     */
    private $navigation = [];

    /**
     * User data
     *
     * @var array
     */
    private $user = [];

    /**
     * Constructor
     *
     * @param array $config Layout configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'title' => 'Vape Shed Transfer Engine',
            'description' => 'Advanced stock transfer management system',
            'theme' => 'light',
            'sidebar_collapsed' => false,
            'show_breadcrumbs' => true,
            'show_notifications' => true,
            'show_search' => true,
            'layout' => 'default', // default, minimal, fullscreen
            'scripts' => [],
            'styles' => [],
            'meta' => []
        ], $config);

        $this->initializeNavigation();
        $this->loadUserData();
    }

    /**
     * Render complete layout
     *
     * @param string $content Main content
     * @param array $data Additional data
     * @return string HTML output
     */
    public function render(string $content, array $data = []): string
    {
        ob_start();
        $this->renderDoctype();
        $this->renderHead();
        $this->renderBodyStart();
        
        if ($this->config['layout'] === 'minimal') {
            $this->renderMinimalLayout($content, $data);
        } elseif ($this->config['layout'] === 'fullscreen') {
            $this->renderFullscreenLayout($content, $data);
        } else {
            $this->renderDefaultLayout($content, $data);
        }
        
        $this->renderBodyEnd();
        $this->renderScripts();
        
        return ob_get_clean();
    }

    /**
     * Render HTML doctype and opening tag
     */
    private function renderDoctype(): void
    {
        echo '<!DOCTYPE html>' . "\n";
        echo '<html lang="en" data-theme="' . $this->config['theme'] . '">' . "\n";
    }

    /**
     * Render document head
     */
    private function renderHead(): void
    {
        ?>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            
            <title><?= htmlspecialchars($this->config['title']) ?></title>
            <meta name="description" content="<?= htmlspecialchars($this->config['description']) ?>">
            
            <?php $this->renderMetaTags(); ?>
            <?php $this->renderFavicons(); ?>
            <?php $this->renderPreloadTags(); ?>
            <?php $this->renderStylesheets(); ?>
        </head>
        <?php
    }

    /**
     * Render meta tags
     */
    private function renderMetaTags(): void
    {
        $defaultMeta = [
            'robots' => 'noindex,nofollow',
            'application-name' => 'Vape Shed Transfer Engine',
            'theme-color' => '#007bff'
        ];

        $meta = array_merge($defaultMeta, $this->config['meta']);

        foreach ($meta as $name => $content) {
            echo '<meta name="' . htmlspecialchars($name) . '" content="' . htmlspecialchars($content) . '">' . "\n";
        }
    }

    /**
     * Render favicon links
     */
    private function renderFavicons(): void
    {
        ?>
        <link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
        <link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon-16x16.png">
        <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32x32.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png">
        <link rel="manifest" href="/assets/site.webmanifest">
        <?php
    }

    /**
     * Render preload tags for performance
     */
    private function renderPreloadTags(): void
    {
        $preloads = [
            '/assets/css/dashboard-enhanced.css' => 'style',
            '/assets/js/ui-components.js' => 'script',
            '/assets/fonts/inter-var.woff2' => 'font'
        ];

        foreach ($preloads as $href => $as) {
            $crossorigin = $as === 'font' ? ' crossorigin' : '';
            echo '<link rel="preload" href="' . $href . '" as="' . $as . '"' . $crossorigin . '>' . "\n";
        }
    }

    /**
     * Render stylesheets
     */
    private function renderStylesheets(): void
    {
        $defaultStyles = [
            '/assets/css/dashboard-enhanced.css',
            '/assets/css/components.css'
        ];

        $styles = array_merge($defaultStyles, $this->config['styles']);

        foreach ($styles as $href) {
            echo '<link rel="stylesheet" href="' . $href . '">' . "\n";
        }
    }

    /**
     * Render body opening tag
     */
    private function renderBodyStart(): void
    {
        $bodyClass = [
            'layout-' . $this->config['layout'],
            'theme-' . $this->config['theme']
        ];

        if ($this->config['sidebar_collapsed']) {
            $bodyClass[] = 'sidebar-collapsed';
        }

        echo '<body class="' . implode(' ', $bodyClass) . '" data-breakpoint="lg">' . "\n";
        
        // Accessibility skip link
        echo '<a href="#main-content" class="sr-only sr-only-focusable">Skip to main content</a>' . "\n";
    }

    /**
     * Render default layout
     */
    private function renderDefaultLayout(string $content, array $data): void
    {
        ?>
        <div class="dashboard-container">
            <?php $this->renderHeader(); ?>
            <div class="dashboard-main">
                <?php $this->renderSidebar(); ?>
                <main class="dashboard-content" id="main-content" role="main">
                    <?php if ($this->config['show_breadcrumbs']): ?>
                        <?php $this->renderBreadcrumbs(); ?>
                    <?php endif; ?>
                    
                    <div class="content-wrapper">
                        <?= $content ?>
                    </div>
                </main>
            </div>
        </div>
        
        <?php $this->renderNotificationContainer(); ?>
        <?php $this->renderModalOverlay(); ?>
        <?php
    }

    /**
     * Render minimal layout
     */
    private function renderMinimalLayout(string $content, array $data): void
    {
        ?>
        <div class="minimal-container">
            <header class="minimal-header">
                <h1><?= htmlspecialchars($this->config['title']) ?></h1>
            </header>
            <main class="minimal-content" id="main-content" role="main">
                <?= $content ?>
            </main>
        </div>
        <?php
    }

    /**
     * Render fullscreen layout
     */
    private function renderFullscreenLayout(string $content, array $data): void
    {
        ?>
        <div class="fullscreen-container">
            <?= $content ?>
        </div>
        <?php
    }

    /**
     * Render header component
     */
    private function renderHeader(): void
    {
        ?>
        <header class="dashboard-header" role="banner">
            <div class="header-left">
                <button class="sidebar-toggle" aria-label="Toggle sidebar" aria-expanded="true">
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                </button>
                
                <div class="brand">
                    <img src="/assets/images/logo.svg" alt="Vape Shed" class="brand-logo">
                    <span class="brand-text">Transfer Engine</span>
                </div>
            </div>

            <div class="header-center">
                <?php if ($this->config['show_search']): ?>
                    <?php $this->renderSearchBar(); ?>
                <?php endif; ?>
            </div>

            <div class="header-right">
                <?php $this->renderHeaderActions(); ?>
                <?php $this->renderUserMenu(); ?>
            </div>
        </header>
        <?php
    }

    /**
     * Render search bar
     */
    private function renderSearchBar(): void
    {
        ?>
        <div class="search-container">
            <input type="search" 
                   class="search-input" 
                   placeholder="Search transfers, products, stores..." 
                   aria-label="Search"
                   autocomplete="off">
            <button class="search-button" aria-label="Search">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
            </button>
        </div>
        <?php
    }

    /**
     * Render header actions
     */
    private function renderHeaderActions(): void
    {
        ?>
        <div class="header-actions">
            <?php if ($this->config['show_notifications']): ?>
                <button class="header-action notification-toggle" aria-label="Notifications" data-badge="3">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                </button>
            <?php endif; ?>

            <button class="header-action theme-toggle" aria-label="Toggle theme">
                <svg class="theme-icon theme-icon-light" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="12" cy="12" r="5"></circle>
                    <line x1="12" y1="1" x2="12" y2="3"></line>
                    <line x1="12" y1="21" x2="12" y2="23"></line>
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                    <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                    <line x1="1" y1="12" x2="3" y2="12"></line>
                    <line x1="21" y1="12" x2="23" y2="12"></line>
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                    <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                </svg>
                <svg class="theme-icon theme-icon-dark" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                </svg>
            </button>

            <button class="header-action fullscreen-toggle" aria-label="Toggle fullscreen">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path>
                </svg>
            </button>
        </div>
        <?php
    }

    /**
     * Render user menu
     */
    private function renderUserMenu(): void
    {
        ?>
        <div class="user-menu dropdown">
            <button class="user-menu-trigger dropdown-trigger" aria-label="User menu">
                <img src="<?= $this->user['avatar'] ?? '/assets/images/default-avatar.svg' ?>" 
                     alt="<?= htmlspecialchars($this->user['name'] ?? 'User') ?>" 
                     class="user-avatar">
                <span class="user-name"><?= htmlspecialchars($this->user['name'] ?? 'User') ?></span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <polyline points="6,9 12,15 18,9"></polyline>
                </svg>
            </button>
            
            <div class="dropdown-menu user-dropdown-menu">
                <div class="user-info">
                    <div class="user-details">
                        <div class="user-name"><?= htmlspecialchars($this->user['name'] ?? 'User') ?></div>
                        <div class="user-email"><?= htmlspecialchars($this->user['email'] ?? '') ?></div>
                    </div>
                </div>
                
                <div class="dropdown-divider"></div>
                
                <a href="/profile" class="dropdown-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    Profile
                </a>
                
                <a href="/settings" class="dropdown-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1 1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                    </svg>
                    Settings
                </a>
                
                <div class="dropdown-divider"></div>
                
                <a href="/logout" class="dropdown-item dropdown-item-danger">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16,17 21,12 16,7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    Logout
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render sidebar navigation
     */
    private function renderSidebar(): void
    {
        ?>
        <aside class="dashboard-sidebar" role="navigation" aria-label="Main navigation">
            <nav class="sidebar-nav">
                <?php foreach ($this->navigation as $section => $items): ?>
                    <div class="nav-section">
                        <div class="nav-section-title"><?= htmlspecialchars($section) ?></div>
                        <ul class="nav-list">
                            <?php foreach ($items as $item): ?>
                                <li class="nav-item">
                                    <a href="<?= htmlspecialchars($item['url']) ?>" 
                                       class="nav-link <?= $item['active'] ? 'active' : '' ?>"
                                       <?= $item['external'] ? 'target="_blank" rel="noopener"' : '' ?>>
                                        
                                        <?php if (isset($item['icon'])): ?>
                                            <span class="nav-icon"><?= $item['icon'] ?></span>
                                        <?php endif; ?>
                                        
                                        <span class="nav-text"><?= htmlspecialchars($item['title']) ?></span>
                                        
                                        <?php if (isset($item['badge'])): ?>
                                            <span class="nav-badge"><?= htmlspecialchars($item['badge']) ?></span>
                                        <?php endif; ?>
                                        
                                        <?php if ($item['external']): ?>
                                            <svg class="nav-external" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                                <polyline points="15,3 21,3 21,9"></polyline>
                                                <line x1="10" y1="14" x2="21" y2="3"></line>
                                            </svg>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </nav>
        </aside>
        <?php
    }

    /**
     * Render breadcrumb navigation
     */
    private function renderBreadcrumbs(): void
    {
        $breadcrumbs = $this->getBreadcrumbs();
        if (empty($breadcrumbs)) return;

        ?>
        <nav class="breadcrumb-nav" aria-label="Breadcrumb">
            <ol class="breadcrumb">
                <?php foreach ($breadcrumbs as $index => $crumb): ?>
                    <li class="breadcrumb-item <?= $index === count($breadcrumbs) - 1 ? 'active' : '' ?>">
                        <?php if ($index === count($breadcrumbs) - 1): ?>
                            <span aria-current="page"><?= htmlspecialchars($crumb['title']) ?></span>
                        <?php else: ?>
                            <a href="<?= htmlspecialchars($crumb['url']) ?>"><?= htmlspecialchars($crumb['title']) ?></a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>
        <?php
    }

    /**
     * Render notification container
     */
    private function renderNotificationContainer(): void
    {
        echo '<div id="notification-container" class="notification-container"></div>' . "\n";
    }

    /**
     * Render modal overlay
     */
    private function renderModalOverlay(): void
    {
        echo '<div id="modal-overlay" class="modal-overlay" style="display: none;"></div>' . "\n";
    }

    /**
     * Render closing body tag
     */
    private function renderBodyEnd(): void
    {
        echo '</body>' . "\n";
    }

    /**
     * Render JavaScript includes
     */
    private function renderScripts(): void
    {
        $defaultScripts = [
            '/assets/js/ui-components.js',
            '/assets/js/notifications.js',
            '/assets/js/toast-system.js',
            '/assets/js/app.js'
        ];

        $scripts = array_merge($defaultScripts, $this->config['scripts']);

        foreach ($scripts as $src) {
            echo '<script src="' . $src . '"></script>' . "\n";
        }

        echo '</html>' . "\n";
    }

    /**
     * Initialize navigation structure
     */
    private function initializeNavigation(): void
    {
        $this->navigation = [
            'Overview' => [
                [
                    'title' => 'Dashboard',
                    'url' => '/dashboard',
                    'icon' => '🏠',
                    'active' => $this->isCurrentPage('/dashboard')
                ],
                [
                    'title' => 'Analytics',
                    'url' => '/analytics',
                    'icon' => '📊',
                    'active' => $this->isCurrentPage('/analytics')
                ]
            ],
            'Operations' => [
                [
                    'title' => 'Transfers',
                    'url' => '/transfers',
                    'icon' => '🔄',
                    'active' => $this->isCurrentPage('/transfers'),
                    'badge' => '12'
                ],
                [
                    'title' => 'Inventory',
                    'url' => '/inventory',
                    'icon' => '📦',
                    'active' => $this->isCurrentPage('/inventory')
                ],
                [
                    'title' => 'Reports',
                    'url' => '/reports',
                    'icon' => '📈',
                    'active' => $this->isCurrentPage('/reports')
                ]
            ],
            'Management' => [
                [
                    'title' => 'Stores',
                    'url' => '/stores',
                    'icon' => '🏪',
                    'active' => $this->isCurrentPage('/stores')
                ],
                [
                    'title' => 'Users',
                    'url' => '/users',
                    'icon' => '👥',
                    'active' => $this->isCurrentPage('/users')
                ],
                [
                    'title' => 'Settings',
                    'url' => '/settings',
                    'icon' => '⚙️',
                    'active' => $this->isCurrentPage('/settings')
                ]
            ]
        ];
    }

    /**
     * Load user data
     */
    private function loadUserData(): void
    {
        // This would typically load from session or database
        $this->user = [
            'name' => 'Admin User',
            'email' => 'admin@vapeshed.co.nz',
            'avatar' => '/assets/images/avatars/admin.jpg',
            'role' => 'Administrator'
        ];
    }

    /**
     * Get breadcrumbs for current page
     */
    private function getBreadcrumbs(): array
    {
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        $segments = array_filter(explode('/', $path));
        
        $breadcrumbs = [
            ['title' => 'Home', 'url' => '/dashboard']
        ];

        $currentPath = '';
        foreach ($segments as $segment) {
            $currentPath .= '/' . $segment;
            $breadcrumbs[] = [
                'title' => ucfirst($segment),
                'url' => $currentPath
            ];
        }

        return $breadcrumbs;
    }

    /**
     * Check if current page matches URL
     */
    private function isCurrentPage(string $url): bool
    {
        $currentPath = $_SERVER['REQUEST_URI'] ?? '/';
        return strpos($currentPath, $url) === 0;
    }

    /**
     * Set page configuration
     */
    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    /**
     * Add navigation item
     */
    public function addNavigationItem(string $section, array $item): self
    {
        if (!isset($this->navigation[$section])) {
            $this->navigation[$section] = [];
        }
        
        $this->navigation[$section][] = $item;
        return $this;
    }

    /**
     * Set user data
     */
    public function setUser(array $user): self
    {
        $this->user = array_merge($this->user, $user);
        return $this;
    }
}