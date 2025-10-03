<?php
declare(strict_types=1);
namespace Unified\Support;
/** Validator.php (Phase M1)
 * Lightweight parameter validation utilities.
 */
final class Validator
{
    public static function intRange(int $v, int $min, int $max, string $field): void
    { if($v<$min||$v>$max) throw new \InvalidArgumentException("$field out of range"); }
}
