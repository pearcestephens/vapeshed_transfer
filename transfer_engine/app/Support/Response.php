<?php
declare(strict_types=1);

namespace App\Support;

final class Response
{
    public static function json(array $payload, int $status = 200, array $headers = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        header('X-Content-Type-Options: nosniff');

        foreach ($headers as $key => $value) {
            header($key . ': ' . $value);
        }

        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public static function envelope(?array $data = null, bool $success = true, ?array $error = null, array $meta = []): array
    {
        return [
            'success' => $success,
            'data' => $data,
            'error' => $error,
            'meta' => self::buildMeta($meta)
        ];
    }

    public static function success(array $data = [], array $meta = [], int $status = 200): void
    {
        self::json(self::envelope($data, true, null, $meta), $status, self::correlationHeaders());
    }

    public static function error(string $message, string $code = 'ERROR', array $meta = [], int $status = 400): void
    {
        $payload = self::envelope(null, false, [
            'code' => $code,
            'message' => $message
        ], $meta);

        self::json($payload, $status, self::correlationHeaders());
    }

    private static function buildMeta(array $overrides = []): array
    {
        return array_merge([
            'correlation_id' => self::correlationId(),
            'timestamp' => date('c'),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'endpoint' => $_GET['endpoint'] ?? ($_SERVER['REQUEST_URI'] ?? ''),
        ], $overrides);
    }

    private static function correlationHeaders(): array
    {
        return ['X-Correlation-ID' => self::correlationId()];
    }

    private static function correlationId(): string
    {
        if (!isset($GLOBALS['__correlation_id'])) {
            $incoming = $_SERVER['HTTP_X_CORRELATION_ID'] ?? null;
            $GLOBALS['__correlation_id'] = $incoming && is_string($incoming)
                ? trim($incoming)
                : bin2hex(random_bytes(8));
        }

        return $GLOBALS['__correlation_id'];
    }
}
