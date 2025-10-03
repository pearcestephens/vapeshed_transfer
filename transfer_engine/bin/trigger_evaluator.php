<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app.php';

use VapeshedTransfer\Core\Database;
use VapeshedTransfer\Core\Logger;

$logger = new Logger('trigger_evaluator');
$db = new Database();
$conn = $db->getConnection();

function tableExists(mysqli $c, string $t): bool { $r=$c->query("SHOW TABLES LIKE '".$c->real_escape_string($t)."'"); return $r && $r->num_rows>0; }

try {
    $assistantUserId = (int)($_GET['user_id'] ?? 1); // default target staff user

    $insightsCreated = [];
    $now = date('c');

    // Trigger 1: Directive staleness
    if (tableExists($conn,'neuro_directives') && tableExists($conn,'assistant_insights')) {
        $r=$conn->query("SELECT MAX(created_at) as last FROM neuro_directives");
        $row=$r?$r->fetch_assoc():null;
        if ($row && $row['last']) {
            $age = time()-strtotime($row['last']);
            if ($age > 21600) { // >6h
                $iid = 'INS_'.substr(md5('stale'.$row['last']),0,16);
                // Upsert-like guard
                $check=$conn->query("SELECT insight_id FROM assistant_insights WHERE insight_id='".$conn->real_escape_string($iid)."'");
                if ($check && $check->num_rows===0) {
                    $stmt=$conn->prepare("INSERT INTO assistant_insights (insight_id,user_id,category,priority,title,summary,payload_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())");
                    $cat='neuro'; $pri='high'; $title='Directive Refresh Required';
                    $summary='Neuro directives have not updated for '.round($age/3600,1).' hours.'; $payload=json_encode(['age_seconds'=>$age]);
                    $stmt->bind_param('sisssss',$iid,$assistantUserId,$cat,$pri,$title,$summary,$payload); $stmt->execute();
                    $insightsCreated[]=$iid;
                }
            }
        }
    }

    // Trigger 2: Large transfer imbalance (many low stock items)
    if (tableExists($conn,'outlet_inventory') && tableExists($conn,'assistant_insights')) {
        $r=$conn->query("SELECT COUNT(*) as low FROM outlet_inventory WHERE min_stock_level>0 AND current_stock < min_stock_level");
        $lowCount = $r? (int)$r->fetch_assoc()['low'] : 0;
        if ($lowCount >= 50) {
            $iid='INS_'.substr(md5('lowstock'.$lowCount.date('YmdH')),0,16);
            $check=$conn->query("SELECT insight_id FROM assistant_insights WHERE insight_id='".$conn->real_escape_string($iid)."'");
            if ($check && $check->num_rows===0) {
                $stmt=$conn->prepare("INSERT INTO assistant_insights (insight_id,user_id,category,priority,title,summary,payload_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())");
                $cat='inventory'; $pri='critical'; $title='Widespread Low Stock';
                $summary=$lowCount.' SKUs below minimum thresholds. Immediate redistribution advised.'; $payload=json_encode(['low_stock_items'=>$lowCount]);
                $stmt->bind_param('sisssss',$iid,$assistantUserId,$cat,$pri,$title,$summary,$payload); $stmt->execute();
                $insightsCreated[]=$iid;
            }
        }
    }

    // Trigger 3: Competitive threat spike
    if (tableExists($conn,'competitive_analysis') && tableExists($conn,'assistant_insights')) {
        $r=$conn->query("SELECT COUNT(*) as high FROM competitive_analysis WHERE threat_level='high' AND analyzed_at >= NOW() - INTERVAL 6 HOUR");
        $high=(int)$r->fetch_assoc()['high'];
        if ($high >= 10) {
            $iid='INS_'.substr(md5('threat'.$high.date('YmdH')),0,16);
            $check=$conn->query("SELECT insight_id FROM assistant_insights WHERE insight_id='".$conn->real_escape_string($iid)."'");
            if ($check && $check->num_rows===0) {
                $stmt=$conn->prepare("INSERT INTO assistant_insights (insight_id,user_id,category,priority,title,summary,payload_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())");
                $cat='competitive'; $pri='high'; $title='Competitive Threat Surge';
                $summary=$high.' high-level pricing threats detected in last 6h.'; $payload=json_encode(['high_threats'=>$high]);
                $stmt->bind_param('sisssss',$iid,$assistantUserId,$cat,$pri,$title,$summary,$payload); $stmt->execute();
                $insightsCreated[]=$iid;
            }
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['success'=>true,'data'=>['insights_created'=>$insightsCreated,'count'=>count($insightsCreated),'timestamp'=>$now]]);
} catch (Throwable $e) {
    $logger->error('Trigger evaluator failure',['error'=>$e->getMessage()]);
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>['code'=>500,'message'=>'Trigger evaluation failed']]);
}
