#!/usr/bin/env php
<?php
declare(strict_types=1);
/** test_working_sites.php - Quick test of confirmed working sites
 * Tests sites that responded successfully
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

$logger = new Logger('quick_test');
$http = new HttpClient($logger);

$bnClass = 'Unified\\Matching\\BrandNormalizer';
$teClass = 'Unified\\Matching\\TokenExtractor';
$fmClass = 'Unified\\Matching\\FuzzyMatcher';

$bn = new $bnClass($logger);
$te = new $teClass($logger);
$fm = new $fmClass($logger);

// Confirmed working sites + product pages
$tests = [
    ['url' => 'https://www.vapeshed.co.nz', 'type' => 'homepage'],
    ['url' => 'https://www.vapemate.co.nz', 'type' => 'homepage'],
    ['url' => 'https://www.vapo.co.nz', 'type' => 'homepage'],
    ['url' => 'https://www.vapemate.co.nz/products/fury-edge', 'type' => 'product'],
    ['url' => 'https://www.vapo.co.nz/collections/vape-starter-kits', 'type' => 'category'],
];

echo "=== Quick Scrape Test (Working Sites) ===\n\n";

$products = [];

foreach ($tests as $test) {
    echo "Testing: {$test['url']} ({$test['type']})\n";
    $result = $http->fetch($test['url']);
    
    if ($result['success']) {
        $productName = $http->extractProductName($result['html']);
        if (!$productName && preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $result['html'], $m)) {
            $productName = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        
        if ($productName) {
            $tokens = $te->extract($productName);
            $brand = $bn->normalize($tokens[0] ?? '');
            
            $products[] = [
                'url' => $test['url'],
                'type' => $test['type'],
                'name' => $productName,
                'tokens' => $tokens,
                'brand' => $brand
            ];
            
            echo "  ✓ Product: $productName\n";
            echo "    Tokens: ".implode(', ', array_slice($tokens, 0, 10))."\n";
            echo "    Brand: $brand\n\n";
        } else {
            echo "  ⚠ No product extracted\n\n";
        }
    } else {
        echo "  ✗ Failed: {$result['error']}\n\n";
    }
    
    sleep(1);
}

// Similarity analysis
if (count($products) >= 2) {
    echo "\n=== Cross-Site Similarity Analysis ===\n";
    for ($i = 0; $i < count($products); $i++) {
        for ($j = $i + 1; $j < count($products); $j++) {
            $simScore = $fm->similarity($products[$i]['tokens'], $products[$j]['tokens']);
            echo sprintf(
                "%s (%s)\nvs\n%s (%s)\nSimilarity: %.4f\n\n",
                substr($products[$i]['name'], 0, 60),
                parse_url($products[$i]['url'], PHP_URL_HOST),
                substr($products[$j]['name'], 0, 60),
                parse_url($products[$j]['url'], PHP_URL_HOST),
                $simScore
            );
        }
    }
}

echo "=== Summary ===\n";
echo "Products extracted: ".count($products)."\n";
echo "Ready for matching pipeline integration\n";
