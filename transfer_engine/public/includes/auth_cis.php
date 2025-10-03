<?php
/**
 * CIS Authentication Integration
 * 
 * Enhanced authentication system integrated with CIS (Central Information System).
 * Provides secure session management, permission checking, and CIS API integration.
 * 
 * @package VapeshedTransfer
 * @subpackage Includes
 * @version 2.0.0
 */
declare(strict_types=1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => ($_SERVER['HTTPS'] ?? 'off') === 'on',
        'use_strict_mode' => true
    ]);
}

/**
 * CIS Authentication Manager
 * 
 * Handles all authentication and authorization with CIS backend
 */
class CISAuthManager
{
    private const SESSION_TIMEOUT = 7200; // 2 hours
    private const CIS_API_ENDPOINT = 'https://staff.vapeshed.co.nz/api/auth';
    private const DEV_MODE_KEY = 'CIS_DEV_MODE';
    
    /**
     * Check if user is authenticated via CIS
     * 
     * @return bool True if authenticated
     */
    public static function isAuthenticated(): bool
    {
        // Development mode bypass
        if (self::isDevelopmentMode()) {
            return true;
        }
        
        // Check session variables
        if (!isset($_SESSION['cis_user_id']) || empty($_SESSION['cis_user_id'])) {
            return false;
        }
        
        // Check session timeout
        if (isset($_SESSION['cis_last_activity'])) {
            $elapsed = time() - $_SESSION['cis_last_activity'];
            if ($elapsed > self::SESSION_TIMEOUT) {
                self::logout();
                return false;
            }
        }
        
        // Update last activity timestamp
        $_SESSION['cis_last_activity'] = time();
        
        return true;
    }
    
    /**
     * Authenticate user with CIS credentials
     * 
     * @param string $username CIS username
     * @param string $password User password
     * @return array Authentication result ['success' => bool, 'user' => array|null, 'error' => string|null]
     */
    public static function authenticate(string $username, string $password): array
    {
        try {
            // Call CIS authentication API
            $response = self::callCISAPI('/verify', [
                'username' => $username,
                'password' => $password,
                'system' => 'transfer_engine'
            ]);
            
            if ($response['success'] ?? false) {
                // Store user session
                $_SESSION['cis_user_id'] = $response['user']['id'];
                $_SESSION['cis_username'] = $response['user']['username'];
                $_SESSION['cis_name'] = $response['user']['name'] ?? $username;
                $_SESSION['cis_email'] = $response['user']['email'] ?? '';
                $_SESSION['cis_role'] = $response['user']['role'] ?? 'user';
                $_SESSION['cis_permissions'] = $response['user']['permissions'] ?? [];
                $_SESSION['cis_avatar'] = $response['user']['avatar'] ?? null;
                $_SESSION['cis_last_activity'] = time();
                $_SESSION['cis_token'] = $response['token'] ?? null;
                
                // Log authentication
                self::logAuthEvent('login', $response['user']['id'], 'success');
                
                return [
                    'success' => true,
                    'user' => $response['user'],
                    'error' => null
                ];
            }
            
            // Log failed attempt
            self::logAuthEvent('login', $username, 'failed');
            
            return [
                'success' => false,
                'user' => null,
                'error' => $response['error'] ?? 'Authentication failed'
            ];
            
        } catch (Exception $e) {
            error_log("CIS authentication error: " . $e->getMessage());
            return [
                'success' => false,
                'user' => null,
                'error' => 'Authentication service unavailable'
            ];
        }
    }
    
    /**
     * Get current authenticated user from CIS session
     * 
     * @return array User data or empty array if not authenticated
     */
    public static function getCurrentUser(): array
    {
        // Development mode fallback
        if (self::isDevelopmentMode()) {
            return self::getMockUser();
        }
        
        if (!self::isAuthenticated()) {
            return [];
        }
        
        return [
            'id' => $_SESSION['cis_user_id'] ?? 0,
            'username' => $_SESSION['cis_username'] ?? 'unknown',
            'name' => $_SESSION['cis_name'] ?? $_SESSION['cis_username'] ?? 'Unknown User',
            'email' => $_SESSION['cis_email'] ?? '',
            'role' => $_SESSION['cis_role'] ?? 'user',
            'permissions' => $_SESSION['cis_permissions'] ?? [],
            'avatar' => $_SESSION['cis_avatar'] ?? null,
            'last_activity' => $_SESSION['cis_last_activity'] ?? time()
        ];
    }
    
    /**
     * Check if user has specific permission
     * 
     * @param string $permission Permission identifier (e.g., 'transfer.execute', 'pricing.approve')
     * @return bool True if user has permission
     */
    public static function hasPermission(string $permission): bool
    {
        $user = self::getCurrentUser();
        
        if (empty($user)) {
            return false;
        }
        
        // Administrator role has all permissions
        if (in_array($user['role'], ['administrator', 'super_admin', 'admin'])) {
            return true;
        }
        
        // Check wildcard permission
        if (in_array('*', $user['permissions'])) {
            return true;
        }
        
        // Check specific permission
        if (in_array($permission, $user['permissions'])) {
            return true;
        }
        
        // Check permission prefix (e.g., 'transfer.*' grants 'transfer.execute')
        $parts = explode('.', $permission);
        if (count($parts) > 1) {
            $prefix = $parts[0] . '.*';
            if (in_array($prefix, $user['permissions'])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if user has any of the specified permissions
     * 
     * @param array $permissions List of permissions
     * @return bool True if user has at least one permission
     */
    public static function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (self::hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if user has all specified permissions
     * 
     * @param array $permissions List of permissions
     * @return bool True if user has all permissions
     */
    public static function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!self::hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Logout user and clear session
     */
    public static function logout(): void
    {
        $userId = $_SESSION['cis_user_id'] ?? null;
        
        // Log logout event
        if ($userId) {
            self::logAuthEvent('logout', $userId, 'success');
        }
        
        // Clear CIS session variables
        unset($_SESSION['cis_user_id']);
        unset($_SESSION['cis_username']);
        unset($_SESSION['cis_name']);
        unset($_SESSION['cis_email']);
        unset($_SESSION['cis_role']);
        unset($_SESSION['cis_permissions']);
        unset($_SESSION['cis_avatar']);
        unset($_SESSION['cis_last_activity']);
        unset($_SESSION['cis_token']);
        
        // Destroy session if empty
        if (empty($_SESSION)) {
            session_destroy();
        }
    }
    
    /**
     * Refresh user data from CIS
     * 
     * @return bool True if refresh successful
     */
    public static function refreshUserData(): bool
    {
        if (!self::isAuthenticated()) {
            return false;
        }
        
        try {
            $userId = $_SESSION['cis_user_id'];
            $response = self::callCISAPI("/user/{$userId}");
            
            if ($response['success'] ?? false) {
                $_SESSION['cis_name'] = $response['user']['name'] ?? $_SESSION['cis_username'];
                $_SESSION['cis_email'] = $response['user']['email'] ?? '';
                $_SESSION['cis_role'] = $response['user']['role'] ?? 'user';
                $_SESSION['cis_permissions'] = $response['user']['permissions'] ?? [];
                $_SESSION['cis_avatar'] = $response['user']['avatar'] ?? null;
                
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("CIS user refresh error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if running in development mode
     * 
     * @return bool True if development mode
     */
    private static function isDevelopmentMode(): bool
    {
        // Check environment variable
        if (getenv('APP_ENV') === 'development') {
            return true;
        }
        
        // Check constant
        if (defined(self::DEV_MODE_KEY) && constant(self::DEV_MODE_KEY) === true) {
            return true;
        }
        
        // Check for local development domains
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $localHosts = ['localhost', '127.0.0.1', 'local.vapeshed.co.nz', '::1'];
        
        return in_array($host, $localHosts) || str_ends_with($host, '.local');
    }
    
    /**
     * Get mock user for development
     * 
     * @return array Mock user data
     */
    private static function getMockUser(): array
    {
        return [
            'id' => 1,
            'username' => 'dev_admin',
            'name' => 'Development Admin',
            'email' => 'dev@vapeshed.co.nz',
            'role' => 'administrator',
            'permissions' => ['*'],
            'avatar' => null,
            'last_activity' => time()
        ];
    }
    
    /**
     * Call CIS API endpoint
     * 
     * @param string $endpoint API endpoint path
     * @param array $data Request data
     * @return array API response
     * @throws Exception If API call fails
     */
    private static function callCISAPI(string $endpoint, array $data = []): array
    {
        // In development, return mock response
        if (self::isDevelopmentMode()) {
            return [
                'success' => false,
                'error' => 'Development mode - CIS API not called'
            ];
        }
        
        $url = self::CIS_API_ENDPOINT . $endpoint;
        
        $options = [
            'http' => [
                'header' => "Content-Type: application/json\r\n" .
                           "User-Agent: TransferEngine/1.0\r\n",
                'method' => 'POST',
                'content' => json_encode($data),
                'timeout' => 10,
                'ignore_errors' => true
            ]
        ];
        
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new Exception("Failed to connect to CIS API at {$url}");
        }
        
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response from CIS API");
        }
        
        return $decoded ?? [];
    }
    
    /**
     * Log authentication event
     * 
     * @param string $event Event type (login, logout, failed, etc.)
     * @param mixed $identifier User ID or username
     * @param string $status Event status
     */
    private static function logAuthEvent(string $event, $identifier, string $status): void
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'identifier' => $identifier,
            'status' => $status,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        error_log("AUTH_EVENT: " . json_encode($logData));
    }
}

// ============================================
// LEGACY FUNCTION WRAPPERS (Backward Compatibility)
// ============================================

/**
 * Check if user is authenticated
 * @return bool
 */
function isAuthenticated(): bool
{
    return CISAuthManager::isAuthenticated();
}

/**
 * Get current authenticated user
 * @return array
 */
function getCurrentUser(): array
{
    return CISAuthManager::getCurrentUser();
}

/**
 * Check if user has specific permission
 * @param string $permission
 * @return bool
 */
function hasPermission(string $permission): bool
{
    return CISAuthManager::hasPermission($permission);
}

/**
 * Require authentication or redirect
 * @param string $redirectTo
 */
function requireAuth(string $redirectTo = '/login.php'): void
{
    if (!CISAuthManager::isAuthenticated()) {
        header("Location: $redirectTo");
        exit;
    }
}

/**
 * Require specific permission or show error
 * @param string $permission
 * @param int $errorCode
 */
function requirePermission(string $permission, int $errorCode = 403): void
{
    if (!CISAuthManager::hasPermission($permission)) {
        http_response_code($errorCode);
        echo json_encode([
            'success' => false,
            'error' => 'Access denied. Required permission: ' . $permission
        ]);
        exit;
    }
}

/**
 * Logout current user
 */
function logout(): void
{
    CISAuthManager::logout();
}
