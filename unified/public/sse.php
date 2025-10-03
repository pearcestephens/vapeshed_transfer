<?php
declare(strict_types=1);
use Unified\Realtime\Streams; use Unified\Bootstrap;
require_once __DIR__.'/../../src/Bootstrap.php';
Bootstrap::init();
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
Streams::sendKeepAlive();
