<?php
declare(strict_types=1);
/**
 * Authentication Helper
 * Simple session-based authentication for dashboard access
 * 
 * TODO: Integrate with CIS auth system
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is authenticated
 */
function isAuthenticated(): bool {
    // TODO: Replace with actual CIS auth check
    // For now, return true to allow development
    return true;
    
    // Future implementation:
    // return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user data
 */
function getCurrentUser(): array {
    // TODO: Fetch from CIS user session
    // For now, return mock user
    return [
        'id' => 1,
        'name' => 'Admin User',
        'email' => 'admin@vapeshed.co.nz',
        'role' => 'administrator',
        'permissions' => ['all']
    ];
    
    // Future implementation:
    // if (!isAuthenticated()) return [];
    // return $_SESSION['user_data'] ?? [];
}

/**
 * Check if user has permission
 */
function hasPermission(string $permission): bool {
    $user = getCurrentUser();
    
    // Admin has all permissions
    if (isset($user['role']) && $user['role'] === 'administrator') {
        return true;
    }
    
    // Check specific permission
    return in_array($permission, $user['permissions'] ?? []);
}

/**
 * Require authentication
 */
function requireAuth(): void {
    if (!isAuthenticated()) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * Require specific permission
 */
function requirePermission(string $permission): void {
    requireAuth();
    
    if (!hasPermission($permission)) {
        http_response_code(403);
        echo 'Access Denied: Insufficient permissions';
        exit;
    }
}
