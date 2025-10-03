<?php
/**
 * _cli_bootstrap.php
 * Unified CLI bootstrap for transfer engine scripts.
 *
 * Responsibilities:
 *  - Load Composer autoloader
 *  - Load application bootstrap & legacy CIS config integration
 *  - Provide standardized, single mysqli connection getter (lazy, reused)
 *  - Enforce utf8mb4 charset & timezone
 *  - Optional persistent connection (enable via CLI_PERSISTENT=1)
 *  - Optional read-only mode guard (CLI_READ_ONLY=1 prevents write queries)
 *
 * Usage in scripts:
 *   require_once __DIR__ . '/_cli_bootstrap.php';
 *   $db = cli_db(); // mysqli
 *
 * Environment Variables Recognized:
 *   DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT
 *   CLI_PERSISTENT=1  -> use p:host for persistent connection
 *   CLI_READ_ONLY=1   -> wrap write queries with guard (basic regex heuristic)
 */

declare(strict_types=1);

// Composer autoload
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoloadPath)) { require_once $autoloadPath; }

// Application bootstrap (defines constants, may import CIS globals)
$bootstrapPath = __DIR__ . '/../config/bootstrap.php';
if (is_file($bootstrapPath)) { require_once $bootstrapPath; }

static $CLI_DB = null; // shared connection instance

/** Return shared mysqli connection (lazy) */
function cli_db(): mysqli {
    global $CLI_DB;
    if ($CLI_DB instanceof mysqli && @$CLI_DB->ping()) { return $CLI_DB; }

    $host = getenv('DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : 'localhost');
    $user = getenv('DB_USER') ?: (defined('DB_USER') ? DB_USER : 'root');
    $pass = getenv('DB_PASS') ?: (defined('DB_PASS') ? DB_PASS : '');
    $name = getenv('DB_NAME') ?: (defined('DB_NAME') ? DB_NAME : 'cis');
    $port = (int)(getenv('DB_PORT') ?: (defined('DB_PORT') ? DB_PORT : 3306));
    $persistent = getenv('CLI_PERSISTENT') === '1';
    if ($persistent && strncmp($host, 'p:', 2) !== 0) { $host = 'p:' . $host; }

    $mysqli = @new mysqli($host, $user, $pass, $name, $port);
    if ($mysqli->connect_errno) {
        fwrite(STDERR, "[cli_db] Connection failed: {$mysqli->connect_error}\n");
        exit(2);
    }
    $mysqli->set_charset('utf8mb4');
    $mysqli->query("SET time_zone = '+12:00'");

    $CLI_DB = $mysqli;
    return $CLI_DB;
}

/** Basic read-only guard for accidental writes (heuristic). */
function cli_query(mysqli $db, string $sql): mysqli_result|bool {
    if (getenv('CLI_READ_ONLY') === '1') {
        if (preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|CREATE|TRUNCATE|RENAME)\b/i', $sql)) {
            fwrite(STDERR, "[cli_db] Write blocked in read-only mode: $sql\n");
            return false;
        }
    }
    $res = $db->query($sql);
    if ($res === false) { fwrite(STDERR, "[cli_db] SQL error: {$db->error} | $sql\n"); }
    return $res;
}

/** Fetch all rows helper */
function cli_fetch_all(mysqli_result $res): array { $rows=[]; while($res && $r=$res->fetch_assoc()){ $rows[]=$r; } return $rows; }

