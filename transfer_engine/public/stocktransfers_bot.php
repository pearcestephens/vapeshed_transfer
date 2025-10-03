<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

// 1) Proxy through to your existing engine (includes GET passthrough)
ob_start();
include __DIR__ . '/adhoc_rebalance_http.php'; // prints JSON
$json = ob_get_clean();

$resp = json_decode($json, true);
if (!$resp || empty($resp['ok'])) {
    http_response_code(502);
    echo json_encode(['ok'=>false,'error'=>'Upstream JSON malformed or ok=false','raw'=>$json], JSON_UNESCAPED_SLASHES);
    exit;
}

$meta   = $resp['meta'] ?? [];
$params = $meta['params'] ?? [];

$out = [
    'ok'      => true,
    'run_id'  => $meta['run_id'] ?? null,
    'meta'    => [
        'outlets'      => (int)($meta['outlets'] ?? 0),
        'hub_products' => (int)($meta['hub_products'] ?? 0),
        'lines'        => (int)($meta['lines'] ?? 0),
        'units'        => (int)($meta['units'] ?? 0),
        'elapsed_ms'   => (int)($meta['elapsed_ms'] ?? 0),
    ],
    'params'  => [
        'limit_products'     => (int)($params['limit_products']     ?? 0),
        'reserve_percent'    => (float)($params['reserve_percent']  ?? 0),
        'reserve_min'        => (int)($params['reserve_min']        ?? 0),
        'max_per_store'      => (int)($params['max_per_store']      ?? 0),
        'min_line_qty'       => (int)($params['min_line_qty']       ?? 0),
        'prop_share'         => (float)($params['prop_share']       ?? 0),
        'target_level'       => (int)($params['target_level']       ?? 0),
        'no_send_if_atleast' => (int)($params['no_send_if_atleast'] ?? 0),
        'snap_multiple'      => (int)($params['quantize']['snap_multiple'] ?? ($params['snap_multiple'] ?? 0)),
        'snap_delta'         => (int)($params['quantize']['snap_delta']    ?? ($params['snap_delta']    ?? 0)),
    ],
    'stores'  => [],
    'triage'  => [
        'best'   => null,
        'middle' => null,
        'worst'  => null,
    ],
];

// Per-store summaries
foreach ($resp['per_outlet'] ?? [] as $o) {
    $out['stores'][] = [
        'store_code' => (string)($o['store_code'] ?? ''),
        'name'       => (string)($o['name'] ?? ($o['store_code'] ?? '')),
        'products'   => (int)($o['product_count'] ?? 0),
        'units'      => (int)($o['units'] ?? 0),
        'kg'         => round(((int)($o['total_weight_g'] ?? 0)) / 1000, 2),
    ];
}

// Triage
if (!empty($out['stores'])) {
    $sorted = $out['stores'];
    usort($sorted, fn($a,$b)=>$a['units'] <=> $b['units']);
    $out['triage'] = [
        'best'   => $sorted[count($sorted)-1],
        'middle' => $sorted[(int)floor(count($sorted)/2)],
        'worst'  => $sorted[0],
    ];
}

echo json_encode($out, JSON_UNESCAPED_SLASHES);
