<?php
declare(strict_types=1);

namespace App\Services\Crawler;

use App\Core\Logger;
use App\Core\Database;

/**
 * Competitive Intelligence Crawler
 * 
 * Automated competitor price monitoring and data collection
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 */
class CompetitorCrawlerEngine
{
    private Logger $logger;
    private Database $db;
    private array $competitors;
    private array $userAgents;
    private array $proxies;
    private int $requestDelay = 2; // seconds between requests
    
    public function __construct()
    {
        $this->logger = new Logger();
        $this->db = Database::getInstance();
        $this->initializeCompetitors();
        $this->initializeUserAgents();
        $this->initializeProxies();
    }
    
    /**
     * Crawl all configured competitors for current pricing
     */
    public function crawlAllCompetitors(): array
    {
        $startTime = microtime(true);
        
        try {
            $this->logger->info('Starting competitor crawl cycle');
            
            $results = [];
            $totalProducts = 0;
            $successfulCrawls = 0;
            $failedCrawls = 0;
            
            foreach ($this->competitors as $competitor) {
                $this->logger->info('Crawling competitor', ['name' => $competitor['name']]);
                
                $crawlResult = $this->crawlCompetitor($competitor);
                
                $results[$competitor['id']] = $crawlResult;
                $totalProducts += $crawlResult['products_found'];
                
                if ($crawlResult['success']) {
                    $successfulCrawls++;
                } else {
                    $failedCrawls++;
                }
                
                // Respectful delay between competitors
                sleep($this->requestDelay * 2);
            }
            
            $executionTime = microtime(true) - $startTime;
            
            $summary = [
                'crawl_summary' => [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'execution_time' => round($executionTime, 3),
                    'competitors_crawled' => count($this->competitors),
                    'successful_crawls' => $successfulCrawls,
                    'failed_crawls' => $failedCrawls,
                    'total_products_found' => $totalProducts
                ],
                'competitor_results' => $results,
                'price_analysis' => $this->analyzeCrawledPrices($results),
                'market_insights' => $this->generateMarketInsights($results)
            ];
            
            // Store crawl results
            $this->storeCrawlResults($summary);
            
            return $summary;
            
        } catch (\Exception $e) {
            $this->logger->error('Competitor crawl failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'error' => $e->getMessage(),
                'execution_time' => microtime(true) - $startTime
            ];
        }
    }
    
    /**
     * Crawl specific competitor
     */
    private function crawlCompetitor(array $competitor): array
    {
        $startTime = microtime(true);
        
        try {
            $crawlConfig = [
                'base_url' => $competitor['base_url'],
                'product_patterns' => $competitor['product_patterns'],
                'price_selectors' => $competitor['price_selectors'],
                'category_urls' => $competitor['category_urls'] ?? [],
                'anti_detection' => $competitor['anti_detection'] ?? true
            ];
            
            $products = [];
            $totalPages = 0;
            
            // Crawl each category
            foreach ($crawlConfig['category_urls'] as $categoryName => $categoryUrl) {
                $this->logger->debug('Crawling category', [
                    'competitor' => $competitor['name'],
                    'category' => $categoryName,
                    'url' => $categoryUrl
                ]);
                
                $categoryProducts = $this->crawlCategory($categoryUrl, $crawlConfig);
                $products = array_merge($products, $categoryProducts);
                $totalPages++;
                
                // Anti-detection delay
                if ($crawlConfig['anti_detection']) {
                    sleep(rand(2, 5));
                }
            }
            
            // Process and normalize product data
            $normalizedProducts = $this->normalizeProductData($products, $competitor);
            
            // Store competitor prices
            $this->storeCompetitorPrices($normalizedProducts, $competitor['id']);
            
            $executionTime = microtime(true) - $startTime;
            
            return [
                'success' => true,
                'competitor_id' => $competitor['id'],
                'competitor_name' => $competitor['name'],
                'execution_time' => round($executionTime, 3),
                'pages_crawled' => $totalPages,
                'products_found' => count($normalizedProducts),
                'products' => $normalizedProducts,
                'last_crawled' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Competitor crawl failed', [
                'competitor' => $competitor['name'],
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'competitor_id' => $competitor['id'],
                'competitor_name' => $competitor['name'],
                'error' => $e->getMessage(),
                'execution_time' => microtime(true) - $startTime
            ];
        }
    }
    
    /**
     * Crawl specific category page
     */
    private function crawlCategory(string $url, array $config): array
    {
        $products = [];
        
        try {
            // Use cURL with stealth headers
            $html = $this->fetchPageWithStealth($url);
            
            if (!$html) {
                throw new \Exception("Failed to fetch page: $url");
            }
            
            // Parse HTML for products
            $dom = new \DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new \DOMXPath($dom);
            
            // Extract products using configured patterns
            foreach ($config['product_patterns'] as $pattern) {
                $productNodes = $xpath->query($pattern['selector']);
                
                foreach ($productNodes as $node) {
                    $product = $this->extractProductData($node, $config['price_selectors'], $xpath);
                    
                    if ($product && $product['price'] > 0) {
                        $products[] = $product;
                    }
                }
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Category crawl failed', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
        }
        
        return $products;
    }
    
    /**
     * Fetch page with stealth techniques
     */
    private function fetchPageWithStealth(string $url): ?string
    {
        $ch = curl_init();
        
        $userAgent = $this->getRandomUserAgent();
        $proxy = $this->getRandomProxy();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
                'Accept-Encoding: gzip, deflate',
                'DNT: 1',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: none',
                'Cache-Control: max-age=0'
            ],
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        // Use proxy if available
        if ($proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy['address']);
            if (isset($proxy['auth'])) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy['auth']);
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $this->logger->warning('cURL error', ['url' => $url, 'error' => $error]);
            return null;
        }
        
        if ($httpCode !== 200) {
            $this->logger->warning('HTTP error', ['url' => $url, 'code' => $httpCode]);
            return null;
        }
        
        return $response;
    }
    
    /**
     * Extract product data from DOM node
     */
    private function extractProductData(\DOMNode $node, array $priceSelectors, \DOMXPath $xpath): ?array
    {
        $product = [];
        
        try {
            // Extract product name
            $nameNodes = $xpath->query('.//h3 | .//h4 | .//a[@class*="product"] | .//*[@class*="title"]', $node);
            $product['name'] = $nameNodes->length > 0 ? trim($nameNodes->item(0)->textContent) : '';
            
            // Extract price using multiple selectors
            $product['price'] = 0;
            foreach ($priceSelectors as $selector) {
                $priceNodes = $xpath->query($selector, $node);
                if ($priceNodes->length > 0) {
                    $priceText = $priceNodes->item(0)->textContent;
                    $price = $this->parsePrice($priceText);
                    if ($price > 0) {
                        $product['price'] = $price;
                        break;
                    }
                }
            }
            
            // Extract product URL
            $linkNodes = $xpath->query('.//a[@href]', $node);
            $product['url'] = $linkNodes->length > 0 ? $linkNodes->item(0)->getAttribute('href') : '';
            
            // Extract image URL
            $imgNodes = $xpath->query('.//img[@src]', $node);
            $product['image'] = $imgNodes->length > 0 ? $imgNodes->item(0)->getAttribute('src') : '';
            
            // Extract brand/category if available
            $brandNodes = $xpath->query('.//*[@class*="brand"] | .//*[@class*="manufacturer"]', $node);
            $product['brand'] = $brandNodes->length > 0 ? trim($brandNodes->item(0)->textContent) : '';
            
            return empty($product['name']) || $product['price'] <= 0 ? null : $product;
            
        } catch (\Exception $e) {
            $this->logger->debug('Product extraction failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Parse price from text
     */
    private function parsePrice(string $priceText): float
    {
        // Remove non-numeric characters except dots and commas
        $cleanPrice = preg_replace('/[^\d.,]/', '', $priceText);
        
        // Handle different decimal separators
        if (strpos($cleanPrice, ',') !== false && strpos($cleanPrice, '.') !== false) {
            // Both comma and dot present - assume comma is thousands separator
            $cleanPrice = str_replace(',', '', $cleanPrice);
        } elseif (strpos($cleanPrice, ',') !== false) {
            // Only comma - could be decimal separator in some locales
            $cleanPrice = str_replace(',', '.', $cleanPrice);
        }
        
        return (float)$cleanPrice;
    }
    
    /**
     * Normalize product data for comparison
     */
    private function normalizeProductData(array $products, array $competitor): array
    {
        $normalized = [];
        
        foreach ($products as $product) {
            // Try to match with our products
            $ourProductId = $this->findMatchingProduct($product['name'], $product['brand']);
            
            $normalized[] = [
                'competitor_id' => $competitor['id'],
                'competitor_name' => $competitor['name'],
                'competitor_product_name' => $product['name'],
                'our_product_id' => $ourProductId,
                'price' => $product['price'],
                'brand' => $product['brand'],
                'url' => $product['url'],
                'image' => $product['image'],
                'crawled_at' => date('Y-m-d H:i:s'),
                'confidence_score' => $ourProductId ? 0.9 : 0.3 // High confidence if matched
            ];
        }
        
        return $normalized;
    }
    
    /**
     * Find matching product in our database
     */
    private function findMatchingProduct(string $productName, string $brand): ?string
    {
        // Fuzzy matching algorithm
        $sql = "
            SELECT product_id, name, brand,
                   MATCH(name) AGAINST(? IN BOOLEAN MODE) as name_score,
                   MATCH(brand) AGAINST(? IN BOOLEAN MODE) as brand_score
            FROM products 
            WHERE MATCH(name) AGAINST(? IN BOOLEAN MODE) > 0
               OR MATCH(brand) AGAINST(? IN BOOLEAN MODE) > 0
            ORDER BY (name_score + brand_score) DESC
            LIMIT 1
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('ssss', $productName, $brand, $productName, $brand);
        $stmt->execute();
        
        $result = $stmt->get_result()->fetch_assoc();
        
        // Return match if score is high enough
        return ($result && ($result['name_score'] + $result['brand_score']) > 1.0) 
            ? $result['product_id'] 
            : null;
    }
    
    /**
     * Store competitor prices in database
     */
    private function storeCompetitorPrices(array $products, string $competitorId): void
    {
        $sql = "
            INSERT INTO competitor_prices (
                competitor_id,
                competitor_product_name,
                our_product_id,
                price,
                brand,
                url,
                confidence_score,
                crawled_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                price = VALUES(price),
                crawled_at = VALUES(crawled_at),
                confidence_score = VALUES(confidence_score)
        ";
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($products as $product) {
            $stmt->bind_param(
                'sssdssds',
                $competitorId,
                $product['competitor_product_name'],
                $product['our_product_id'],
                $product['price'],
                $product['brand'],
                $product['url'],
                $product['confidence_score'],
                $product['crawled_at']
            );
            
            $stmt->execute();
        }
    }
    
    /**
     * Initialize competitor configurations
     */
    private function initializeCompetitors(): void
    {
        $this->competitors = [
            [
                'id' => 'shosha',
                'name' => 'Shosha',
                'base_url' => 'https://www.shosha.co.nz',
                'category_urls' => [
                    'vape_kits' => 'https://www.shosha.co.nz/vape/vape-kits',
                    'e_liquids' => 'https://www.shosha.co.nz/vape/e-liquids',
                    'accessories' => 'https://www.shosha.co.nz/vape/accessories'
                ],
                'product_patterns' => [
                    ['selector' => '//div[@class*="product-item"]'],
                    ['selector' => '//div[@class*="product-card"]']
                ],
                'price_selectors' => [
                    './/*[@class*="price"]',
                    './/*[@class*="cost"]',
                    './/span[contains(text(), "$")]'
                ],
                'anti_detection' => true
            ],
            [
                'id' => 'cosmic',
                'name' => 'Cosmic',
                'base_url' => 'https://www.cosmic.co.nz',
                'category_urls' => [
                    'devices' => 'https://www.cosmic.co.nz/collections/devices',
                    'e_liquids' => 'https://www.cosmic.co.nz/collections/e-liquids'
                ],
                'product_patterns' => [
                    ['selector' => '//div[@class*="product"]']
                ],
                'price_selectors' => [
                    './/*[@class*="price"]',
                    './/span[contains(@class, "money")]'
                ],
                'anti_detection' => true
            ],
            [
                'id' => 'vapo',
                'name' => 'Vapo',
                'base_url' => 'https://www.vapo.co.nz',
                'category_urls' => [
                    'starter_kits' => 'https://www.vapo.co.nz/starter-kits',
                    'e_liquids' => 'https://www.vapo.co.nz/e-liquids'
                ],
                'product_patterns' => [
                    ['selector' => '//div[@class*="product"]']
                ],
                'price_selectors' => [
                    './/*[@class*="price"]'
                ],
                'anti_detection' => true
            ]
        ];
    }
    
    /**
     * Initialize rotating user agents
     */
    private function initializeUserAgents(): void
    {
        $this->userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/119.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36'
        ];
    }
    
    /**
     * Initialize proxy rotation (if available)
     */
    private function initializeProxies(): void
    {
        $this->proxies = [
            // Add proxy servers if available
            // ['address' => 'proxy1.example.com:8080', 'auth' => 'user:pass'],
        ];
    }
    
    private function getRandomUserAgent(): string
    {
        return $this->userAgents[array_rand($this->userAgents)];
    }
    
    private function getRandomProxy(): ?array
    {
        return empty($this->proxies) ? null : $this->proxies[array_rand($this->proxies)];
    }
}