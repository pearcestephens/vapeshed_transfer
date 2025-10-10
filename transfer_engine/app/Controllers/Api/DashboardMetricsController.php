<?php
declare(strict_types=1);
namespace App\Controllers\Api;
use App\Controllers\BaseController;
use mysqli;

/**
 * DashboardMetricsController - aggregated analytics for forensics & health dashboards.
 */
class DashboardMetricsController extends BaseController
{
    public function index(): void
    {
        $this->requireAdmin('dashboard_metrics');
        header('Content-Type: application/json');
        try {
            $db = new mysqli(getenv('DB_HOST')?:'localhost', getenv('DB_USER')?:'root', getenv('DB_PASS')?:'', getenv('DB_NAME')?:'cis');
            if($db->connect_errno){ echo json_encode(['success'=>false,'error'=>'db_connect_failed']); return; }

            $out = [
                'acceptance_path_distribution' => $this->acceptancePathDist($db),
                'rejection_reason_dist' => $this->rejectionReasonDist($db),
                'recent_drift' => $this->recentDrift($db),
                'synonym_promotions_recent' => $this->recentPromotions($db),
                'cluster_stats' => $this->clusterStats($db),
                'timestamp' => date('c')
            ];
            echo json_encode(['success'=>true,'metrics'=>$out]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        }
    }

    private function acceptancePathDist($db): array
    {
        $sql = "SELECT JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.accept_eval.path')) path, COUNT(*) c
                FROM product_candidate_match_events
                WHERE event_type='confidence_update' AND JSON_EXTRACT(payload_json,'$.accept_eval.accepted')=true
                GROUP BY path ORDER BY c DESC LIMIT 10";
        $res=$db->query($sql); $out=[];
        while($res && $row=$res->fetch_assoc()){ $out[$row['path']??'unknown']=(int)$row['c']; }
        return $out;
    }

    private function rejectionReasonDist($db): array
    {
        $sql = "SELECT reason_code, COUNT(*) c FROM product_match_rejections WHERE created_at >= NOW()-INTERVAL 7 DAY GROUP BY reason_code ORDER BY c DESC LIMIT 10";
        $res=$db->query($sql); $out=[];
        while($res && $row=$res->fetch_assoc()){ $out[$row['reason_code']]=(int)$row['c']; }
        return $out;
    }

    private function recentDrift($db): array
    {
        $sql = "SELECT scope, delta_primary, delta_secondary, created_at FROM product_match_threshold_drift ORDER BY drift_id DESC LIMIT 8";
        $res=$db->query($sql); $out=[];
        while($res && $row=$res->fetch_assoc()){ $out[]=$row; }
        return $out;
    }

    private function recentPromotions($db): array
    {
        $sql = "SELECT token, occurrences, sku_spread, created_at FROM brand_synonym_promotion_audit ORDER BY audit_id DESC LIMIT 12";
        $res=$db->query($sql); $out=[];
        while($res && $row=$res->fetch_assoc()){ $out[]=$row; }
        return $out;
    }

    private function clusterStats($db): array
    {
        $sql = "SELECT COUNT(DISTINCT representative_hash) clusters, COUNT(*) members FROM image_hash_clusters";
        $res=$db->query($sql);
        if($res && $row=$res->fetch_assoc()){ return ['total_clusters'=>(int)$row['clusters'],'total_members'=>(int)$row['members']]; }
        return ['total_clusters'=>0,'total_members'=>0];
    }
}
