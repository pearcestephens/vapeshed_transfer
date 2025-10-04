<?php
/**
 * Stat Card Helper
 * Generates consistent stat card HTML for module dashboards
 */

if (!function_exists('statCard')) {
function statCard(string $label, $value, string $icon, string $color, string $key = ''): string {
    $dataAttr = $key ? "data-stat=\"{$key}\"" : '';
    
    return <<<HTML
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, {$color}, {$color}dd);">
            <i class="fas fa-{$icon}"></i>
        </div>
        <div class="stat-details">
            <div class="stat-label">{$label}</div>
            <div class="stat-value" {$dataAttr}>{$value}</div>
        </div>
    </div>
    HTML;
}
}

if (!function_exists('statusBadge')) {
function statusBadge(string $status, $mapOrText = null): string {
    // Support backward compatibility: if second param is string, treat as custom text
    $customText = is_string($mapOrText) ? $mapOrText : null;
    // Optional override map: status => bootstrap-class
    $overrideMap = is_array($mapOrText) ? $mapOrText : [];

    $defaultMap = [
        'success' => 'success',
        'active' => 'success',
        'online' => 'success',
        'connected' => 'success',
        'pending' => 'warning',
        'warning' => 'warning',
        'error' => 'danger',
        'failed' => 'danger',
        'offline' => 'danger',
        'blocked' => 'danger',
        'disabled' => 'secondary',
        'inactive' => 'secondary',
        'applied' => 'success',
        'rejected' => 'danger',
        'auto_applied' => 'info',
        'completed' => 'success',
        'cancelled' => 'secondary'
    ];

    $map = array_merge($defaultMap, $overrideMap);
    $class = $map[$status] ?? 'secondary';
    $text = $customText ?? ucfirst(str_replace('_', ' ', $status));
    return "<span class=\"badge badge-{$class}\">{$text}</span>";
}
}

if (!function_exists('moduleActions')) {
function moduleActions(array $actions): string {
    $html = '';
    foreach ($actions as $action) {
        $id = $action['id'] ?? '';
        $class = $action['class'] ?? 'btn-primary';
        $icon = $action['icon'] ?? '';
        $text = $action['text'] ?? 'Action';
        $disabled = $action['disabled'] ?? false;
        
        $disabledAttr = $disabled ? 'disabled' : '';
        $iconHtml = $icon ? "<i class=\"fas fa-{$icon}\"></i> " : '';
        
        $html .= "<button class=\"btn {$class} ml-2\" id=\"{$id}\" {$disabledAttr}>{$iconHtml}{$text}</button>";
    }
    
    return $html;
}
}