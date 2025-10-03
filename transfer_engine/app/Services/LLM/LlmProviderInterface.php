<?php
declare(strict_types=1);

namespace VapeshedTransfer\App\Services\LLM;

/**
 * LlmProviderInterface
 * Unified contract for textual + vision completion providers.
 * Implementations MUST return associative arrays with at minimum keys:
 *  - provider (string)
 *  - model (string)
 *  - completion (string) for text
 *  - usage (array{prompt_tokens?:int,completion_tokens?:int,total_tokens?:int})
 * Vision calls MUST additionally return:
 *  - labels (array<string>) optional
 *  - extracted (array) optional structured fields
 */
interface LlmProviderInterface
{
    /** @param array<int,array{role:string,content:string}> $messages */
    public function complete(array $messages, array $options = []): array;

    /**
     * Vision multimodal completion.
     * @param array<int,array{role:string,content:string}> $messages textual context messages
     * @param array<int,string> $images array of base64-encoded image binaries (no data URI prefix)
     */
    public function vision(array $messages, array $images, array $options = []): array;

    public function name(): string;
}
