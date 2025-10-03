<?php
declare(strict_types=1);

namespace App\Services;

/**
 * LegacyEngineBridge
 * Allows calling the legacy engine script from MVC code and getting JSON back without altering legacy script.
 */
class LegacyEngineBridge
{
    /**
     * Resolve a full absolute base URL for the app.
     * Falls back to production host if APP_BASE_URL is not absolute.
     */
    private function baseUrl(): string
    {
        $base = defined('APP_BASE_URL') ? (string)APP_BASE_URL : '';
        // If APP_BASE_URL isn't absolute, fall back to APP_URL (both configured in bootstrap.php)
        if (!is_string($base) || $base === '' || !preg_match('/^https?:\\/\\//i', $base)) {
            $base = defined('APP_URL') ? (string)APP_URL : '';
        }
        return rtrim($base, '/');
    }

    /**
     * Get execution timeout aligned with system-wide MAX_EXECUTION_TIME (seconds).
     */
    private function getTimeout(): int
    {
        $t = defined('MAX_EXECUTION_TIME') ? (int)MAX_EXECUTION_TIME : 600;
        return $t > 0 ? $t : 600;
    }

    /**
     * Build full engine URL with query parameters.
     */
    private function buildUrl(array $params = [], string $format = 'json'): string
    {
        $qs = http_build_query(array_merge($params, ['format' => $format]));
    // Engine entrypoint is under the app's public router. Base URL in this project points to app root; append /public when missing
    $base = $this->baseUrl();
    $path = (strpos($base, '/public') !== false) ? '' : '/public';
    return $base . $path . '/engine.php' . ($qs ? ('?' . $qs) : '');
    }

    /**
     * Execute legacy engine and return raw output string.
     * $params are translated into a query string appended to the legacy script.
     */
    public function runRaw(array $params = [], string $format = 'json'): string
    {
        $url = $this->buildUrl($params, $format);
        $timeout = $this->getTimeout();

        // Prefer cURL for robust control; fallback to streams if unavailable
        if (function_exists('curl_init')) {
            $ch = curl_init();
            $headers = [
                'Accept: ' . ($format === 'json' ? 'application/json' : 'text/plain'),
                'User-Agent: VapeshedTransfer/1.0 (+https://staff.vapeshed.co.nz)'
            ];
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => $headers,
            ]);

            $resp = curl_exec($ch);
            if ($resp === false) {
                $err = curl_error($ch);
                $code = curl_errno($ch);
                curl_close($ch);
                throw new \RuntimeException('Legacy engine cURL error: ' . $err . ' (code ' . $code . ') URL: ' . $url);
            }

            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $body = substr((string)$resp, $headerSize);
            curl_close($ch);

            if ($status < 200 || $status >= 300) {
                $snippet = $this->snippet($body);
                throw new \RuntimeException('Legacy engine HTTP ' . $status . ' for ' . $url . ' body: ' . $snippet);
            }

            return (string)$body;
        }

        // Fallback to file_get_contents
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeout,
                'header' => implode("\r\n", [
                    'Accept: ' . ($format === 'json' ? 'application/json' : 'text/plain'),
                    'User-Agent: VapeshedTransfer/1.0 (+https://staff.vapeshed.co.nz)'
                ]),
                'ignore_errors' => true, // capture body for non-200s
            ],
        ]);

        $out = @file_get_contents($url, false, $ctx);
        if ($out === false) {
            throw new \RuntimeException('Legacy engine unreachable at ' . $url);
        }
        // Attempt to parse HTTP status from $http_response_header if present
        $status = 200;
        global $http_response_header;
        if (is_array($http_response_header) && isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int)$m[1];
        }
        if ($status < 200 || $status >= 300) {
            $snippet = $this->snippet((string)$out);
            throw new \RuntimeException('Legacy engine HTTP ' . $status . ' for ' . $url . ' body: ' . $snippet);
        }
        return (string)$out;
    }

    /**
     * Execute legacy engine and decode JSON payload.
     */
    public function runJson(array $params = []): array
    {
        $raw = $this->runRaw($params, 'json');
        try {
            /** @var array<string,mixed> $decoded */
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new \RuntimeException('Decoded JSON is not an object/array');
            }
            return $decoded;
        } catch (\JsonException $e) {
            $snippet = $this->snippet($raw);
            throw new \RuntimeException('Legacy engine returned invalid JSON: ' . $e->getMessage() . ' body: ' . $snippet);
        }
    }

    /**
     * Return a safe body snippet for error messages.
     */
    private function snippet(string $body, int $max = 400): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $body) ?? '');
        if (mb_strlen($clean) > $max) {
            return mb_substr($clean, 0, $max) . '…';
        }
        return $clean;
    }
}
