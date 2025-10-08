<?php
/** @var string $title */
/** @var string $correlationId */
/** @var array $flags */
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="x-correlation-id" content="<?= htmlspecialchars($correlationId ?? '', ENT_QUOTES, 'UTF-8') ?>">
  <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
  <link rel="preload" href="/public/admin/assets/app.css" as="style">
  <link rel="stylesheet" href="/public/admin/assets/app.css">
  <script defer src="/public/admin/assets/app.js"></script>
</head>
<body class="<?= !empty($flags['sidebar_compact']) ? 'sidebar-compact' : '' ?>">
  <a class="skip-link" href="#main">Skip to content</a>

  <header class="admin-header" role="banner" aria-label="Header">
    <div class="brand">
      <span class="brand-logo" aria-hidden="true">VS</span>
      <span class="brand-name">The Vape Shed — Admin</span>
    </div>
    <nav class="header-actions" aria-label="Header actions">
      <button id="sidebarToggle" class="btn" aria-controls="sidebar" aria-expanded="false">Menu</button>
    </nav>
  </header>

  <div class="admin-shell">
    <aside id="sidebar" class="sidebar" aria-label="Sidebar navigation">
      <ul class="nav" role="menu">
        <li role="none"><a role="menuitem" href="?endpoint=admin/layout" data-nav="dashboard">Dashboard</a></li>
        <li role="none"><a role="menuitem" href="?endpoint=admin/health/ping" data-nav="health-ping">Health — Ping</a></li>
        <?php if (!empty($flags['show_phpinfo'])): ?>
          <li role="none"><a role="menuitem" href="?endpoint=admin/health/phpinfo" data-nav="phpinfo">PHP Info</a></li>
        <?php endif; ?>
        <li role="none"><a role="menuitem" href="?endpoint=admin/http/one-click-check" data-nav="bundle-probe">Bundle Probe</a></li>
        <li role="none"><a role="menuitem" href="?endpoint=admin/logs/apache-error-tail" data-nav="logs">Logs Tail</a></li>
        <li class="divider" aria-hidden="true"></li>
        <li role="none"><a role="menuitem" href="?endpoint=config/probe" data-nav="config">Config Probe</a></li>
      </ul>
    </aside>

    <main id="main" class="content" tabindex="-1">
      <h1>Admin Console</h1>
      <p>Welcome to the standalone admin shell. This layout uses only <code>public/admin/assets/app.css</code> and <code>app.js</code> (no legacy bundles).</p>

      <section class="tiles">
        <article class="tile">
          <h2>SAFE MODE</h2>
          <p><?= (!empty($flags['safe_mode'])) ? 'Enabled (protected endpoints require auth).' : 'Disabled (dev only).' ?></p>
        </article>
        <article class="tile">
          <h2>Bundles</h2>
          <p><button class="btn" data-action="probe">Run Bundle Probe</button></p>
          <pre class="probe-output" aria-live="polite"></pre>
        </article>
      </section>
    </main>
  </div>

  <footer class="admin-footer" role="contentinfo" aria-label="Footer">
    <div class="meta">
      <span class="badge">SAFE_MODE: <?= (!empty($flags['safe_mode'])) ? 'ON' : 'OFF' ?></span>
      <span>Correlation: <code><?= htmlspecialchars($correlationId ?? '-', ENT_QUOTES, 'UTF-8') ?></code></span>
      <?php if (defined('APP_BUILD_TAG')): ?>
        <span>Build: <code><?= htmlspecialchars((string)APP_BUILD_TAG, ENT_QUOTES, 'UTF-8') ?></code></span>
      <?php endif; ?>
    </div>
  </footer>
</body>
</html>
