<?php
declare(strict_types=1);
namespace Unified\Crawler;
use Unified\Support\Logger;
/** ProductScraper - Fetches product names from Google search results (basic HTML parsing)
 * Phase M19+: Real product discovery from competitor sites.
 * Safety: respects rate limits, user-agent rotation, caching.
 */
final class ProductScraper
{
    private array $cache = [];
    private ?HttpClient $http = null;
    
    public function __construct(private Logger $logger, private int $maxResults = 10) {
        $this->http = new HttpClient($logger);
    }

    /**
     * Search Google for vape products and extract product names from snippets.
     * @param string $query e.g., "vape pods NZ"
     * @return array<int,string> Product name candidates
     */
    public function search(string $query): array
    {
        $cacheKey = md5($query);
        if (isset($this->cache[$cacheKey])) {
            $this->logger->info('crawler.cache_hit',['query'=>$query]);
            return $this->cache[$cacheKey];
        }

        $url = 'https://www.google.com/search?' . http_build_query([
            'q' => $query,
            'num' => $this->maxResults,
            'hl' => 'en'
        ]);

        $result = $this->http->fetch($url);
        if (!$result['success']) {
            $this->logger->warn('crawler.fetch_failed',['query'=>$query,'url'=>$url]);
            return [];
        }

        $html = $result['html'];
        $this->logger->info('crawler.html_fetched',['query'=>$query,'bytes'=>strlen($html)]);

        // Extract product-like strings from HTML (simple heuristic: text between <h3> tags and snippets)
        $products = [];
        
        // Strategy 1: Match <h3> headings (search result titles)
        if (preg_match_all('/<h3[^>]*>(.*?)<\/h3>/is', $html, $matches)) {
            $this->logger->info('crawler.h3_matches',['count'=>count($matches[1])]);
            foreach ($matches[1] as $title) {
                $clean = strip_tags($title);
                $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $clean = trim($clean);
                if (strlen($clean) > 10 && strlen($clean) < 120) {
                    $products[] = $clean;
                }
            }
        }

        // Strategy 2: Generic text extraction (fallback for testing)
        // Extract any text that looks like a product name (contains vape-related keywords)
        if (count($products) < 3) {
            $this->logger->info('crawler.fallback_extraction',['current_count'=>count($products)]);
            // Extract all text nodes and filter
            $text = strip_tags($html);
            $lines = explode("\n", $text);
            foreach ($lines as $line) {
                $line = trim($line);
                if (strlen($line) > 15 && strlen($line) < 150 && 
                    preg_match('/\b(vape|pod|coil|tank|mod|juice|liquid|kit|starter|disposable)\b/i', $line)) {
                    $products[] = $line;
                    if (count($products) >= $this->maxResults) break;
                }
            }
        }

        // Match snippets (description text) - keep existing logic
        if (count($products) < $this->maxResults && preg_match_all('/<div class="[^"]*VwiC3b[^"]*"[^>]*>(.*?)<\/div>/is', $html, $snippets)) {
            foreach ($snippets[1] as $snip) {
                $clean = strip_tags($snip);
                $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $clean = trim($clean);
                if (strlen($clean) > 15 && strlen($clean) < 200 && preg_match('/\b(vape|pod|coil|tank|mod|juice|liquid)\b/i', $clean)) {
                    $products[] = $clean;
                }
            }
        }

        $products = array_values(array_unique($products));
        $products = array_slice($products, 0, $this->maxResults);

        $this->cache[$cacheKey] = $products;
        $this->logger->info('crawler.search',['query'=>$query,'found'=>count($products)]);
        return $products;
    }

    /**
     * Extract structured product data (brand, model, attributes) from raw string.
     * Placeholder heuristic; future: NER or GPT extraction.
     */
    public function parseProduct(string $raw): array
    {
        // Simple pattern: "Brand Model Details"
        $parts = explode(' ', $raw, 3);
        return [
            'raw' => $raw,
            'brand' => $parts[0] ?? '',
            'model' => $parts[1] ?? '',
            'attributes' => $parts[2] ?? ''
        ];
    }
}
