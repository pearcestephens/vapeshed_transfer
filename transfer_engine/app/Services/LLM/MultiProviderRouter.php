<?php
declare(strict_types=1);

namespace VapeshedTransfer\App\Services\LLM;

use RuntimeException;

/**
 * MultiProviderRouter
 * Routes requests across multiple providers with failover and simple latency/health scoring.
 */
class MultiProviderRouter
{
    /** @var array<string,LlmProviderInterface> */
    private array $providers = [];
    /** @var array<string,array{failures:int,success:int,latency_ms:int|null}> */
    private array $stats = [];

    public function register(LlmProviderInterface $provider): void
    {
        $this->providers[$provider->name()] = $provider;
        $this->stats[$provider->name()] = ['failures'=>0,'success'=>0,'latency_ms'=>null];
    }

    /** @param array<int,array{role:string,content:string}> $messages */
    public function complete(array $messages, array $options = []): array
    {
        $order = $this->selectionOrder($options['preferred'] ?? []);
        $lastEx = null;
        foreach ($order as $name) {
            $p = $this->providers[$name] ?? null; if(!$p) continue;
            $start=microtime(true);
            try {
                $resp = $p->complete($messages, $options);
                $this->recordSuccess($name,(int)round((microtime(true)-$start)*1000));
                $resp['routing'] = ['provider_order'=>$order,'chosen'=>$name];
                return $resp;
            } catch (\Throwable $e) { $this->recordFailure($name); $lastEx=$e; }
        }
        throw new RuntimeException('All LLM providers failed: '.($lastEx? $lastEx->getMessage():'unknown'));
    }

    /** @param array<int,array{role:string,content:string}> $messages */
    public function vision(array $messages, array $images, array $options = []): array
    {
        $order = $this->selectionOrder($options['preferred'] ?? []);
        $lastEx = null;
        foreach ($order as $name) {
            $p = $this->providers[$name] ?? null; if(!$p) continue;
            $start=microtime(true);
            try {
                $resp = $p->vision($messages, $images, $options);
                $this->recordSuccess($name,(int)round((microtime(true)-$start)*1000));
                $resp['routing'] = ['provider_order'=>$order,'chosen'=>$name];
                return $resp;
            } catch (\Throwable $e) { $this->recordFailure($name); $lastEx=$e; }
        }
        throw new RuntimeException('All vision providers failed: '.($lastEx? $lastEx->getMessage():'unknown'));
    }

    public function health(): array
    {
        $out=[]; foreach($this->stats as $name=>$s){
            $total=$s['success']+$s['failures'];
            $sr= $total? round($s['success']/$total,3):null;
            $out[$name]=[ 'success_rate'=>$sr, 'latency_ms'=>$s['latency_ms'] ];
        } return $out;
    }

    private function selectionOrder(array $preferred): array
    {
        $available = array_keys($this->providers);
        // Start with preferred order; append rest based on success rate desc
        $preferred = array_values(array_filter($preferred, fn($p)=>isset($this->providers[$p])));
        $remaining = array_diff($available, $preferred);
        usort($remaining, function($a,$b){
            $sa=$this->stats[$a]; $sb=$this->stats[$b];
            $ra=$this->rate($sa); $rb=$this->rate($sb);
            if ($ra === $rb) { return ($sa['latency_ms'] ?? 999999) <=> ($sb['latency_ms'] ?? 999999); }
            return $rb <=> $ra;
        });
        return array_merge($preferred,$remaining);
    }

    private function rate(array $st): float
    { $tot=$st['success']+$st['failures']; return $tot? ($st['success']/$tot):0.0; }

    private function recordSuccess(string $name, int $latency): void
    { $this->stats[$name]['success']++; $this->stats[$name]['latency_ms']=$latency; }
    private function recordFailure(string $name): void
    { $this->stats[$name]['failures']++; }
}
