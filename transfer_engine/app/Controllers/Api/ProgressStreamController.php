<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;

class ProgressStreamController extends BaseController
{
    public function stream(): void
    {
        // SSE headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable Nginx buffering if present

        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', 'off');
        @ini_set('implicit_flush', '1');
        while (ob_get_level() > 0) { @ob_end_flush(); }
        @ob_implicit_flush(1);

        $runId = $_GET['run_id'] ?? '';
        if (!preg_match('/^run_[A-Za-z0-9_\-]+$/', $runId)) {
            echo "event: error\n";
            echo 'data: {"error":"invalid run_id"}' . "\n\n";
            flush();
            return;
        }

        $streamPath = STORAGE_PATH . '/runs/' . $runId . '.stream';

        // Wait for file to appear (up to ~10s)
        $waitMs = 0;
        while (!is_file($streamPath) && $waitMs < 10000) {
            usleep(200 * 1000);
            $waitMs += 200;
        }

        // If still not found, end
        if (!is_file($streamPath)) {
            echo "event: error\n";
            echo 'data: {"error":"stream not found"}' . "\n\n";
            flush();
            return;
        }

        $fp = fopen($streamPath, 'r');
        if (!$fp) {
            echo "event: error\n";
            echo 'data: {"error":"failed to open stream"}' . "\n\n";
            flush();
            return;
        }

        // Seek to end initially to only receive new events
        fseek($fp, 0, SEEK_END);

        $idleBeats = 0;
        $maxSeconds = 600; // 10 minutes cap
        $start = time();

        while (!connection_aborted()) {
            $line = fgets($fp);
            if ($line !== false) {
                $idleBeats = 0;
                $payload = trim($line);
                if ($payload === '') { continue; }
                echo 'data: ' . $payload . "\n\n";
                flush();
                // Check if done flag present
                $decoded = json_decode($payload, true);
                if (isset($decoded['done']) && $decoded['done']) {
                    break;
                }
            } else {
                // Heartbeat every ~2s
                usleep(200 * 1000);
                $idleBeats++;
                if ($idleBeats % 10 === 0) {
                    echo "event: ping\n";
                    echo 'data: {"ts":' . json_encode(date('c')) . '}' . "\n\n";
                    flush();
                }
            }

            if ((time() - $start) > $maxSeconds) {
                echo "event: timeout\n";
                echo 'data: {"reason":"timeout"}' . "\n\n";
                flush();
                break;
            }
        }

        fclose($fp);
    }
}
