#!/usr/bin/env php
<?php
declare(strict_types=1);
/** test_url_scrape.php - Test HTTP client + product extraction from real URLs
 * Usage: php bin/test_url_scrape.php "https://vapeshed.co.nz/product/example"
 */
require_once __DIR__.'/_cli_bootstrap.php';

$supportDir = __DIR__.'/../src/Support';
foreach(['Config','Logger'] as $cls){
    $path = $supportDir.'/'.$cls.'.php';
    if(is_file($path) && !class_exists("Unified\\Support\\$cls")) require_once $path;
}
$crawlerDir = __DIR__.'/../src/Crawler';
require_once $crawlerDir.'/HttpClient.php';
require_once $crawlerDir.'/ProductScraper.php';

use Unified\Support\Logger; use Unified\Crawler\HttpClient;

$url = $argv[1] ?? 'https://www.vapeshed.co.nz';
$logger = new Logger('test_scrape');
$http = new HttpClient($logger);

echo "=== URL Scraper Test ===\n";
echo "URL: $url\n\n";

$result = $http->fetch($url);

if (!$result['success']) {
    echo "❌ Fetch failed: {$result['error']}\n";
    echo "   Status: {$result['status']}\n";
    exit(1);
}

echo "✓ Fetched successfully\n";
echo "  Status: {$result['status']}\n";
echo "  Size: ".number_format(strlen($result['html']))." bytes\n\n";

$title = $http->extractTitle($result['html']);
$productName = $http->extractProductName($result['html']);

echo "Page Title: ".($title ?: '(not found)')."\n";
echo "Product Name: ".($productName ?: '(not found)')."\n";

// Token extraction demo
if ($productName) {
    $matchingDir = __DIR__.'/../src/Matching';
    require_once $matchingDir.'/BrandNormalizer.php';
    require_once $matchingDir.'/TokenExtractor.php';
    
    $bnClass = 'Unified\\Matching\\BrandNormalizer';
    $teClass = 'Unified\\Matching\\TokenExtractor';
    
    $bn = new $bnClass($logger);
    $te = new $teClass($logger);
    
    $tokens = $te->extract($productName);
    echo "\nTokens: ".implode(', ', $tokens)."\n";
    
    // Extract potential brand (first token)
    if (isset($tokens[0])) {
        $brand = $bn->normalize($tokens[0]);
        echo "Normalized Brand: $brand\n";
    }
}

echo "\n=== Test Complete ===\n";
