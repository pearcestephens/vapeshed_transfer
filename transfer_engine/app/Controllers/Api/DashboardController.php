<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Core\Database;
use mysqli;

/**
 * Dashboard API Controller (Staff + Customer Intelligence Surface)
 * Provides consolidated endpoints without creating new overlapping tables.
 * Reuses:
 *  - interaction_events (behavioral telemetry)
 *  - staff_assistants / assistant_insights (staff AI layer) if present
 *  - neuro_directives (strategic directives) if present
 *  - agent_runs (agent execution telemetry) if present
 *  - customer_feedback / product_issues (customer sentiment & issue signals)
 *
 * Endpoints:
 *  GET /api/dashboard/neuro-state
 *  GET /api/dashboard/assistant/insights
 *  GET /api/dashboard/behavior/heatmap
 *  GET /api/dashboard/agent/activity
 *  GET /api/dashboard/customer/segments
 *
 * All responses follow existing success/error envelope patterns.
 */
class DashboardController extends BaseController
{
    private mysqli $conn;

    public function __construct()
    {
        parent::__construct();
        $this->conn = Database::getInstance()->getConnection();
    }

    /**
     * GET /api/dashboard/neuro-state
     */
    public function neuroState(): array
    {
        try {
            $this->validateBrowseMode('Neuro state requires authentication');

            $directives = [];
            if ($this->tableExists('neuro_directives')) {
                $res = $this->conn->query("SELECT id, directive_type, weight, confidence, decay_factor, created_at FROM neuro_directives ORDER BY created_at DESC LIMIT 50");
                while ($row = $res->fetch_assoc()) {
                    $directives[] = $row;
                }
            }

            $vector = [];
            if ($this->tableExists('neuro_state_vector')) { // optional future table
                $res = $this->conn->query("SELECT metric, value, updated_at FROM neuro_state_vector ORDER BY updated_at DESC LIMIT 100");
                while ($row = $res->fetch_assoc()) {
                    $vector[$row['metric']] = [ 'value' => (float)$row['value'], 'updated_at' => $row['updated_at'] ];
                }
            }

            $freshnessSeconds = null;
            if ($directives) {
                $freshnessSeconds = time() - strtotime($directives[0]['created_at']);
            }

            return $this->successResponse([
                'directives' => $directives,
                'state_vector' => $vector,
                'freshness_seconds' => $freshnessSeconds,
                'degraded' => $freshnessSeconds !== null && $freshnessSeconds > 21600 // 6h threshold
            ], 'Neuro state loaded');
        } catch (\Throwable $e) {
            $this->logger->error('Neuro state fetch error', ['error'=>$e->getMessage()]);
            return $this->errorResponse('Failed to load neuro state', 500);
        }
    }

    /**
     * GET /api/dashboard/assistant/insights
     * Query params: user_id, limit, unread_only
     */
    public function assistantInsights(): array
    {
        try {
            $this->validateBrowseMode('Assistant insights require authentication');
            $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
            $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 25;
            $unreadOnly = isset($_GET['unread_only']) && (int)$_GET['unread_only'] === 1;

            if (!$this->tableExists('assistant_insights')) {
                return $this->successResponse(['insights'=>[],'unavailable'=>true], 'Assistant insights not provisioned');
            }

            $where = [];
            if ($userId) { $where[] = 'user_id = '. (int)$userId; }
            if ($unreadOnly && $this->columnExists('assistant_insights','is_read')) { $where[] = 'is_read = 0'; }
            $sql = 'SELECT insight_id, user_id, category, priority, title, summary, payload_json, created_at'.($this->columnExists('assistant_insights','is_read')?', is_read':'').' FROM assistant_insights';
            if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
            $sql .= ' ORDER BY created_at DESC LIMIT ' . $limit;

            $res = $this->conn->query($sql);
            $insights = [];
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $row['payload'] = $row['payload_json'] ? json_decode($row['payload_json'], true) : null;
                    unset($row['payload_json']);
                    $insights[] = $row;
                }
            }

            return $this->successResponse(['insights'=>$insights,'count'=>count($insights)], 'Assistant insights loaded');
        } catch (\Throwable $e) {
            $this->logger->error('Assistant insights error', ['error'=>$e->getMessage()]);
            return $this->errorResponse('Failed to load assistant insights', 500);
        }
    }

    /**
     * GET /api/dashboard/behavior/heatmap
     * Returns aggregate event counts by event_type + hour (last 24h)
     */
    public function behaviorHeatmap(): array
    {
        try {
            $this->validateBrowseMode('Behavior analytics require authentication');
            if (!$this->tableExists('interaction_events')) {
                return $this->successResponse(['buckets'=>[],'unavailable'=>true], 'No interaction_events table');
            }
            $sql = "SELECT event_type, DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as bucket_hour, COUNT(*) as cnt FROM interaction_events WHERE created_at >= NOW() - INTERVAL 24 HOUR GROUP BY event_type, bucket_hour ORDER BY bucket_hour ASC";
            $res = $this->conn->query($sql);
            $buckets = [];
            while ($res && $row = $res->fetch_assoc()) { $buckets[] = $row; }
            return $this->successResponse(['buckets'=>$buckets], 'Behavior heatmap compiled');
        } catch (\Throwable $e) {
            $this->logger->error('Behavior heatmap error', ['error'=>$e->getMessage()]);
            return $this->errorResponse('Failed to load behavior heatmap', 500);
        }
    }

    /**
     * GET /api/dashboard/agent/activity
     * Recent agent runs with basic performance stats
     */
    public function agentActivity(): array
    {
        try {
            $this->validateBrowseMode('Agent activity requires authentication');
            if (!$this->tableExists('agent_runs')) {
                return $this->successResponse(['runs'=>[],'unavailable'=>true], 'No agent_runs table');
            }
            $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 30;
            $sql = "SELECT run_id, agent_name, status, duration_ms, started_at, finished_at, directives_snapshot FROM agent_runs ORDER BY started_at DESC LIMIT $limit";
            $res = $this->conn->query($sql);
            $runs = [];
            while ($res && $row = $res->fetch_assoc()) {
                $row['directives'] = $row['directives_snapshot'] ? json_decode($row['directives_snapshot'], true) : null;
                unset($row['directives_snapshot']);
                $runs[] = $row;
            }
            return $this->successResponse(['runs'=>$runs,'count'=>count($runs)], 'Agent activity loaded');
        } catch (\Throwable $e) {
            $this->logger->error('Agent activity error', ['error'=>$e->getMessage()]);
            return $this->errorResponse('Failed to load agent activity', 500);
        }
    }

    /**
     * GET /api/dashboard/customer/segments
     * Segment signals from existing feedback & issues tables (lightweight heuristic)
     */
    public function customerSegments(): array
    {
        try {
            $this->validateBrowseMode('Customer segments require authentication');
            $segments = [];

            $haveFeedback = $this->tableExists('customer_feedback');
            $haveIssues = $this->tableExists('product_issues');

            if ($haveFeedback) {
                $sql = "SELECT sentiment, COUNT(*) as cnt FROM customer_feedback WHERE created_at >= NOW() - INTERVAL 30 DAY GROUP BY sentiment";
                $res = $this->conn->query($sql);
                $sentiment = [];
                while ($res && $row = $res->fetch_assoc()) { $sentiment[$row['sentiment']] = (int)$row['cnt']; }
                $segments['feedback_sentiment'] = $sentiment;
            }
            if ($haveIssues) {
                $sql = "SELECT severity, COUNT(*) as cnt FROM product_issues WHERE created_at >= NOW() - INTERVAL 30 DAY GROUP BY severity";
                $res = $this->conn->query($sql);
                $sev = [];
                while ($res && $row = $res->fetch_assoc()) { $sev[$row['severity']] = (int)$row['cnt']; }
                $segments['issue_severity'] = $sev;
            }
            if ($haveFeedback && $this->columnExists('customer_feedback','product_id')) {
                $sql = "SELECT product_id, COUNT(*) as feedback_count FROM customer_feedback WHERE created_at >= NOW() - INTERVAL 30 DAY GROUP BY product_id ORDER BY feedback_count DESC LIMIT 10";
                $res = $this->conn->query($sql);
                $topProducts = [];
                while ($res && $row = $res->fetch_assoc()) { $topProducts[] = $row; }
                $segments['top_feedback_products'] = $topProducts;
            }

            return $this->successResponse(['segments'=>$segments], 'Customer segments compiled');
        } catch (\Throwable $e) {
            $this->logger->error('Customer segments error', ['error'=>$e->getMessage()]);
            return $this->errorResponse('Failed to load customer segments', 500);
        }
    }

    /**
     * GET /api/dashboard/transfer/suggestions
     * Intelligent stock redistribution proposals (no DB writes)
     */
    public function transferSuggestions(): array
    {
        try {
            $this->validateBrowseMode('Transfer suggestions require authentication');
            if (!$this->tableExists('outlet_inventory')) {
                return $this->successResponse(['suggestions'=>[],'unavailable'=>true], 'Inventory table not present');
            }

            // 1. Identify deficits
            $deficits = [];
            $deficitSql = "SELECT oi.product_id, oi.outlet_id, oi.current_stock, oi.min_stock_level FROM outlet_inventory oi JOIN outlets o ON o.outlet_id=oi.outlet_id WHERE oi.min_stock_level > 0 AND oi.current_stock < oi.min_stock_level AND o.is_active=1 LIMIT 250";
            $res = $this->conn->query($deficitSql);
            while ($res && $row = $res->fetch_assoc()) {
                $row['need'] = (int)$row['min_stock_level'] - (int)$row['current_stock'];
                $deficits[] = $row;
            }
            if (!$deficits) {
                return $this->successResponse(['suggestions'=>[],'message'=>'No current deficits'], 'No transfer suggestions necessary');
            }

            // Group deficits by product
            $byProduct = [];
            foreach ($deficits as $d) { $byProduct[$d['product_id']][] = $d; }

            // 2. Fetch surplus outlets for impacted products
            $productIds = array_keys($byProduct);
            $idList = implode("','", array_map(fn($p)=>$this->conn->real_escape_string($p), $productIds));
            $surplusMap = [];
            $surplusSql = "SELECT oi.product_id, oi.outlet_id, oi.current_stock, oi.min_stock_level FROM outlet_inventory oi JOIN outlets o ON o.outlet_id=oi.outlet_id WHERE oi.product_id IN ('$idList') AND oi.min_stock_level > 0 AND oi.current_stock > (oi.min_stock_level * 1.4) AND o.is_active=1";
            $sr = $this->conn->query($surplusSql);
            while ($sr && $row = $sr->fetch_assoc()) {
                $surplusMap[$row['product_id']][] = $row;
            }

            // 3. Build suggestions greedily
            $suggestions = [];
            foreach ($byProduct as $productId => $needs) {
                if (empty($surplusMap[$productId])) { continue; }
                // Sort needs (largest first) and surplus (largest surplus first)
                usort($needs, fn($a,$b)=> $b['need'] <=> $a['need']);
                $surplus = $surplusMap[$productId];
                foreach ($surplus as &$s) {
                    $s['excess'] = (int)$s['current_stock'] - (int)max($s['min_stock_level'], floor($s['min_stock_level'] * 1.15));
                }
                usort($surplus, fn($a,$b)=> $b['excess'] <=> $a['excess']);

                foreach ($needs as $needRow) {
                    $remainingNeed = $needRow['need'];
                    foreach ($surplus as &$sup) {
                        if ($remainingNeed <= 0) { break; }
                        if ($sup['excess'] <= 0) { continue; }
                        $move = min($remainingNeed, $sup['excess']);
                        if ($move <= 0) { continue; }
                        $suggestions[] = [
                            'product_id' => $productId,
                            'from_outlet' => $sup['outlet_id'],
                            'to_outlet' => $needRow['outlet_id'],
                            'quantity' => $move,
                            'need_remaining_post' => $remainingNeed - $move,
                            'surplus_remaining_post' => $sup['excess'] - $move,
                            'severity' => $needRow['current_stock'] == 0 ? 'critical' : ($needRow['current_stock'] < ($needRow['min_stock_level'] * 0.5) ? 'high' : 'moderate'),
                            'score' => $move * (1 + ($needRow['need']/max(1,$needRow['min_stock_level']))),
                            'reason' => 'Destination below minimum; source above buffer'
                        ];
                        $remainingNeed -= $move;
                        $sup['excess'] -= $move;
                    }
                }
            }

            // Score & enrich product metadata if products table exists
            usort($suggestions, fn($a,$b)=> $b['score'] <=> $a['score']);
            $suggestions = array_slice($suggestions, 0, 100);
            if ($this->tableExists('products')) {
                $meta = $this->fetchProductMeta(array_unique(array_map(fn($s)=>$s['product_id'],$suggestions)));
                foreach ($suggestions as &$s) { if (isset($meta[$s['product_id']])) { $s['product'] = $meta[$s['product_id']]; } }
            }

            return $this->successResponse([
                'generated_at' => date('c'),
                'suggestion_count' => count($suggestions),
                'suggestions' => $suggestions
            ], 'Transfer suggestions generated');
        } catch (\Throwable $e) {
            $this->logger->error('Transfer suggestions error', ['error'=>$e->getMessage()]);
            return $this->errorResponse('Failed to compute transfer suggestions', 500);
        }
    }

    /**
     * GET /api/dashboard/crawler/summary
     * Summarize recent crawl activity & top competitive signals
     */
    public function crawlerSummary(): array
    {
        try {
            $this->validateBrowseMode('Crawler summary requires authentication');
            if (!$this->tableExists('competitor_crawl_logs')) {
                return $this->successResponse(['summary'=>[],'unavailable'=>true], 'No crawl logs');
            }
            $summary = [ 'window_hours'=>24 ];
            $baseWindow = "NOW() - INTERVAL 24 HOUR";
            $aggSql = "SELECT COUNT(*) as crawl_count, SUM(products_found) as products_found, COUNT(DISTINCT competitor_name) as competitors, MAX(created_at) as last_run FROM competitor_crawl_logs WHERE created_at >= $baseWindow";
            $r = $this->conn->query($aggSql); $summary['aggregate'] = $r? $r->fetch_assoc():[];
            $detailSql = "SELECT competitor_name, products_found, duration_ms, status, created_at FROM competitor_crawl_logs WHERE created_at >= $baseWindow ORDER BY created_at DESC LIMIT 20";
            $details=[]; $dr = $this->conn->query($detailSql); while ($dr && $row=$dr->fetch_assoc()) { $details[]=$row; }
            $summary['recent'] = $details;
            if ($this->tableExists('competitive_analysis')) {
                $threatSql = "SELECT our_product_id, competitor_id, competitor_price, our_price, price_difference_percent, threat_level FROM competitive_analysis WHERE analyzed_at >= $baseWindow ORDER BY ABS(price_difference_percent) DESC LIMIT 10";
                $threats=[]; $tr=$this->conn->query($threatSql); while($tr && $row=$tr->fetch_assoc()){ $threats[]=$row; }
                $summary['top_threats']=$threats;
            }
            return $this->successResponse(['summary'=>$summary], 'Crawler summary compiled');
        } catch (\Throwable $e) {
            $this->logger->error('Crawler summary error', ['error'=>$e->getMessage()]);
            return $this->errorResponse('Failed to load crawler summary', 500);
        }
    }

    /**
     * GET /api/dashboard/kpis
     * High-level operational KPIs (transfer, directives, inventory risk, engagement approximations)
     */
    public function kpis(): array
    {
        try {
            $this->validateBrowseMode('KPIs require authentication');
            $kpis = [ 'generated_at'=>date('c') ];
            // Transfer volume past 7d
            if ($this->tableExists('transfer_logs')) {
                $r = $this->conn->query("SELECT COUNT(*) as transfers, SUM(quantity) as units FROM transfer_logs WHERE created_at >= NOW() - INTERVAL 7 DAY");
                if ($r) { $kpis['transfers_7d'] = $r->fetch_assoc(); }
            }
            // Directive freshness
            if ($this->tableExists('neuro_directives')) {
                $r=$this->conn->query("SELECT MAX(created_at) as last FROM neuro_directives");
                if ($r) { $row=$r->fetch_assoc(); $kpis['directive_freshness_seconds'] = $row['last']? (time()-strtotime($row['last'])):null; }
            }
            // Low stock count
            if ($this->tableExists('outlet_inventory')) {
                $r=$this->conn->query("SELECT COUNT(*) as low FROM outlet_inventory WHERE min_stock_level>0 AND current_stock < min_stock_level");
                if ($r) { $kpis['low_stock_items'] = (int)$r->fetch_assoc()['low']; }
            }
            // Crawl recency
            if ($this->tableExists('competitor_crawl_logs')) {
                $r=$this->conn->query("SELECT MAX(created_at) as last FROM competitor_crawl_logs");
                if ($r) { $row=$r->fetch_assoc(); $kpis['last_crawl_seconds'] = $row['last']? (time()-strtotime($row['last'])):null; }
            }
            // Agent success rate (if agent_runs has status)
            if ($this->tableExists('agent_runs')) {
                $r=$this->conn->query("SELECT SUM(status='success') as ok, COUNT(*) as total FROM agent_runs WHERE started_at >= NOW() - INTERVAL 7 DAY");
                if ($r) { $row=$r->fetch_assoc(); $kpis['agent_success_rate'] = ($row['total'] ?? 0) ? round(($row['ok']/$row['total'])*100,2):null; }
            }
            // Insight acceptance ratio (last 24h)
            if ($this->tableExists('assistant_insights_feedback')) {
                $r=$this->conn->query("SELECT SUM(reaction='up') as up_votes, SUM(reaction='down') as down_votes, COUNT(*) as total FROM assistant_insights_feedback WHERE created_at >= NOW() - INTERVAL 24 HOUR");
                if ($r) { $row=$r->fetch_assoc();
                    $accRate = ($row['total'] ?? 0) ? round(($row['up_votes'] / max(1,$row['total']))*100,2):null;
                    $kpis['insight_acceptance_rate'] = $accRate;
                    if ($accRate !== null) {
                        $kpis['directive_weight_adjustment_hint'] = $accRate < 40 ? 'consider_decay' : ($accRate > 75 ? 'consider_amplify' : 'stable');
                    }
                }
            }
            return $this->successResponse(['kpis'=>$kpis], 'KPIs compiled');
        } catch (\Throwable $e) {
            $this->logger->error('KPIs error', ['error'=>$e->getMessage()]);
            return $this->errorResponse('Failed to load KPIs', 500);
        }
    }

    // ---- Private helpers ---------------------------------------------------
    private function fetchProductMeta(array $productIds): array
    {
        if (!$productIds) { return []; }
        $escaped = array_map(fn($p)=>"'".$this->conn->real_escape_string($p)."'", $productIds);
        $sql = "SELECT product_id, name, brand FROM products WHERE product_id IN (".implode(',',$escaped).")";
        $res = $this->conn->query($sql); $meta=[]; while($res && $row=$res->fetch_assoc()){ $meta[$row['product_id']]=$row; }
        return $meta;
    }

    // Utility helpers (schema introspection) ---------------------------------
    private function tableExists(string $table): bool
    {
        $res = $this->conn->query("SHOW TABLES LIKE '".$this->conn->real_escape_string($table)."'");
        return $res && $res->num_rows > 0;
    }
    private function columnExists(string $table, string $column): bool
    {
        if (!$this->tableExists($table)) { return false; }
        $res = $this->conn->query("SHOW COLUMNS FROM `".$this->conn->real_escape_string($table)."` LIKE '".$this->conn->real_escape_string($column)."'");
        return $res && $res->num_rows > 0;
    }
}
