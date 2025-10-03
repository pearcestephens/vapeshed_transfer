<?php
declare(strict_types=1);
namespace Unified\Crawler;
use Unified\Support\Logger;
/** HttpClient - Robust HTTP client with browser-like headers
 * Fetches URLs with Chrome user-agent to avoid blocking.
 */
final class HttpClient
{
    private string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    
    public function __construct(private Logger $logger, private int $timeout = 15) {}

    /**
     * Fetch URL content with browser-like headers.
     * @return array { success:bool, html:?string, status:?int, error:?string }
     */
    public function fetch(string $url): array
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
                'Accept-Encoding: gzip, deflate',
                'DNT: 1',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1'
            ],
            CURLOPT_ENCODING => '', // Handle gzip/deflate automatically
        ]);

        $html = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($html === false || $status !== 200) {
            $this->logger->warn('http.fetch_failed', ['url'=>$url, 'status'=>$status, 'error'=>$error]);
            return ['success' => false, 'html' => null, 'status' => $status, 'error' => $error];
        }

        $this->logger->info('http.fetch_ok', ['url'=>$url, 'status'=>$status, 'bytes'=>strlen($html)]);
        return ['success' => true, 'html' => $html, 'status' => $status, 'error' => null];
    }

    /**
     * Extract title from HTML (basic <title> tag parsing).
     */
    public function extractTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $title = html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return trim($title);
        }
        return null;
    }

    /**
     * Extract product name from common e-commerce meta tags or structured data.
     */
    public function extractProductName(string $html): ?string
    {
        // Try og:title meta tag
        if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']/is', $html, $m)) {
            return trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        // Try product name meta
        if (preg_match('/<meta\s+name=["\']product[_-]?name["\']\s+content=["\'](.*?)["\']/is', $html, $m)) {
            return trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        // Try h1 product title
        if (preg_match('/<h1[^>]*class=["\'].*?product.*?["\']\s+[^>]*>(.*?)<\/h1>/is', $html, $m)) {
            return trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        // Fallback to title tag
        return $this->extractTitle($html);
    }
}
