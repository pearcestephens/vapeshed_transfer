<?php
declare(strict_types=1);
/**
 * Template Helper Functions
 * Utility functions for rendering dashboard templates
 */

/**
 * Render page title
 */
function pageTitle(string $title): string {
    return htmlspecialchars($title) . ' | Unified Intelligence Platform';
}

/**
 * Set breadcrumbs
 */
function setBreadcrumbs(array $crumbs): array {
    return $crumbs;
}

/**
 * Format number with commas
 */
function formatNumber($number, int $decimals = 0): string {
    return number_format((float)$number, $decimals);
}

/**
 * Format currency (NZD)
 */
function formatCurrency($amount): string {
    return '$' . number_format((float)$amount, 2);
}

/**
 * Format percentage
 */
function formatPercent($value, int $decimals = 1): string {
    return number_format((float)$value * 100, $decimals) . '%';
}

/**
 * Format date
 */
function formatDate($date): string {
    if (is_string($date)) {
        $date = strtotime($date);
    }
    return date('d M Y', $date);
}

/**
 * Format datetime
 */
function formatDateTime($datetime): string {
    if (is_string($datetime)) {
        $datetime = strtotime($datetime);
    }
    return date('d M Y H:i:s', $datetime);
}

/**
 * Get status badge HTML
 */
function statusBadge(string $status): string {
    $badges = [
        'active' => '<span class="badge badge-success">Active</span>',
        'pending' => '<span class="badge badge-warning">Pending</span>',
        'completed' => '<span class="badge badge-info">Completed</span>',
        'failed' => '<span class="badge badge-danger">Failed</span>',
        'beta' => '<span class="badge badge-warning">Beta</span>',
        'planned' => '<span class="badge badge-secondary">Planned</span>',
    ];
    
    return $badges[$status] ?? '<span class="badge badge-secondary">' . htmlspecialchars($status) . '</span>';
}

/**
 * Truncate text
 */
function truncate(string $text, int $length = 100): string {
    if (strlen($text) <= $length) {
        return htmlspecialchars($text);
    }
    return htmlspecialchars(substr($text, 0, $length)) . '...';
}

/**
 * Generate alert HTML
 */
function alert(string $type, string $message, bool $dismissible = true): string {
    $dismissClass = $dismissible ? ' alert-dismissible fade show' : '';
    $dismissButton = $dismissible ? '<button type="button" class="close" data-dismiss="alert">&times;</button>' : '';
    
    return sprintf(
        '<div class="alert alert-%s%s" role="alert">%s%s</div>',
        htmlspecialchars($type),
        $dismissClass,
        $dismissButton,
        htmlspecialchars($message)
    );
}

/**
 * Generate card HTML
 */
function card(string $title, string $content, ?string $footer = null): string {
    $footerHtml = $footer ? '<div class="card-footer">' . $footer . '</div>' : '';
    
    return sprintf(
        '<div class="card"><div class="card-header">%s</div><div class="card-body">%s</div>%s</div>',
        htmlspecialchars($title),
        $content,
        $footerHtml
    );
}

/**
 * Generate icon HTML
 */
function icon(string $name, ?string $class = null): string {
    $classAttr = $class ? ' ' . htmlspecialchars($class) : '';
    return sprintf('<i class="fas fa-%s%s"></i>', htmlspecialchars($name), $classAttr);
}

/**
 * Check if current page matches
 */
function isCurrentPage(string $page): bool {
    return strpos($_SERVER['REQUEST_URI'] ?? '', $page) !== false;
}
