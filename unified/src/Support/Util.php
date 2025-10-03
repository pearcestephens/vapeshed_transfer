<?php
declare(strict_types=1);
namespace Unified\Support;
/** Util.php
 * General shared helpers.
 */
final class Util
{
    public static function microtimeMs(): float { return microtime(true)*1000; }
}
