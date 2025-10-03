<?php
declare(strict_types=1);
namespace Unified\Support;
/** Http.php (Phase M1)
 * Lightweight HTTP helpers (JSON responses). For internal staff endpoints only.
 */
final class Http
{
    public static function json(array $payload, int $code=200): void
    { http_response_code($code); header('Content-Type: application/json'); echo json_encode($payload)."\n"; }
}
