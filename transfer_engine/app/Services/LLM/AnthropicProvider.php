<?php
declare(strict_types=1);

namespace VapeshedTransfer\App\Services\LLM;

use RuntimeException;

class AnthropicProvider implements LlmProviderInterface
{
    private string $apiKey; private string $baseUrl;
    public function __construct(string $apiKey, string $baseUrl='https://api.anthropic.com/v1') { $this->apiKey=$apiKey; $this->baseUrl=rtrim($baseUrl,'/'); }
    public function name(): string { return 'anthropic'; }

    public function complete(array $messages, array $options = []): array
    {
        $model = $options['model'] ?? 'claude-3-5-sonnet-latest';
        $mapped = $this->mapMessages($messages);
        $payload = [
            'model'=>$model,
            'max_tokens'=> $options['max_tokens'] ?? 800,
            'temperature'=>$options['temperature'] ?? 0.2,
            'messages'=>$mapped
        ];
        $resp = $this->request('/messages', $payload);
        $contentBlock = $resp['content'][0]['text'] ?? '';
        return [ 'provider'=>'anthropic','model'=>$model,'completion'=>$contentBlock,'raw'=>$resp,'usage'=>$resp['usage'] ?? [] ];
    }

    public function vision(array $messages, array $images, array $options = []): array
    {
        $model = $options['model'] ?? 'claude-3-5-sonnet-latest';
        $mapped = $this->mapMessages($messages);
        // Append vision message: Anthropic expects content array with text+image blocks
        $imageBlocks=[]; foreach($images as $b64){ $imageBlocks[]=[ 'type'=>'image', 'source'=>['type'=>'base64','media_type'=>'image/png','data'=>$b64]]; }
        $mapped[] = [ 'role'=>'user', 'content'=>$imageBlocks ];
        $payload=[
            'model'=>$model,
            'max_tokens'=>$options['max_tokens'] ?? 800,
            'temperature'=>$options['temperature'] ?? 0.2,
            'messages'=>$mapped
        ];
        $resp=$this->request('/messages',$payload);
        $text=$resp['content'][0]['text'] ?? '';
        return [
            'provider'=>'anthropic','model'=>$model,'completion'=>$text,
            'labels'=>$this->extractLabels($text),'extracted'=>$this->structuredExtraction($text),
            'raw'=>$resp,'usage'=>$resp['usage'] ?? []
        ];
    }

    private function request(string $path, array $payload): array
    {
        $ch=curl_init($this->baseUrl.$path);
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_POST=>true,
            CURLOPT_HTTPHEADER=>[
                'x-api-key: '.$this->apiKey,
                'anthropic-version: 2023-06-01',
                'content-type: application/json'
            ],
            CURLOPT_POSTFIELDS=>json_encode($payload),
            CURLOPT_TIMEOUT=>55
        ]);
        $body=curl_exec($ch); $err=curl_error($ch); $code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); curl_close($ch);
        if($err || $code>=400){ throw new RuntimeException('Anthropic request failed code='.$code.' err='.$err.' body='.$body); }
        $json=json_decode((string)$body,true); if(!is_array($json)){ throw new RuntimeException('Invalid Anthropic JSON'); }
        return $json;
    }

    private function mapMessages(array $messages): array
    {
        $out=[]; foreach($messages as $m){ $out[]=['role'=>$m['role'],'content'=>$m['content']]; } return $out;
    }

    private function extractLabels(string $text): array
    { preg_match_all('/#([A-Za-z0-9_\-]{2,32})/',$text,$m); return array_values(array_unique($m[1]??[])); }

    private function structuredExtraction(string $text): array
    {
        $out=[]; if(preg_match('/brand\s*[:=-]\s*([A-Za-z0-9 \-]+)/i',$text,$m)){ $out['brand']=trim($m[1]); }
        if(preg_match('/(\d{1,3})\s?mg/i',$text,$m)){ $out['nicotine_mg']=(float)$m[1]; }
        if(preg_match('/(\d{1,3})\s?ml/i',$text,$m)){ $out['volume_ml']=(float)$m[1]; }
        if(preg_match('/(0\.[0-9]{2,3}|[0-9]\.[0-9]{2})\s?(ohm|Ω)/i',$text,$m)){ $out['coil_ohm']=(float)$m[1]; }
        return $out;
    }
}
