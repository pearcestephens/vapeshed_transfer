<aside class="sidebar">
  <nav>
    <ul>
    <?php $indexUrl = $this->app['urls']['index'] ?? (htmlspecialchars($baseUrl) . 'index.php'); ?>
    <li><a href="<?= $indexUrl ?>?endpoint=admin/health/ping">Health: Ping</a></li>
    <li><a href="<?= $indexUrl ?>?endpoint=admin/health/phpinfo">Health: PHP Info</a></li>
    <li><a href="<?= $indexUrl ?>?endpoint=admin/traffic/live">Traffic: Live (SSE)</a></li>
  <li><a href="<?= $indexUrl ?>?endpoint=admin/monitoring">Monitoring Dashboard</a></li>
    <li><a href="<?= $indexUrl ?>?endpoint=admin/logs/apache-error-tail">Logs: Apache Error Tail</a></li>
    </ul>
  </nav>
</aside>
