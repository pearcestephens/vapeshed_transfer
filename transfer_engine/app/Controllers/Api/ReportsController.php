<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;

/**
 * Reports API Controller
 * Serves latest generated HTML transfer report safely via base-path-aware route
 */
class ReportsController extends BaseController
{
    /**
     * GET /api/reports/latest
     * - If ?json=1: returns JSON with a URL to view the latest report
     * - Otherwise: streams the latest HTML report contents directly
     */
    public function latest(): void
    {
        $reportFile = APP_ROOT . '/var/runs/transfer_report.html';

        // If JSON requested, return a link (base-path safe)
        if (isset($_GET['json']) && (string)$_GET['json'] === '1') {
            header('Content-Type: application/json');
            if (!is_file($reportFile)) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'No report found. Generate a report from the control panel first.'
                ]);
                return;
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    // Use route() to ensure subfolder-safe URL
                    'url' => route('/api/reports/latest')
                ]
            ]);
            return;
        }

        // Default: stream the HTML if it exists
        if (!is_file($reportFile)) {
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(404);
            echo 'No transfer report found. Please run a test or generate a report first.';
            return;
        }

        // Basic safety headers
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Stream content
        readfile($reportFile);
    }
}
