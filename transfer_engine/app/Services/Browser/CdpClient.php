<?php
declare(strict_types=1);

namespace VapeshedTransfer\App\Services\Browser;

use RuntimeException;

/**
 * Minimal Chrome DevTools Protocol client (scaffold)
 * NOTE: This is a lightweight placeholder. Full implementation would include:
 *  - WebSocket handshake
 *  - Incremental message id mgmt
 *  - navigate, DOM.getDocument, Runtime.evaluate, Page.captureScreenshot
 */
class CdpClient
{
    private ?string $endpoint;
    private $socket = null; // resource
    private int $msgId = 0;

    public function __construct(?string $endpoint)
    {
        $this->endpoint = $endpoint;
    }

    public function isEnabled(): bool
    {
        return (bool)$this->endpoint;
    }

    public function connect(): void
    {
        if (!$this->endpoint) { throw new RuntimeException('No endpoint'); }
        if ($this->socket) { return; }
        $parts = parse_url($this->endpoint);
        if (!$parts || !isset($parts['host'],$parts['port'])) { throw new RuntimeException('Invalid endpoint'); }
        $errNo=0; $errStr='';
        $sock = @fsockopen($parts['host'], (int)$parts['port'], $errNo, $errStr, 3.0);
        if (!$sock) { throw new RuntimeException('CDP connect failed: '.$errStr); }
        stream_set_timeout($sock, 3);
        $this->socket = $sock;
        // Skipping actual WS upgrade handshake for brevity (would send GET /devtools/page/... HTTP/1.1 etc.)
    }

    /** Placeholder send (no real WS framing). */
    private function send(array $payload): void
    {
        if (!$this->socket) { throw new RuntimeException('Not connected'); }
        $payload['id'] = ++$this->msgId;
        // Real impl: encode as JSON frame with proper WebSocket framing.
    }

    public function navigate(string $url): void
    {
        if (!$this->endpoint) { return; }
        $this->connect();
        $this->send(['method'=>'Page.navigate','params'=>['url'=>$url]]);
        usleep(500000); // crude wait
    }

    public function captureScreenshot(): ?string
    {
        if (!$this->endpoint) { return null; }
        // Real impl would request Page.captureScreenshot and parse result.
        return null; // Defer to fallback for now.
    }

    public function evaluate(string $expression): ?array
    {
        if (!$this->endpoint) { return null; }
        // Real impl would call Runtime.evaluate and parse result.
        return null;
    }
}
