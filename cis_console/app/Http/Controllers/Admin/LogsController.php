<?php
declare(strict_types=1);

namespace CisConsole\App\Http\Controllers\Admin;

use CisConsole\App\Support\Response;

final class LogsController
{
    private array $app;
    private array $security;

    public function __construct(array $app, array $security)
    {
        $this->app = $app;
        $this->security = $security;
    }

    public function apacheErrorTail(): void
    {
        $lines = isset($_GET['lines']) ? max(1, min(2000, (int)$_GET['lines'])) : 200;
        $logPath = $this->security['logs']['apache_error'] ?? '';
        if ($logPath === '' || !is_readable($logPath)) {
            Response::json(['success' => false, 'error' => ['code' => 'log_unavailable', 'message' => 'Log file not readable']], 500);
            return;
        }
        $content = $this->tail($logPath, $lines);
        Response::json(['success' => true, 'data' => ['path' => $logPath, 'lines' => $lines, 'tail' => $content]]);
    }

    private function tail(string $file, int $lines = 200): string
    {
        $f = @fopen($file, 'rb');
        if (!$f) {
            return '';
        }
        $buffer = '';
        $pos = -2;
        $lineCount = 0;
        fseek($f, 0, SEEK_END);
        while ($lineCount < $lines && -$pos < ftell($f)) {
            fseek($f, $pos, SEEK_END);
            $char = fgetc($f);
            if ($char === "\n") {
                $lineCount++;
                if ($lineCount === $lines) {
                    break;
                }
            }
            $buffer = $char . $buffer;
            $pos--;
        }
        fclose($f);
        return $buffer;
    }
}
