<?php
declare(strict_types=1);
namespace Unified\Guardrail;
use Unified\Support\Logger;
/** AbstractGuardrail (Phase M3)
 * Base convenience: implements common helpers for concrete guardrails.
 */
abstract class AbstractGuardrail implements GuardrailInterface
{
    protected string $code;
    protected string $description;

    public function __construct(string $code, string $description)
    { $this->code=$code; $this->description=$description; }

    protected function pass(array $meta=[]): array { return $this->result('PASS','', $meta); }
    protected function warn(string $msg, array $meta=[]): array { return $this->result('WARN',$msg,$meta); }
    protected function block(string $msg, array $meta=[]): array { return $this->result('BLOCK',$msg,$meta); }

    private function result(string $status, string $message, array $meta): array
    { return ['code'=>$this->code,'status'=>$status,'message'=>$message,'meta'=>$meta]; }
}
