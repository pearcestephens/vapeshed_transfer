<?php
declare(strict_types=1);

namespace App\Services\Assistants;

use App\Core\Database;
use App\Core\Logger;

/**
 * AssistantConversationService
 * Builds contextual frames and persists assistant conversations & insights.
 * Reuses existing tables if present: staff_assistants, assistant_conversations, assistant_messages, assistant_insights.
 * No LLM call implemented here (placeholder hook) to avoid external dependency.
 */
class AssistantConversationService
{
    public function __construct(private Database $db, private Logger $logger) {}

    public function ensureAssistant(int $userId, string $persona='operations'): ?array
    {
        if (!$this->tableExists('staff_assistants')) { return null; }
        $stmt = $this->db->getConnection()->prepare("SELECT * FROM staff_assistants WHERE user_id=? AND active=1 ORDER BY created_at ASC LIMIT 1");
        $stmt->bind_param('i',$userId); $stmt->execute(); $res=$stmt->get_result();
        $row=$res->fetch_assoc();
        if ($row) { return $row; }
        // create basic assistant
        $assistantId = 'AS_'.substr(md5($userId.$persona.microtime(true)),0,16);
        $ins = $this->db->getConnection()->prepare("INSERT INTO staff_assistants (assistant_id,user_id,persona,system_prompt,temperature,top_p,active,created_at) VALUES (?,?,?,?,0.2,0.9,1,NOW())");
        $prompt = 'You are a focused internal assistant for operations efficiency.';
        $ins->bind_param('siss',$assistantId,$userId,$persona,$prompt); $ins->execute();
        return $this->ensureAssistant($userId,$persona);
    }

    public function createConversation(string $assistantId, int $userId): ?string
    {
        if (!$this->tableExists('assistant_conversations')) { return null; }
        $id = 'CONV_'.substr(md5($assistantId.microtime(true)),0,16);
        $stmt = $this->db->getConnection()->prepare("INSERT INTO assistant_conversations (conversation_id,assistant_id,user_id,created_at) VALUES (?,?,?,NOW())");
        $stmt->bind_param('ssi',$id,$assistantId,$userId); $stmt->execute();
        return $id;
    }

    public function addMessage(string $conversationId, int $userId, string $role, string $content, array $meta=[]): ?string
    {
        if (!$this->tableExists('assistant_messages')) { return null; }
        $id = 'MSG_'.substr(md5($conversationId.$role.microtime(true)),0,16);
        $stmt = $this->db->getConnection()->prepare("INSERT INTO assistant_messages (message_id,conversation_id,user_id,role,content,tokens,meta_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())");
        $tokens = str_word_count($content) + 5; // naive token approximation
        $metaJson = json_encode($meta);
        $stmt->bind_param('ssissis',$id,$conversationId,$userId,$role,$content,$tokens,$metaJson); $stmt->execute();
        return $id;
    }

    public function getRecentContext(string $conversationId, int $limit=12): array
    {
        if (!$this->tableExists('assistant_messages')) { return []; }
        $stmt=$this->db->getConnection()->prepare("SELECT role, content FROM assistant_messages WHERE conversation_id=? ORDER BY created_at DESC LIMIT ?");
        $stmt->bind_param('si',$conversationId,$limit); $stmt->execute();
        $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        return array_reverse($rows);
    }

    public function generateAssistantReply(array $assistantRow, array $context, string $userMessage): string
    {
        // Attempt provider integration if configured
        $providerName = $_ENV['LLM_PROVIDER'] ?? ''; // e.g., 'stub'
        $useProvider = $providerName !== '';
        if ($useProvider) {
            try {
                $provider = $this->resolveProvider($providerName);
                $messages = [
                    ['role'=>'system','content'=>$assistantRow['system_prompt'] ?? 'Internal operations assistant.'],
                    ...array_map(fn($m)=>['role'=>$m['role'],'content'=>$m['content']], $context),
                    ['role'=>'user','content'=>$userMessage]
                ];
                $result = $provider->generate($messages, [
                    'temperature' => (float)($assistantRow['temperature'] ?? 0.2),
                    'top_p' => (float)($assistantRow['top_p'] ?? 0.9)
                ]);
                if (!empty($result['reply'])) {
                    return $result['reply'];
                }
            } catch (\Throwable $e) {
                $this->logger->error('LLM provider failure', ['error'=>$e->getMessage(),'provider'=>$providerName]);
            }
        }
        // Heuristic fallback
        $lastUserFocus = $this->inferFocusTopic($userMessage);
        $reply = "Understood. Focusing on {$lastUserFocus}. I will surface transfer risks, low stock, and crawl anomalies. Ask for 'transfers', 'crawler', or 'insights' to drill deeper.";
        if (stripos($userMessage,'transfer') !== false) {
            $reply .= " Current priority: balancing inventory to prevent stockouts while minimizing overstock holding cost.";
        }
        return $reply;
    }

    private function resolveProvider(string $name)
    {
        // Currently only 'stub' supported; can extend mapping
        return match(strtolower($name)) {
            'stub' => new \App\Services\LLM\StubLLMProvider(),
            default => throw new \RuntimeException('Unknown provider: ' . $name)
        };
    }

    public function createInsight(int $userId, string $category, string $title, string $summary, array $payload=[], string $priority='normal'): ?string
    {
        if (!$this->tableExists('assistant_insights')) { return null; }
        $id='INS_'.substr(md5($userId.$category.microtime(true)),0,16);
        $stmt=$this->db->getConnection()->prepare("INSERT INTO assistant_insights (insight_id,user_id,category,priority,title,summary,payload_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())");
        $payloadJson=json_encode($payload);
        $stmt->bind_param('sisssss',$id,$userId,$category,$priority,$title,$summary,$payloadJson); $stmt->execute();
        return $id;
    }

    private function inferFocusTopic(string $text): string
    {
        $keywords = [ 'transfer'=>'Transfers', 'crawl'=>'Crawler', 'price'=>'Pricing', 'stock'=>'Stock Levels', 'velocity'=>'Velocity', 'issue'=>'Issues' ];
        foreach ($keywords as $k=>$v) { if (stripos($text,$k)!==false) return $v; }
        return 'Operations';
    }

    private function tableExists(string $table): bool
    {
        $conn=$this->db->getConnection();
        $res=$conn->query("SHOW TABLES LIKE '".$conn->real_escape_string($table)."'");
        return $res && $res->num_rows>0;
    }
}
