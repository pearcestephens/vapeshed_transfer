<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Database;

class ForensicsController extends BaseController
{
    public function index(): void
    {
        try {
            $db = Database::getInstance()->getConnection();
            $recent = $this->recentAutoAccepts($db);
            $cats = $this->categoryDistribution($db);
            $flags = $this->featureFlags($db);
            $syn = $this->topSynonymCandidates($db);
            // Placeholder: client-side will fetch /api/dashboard/metrics for extended widgets (rejections, drift, clusters)
            $this->render('forensics/index', [
                'title'=>'Match Forensics',
                'recent_accepts'=>$recent,
                'category_dist'=>$cats,
                'flags'=>$flags,
                'syn_candidates'=>$syn
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Forensics error '.$e->getMessage());
            http_response_code(500); echo 'Forensics error';
        }
    }

    private function recentAutoAccepts($db): array
    {
        $sql = "SELECT m.match_id,m.candidate_id,m.sku_id,m.confidence,m.created_at FROM product_candidate_matches m WHERE m.status='accepted' ORDER BY m.created_at DESC LIMIT 25";
        $res=$db->query($sql); $rows=[]; while($res && $r=$res->fetch_assoc()){ $rows[]=$r; } return $rows;
    }
    private function categoryDistribution($db): array
    {
        $sql = "SELECT DATE(e.created_at) d, JSON_UNQUOTE(JSON_EXTRACT(e.payload_json,'$.category')) cat, COUNT(*) c FROM product_candidate_match_events e WHERE e.event_type='category_annotation' GROUP BY d,cat ORDER BY d DESC LIMIT 60";
        $res=$db->query($sql); $out=[]; while($res && $r=$res->fetch_assoc()){ $out[]=$r; } return $out;
    }
    private function featureFlags($db): array
    { $res=$db->query("SELECT flag_key, flag_value FROM feature_flags"); $out=[]; while($res && $r=$res->fetch_assoc()){ $out[$r['flag_key']]=(bool)$r['flag_value']; } return $out; }
    private function topSynonymCandidates($db): array
    { $res=$db->query("SELECT token,occurrences FROM brand_synonym_candidates WHERE flagged=0 ORDER BY occurrences DESC LIMIT 15"); $out=[]; while($res && $r=$res->fetch_assoc()){ $out[]=$r; } return $out; }
}
