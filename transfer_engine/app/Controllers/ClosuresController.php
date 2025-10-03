<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Security;

class ClosuresController extends BaseController
{
    public function backfill(): void
    {
        $page_header = 'Register Closures Backfill';
        $subtitle = 'Create closure-only rows by default. Optionally fill totals and daily cash.';
        $breadcrumbs = [
            [ 'label' => 'Dashboard', 'url' => $this->url('/dashboard') ],
            [ 'label' => 'Backfill', 'url' => $this->url('/closures/backfill') ],
        ];
        $additional_js = [ 'js/closures-backfill.js' ];
        $this->render('closures/backfill', compact('page_header','subtitle','breadcrumbs','additional_js'));
    }

    public function apiHealth(): void
    {
        // Minimal health check; extend with real DB ping if needed
        $this->json([ 'success' => true, 'data' => [ 'status' => 'ok' ] ]);
    }

    public function apiScan(): void
    {
        $date = $_POST['date'] ?? $_GET['date'] ?? '';
        $mode = $_POST['mode'] ?? $_GET['mode'] ?? 'vend';
        $fillTotals = isset($_POST['fill_totals']) || isset($_GET['fill_totals']);
        $dailyCash = isset($_POST['daily_cash']) || isset($_GET['daily_cash']);

        if (!$date) {
            return $this->json([ 'success' => false, 'error' => 'date required' ], 400);
        }

        // Placeholder preview payload (no writes)
        $preview = [
            'excl' => null,
            'incl' => null,
            'loyalty' => null,
            'tax' => null,
            'discounts' => null,
            'cogs' => null,
        ];

        $cash = [ 'total' => null ];

        return $this->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'mode' => $mode,
                'fill_totals' => $fillTotals,
                'daily_cash' => $dailyCash,
                'preview' => $preview,
                'cash' => $cash,
            ]
        ]);
    }
}
