<?php
declare(strict_types=1);

namespace VapeshedTransfer\App\Services\Browser;

use VapeshedTransfer\Core\Logger;

/**
 * AuthSessionManager
 * Manages legitimate authenticated sessions (login form POST) with per-domain cookie jars.
 * Only for accessing content a normal logged-in user is permitted to see.
 */
class AuthSessionManager
{
    private string $cookieFile;
    private Logger $logger;

    public function __construct(Logger $logger, ?string $cookieFile = null)
    {
        $this->logger = $logger;
        $this->cookieFile = $cookieFile ?? sys_get_temp_dir().'/crawler_session_'.bin2hex(random_bytes(6)).'.cookie';
    }

    public function getCookieFile(): string
    {
        return $this->cookieFile;
    }

    /**
     * Perform a login request.
     * @param string $url Login endpoint URL (HTTPS strongly recommended)
     * @param string $method HTTP method (POST/GET)
     * @param array $fields Form fields (key=>value)
     * @param array $headers Optional extra headers
     */
    public function login(string $url, string $method = 'POST', array $fields = [], array $headers = []): bool
    {
        if (!$url) { throw new \InvalidArgumentException('Login URL required'); }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 25);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $normalizedHeaders = [];
        foreach ($headers as $h) { if (is_string($h)) { $normalizedHeaders[] = $h; } }
        if (!array_filter($normalizedHeaders, fn($h)=>str_starts_with(strtolower($h),'user-agent:'))) {
            $normalizedHeaders[] = 'User-Agent: Mozilla/5.0';
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $normalizedHeaders);
        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        }
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($err) {
            $this->logger->warning('Auth login curl error', ['error'=>$err]);
            return false;
        }
        $ok = ($status >=200 && $status < 400);
        $this->logger->info('Auth login attempt', ['url'=>$url,'status'=>$status,'success'=>$ok]);
        return $ok;
    }
}
