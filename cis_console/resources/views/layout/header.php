<?php
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'staff.vapeshed.co.nz';
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
$baseUrl = $scheme . '://' . $host . $scriptDir . '/';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>CIS Console</title>
    <?php 
      $assets = $this->app['urls']['assets'] ?? (htmlspecialchars($baseUrl) . 'assets');
      $indexUrl = $this->app['urls']['index'] ?? (htmlspecialchars($baseUrl) . 'index.php');
    ?>
    <link rel="stylesheet" href="<?= $assets ?>/css/app.css" />
    <link rel="stylesheet" href="<?= $assets ?>/css/dashboard.css" />
    <script>window.CIS_BASE = '<?= $indexUrl ?>';</script>
  </head>
  <body>
    <header class="header"><h1>CIS Console</h1></header>
