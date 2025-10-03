<?php
declare(strict_types=1);
/**
 * match_readiness.php
 * Schema + feature readiness diagnostics for the Matching / Synonym / Analytics stack.
 * Outputs JSON: existence of critical tables, column types, row counts, and suggested flag overrides.
 *
 * Usage:
 *   php bin/match_readiness.php            # JSON summary
 *   php bin/match_readiness.php pretty     # Pretty printed JSON
 *
 * Author: Ecigdis Ltd (The Vape Shed)
 */

require_once __DIR__.'/_cli_bootstrap.php';

$mysqli = cli_db();

// Helper: does table exist
function tableExists(mysqli $db, string $table): bool {
    $tEsc = $db->real_escape_string($table);
    $res = $db->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name='$tEsc' LIMIT 1");
    return (bool)($res && $res->num_rows === 1);
}

function columnInfo(mysqli $db, string $table, string $col): ?array {
    $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
    $res=$db->query("SELECT DATA_TYPE,COLUMN_TYPE,IS_NULLABLE FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='$t' AND column_name='$c' LIMIT 1");
    if($res && $row=$res->fetch_assoc()) return $row; return null;
}

$tables = [
  'product_candidate_matches',
  'product_candidate_match_events',
  'brand_synonyms',
  'brand_synonym_candidates',
  'feature_flags',
  'product_candidate_match_event_rollup',
  'image_hash_clusters'
];

$status = [];
foreach($tables as $t){ $status[$t] = ['exists'=>tableExists($mysqli,$t)]; }

// Row counts (only if exists, cheap count for small/ moderate tables)
$countTargets = [
  'product_candidate_matches','product_candidate_match_events','brand_synonym_candidates'
];
foreach($countTargets as $t){
  if($status[$t]['exists']){
    $res = $mysqli->query("SELECT COUNT(*) c FROM `$t`");
    $status[$t]['rows'] = ($res && $row=$res->fetch_assoc()) ? (int)$row['c'] : null;
  }
}

// event_type column shape
$eventCol = columnInfo($mysqli,'product_candidate_match_events','event_type');
if($eventCol){
  $status['product_candidate_match_events']['event_type'] = $eventCol;
  $isEnum = stripos($eventCol['COLUMN_TYPE'],'enum(')!==false;
  $status['product_candidate_match_events']['event_type_needs_alter'] = $isEnum; // should be VARCHAR(64)
}

// Suggest flag overrides based on missing tables
$suggestFlags = [];
if(!$status['brand_synonyms']['exists']){ $suggestFlags['brand_weighting'] = false; }
if(!$status['brand_synonym_candidates']['exists']){ $suggestFlags['synonym_learning'] = false; }
if(!$status['feature_flags']['exists']){ $suggestFlags['_note'] = 'feature_flags table missing; env-only flags active'; }
// category_analytics depends on widened event_type (not enum)
if(!empty($status['product_candidate_match_events']['event_type_needs_alter'])){ $suggestFlags['category_analytics'] = false; }

// Overall readiness score (simple heuristic)
$requiredCore = ['product_candidate_matches','product_candidate_match_events'];
$coreOk = array_reduce($requiredCore, fn($c,$t)=> $c && ($status[$t]['exists']??false), true);
$readiness = $coreOk ? 'core-ok' : 'missing-core';
if($readiness==='core-ok'){
  if(isset($status['product_candidate_match_events']['event_type_needs_alter']) && $status['product_candidate_match_events']['event_type_needs_alter']){
    $readiness='degraded-enum-mode';
  }
}

$out = [
  'readiness'=>$readiness,
  'tables'=>$status,
  'suggest_flag_overrides'=>$suggestFlags,
  'applied_env_flags'=>[
    'brand_weighting'=> getenv('MATCH_FLAG_BRAND_WEIGHTING'),
    'duplicate_suppression'=> getenv('MATCH_FLAG_DUP_SUPPRESS'),
    'category_analytics'=> getenv('MATCH_FLAG_CATEGORY_ANALYTICS'),
    'synonym_learning'=> getenv('MATCH_FLAG_SYNONYM_LEARN'),
    'image_similarity'=> getenv('MATCH_FLAG_IMAGE_SIMILARITY'),
    'vision_bonus'=> getenv('MATCH_FLAG_VISION_BONUS'),
  ],
  'recommendations'=>[
    'run_migrations_in_order'=>['20251002_0300','20251002_0303'],'if_degraded_reason'=>($readiness==='degraded-enum-mode'?'event_type still ENUM':'')
  ]
];

$pretty = ($argv[1] ?? '') === 'pretty';
echo $pretty ? json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n" : json_encode($out)."\n";
