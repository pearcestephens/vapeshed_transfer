<?php
declare(strict_types=1);

namespace CisConsole\App\Http\Controllers\Admin;

use CisConsole\App\Support\Response;

final class ErrorsController
{
    private array $app;
    private array $security;

    public function __construct(array $app, array $security)
    {
        $this->app = $app;
        $this->security = $security;
    }

    public function top404(): void
    {
        $access = $this->security['logs']['apache_access'] ?? '';
        $data = $this->parseAccessTop($access, 404, 50);
        Response::json(['success' => true, 'data' => $data]);
    }

    public function top500(): void
    {
        $access = $this->security['logs']['apache_access'] ?? '';
        $data = $this->parseAccessTop($access, 500, 50);
        Response::json(['success' => true, 'data' => $data]);
    }

    /**
     * Naive parser: expects common/combined log with status code and request path.
     * Returns top N paths for the target status.
     *
     * @return array<int, array{path:string,count:int}>
     */
    private function parseAccessTop(string $file, int $status, int $limit): array
    {
        if ($file === '' || !is_readable($file)) {
            return [];
        }
        $counts = [];
        $fh = @fopen($file, 'rb');
        if (!$fh) {
            return [];
        }
        while (($line = fgets($fh)) !== false) {
            // Very rough: match "\"GET /path HTTP/1.1\" 404"
            if (preg_match('#\"[A-Z]+\s+([^\s]+)\s+HTTP/[0-9.]+\"\s+' . $status . '#', $line, $m)) {
                $path = $m[1];
                // Sanitize query parameters and numeric IDs
                $path = preg_replace('#\?.*$#', '', $path);
                $path = preg_replace('#/(\d{3,})#', '/{id}', $path);
                if (!isset($counts[$path])) $counts[$path] = 0;
                $counts[$path]++;
            }
        }
        fclose($fh);
        arsort($counts);
        $out = [];
        foreach (array_slice($counts, 0, $limit, true) as $path => $count) {
            $out[] = ['path' => (string)$path, 'count' => (int)$count];
        }
        return $out;
    }

    public function createRedirect(): void
    {
        // NOTE: GET for compatibility with ?endpoint=... routing model; in Phase 3 we can add CSRF + POST form.
        $from = isset($_GET['from']) ? (string)$_GET['from'] : '';
        $to = isset($_GET['to']) ? (string)$_GET['to'] : '';
        if ($from === '' || $to === '') {
            Response::json(['success' => false, 'error' => ['code' => 'bad_request', 'message' => 'from and to required']], 400);
            return;
        }
        // Basic validation: absolute or site-local target, simple path for source
        if (!preg_match('#^/[^\s]*$#', $from)) {
            Response::json(['success' => false, 'error' => ['code' => 'invalid_from', 'message' => 'from must be a site-local path']], 400);
            return;
        }
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
        $allowed = '#^(' . preg_quote($scheme . '://' . $host, '#') . '|/)[^\s]*$#';
        if (!preg_match($allowed, $to)) {
            Response::json(['success' => false, 'error' => ['code' => 'invalid_to', 'message' => 'to must be absolute current-host URL or site-local path']], 400);
            return;
        }
        $dir = $this->app['paths']['storage'] . '/redirects';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $file = $dir . '/redirects.json';
        $data = [];
        if (is_file($file)) {
            $raw = (string)@file_get_contents($file);
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $data = $decoded;
        }
        // prevent duplicate
        $data[$from] = $to;
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        Response::json(['success' => true, 'data' => ['from' => $from, 'to' => $to]]);
    }

    public function listRedirects(): void
    {
        $dir = $this->app['paths']['storage'] . '/redirects';
        $file = $dir . '/redirects.json';
        $data = [];
        if (is_file($file)) {
            $raw = (string)@file_get_contents($file);
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $data = $decoded;
        }
        Response::json(['success' => true, 'data' => ['redirects' => $data]]);
    }
}
