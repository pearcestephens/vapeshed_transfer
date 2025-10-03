<?php
declare(strict_types=1);

namespace VapeshedTransfer\App\Services\LLM;

use RuntimeException;

class OpenAiProvider implements LlmProviderInterface
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct(string $apiKey, string $baseUrl = 'https://api.openai.com/v1')
    {
        $this->apiKey = $apiKey; $this->baseUrl = rtrim($baseUrl,'/');
    }

    public function name(): string { return 'openai'; }

    public function complete(array $messages, array $options = []): array
    {
        $model = $options['model'] ?? 'gpt-4o-mini';
        $payload = [ 'model'=>$model, 'messages'=>$messages, 'temperature'=>$options['temperature'] ?? 0.2 ];
        $resp = $this->request('/chat/completions', $payload);
        $choice = $resp['choices'][0]['message']['content'] ?? '';
        return [
            'provider'=>'openai',
            'model'=>$model,
            'completion'=>$choice,
            'raw'=>$resp,
            'usage'=>$resp['usage'] ?? []
        ];
    }

    public function vision(array $messages, array $images, array $options = []): array
    {
        $model = $options['model'] ?? 'gpt-4o-mini';
        // Convert images to content parts
        $visionMessages = [];
        foreach ($messages as $m) { $visionMessages[] = $m; }
        // Append a user message containing inline image objects per OpenAI spec
        $imageParts = [];
        foreach ($images as $b64) {
            $imageParts[] = [
                'type' => 'image_url',
                'image_url' => ['url' => 'data:image/png;base64,' . $b64]
            ];
        }
        $visionMessages[] = [ 'role'=>'user', 'content'=>$imageParts ];
        $payload = [ 'model'=>$model, 'messages'=>$visionMessages, 'temperature'=>$options['temperature'] ?? 0.2 ];
        $resp = $this->request('/chat/completions', $payload);
        $choice = $resp['choices'][0]['message']['content'] ?? '';
        return [
            'provider'=>'openai', 'model'=>$model, 'completion'=>$choice,
            'labels'=>$this->extractLabels($choice), 'extracted'=>$this->structuredExtraction($choice),
            'raw'=>$resp, 'usage'=>$resp['usage'] ?? []
        ];
    }

    private function request(string $path, array $payload): array
    {
        $ch=curl_init($this->baseUrl.$path);
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_POST=>true,
            CURLOPT_HTTPHEADER=>[
                'Authorization: Bearer '.$this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS=>json_encode($payload),
            CURLOPT_TIMEOUT=>50
        ]);
        $body=curl_exec($ch); $err=curl_error($ch); $code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); curl_close($ch);
        if($err || $code>=400){ throw new RuntimeException('OpenAI request failed code='.$code.' err='.$err.' body='.$body); }
        $json=json_decode((string)$body,true);
        if(!is_array($json)){ throw new RuntimeException('Invalid OpenAI JSON'); }
        return $json;
    }

    private function extractLabels(string $text): array
    {
        preg_match_all('/#([A-Za-z0-9_\-]{2,32})/', $text, $m);
        return array_values(array_unique($m[1] ?? []));
    }

    private function structuredExtraction(string $text): array
    {
        $out=[]; if(preg_match('/brand\s*[:=-]\s*([A-Za-z0-9 \-]+)/i',$text,$m)){ $out['brand']=trim($m[1]); }
        if(preg_match('/(\d{1,3})\s?mg/i',$text,$m)){ $out['nicotine_mg']=(float)$m[1]; }
        if(preg_match('/(\d{1,3})\s?ml/i',$text,$m)){ $out['volume_ml']=(float)$m[1]; }
        if(preg_match('/(0\.[0-9]{2,3}|[0-9]\.[0-9]{2})\s?(ohm|Ω)/i',$text,$m)){ $out['coil_ohm']=(float)$m[1]; }
        return $out;
    }
}
