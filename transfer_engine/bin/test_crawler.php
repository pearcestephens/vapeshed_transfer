#!/usr/bin/env php
<?php
declare(strict_types=1);
/** test_crawler.php - Test product scraper with real Google search
 * Usage: php bin/test_crawler.php "vape pods NZ"
 */
require_once __DIR__.'/_cli_bootstrap.php';

$supportDir = __DIR__.'/../src/Support';
foreach(['Config','Logger'] as $cls){
    $path = $supportDir.'/'.$cls.'.php';
    if(is_file($path) && !class_exists("Unified\\Support\\$cls")) require_once $path;
}
$crawlerDir = __DIR__.'/../src/Crawler';
require_once $crawlerDir.'/ProductScraper.php';

use Unified\Support\Logger; use Unified\Crawler\ProductScraper;

$query = $argv[1] ?? 'vape pod system NZ';
$logger = new Logger('test_crawler');
$scraper = new ProductScraper($logger, 15);

echo "=== Product Scraper Test ===\n";
echo "Query: $query\n\n";

$products = $scraper->search($query);

echo "Found ".count($products)." products:\n";
foreach ($products as $i => $p) {
    $parsed = $scraper->parseProduct($p);
    echo sprintf("%2d. %s\n", $i+1, $p);
    echo "    Brand: {$parsed['brand']} | Model: {$parsed['model']}\n";
}

echo "\n=== Test Complete ===\n";
