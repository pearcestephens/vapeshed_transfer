    <footer class="footer">&copy; 2025 Ecigdis Ltd</footer>
  <?php $assets = $this->app['urls']['assets'] ?? (htmlspecialchars($baseUrl) . 'assets'); ?>
  <script src="<?= $assets ?>/js/app.js"></script>
  <script src="<?= $assets ?>/js/dashboard.js"></script>
  </body>
</html>
