<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\Logger;
use App\Services\Assistants\AssistantConversationService;

/**
 * AssistantController
 * Conversation + insight API without external model dependency.
 * Endpoints:
 *  POST /api/assistant/message { user_id, message, conversation_id? }
 *  POST /api/assistant/insight  { user_id, category, title, summary, payload? }
 *  GET  /api/assistant/conversations?user_id= & limit=
 *  GET  /api/assistant/messages?conversation_id= & limit=
 *  POST /api/assistant/insight/feedback { insight_id, user_id, reaction, reason? }
 */
class AssistantController extends BaseController
{
    private AssistantConversationService $service;
    private \mysqli $conn;

    public function __construct()
    {
        parent::__construct();
        $this->conn = Database::getInstance()->getConnection();
        $this->service = new AssistantConversationService(Database::getInstance(), new Logger('assistant_conversation'));
    }

    public function postMessage(): array
    {
        try {
            $this->validateBrowseMode('Assistant messaging requires authentication');
            $this->validateCsrfToken();
            $input = $this->getJsonInput();
            $userId = (int)($input['user_id'] ?? 0);
            $message = trim($input['message'] ?? '');
            if ($userId <=0 || $message==='') { return $this->errorResponse('user_id and message required',422); }
            $assistantRow = $this->service->ensureAssistant($userId);
            if (!$assistantRow) { return $this->errorResponse('Assistant tables not provisioned', 501); }
            $conversationId = $input['conversation_id'] ?? null;
            if (!$conversationId) { $conversationId = $this->service->createConversation($assistantRow['assistant_id'],$userId); }
            $this->service->addMessage($conversationId,$userId,'user',$message);
            // Build context & reply
            $context = $this->service->getRecentContext($conversationId, 12);
            $reply = $this->service->generateAssistantReply($assistantRow,$context,$message);
            $assistantMsgId = $this->service->addMessage($conversationId,$userId,'assistant',$reply,['heuristic'=>true]);
            return $this->successResponse([
                'conversation_id'=>$conversationId,
                'assistant_message_id'=>$assistantMsgId,
                'reply'=>$reply,
                'context' => $context
            ], 'Assistant reply generated');
        } catch (\Throwable $e) {
            $this->logger->error('Assistant message error', ['error'=>$e->getMessage()]);
            return $this->errorResponse('Failed to process message',500);
        }
    }

    public function postInsight(): array
    {
        try {
            $this->validateBrowseMode('Assistant insight creation requires authentication');
            $this->validateCsrfToken();
            $input=$this->getJsonInput();
            $userId=(int)($input['user_id'] ?? 0);
            $category=$input['category'] ?? 'general';
            $title=$input['title'] ?? 'Untitled';
            $summary=$input['summary'] ?? '';
            $payload=$input['payload'] ?? [];
            if ($userId<=0 || !$summary) { return $this->errorResponse('user_id and summary required',422); }
            $id=$this->service->createInsight($userId,$category,$title,$summary,$payload,'normal');
            if (!$id) { return $this->errorResponse('Insights table not available',501); }
            return $this->successResponse(['insight_id'=>$id],'Insight recorded');
        } catch (\Throwable $e) {
            $this->logger->error('Assistant insight error', ['error'=>$e->getMessage()]);
            return $this->errorResponse('Failed to create insight',500);
        }
    }

    public function listConversations(): array
    {
        try {
            $this->validateBrowseMode('Assistant conversations require authentication');
            $userId = (int)($_GET['user_id'] ?? 0);
            $limit = min(50, (int)($_GET['limit'] ?? 20));
            if ($userId<=0) { return $this->errorResponse('user_id required',422); }
            if (!$this->tableExists('assistant_conversations')) { return $this->successResponse(['conversations'=>[],'unavailable'=>true],'No conversations table'); }
            $stmt=$this->conn->prepare("SELECT conversation_id, created_at FROM assistant_conversations WHERE user_id=? ORDER BY created_at DESC LIMIT ?");
            $stmt->bind_param('ii',$userId,$limit); $stmt->execute();
            $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            return $this->successResponse(['conversations'=>$rows],'Conversations listed');
        } catch (\Throwable $e) {
            $this->logger->error('Assistant conversation list error', ['error'=>$e->getMessage()]);
            return $this->errorResponse('Failed to list conversations',500);
        }
    }

    public function listMessages(): array
    {
        try {
            $this->validateBrowseMode('Assistant messages require authentication');
            $conversationId = $_GET['conversation_id'] ?? '';
            $limit = min(100, (int)($_GET['limit'] ?? 40));
            if ($conversationId==='') { return $this->errorResponse('conversation_id required',422); }
            if (!$this->tableExists('assistant_messages')) { return $this->successResponse(['messages'=>[],'unavailable'=>true],'No messages table'); }
            $stmt=$this->conn->prepare("SELECT role, content, created_at FROM assistant_messages WHERE conversation_id=? ORDER BY created_at DESC LIMIT ?");
            $stmt->bind_param('si',$conversationId,$limit); $stmt->execute();
            $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            return $this->successResponse(['messages'=>array_reverse($rows)],'Messages listed');
        } catch (\Throwable $e) {
            $this->logger->error('Assistant messages list error', ['error'=>$e->getMessage()]);
            return $this->errorResponse('Failed to list messages',500);
        }
    }

    public function postInsightFeedback(): array
    {
        try {
            $this->validateBrowseMode('Assistant feedback requires authentication');
            $this->validateCsrfToken();
            $input=$this->getJsonInput();
            $insightId=$input['insight_id'] ?? '';
            $userId=(int)($input['user_id'] ?? 0);
            $reaction=strtolower($input['reaction'] ?? '');
            $reason= $input['reason'] ?? null;
            if ($insightId==='' || $userId<=0 || !in_array($reaction,['up','down'],true)) {
                return $this->errorResponse('insight_id, user_id, reaction required',422);
            }
            // Direct insert (avoid new repository injection complexity here)
            $conn=$this->conn;
            $fid='FDBK_'.substr(md5($insightId.$userId.$reaction.microtime(true)),0,16);
            if (!$this->tableExists('assistant_insights_feedback')) {
                return $this->errorResponse('Feedback table not available',501);
            }
            $stmt=$conn->prepare("INSERT INTO assistant_insights_feedback (feedback_id, insight_id, user_id, reaction, reason) VALUES (?,?,?,?,?)");
            $stmt->bind_param('ssiss',$fid,$insightId,$userId,$reaction,$reason);
            $stmt->execute();
            return $this->successResponse(['feedback_id'=>$fid],'Feedback recorded');
        } catch (\Throwable $e) {
            $this->logger->error('Assistant feedback error',[ 'error'=>$e->getMessage() ]);
            return $this->errorResponse('Failed to record feedback',500);
        }
    }

    private function tableExists(string $table): bool
    {
        $res=$this->conn->query("SHOW TABLES LIKE '".$this->conn->real_escape_string($table)."'");
        return $res && $res->num_rows>0;
    }
}
