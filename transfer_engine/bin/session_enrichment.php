<?php
declare(strict_types=1);

// Session Enrichment Task (lightweight initial version)
// Aggregates recent interaction_events into in-memory enrichment metrics.
// NOTE: Intentionally does NOT create new tables yet – prepares data for future persistence.

require_once __DIR__ . '/../../../app.php';

use VapeshedTransfer\Core\Database;
use VapeshedTransfer\Core\Logger;

$logger = new Logger('session_enrichment');
$db = new Database();

$windowMinutes = (int)($_GET['window'] ?? 30); // if run via web, else default
$since = date('Y-m-d H:i:s', time() - $windowMinutes * 60);

try {
    // Pull recent events grouped by session/user
    $rows = $db->query(
        'SELECT session_id, user_type, user_id, event_type, COUNT(*) as cnt, MIN(created_at) as first_at, MAX(created_at) as last_at
         FROM interaction_events
         WHERE created_at >= :since AND session_id IS NOT NULL
         GROUP BY session_id, user_type, user_id, event_type',
        ['since'=>$since]
    );

    // Organize into sessions
    $sessions = [];
    foreach ($rows as $r) {
        $sid = $r['session_id'];
        if (!isset($sessions[$sid])) {
            $sessions[$sid] = [
                'session_id' => $sid,
                'user_type' => $r['user_type'],
                'user_id' => $r['user_id'],
                'events' => [],
                'first_at' => $r['first_at'],
                'last_at' => $r['last_at']
            ];
        }
        $sessions[$sid]['events'][$r['event_type']] = (int)$r['cnt'];
        // Update time span
        if ($r['first_at'] < $sessions[$sid]['first_at']) { $sessions[$sid]['first_at'] = $r['first_at']; }
        if ($r['last_at'] > $sessions[$sid]['last_at']) { $sessions[$sid]['last_at'] = $r['last_at']; }
    }

    // Compute lightweight engagement metrics
    $enriched = [];
    foreach ($sessions as $sid => $s) {
        $duration = strtotime($s['last_at']) - strtotime($s['first_at']);
        $totalEvents = array_sum($s['events']);
        $focus = $s['events']['focus'] ?? 0;
        $clicks = $s['events']['click'] ?? 0;
        $pageViews = $s['events']['page_view'] ?? 0;
        $engagementScore = ($focus * 1.5) + ($clicks * 1.2) + ($pageViews * 0.8);
        $enriched[] = [
            'session_id' => $sid,
            'user_type' => $s['user_type'],
            'user_id' => $s['user_id'],
            'duration_sec' => $duration,
            'total_events' => $totalEvents,
            'engagement_score' => round($engagementScore,2),
            'events_breakdown' => $s['events']
        ];
    }

    $logger->info('Session enrichment batch', [
        'window_minutes'=>$windowMinutes,
        'session_count'=>count($enriched)
    ]);

    // For now output JSON to stdout (CLI) or browser
    header('Content-Type: application/json');
    echo json_encode(['success'=>true,'data'=>['sessions'=>$enriched,'count'=>count($enriched)]], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    $logger->error('Session enrichment failure', ['error'=>$e->getMessage()]);
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>['code'=>500,'message'=>'Enrichment failure']]);
}
