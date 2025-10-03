<?php
declare(strict_types=1);
use Unified\Bootstrap;
require_once __DIR__.'/../../src/Bootstrap.php';
Bootstrap::init();
$view = $_GET['view'] ?? 'overview';
?><!DOCTYPE html><html><head><meta charset="utf-8"><title>Unified Dashboard</title></head><body>
<h1>Unified Dashboard (Skeleton)</h1>
<nav>
 <a href="?view=overview">Overview</a> |
 <a href="?view=transfers">Transfers</a>
</nav>
<section>
<?php if($view==='overview'): ?>
 <h2>Overview</h2>
 <p>Placeholder metrics will appear here.</p>
<?php elseif($view==='transfers'): ?>
 <h2>Transfers</h2>
 <p>Pending allocation table placeholder.</p>
<?php else: ?>
 <p>Unknown view.</p>
<?php endif; ?>
</section>
</body></html>
