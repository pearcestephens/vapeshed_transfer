#!/usr/bin/env php
<?php
declare(strict_types=1);
/** test_smaller_competitors.php - Test scraping smaller NZ vape retailers
 * Usage: php bin/test_smaller_competitors.php
 * Focuses on less-protected sites with simpler HTML structures
 */
require_once __DIR__.'/_cli_bootstrap.php';

$supportDir = __DIR__.'/../src/Support';
foreach(['Config','Logger'] as $cls){
    $path = $supportDir.'/'.$cls.'.php';
    if(is_file($path) && !class_exists("Unified\\Support\\$cls")) require_once $path;
}
$crawlerDir = __DIR__.'/../src/Crawler';
require_once $crawlerDir.'/HttpClient.php';
$matchingDir = __DIR__.'/../src/Matching';
foreach(['BrandNormalizer','TokenExtractor','FuzzyMatcher'] as $mc){
    require_once $matchingDir.'/'.$mc.'.php';
}

use Unified\Support\Logger; use Unified\Crawler\HttpClient;

$logger = new Logger('test_competitors');
$http = new HttpClient($logger);

$bnClass = 'Unified\\Matching\\BrandNormalizer';
$teClass = 'Unified\\Matching\\TokenExtractor';
$fmClass = 'Unified\\Matching\\FuzzyMatcher';

$bn = new $bnClass($logger);
$te = new $teClass($logger);
$fm = new $fmClass($logger);

// Smaller NZ vape retailers (less likely to have aggressive anti-bot)
$testUrls = [
    'https://www.vapemate.co.nz',
    'https://www.vapo.co.nz',
    'https://www.altervape.co.nz',
    'https://www.ecigsnz.co.nz',
    'https://www.vaporlicious.co.nz',
    'https://www.cosmicvapez.co.nz',
    'https://www.vapekings.co.nz',
    'https://www.vapestore.co.nz'
];

echo "=== Smaller Competitor Scraping Test ===\n\n";

$products = [];
$successful = 0;
$failed = 0;

foreach ($testUrls as $url) {
    echo "Fetching: $url\n";
    $result = $http->fetch($url);
    
    if ($result['success']) {
        $successful++;
        $title = $http->extractTitle($result['html']);
        $productName = $http->extractProductName($result['html']);
        
        // Try to extract any h1 tags as fallback
        if (!$productName && preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $result['html'], $m)) {
            $productName = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        
        $displayName = $productName ?: $title ?: '(no title found)';
        
        if ($productName || $title) {
            $tokens = $te->extract($displayName);
            $brand = $bn->normalize($tokens[0] ?? '');
            
            $products[] = [
                'url' => $url,
                'name' => $displayName,
                'tokens' => $tokens,
                'brand' => $brand,
                'bytes' => strlen($result['html'])
            ];
            
            echo "  ✓ ".strlen($result['html'])." bytes\n";
            echo "    Title: $displayName\n";
            echo "    Tokens: ".implode(', ', array_slice($tokens, 0, 8))."\n";
            echo "    Brand: $brand\n\n";
        } else {
            echo "  ⚠ Fetched but no title/product found\n\n";
        }
    } else {
        $failed++;
        echo "  ✗ Fetch failed: {$result['error']}\n\n";
    }
    
    sleep(2); // Respectful rate limit
}

// Compute similarity matrix for "vape" mentions
$vapeProducts = array_filter($products, fn($p) => stripos($p['name'], 'vape') !== false);

if (count($vapeProducts) >= 2) {
    echo "\n=== Similarity Matrix (Vape-related) ===\n";
    $vapeArray = array_values($vapeProducts);
    for ($i = 0; $i < count($vapeArray); $i++) {
        for ($j = $i + 1; $j < count($vapeArray); $j++) {
            $simScore = $fm->similarity($vapeArray[$i]['tokens'], $vapeArray[$j]['tokens']);
            echo sprintf(
                "%s\n  vs %s\n  Similarity: %.4f\n\n",
                substr($vapeArray[$i]['name'], 0, 50),
                substr($vapeArray[$j]['name'], 0, 50),
                $simScore
            );
        }
    }
}

echo "\n=== Test Complete ===\n";
echo "Successful: $successful / ".count($testUrls)."\n";
echo "Failed: $failed\n";
echo "Products extracted: ".count($products)."\n";
