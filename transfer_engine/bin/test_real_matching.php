#!/usr/bin/env php
<?php
declare(strict_types=1);
/** test_real_matching.php - Real product matching across multiple URLs
 * Usage: php bin/test_real_matching.php
 * Tests brand normalization, token extraction, and fuzzy matching with real competitor data
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

$logger = new Logger('test_matching');
$http = new HttpClient($logger);

$bnClass = 'Unified\\Matching\\BrandNormalizer';
$teClass = 'Unified\\Matching\\TokenExtractor';
$fmClass = 'Unified\\Matching\\FuzzyMatcher';

$bn = new $bnClass($logger);
$te = new $teClass($logger);
$fm = new $fmClass($logger);

// Test URLs - mix of competitor sites and product pages
$testUrls = [
    'https://www.vapeshed.co.nz',
    'https://www.nzvapers.com',
    'https://www.shosha.co.nz'
];

echo "=== Real Product Matching Test ===\n\n";

$products = [];
foreach ($testUrls as $url) {
    echo "Fetching: $url\n";
    $result = $http->fetch($url);
    
    if ($result['success']) {
        $productName = $http->extractProductName($result['html']);
        if ($productName) {
            $tokens = $te->extract($productName);
            $brand = $bn->normalize($tokens[0] ?? '');
            
            $products[] = [
                'url' => $url,
                'name' => $productName,
                'tokens' => $tokens,
                'brand' => $brand
            ];
            
            echo "  ✓ Product: $productName\n";
            echo "    Tokens: ".implode(', ', $tokens)."\n";
            echo "    Brand: $brand\n\n";
        } else {
            echo "  ⚠ No product name extracted\n\n";
        }
    } else {
        echo "  ✗ Fetch failed: {$result['error']}\n\n";
    }
    
    sleep(1); // Rate limit
}

// Compute similarity matrix
if (count($products) >= 2) {
    echo "\n=== Similarity Matrix ===\n";
    for ($i = 0; $i < count($products); $i++) {
        for ($j = $i + 1; $j < count($products); $j++) {
            $simScore = $fm->similarity($products[$i]['tokens'], $products[$j]['tokens']);
            echo sprintf(
                "%s vs %s: %.4f\n",
                substr($products[$i]['name'], 0, 40),
                substr($products[$j]['name'], 0, 40),
                $simScore
            );
        }
    }
}

echo "\n=== Test Complete ===\n";
echo "Tested ".count($products)." products\n";
