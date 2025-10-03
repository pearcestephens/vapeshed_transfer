<?php
declare(strict_types=1);

namespace VapeshedTransfer\App\Services\Vision;

use VapeshedTransfer\App\Services\LLM\MultiProviderRouter;
use VapeshedTransfer\App\Repositories\ProductCandidateImageRepository;
use VapeshedTransfer\Core\Logger;

/**
 * VisionAnalysisService
 * Sends candidate images + textual context to multi-provider router (OpenAI/Anthropic) and extracts structured attributes.
 */
class VisionAnalysisService
{
    public function __construct(
        private MultiProviderRouter $router,
        private ProductCandidateImageRepository $imageRepo,
        private Logger $logger
    ) {}

    /**
     * @param array{candidate_id:string, title?:?string, brand?:?string, variant?:?string, nicotine_mg?:?float, volume_ml?:?float} $context
     * @param array<int,array{image_id:string, image_base64:string}> $images
     */
    public function analyze(array $context, array $images, array $options=[]): array
    {
        if (!$images) { return ['error'=>'no_images']; }
        $messages = [
            ['role'=>'system','content'=>'You are an assistant extracting product attributes from vape product images. Return clear short text.'],
            ['role'=>'user','content'=>$this->buildPrompt($context)]
        ];
        $b64Images = array_map(fn($i)=>$i['image_base64'],$images);
        try {
            $resp = $this->router->vision($messages, $b64Images, $options);
            $out = [
                'raw_completion'=>$resp['completion'] ?? '',
                'labels'=>$resp['labels'] ?? [],
                'extracted'=>$resp['extracted'] ?? [],
                'provider'=>$resp['provider'] ?? null,
                'model'=>$resp['model'] ?? null,
                'routing'=>$resp['routing'] ?? null
            ];
            // (Optional) attach labels to first image record if available
            if (!empty($out['labels']) && isset($images[0]['image_id'])) {
                $this->attachLabels($images[0]['image_id'], $out['labels']);
            }
            return $out;
        } catch (\Throwable $e) {
            $this->logger->warning('Vision analysis failed',['error'=>$e->getMessage()]);
            return ['error'=>'vision_fail','message'=>$e->getMessage()];
        }
    }

    private function buildPrompt(array $ctx): string
    {
        $parts=['Extract attributes if visible: brand, nicotine (mg), volume (ml), coil ohm, flavour keywords.'];
        if (!empty($ctx['title'])) { $parts[] = 'Candidate title: '.$ctx['title']; }
        if (!empty($ctx['brand'])) { $parts[] = 'Detected textual brand: '.$ctx['brand']; }
        if (!empty($ctx['variant'])) { $parts[] = 'Variant text: '.$ctx['variant']; }
        if (isset($ctx['nicotine_mg'])) { $parts[] = 'Parsed nicotine: '.$ctx['nicotine_mg'].' mg'; }
        if (isset($ctx['volume_ml'])) { $parts[] = 'Parsed volume: '.$ctx['volume_ml'].' ml'; }
        $parts[]='Return concise summary. Prefix hashtags for key flavour notes (#berry, #mint, etc).';
        return implode("\n", $parts);
    }

    private function attachLabels(string $imageId, array $labels): void
    {
        $json = json_encode($labels, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $mysqli = (function($repo){ return (new \ReflectionClass($repo))->getProperty('db')->getValue($repo); })($this->imageRepo);
        if(!$mysqli) { return; }
        $stmt = $mysqli->prepare("UPDATE product_candidate_images SET vision_labels=? WHERE image_id=?");
        if($stmt){ $stmt->bind_param('ss',$json,$imageId); $stmt->execute(); $stmt->close(); }
    }
}
