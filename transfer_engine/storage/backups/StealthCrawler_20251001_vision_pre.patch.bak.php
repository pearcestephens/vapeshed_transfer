<?php
// Backup of StealthCrawler prior to vision & real-time enhancements (2025-10-01)

declare(strict_types=1);

namespace VapeshedTransfer\AI\ProductAcquisition\Crawlers;

use VapeshedTransfer\Core\Logger;

/**
 * StealthCrawler (Backup Original)
 */
class StealthCrawlerBackupOriginal
{
    private Logger $logger;
    private array $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15',
        'Mozilla/5.0 (X11; Linux x86_64) Gecko/20100101 Firefox/122.0',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'
    ];
    private array $proxies = [];
    private array $lastResponseMeta = [];
    private array $lastHostRequestAt = [];
    private array $hostMinDelayMs = [];
    private ?\mysqli $db = null;
    private array $robotsCache = [];
    private int $robotsTtlSeconds = 1800;
    public function __construct(Logger $logger, array $proxies = [], ?\mysqli $db = null){ $this->logger=$logger; $this->proxies=$proxies; $this->db=$db; }
}
