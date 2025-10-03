<?php
declare(strict_types=1);

namespace App\Services\LLM;

interface ProviderInterface
{
    /**
     * Generate a model response.
     * @param array $messages List of ['role'=>'user|assistant|system','content'=>string]
     * @param array $options  Model options (temperature, top_p, max_tokens, etc.)
     * @return array { reply:string, usage:{prompt_tokens:int, completion_tokens:int,total_tokens:int}, raw?:mixed }
     */
    public function generate(array $messages, array $options = []): array;
}
