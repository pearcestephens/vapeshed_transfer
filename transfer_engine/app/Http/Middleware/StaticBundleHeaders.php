<?php
declare(strict_types=1);

namespace App\Http\Middleware;

/**
 * Applies long-cache + ETag to /public/admin/assets/*.{css,js}.
 * Attach early in the pipeline for direct file hits (if PHP serves static).
 * If Apache/Nginx serves static, mirror these headers in server config.
 */
final class StaticBundleHeaders
{
    /** @return void */
    public function handle(): void
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        if (!preg_match('#/public/admin/assets/(app\.(css|js))$#', $uri)) {
            return;
        }

        $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        $path = realpath($docRoot . $uri);
        if (!$path || !is_file($path)) {
            return;
        }

        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $mime = $ext === 'css' ? 'text/css' : 'application/javascript';
        $etag = '"' . md5_file($path) . '"';

        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=31536000, immutable');
        header('ETag: ' . $etag);

        $ifNone = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        if ($ifNone === $etag) {
            header('HTTP/1.1 304 Not Modified');
            exit;
        }
        // Do not readfile() here; let the server/static handler serve content.
    }
}
