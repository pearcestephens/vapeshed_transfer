<?php
declare(strict_types=1);

namespace App\Services\LLM;

/**
 * StubLLMProvider
 * Deterministic provider producing safe placeholder output until real LLM integrated.
 */
class StubLLMProvider implements ProviderInterface
{
    public function generate(array $messages, array $options = []): array
    {
        $lastUser = 'Unknown request';
        for ($i=count($messages)-1; $i>=0; $i--) {
            if (($messages[$i]['role'] ?? '') === 'user') { $lastUser = $messages[$i]['content']; break; }
        }
        $reply = '[ASSISTANT] Analyzing: '.substr($lastUser,0,180).'. Core focus maintained on operations efficiency, inventory balance, and competitive posture.';
        return [
            'reply' => $reply,
            'usage' => [
                'prompt_tokens' => array_sum(array_map(fn($m)=> str_word_count($m['content']??''), $messages)),
                'completion_tokens' => str_word_count($reply),
                'total_tokens' => array_sum(array_map(fn($m)=> str_word_count($m['content']??''), $messages)) + str_word_count($reply)
            ],
            'raw' => null
        ];
    }
}
