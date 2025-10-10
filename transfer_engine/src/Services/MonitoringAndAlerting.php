<?php
declare(strict_types=1);

namespace Unified\Services;

use DateInterval;
use DateTimeImmutable;
use PDO;
use Unified\Support\Logger;
use Unified\Support\Pdo;

/**
 * MonitoringAndAlerting
 * Centralised metrics collector for transfer observability.
 */
final class MonitoringAndAlerting
{
    private const CACHE_TTL_SECONDS = 30;

    private array $cache = [];
    private array $cacheExpiry = [];

    public function __construct(
        private readonly PDO $pdo,
        private readonly Logger $logger
    ) {
    }

    public static function withDefaults(Logger $logger): self
    {
        return new self(Pdo::instance(), $logger);
    }

    public function incrementTransfersCreated(): void
    {
        $this->invalidateTransferCache();
        $this->logger->debug('monitoring.metric.bump', ['metric' => 'transfers_created_24h']);
    }

    public function incrementTransfersCommitted(): void
    {
        $this->invalidateTransferCache();
        $this->logger->debug('monitoring.metric.bump', ['metric' => 'transfers_committed_24h']);
    }

    public function refreshTransferPendingGauge(): void
    {
        $this->invalidateTransferCache();
    }

    public function getTransferMetrics(): array
    {
        if ($this->isCacheValid('transfers')) {
            return $this->cache['transfers'];
        }

        $metrics = [
            'transfers_created_24h' => $this->countTransfersSince(new DateInterval('PT24H')),
            'transfers_pending' => $this->countTransfersByStatus('proposed'),
            'transfers_committed_24h' => $this->countTransfersSince(new DateInterval('PT24H'), 'committed'),
        ];

        $this->cache['transfers'] = $metrics;
        $this->cacheExpiry['transfers'] = time() + self::CACHE_TTL_SECONDS;

        return $metrics;
    }

    private function countTransfersSince(DateInterval $interval, ?string $status = null): int
    {
        $cutoff = (new DateTimeImmutable('now'))
            ->sub($interval)
            ->format('Y-m-d H:i:s');

        if ($status !== null) {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM transfer_orders WHERE status = ? AND updated_at >= ?'
            );
            $stmt->execute([$status, $cutoff]);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM transfer_orders WHERE created_at >= ?'
            );
            $stmt->execute([$cutoff]);
        }

        return (int)$stmt->fetchColumn();
    }

    private function countTransfersByStatus(string $status): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM transfer_orders WHERE status = ?');
        $stmt->execute([$status]);
        return (int)$stmt->fetchColumn();
    }

    private function isCacheValid(string $key): bool
    {
        return isset($this->cache[$key], $this->cacheExpiry[$key]) && $this->cacheExpiry[$key] > time();
    }

    private function invalidateTransferCache(): void
    {
        unset($this->cache['transfers'], $this->cacheExpiry['transfers']);
    }
}
