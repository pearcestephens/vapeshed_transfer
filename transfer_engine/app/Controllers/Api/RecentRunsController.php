<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;

class RecentRunsController extends BaseController
{
    public function list(): void
    {
        header('Content-Type: application/json');
        $runs = [];
        try {
            if (defined('DB_CONFIGURED') && DB_CONFIGURED) {
                $db = \App\Core\Database::getInstance()->getConnection();
                $res = $db->query("SELECT run_id, status, created_at, preset FROM transfer_executions ORDER BY created_at DESC LIMIT 5");
                while ($row = $res->fetch_assoc()) {
                    $runs[] = [
                        'id' => $row['run_id'],
                        'status' => $row['status'] ?? 'success',
                        'timestamp' => $row['created_at'] ?? date('c'),
                        'preset' => $row['preset'] ?? null
                    ];
                }
            }
        } catch (\Throwable $e) {
            // return empty list on error
        }
        echo json_encode([ 'success' => true, 'data' => $runs ]);
    }
}
