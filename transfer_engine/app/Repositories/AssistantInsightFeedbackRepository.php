<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Logger;

class AssistantInsightFeedbackRepository
{
    public function __construct(private Database $db, private Logger $logger) {}

    public function add(string $insightId, int $userId, string $reaction, ?string $reason=null): ?string
    {
        if (!in_array($reaction,['up','down'],true)) { return null; }
        $id='FDBK_'.substr(md5($insightId.$userId.$reaction.microtime(true)),0,16);
        try {
            $this->db->execute(
                'INSERT INTO assistant_insights_feedback (feedback_id, insight_id, user_id, reaction, reason) VALUES (:fid,:iid,:uid,:react,:reason)',
                [
                    'fid'=>$id,
                    'iid'=>$insightId,
                    'uid'=>$userId,
                    'react'=>$reaction,
                    'reason'=>$reason
                ]
            );
            return $id;
        } catch (\Throwable $e) {
            $this->logger->error('Feedback insert failed',[ 'error'=>$e->getMessage() ]);
            return null;
        }
    }

    public function acceptanceRatio(int $hours=24): ?array
    {
        try {
            $sql='SELECT SUM(reaction="up") as up_votes, SUM(reaction="down") as down_votes, COUNT(*) as total FROM assistant_insights_feedback WHERE created_at >= NOW() - INTERVAL :hrs HOUR';
            // PDO expected; adapt param style if using custom wrapper
            $rows=$this->db->query($sql,['hrs'=>$hours]);
            return $rows[0] ?? null;
        } catch (\Throwable $e) {
            $this->logger->error('Acceptance ratio query failed',[ 'error'=>$e->getMessage() ]);
            return null;
        }
    }
}
