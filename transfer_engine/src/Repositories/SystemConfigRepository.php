<?php
declare(strict_types=1);

namespace Unified\Repositories;

use PDO;
use PDOException;
use RuntimeException;
use Unified\Support\Logger;
use Unified\Support\Pdo;

/**
 * SystemConfigRepository
 * Thin wrapper around ai_system_configuration table.
 */
final class SystemConfigRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly Logger $logger
    ) {
    }

    public static function withDefaults(Logger $logger): self
    {
        return new self(Pdo::instance(), $logger);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $stmt = $this->pdo->prepare('SELECT config_value FROM ai_system_configuration WHERE config_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        if ($value === false) {
            return $default;
        }

        return $this->decode((string)$value, $default);
    }

    public function set(string $key, mixed $value, ?string $actor = null): void
    {
        try {
            $encoded = $this->encode($value);
            $stmt = $this->pdo->prepare(
                'INSERT INTO ai_system_configuration (config_key, config_value, updated_by) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP'
            );
            $stmt->execute([$key, $encoded, $actor]);
        } catch (PDOException $e) {
            throw new RuntimeException('Failed to persist configuration key ' . $key, 0, $e);
        }
    }

    /**
     * @param array<string,mixed> $defaults
     */
    public function ensureDefaults(array $defaults, ?string $actor = null): void
    {
        foreach ($defaults as $key => $value) {
            $existing = $this->get($key, null);
            if ($existing === null) {
                $this->set((string)$key, $value, $actor);
            }
        }
    }

    /**
     * @param list<string>|null $whitelist
     */
    public function all(?array $whitelist = null): array
    {
        if ($whitelist !== null && $whitelist !== []) {
            $placeholders = implode(',', array_fill(0, count($whitelist), '?'));
            $stmt = $this->pdo->prepare('SELECT config_key, config_value FROM ai_system_configuration WHERE config_key IN (' . $placeholders . ')');
            $stmt->execute($whitelist);
        } else {
            $stmt = $this->pdo->query('SELECT config_key, config_value FROM ai_system_configuration');
        }

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[$row['config_key']] = $this->decode($row['config_value']);
        }

        return $results;
    }

    private function encode(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string)$value;
    }

    private function decode(string $value, mixed $default = null): mixed
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return $default;
        }

        if ($trimmed === 'true' || $trimmed === 'false') {
            return $trimmed === 'true';
        }

        if (is_numeric($trimmed)) {
            return str_contains($trimmed, '.') ? (float)$trimmed : (int)$trimmed;
        }

        if ($this->looksLikeJson($trimmed)) {
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $trimmed;
    }

    private function looksLikeJson(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        return ($value[0] === '{' && str_ends_with($value, '}')) || ($value[0] === '[' && str_ends_with($value, ']'));
    }
}
