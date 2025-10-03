<?php
declare(strict_types=1);
use Unified\Bootstrap; use Unified\Support\Http; 
require_once __DIR__.'/../../src/Bootstrap.php';
Bootstrap::init();
$dbOk = true; $err=null;
try { $pdo = Unified\Bootstrap::get('db'); $pdo->query('SELECT 1'); } catch(Throwable $e){ $dbOk=false; $err=$e->getMessage(); }
Http::json([
  'status'=> $dbOk ? 'ok':'degraded',
  'db'=>$dbOk,
  'error'=>$err,
  'ts'=>date('c')
], $dbOk?200:500);
