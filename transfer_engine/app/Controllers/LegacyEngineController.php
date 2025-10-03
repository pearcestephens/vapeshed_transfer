<?php
declare(strict_types=1);

namespace App\Controllers;

/**
 * LegacyEngineController
 * Bridges MVC routes to the legacy engine entrypoint while preserving inputs/outputs 1:1.
 */
class LegacyEngineController extends BaseController
{
    /**
     * GET proxy: 307 redirect to the public legacy engine with original query string.
     */
    public function redirect(): void
    {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    $base = (string)(defined('APP_BASE_URL') ? APP_BASE_URL : (defined('APP_URL') ? APP_URL : ''));
    $base = rtrim($base, '/');
    $path = (strpos($base, '/public') !== false) ? '' : '/public';
    $target = $base . $path . '/engine.php' . ($qs !== '' ? ('?' . $qs) : '');
        // 307 preserves method semantics if client retries; for GET it's equivalent to 302
        header('Location: ' . $target, true, 307);
        exit;
    }

    /**
     * POST proxy: server-side forward to the legacy engine and stream back the response.
     * Preserves content-type and raw body, returns legacy response as-is.
     */
    public function forward(): void
    {
    $base = (string)(defined('APP_BASE_URL') ? APP_BASE_URL : (defined('APP_URL') ? APP_URL : ''));
    $base = rtrim($base, '/');
    $path = (strpos($base, '/public') !== false) ? '' : '/public';
    $url = $base . $path . '/engine.php';
        $body = file_get_contents('php://input') ?: '';
        $ct = $_SERVER['CONTENT_TYPE'] ?? 'application/x-www-form-urlencoded';
        // Release session lock during long-running proxy
        if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
        $ch = curl_init($url . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? ('?' . $_SERVER['QUERY_STRING']) : ''));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: ' . $ct]);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $timeout = defined('MAX_EXECUTION_TIME') ? (int)MAX_EXECUTION_TIME : 300;
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        $resp = curl_exec($ch);
        if ($resp === false) {
            http_response_code(502);
            echo 'Legacy engine proxy error: ' . curl_error($ch);
            curl_close($ch);
            return;
        }
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE) ?: 200;
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE) ?: 0;
        $headers = substr($resp, 0, $headerSize);
        $content = substr($resp, $headerSize);
        curl_close($ch);
        // Pass through status and content-type if present
        http_response_code($status);
        $lines = preg_split('/\r?\n/', (string)$headers) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || stripos($line, 'transfer-encoding:') === 0 || stripos($line, 'content-length:') === 0) continue;
            if (stripos($line, 'content-type:') === 0) header($line);
        }
        echo $content;
    }
}
