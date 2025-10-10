<?php
use App\Support\Response;
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <title><?= isset($title) ? htmlspecialchars($title) . ' · ' : '' ?>Vape Shed Control Panel</title>
    <?php
    $publicRoot = defined('PUBLIC_PATH') ? PUBLIC_PATH : dirname(__DIR__, 2) . '/public';
    $cssPath = $publicRoot . '/assets/css/dashboard.css';
    $jsPath = $publicRoot . '/assets/js/dashboard.js';
    $cssVersion = is_file($cssPath) ? (string)filemtime($cssPath) : '0';
    $jsVersion = is_file($jsPath) ? (string)filemtime($jsPath) : '0';
    ?>
    <link rel="stylesheet" href="/assets/css/dashboard.css?v=<?= $cssVersion ?>">
    <script defer src="/assets/js/dashboard.js?v=<?= $jsVersion"></script>
</head>
<body class="layout dashboard-shell">
<header class="topbar">
    <div class="branding">
        <span class="logo">VS</span>
        <strong>The Vape Shed · Control Panel</strong>
    </div>
    <div class="topbar-actions">
        <span class="badge badge--status" id="advisor-status" data-status="unknown">Advisor: Initialising…</span>
        <span class="user" data-user-id="<?= htmlspecialchars($user['id'] ?? '0', ENT_QUOTES) ?>">
            <?= htmlspecialchars($user['name'] ?? 'Guest', ENT_QUOTES) ?>
        </span>
    </div>
</header>
<div class="layout-grid">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="content" id="content">
