<?php
/**
 * NZ Compliant Website Integration Controller
 * 
 * Handles dynamic homepage personalization and customer verification
 * for NZ vaping law compliance - serves different content based on
 * customer purchase history and verification status.
 * 
 * NZ Compliance Rules:
 * - No nicotine product images/details until after 1+ purchase
 * - Customer verification required for full product access
 * - Website inbox messaging allowed (no email notifications)
 * - Personalized recommendations only for verified customers
 * 
 * @author AI Assistant
 * @created 2025-01-27
 * @updated 2025-01-27
 */

require_once __DIR__ . '/../config/bootstrap.php';

class WebsiteIntegrationController
{
    private $db;
    private $logger;
    
    // NZ Compliance Thresholds
    const VERIFICATION_MIN_ORDERS = 1;
    const VERIFICATION_MIN_VALUE = 0.01;
    const RESTRICTED_CATEGORIES = ['nicotine', 'disposable', 'eliquid'];
    
    // Customer Tier Thresholds (NZD)
    const TIER_BRONZE = 100;
    const TIER_SILVER = 500;
    const TIER_GOLD = 1000;
    const TIER_VIP = 2000;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = Logger::getInstance();
        
        // Handle CORS for website integration
        $this->handleCORS();
    }
    
    private function handleCORS()
    {
        header('Access-Control-Allow-Origin: https://www.vapeshed.co.nz');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Allow-Credentials: true');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            exit(0);
        }
    }
    
    /**
     * Get customer verification status and personalization data
     */
    public function getCustomerProfile()
    {
        try {
            $customerId = $this->getAuthenticatedCustomerId();
            
            if (!$customerId) {
                return $this->jsonResponse([
                    'verified' => false,
                    'guest_content' => $this->getGuestContent(),
                    'verification_message' => 'Complete your first purchase to unlock personalized recommendations and full product access.'
                ]);
            }
            
            $profile = $this->buildCustomerProfile($customerId);
            $isVerified = $this->isCustomerVerified($profile);
            
            return $this->jsonResponse([
                'verified' => $isVerified,
                'customer_id' => $customerId,
                'profile' => $profile,
                'personalization' => $isVerified ? $this->getPersonalizationData($customerId) : null,
                'recommendations' => $isVerified ? $this->getPersonalizedRecommendations($customerId) : null,
                'inbox_count' => $isVerified ? $this->getUnreadMessageCount($customerId) : 0,
                'content_access' => $this->getContentAccess($isVerified)
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('Customer profile error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->jsonResponse([
                'error' => 'Unable to load customer profile',
                'verified' => false
            ], 500);
        }
    }
    
    /**
     * Get dynamic homepage content based on customer verification
     */
    public function getHomepageContent()
    {
        try {
            $customerId = $this->getAuthenticatedCustomerId();
            $isVerified = false;
            
            if ($customerId) {
                $profile = $this->buildCustomerProfile($customerId);
                $isVerified = $this->isCustomerVerified($profile);
            }
            
            $content = [
                'hero_section' => $this->getHeroContent($isVerified, $customerId),
                'featured_products' => $this->getFeaturedProducts($isVerified, $customerId),
                'categories' => $this->getCategoryDisplay($isVerified),
                'promotions' => $this->getPromotions($isVerified, $customerId),
                'trust_signals' => $this->getTrustSignals(),
                'compliance_notice' => $this->getComplianceNotice($isVerified)
            ];
            
            return $this->jsonResponse($content);
            
        } catch (Exception $e) {
            $this->logger->error('Homepage content error', [
                'error' => $e->getMessage()
            ]);
            
            return $this->jsonResponse([
                'error' => 'Unable to load homepage content'
            ], 500);
        }
    }
    
    /**
     * Get customer's website inbox messages
     */
    public function getInboxMessages()
    {
        try {
            $customerId = $this->getAuthenticatedCustomerId();
            
            if (!$customerId) {
                return $this->jsonResponse([
                    'error' => 'Authentication required'
                ], 401);
            }
            
            $profile = $this->buildCustomerProfile($customerId);
            if (!$this->isCustomerVerified($profile)) {
                return $this->jsonResponse([
                    'error' => 'Account verification required'
                ], 403);
            }
            
            // Get messages from VapeShed database
            $messages = $this->db->query("
                SELECT 
                    m.id,
                    m.subject,
                    m.message,
                    m.priority,
                    m.is_read,
                    m.created_at,
                    m.message_type
                FROM customer_messages m
                WHERE m.customer_id = ?
                ORDER BY m.created_at DESC
                LIMIT 20
            ", [$customerId]);
            
            $unreadCount = $this->getUnreadMessageCount($customerId);
            
            return $this->jsonResponse([
                'messages' => $messages,
                'unread_count' => $unreadCount,
                'total_count' => count($messages)
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('Inbox messages error', [
                'customer_id' => $customerId ?? null,
                'error' => $e->getMessage()
            ]);
            
            return $this->jsonResponse([
                'error' => 'Unable to load messages'
            ], 500);
        }
    }
    
    /**
     * Send message to customer's website inbox (NZ compliant - no email)
     */
    public function sendInboxMessage()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $customerId = $data['customer_id'] ?? null;
            $subject = $data['subject'] ?? '';
            $message = $data['message'] ?? '';
            $priority = $data['priority'] ?? 'normal';
            $messageType = $data['type'] ?? 'general';
            
            if (!$customerId || !$subject || !$message) {
                return $this->jsonResponse([
                    'error' => 'Missing required fields'
                ], 400);
            }
            
            // Insert message into VapeShed database
            $messageId = $this->db->insert("
                INSERT INTO customer_messages 
                (customer_id, subject, message, priority, message_type, created_at, is_read)
                VALUES (?, ?, ?, ?, ?, NOW(), 0)
            ", [$customerId, $subject, $message, $priority, $messageType]);
            
            $this->logger->info('Website inbox message sent', [
                'message_id' => $messageId,
                'customer_id' => $customerId,
                'type' => $messageType,
                'priority' => $priority
            ]);
            
            return $this->jsonResponse([
                'success' => true,
                'message_id' => $messageId,
                'note' => 'Message delivered to customer website inbox (NZ compliant - no email notification)'
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('Send inbox message error', [
                'error' => $e->getMessage(),
                'data' => $data ?? null
            ]);
            
            return $this->jsonResponse([
                'error' => 'Unable to send message'
            ], 500);
        }
    }
    
    /**
     * Get personalized product recommendations
     */
    public function getRecommendations()
    {
        try {
            $customerId = $this->getAuthenticatedCustomerId();
            
            if (!$customerId) {
                return $this->jsonResponse([
                    'error' => 'Authentication required'
                ], 401);
            }
            
            $profile = $this->buildCustomerProfile($customerId);
            if (!$this->isCustomerVerified($profile)) {
                return $this->jsonResponse([
                    'recommendations' => [],
                    'message' => 'Complete your first purchase to unlock personalized recommendations'
                ]);
            }
            
            $recommendations = $this->getPersonalizedRecommendations($customerId);
            
            return $this->jsonResponse([
                'recommendations' => $recommendations,
                'profile_data' => [
                    'tier' => $profile['tier'],
                    'favorite_brands' => $profile['favorite_brands'],
                    'purchase_patterns' => $profile['purchase_patterns']
                ]
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('Recommendations error', [
                'customer_id' => $customerId ?? null,
                'error' => $e->getMessage()
            ]);
            
            return $this->jsonResponse([
                'error' => 'Unable to load recommendations'
            ], 500);
        }
    }
    
    /**
     * Build comprehensive customer profile from multiple data sources
     */
    private function buildCustomerProfile($customerId)
    {
        // Get customer data from VapeShed website database
        $customer = $this->db->queryRow("
            SELECT 
                c.id,
                c.first_name,
                c.last_name,
                c.email,
                c.created_at as registration_date,
                COUNT(DISTINCT o.id) as total_orders,
                SUM(o.total) as lifetime_value,
                AVG(o.total) as avg_order_value,
                MAX(o.created_at) as last_order_date,
                MIN(o.created_at) as first_order_date
            FROM customers c
            LEFT JOIN orders o ON c.id = o.customer_id AND o.status != 'cancelled'
            WHERE c.id = ?
            GROUP BY c.id
        ", [$customerId]);
        
        if (!$customer) {
            throw new Exception("Customer not found: {$customerId}");
        }
        
        // Get detailed order analysis
        $orderData = $this->getCustomerOrderAnalysis($customerId);
        $brandAnalysis = $this->getCustomerBrandAnalysis($customerId);
        $productPreferences = $this->getProductPreferences($customerId);
        
        // Calculate loyalty tier
        $tier = $this->calculateCustomerTier($customer['lifetime_value']);
        
        // Calculate days since last order
        $daysSinceLastOrder = null;
        if ($customer['last_order_date']) {
            $lastOrder = new DateTime($customer['last_order_date']);
            $now = new DateTime();
            $daysSinceLastOrder = $now->diff($lastOrder)->days;
        }
        
        return [
            'customer_id' => $customer['id'],
            'name' => trim($customer['first_name'] . ' ' . $customer['last_name']),
            'email' => $customer['email'],
            'registration_date' => $customer['registration_date'],
            'total_orders' => (int)$customer['total_orders'],
            'lifetime_value' => (float)$customer['lifetime_value'],
            'avg_order_value' => (float)$customer['avg_order_value'],
            'last_order_date' => $customer['last_order_date'],
            'first_order_date' => $customer['first_order_date'],
            'days_since_last_order' => $daysSinceLastOrder,
            'tier' => $tier,
            'favorite_brands' => $brandAnalysis,
            'product_preferences' => $productPreferences,
            'purchase_patterns' => $orderData,
            'loyalty_points' => $this->calculateLoyaltyPoints($customer['lifetime_value']),
            'verification_status' => $this->getVerificationDetails($customer)
        ];
    }
    
    /**
     * Check if customer meets NZ compliance verification requirements
     */
    private function isCustomerVerified($profile)
    {
        return $profile['total_orders'] >= self::VERIFICATION_MIN_ORDERS 
            && $profile['lifetime_value'] >= self::VERIFICATION_MIN_VALUE;
    }
    
    /**
     * Get customer brand analysis from order data
     */
    private function getCustomerBrandAnalysis($customerId)
    {
        $brandData = $this->db->query("
            SELECT 
                p.brand,
                COUNT(*) as purchase_count,
                SUM(oi.quantity) as total_quantity,
                SUM(oi.total) as total_spent,
                AVG(oi.price) as avg_price,
                MAX(o.created_at) as last_purchase
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            JOIN products p ON oi.product_id = p.id
            WHERE o.customer_id = ? AND o.status != 'cancelled'
            GROUP BY p.brand
            HAVING COUNT(*) > 0
            ORDER BY total_spent DESC
            LIMIT 10
        ", [$customerId]);
        
        $totalSpent = array_sum(array_column($brandData, 'total_spent'));
        
        // Calculate percentages
        foreach ($brandData as &$brand) {
            $brand['percentage'] = $totalSpent > 0 ? ($brand['total_spent'] / $totalSpent) * 100 : 0;
            $brand['total_spent'] = (float)$brand['total_spent'];
            $brand['avg_price'] = (float)$brand['avg_price'];
        }
        
        return $brandData;
    }
    
    /**
     * Get personalized recommendations based on customer behavior
     */
    private function getPersonalizedRecommendations($customerId)
    {
        $recommendations = [];
        
        // 1. Reorder recommendations based on purchase patterns
        $reorderItems = $this->getReorderRecommendations($customerId);
        $recommendations['reorder'] = $reorderItems;
        
        // 2. Brand-based recommendations
        $brandRecs = $this->getBrandBasedRecommendations($customerId);
        $recommendations['brand_match'] = $brandRecs;
        
        // 3. Trending products in customer's preferred categories
        $trendingRecs = $this->getTrendingRecommendations($customerId);
        $recommendations['trending'] = $trendingRecs;
        
        // 4. Cross-sell recommendations
        $crossSellRecs = $this->getCrossSellRecommendations($customerId);
        $recommendations['cross_sell'] = $crossSellRecs;
        
        return $recommendations;
    }
    
    /**
     * Get reorder recommendations based on purchase history
     */
    private function getReorderRecommendations($customerId)
    {
        $reorderItems = $this->db->query("
            SELECT 
                p.id,
                p.name,
                p.brand,
                p.price,
                p.image_url,
                COUNT(*) as purchase_count,
                MAX(o.created_at) as last_purchased,
                AVG(DATEDIFF(o2.created_at, o.created_at)) as avg_reorder_days,
                DATEDIFF(NOW(), MAX(o.created_at)) as days_since_last
            FROM products p
            JOIN order_items oi ON p.id = oi.product_id
            JOIN orders o ON oi.order_id = o.id
            LEFT JOIN orders o2 ON o2.customer_id = o.customer_id AND o2.created_at > o.created_at
            WHERE o.customer_id = ? AND o.status != 'cancelled'
            GROUP BY p.id
            HAVING purchase_count >= 2 
               AND days_since_last >= (avg_reorder_days * 0.8)
            ORDER BY (days_since_last / COALESCE(avg_reorder_days, 14)) DESC
            LIMIT 5
        ", [$customerId]);
        
        foreach ($reorderItems as &$item) {
            $item['reorder_probability'] = $this->calculateReorderProbability($item);
            $item['price'] = (float)$item['price'];
        }
        
        return $reorderItems;
    }
    
    /**
     * Get trending products based on recent website activity
     */
    private function getTrendingRecommendations($customerId)
    {
        // Get customer's preferred categories
        $preferredCategories = $this->db->query("
            SELECT 
                pc.category_id,
                c.name as category_name,
                COUNT(*) as purchase_count
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            JOIN product_categories pc ON oi.product_id = pc.product_id
            JOIN categories c ON pc.category_id = c.id
            WHERE o.customer_id = ? AND o.status != 'cancelled'
            GROUP BY pc.category_id
            ORDER BY purchase_count DESC
            LIMIT 3
        ", [$customerId]);
        
        if (empty($preferredCategories)) {
            return [];
        }
        
        $categoryIds = array_column($preferredCategories, 'category_id');
        $placeholders = str_repeat('?,', count($categoryIds) - 1) . '?';
        
        $trending = $this->db->query("
            SELECT 
                p.id,
                p.name,
                p.brand,
                p.price,
                p.image_url,
                COUNT(DISTINCT pv.session_id) as recent_views,
                COUNT(DISTINCT oi.order_id) as recent_orders,
                (COUNT(DISTINCT oi.order_id) * 100.0 / COUNT(DISTINCT pv.session_id)) as conversion_rate
            FROM products p
            JOIN product_categories pc ON p.id = pc.product_id
            LEFT JOIN product_views pv ON p.id = pv.product_id 
                AND pv.viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            LEFT JOIN order_items oi ON p.id = oi.product_id
            LEFT JOIN orders o ON oi.order_id = o.id 
                AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                AND o.status != 'cancelled'
            WHERE pc.category_id IN ({$placeholders})
                AND p.is_active = 1
                AND p.stock_quantity > 0
            GROUP BY p.id
            HAVING recent_views >= 10
            ORDER BY recent_views DESC, conversion_rate DESC
            LIMIT 5
        ", $categoryIds);
        
        foreach ($trending as &$item) {
            $item['price'] = (float)$item['price'];
            $item['conversion_rate'] = (float)$item['conversion_rate'];
        }
        
        return $trending;
    }
    
    /**
     * Calculate reorder probability based on purchase patterns
     */
    private function calculateReorderProbability($item)
    {
        $avgDays = $item['avg_reorder_days'] ?? 14;
        $daysSince = $item['days_since_last'];
        
        if ($avgDays <= 0) return 50;
        
        // Higher probability as we approach or exceed average reorder time
        $probability = min(100, ($daysSince / $avgDays) * 100);
        
        return round($probability);
    }
    
    /**
     * Calculate customer loyalty tier
     */
    private function calculateCustomerTier($lifetimeValue)
    {
        if ($lifetimeValue >= self::TIER_VIP) return 'VIP';
        if ($lifetimeValue >= self::TIER_GOLD) return 'Gold';
        if ($lifetimeValue >= self::TIER_SILVER) return 'Silver';
        if ($lifetimeValue >= self::TIER_BRONZE) return 'Bronze';
        return 'New';
    }
    
    /**
     * Get authenticated customer ID (implement based on website session system)
     */
    private function getAuthenticatedCustomerId()
    {
        // This would integrate with VapeShed website authentication
        // For now, returning test customer ID
        
        if (isset($_SESSION['customer_id'])) {
            return $_SESSION['customer_id'];
        }
        
        // Check for API token or other auth method
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            // Validate token and return customer ID
            // Implementation depends on website auth system
        }
        
        // For testing - remove in production
        if (isset($_GET['test_customer_id'])) {
            return (int)$_GET['test_customer_id'];
        }
        
        return null;
    }
    
    /**
     * Get content access levels based on verification status
     */
    private function getContentAccess($isVerified)
    {
        return [
            'can_view_nicotine_products' => $isVerified,
            'can_see_product_images' => $isVerified,
            'can_view_detailed_descriptions' => $isVerified,
            'has_personalized_recommendations' => $isVerified,
            'has_inbox_access' => $isVerified,
            'can_view_pricing' => true, // Always allowed
            'can_browse_categories' => true, // Always allowed
            'compliance_level' => $isVerified ? 'full' : 'restricted'
        ];
    }
    
    /**
     * Get unread message count for customer
     */
    private function getUnreadMessageCount($customerId)
    {
        return $this->db->queryValue("
            SELECT COUNT(*) 
            FROM customer_messages 
            WHERE customer_id = ? AND is_read = 0
        ", [$customerId]) ?? 0;
    }
    
    /**
     * Return JSON response with proper headers
     */
    private function jsonResponse($data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
    
    /**
     * Get guest content for non-verified users
     */
    private function getGuestContent()
    {
        return [
            'welcome_message' => 'Welcome to The Vape Shed - New Zealand\'s premier vaping destination',
            'featured_categories' => ['Hardware', 'Accessories', 'Starter Kits'],
            'compliance_notice' => 'Complete your first purchase to unlock full product catalog and personalized recommendations',
            'trust_signals' => [
                '17 stores across New Zealand',
                'Authentic products only',
                'Expert staff support',
                'Competitive pricing'
            ]
        ];
    }
    
    /**
     * Get hero content based on customer status
     */
    private function getHeroContent($isVerified, $customerId = null)
    {
        if (!$isVerified) {
            return [
                'title' => 'Welcome to The Vape Shed',
                'subtitle' => 'New Zealand\'s Premier Vaping Destination',
                'cta_text' => 'Start Your Vaping Journey',
                'cta_link' => '/products',
                'background_image' => '/images/hero-guest.jpg'
            ];
        }
        
        // Personalized hero for verified customers
        $profile = $this->buildCustomerProfile($customerId);
        
        return [
            'title' => "Welcome Back, " . explode(' ', $profile['name'])[0] . "!",
            'subtitle' => 'Your personalized vaping experience awaits',
            'cta_text' => 'View My Recommendations',
            'cta_link' => '/dashboard',
            'background_image' => '/images/hero-verified.jpg',
            'stats' => [
                'orders' => $profile['total_orders'],
                'tier' => $profile['tier'],
                'days_active' => $this->calculateDaysActive($profile['first_order_date'])
            ]
        ];
    }
    
    /**
     * Calculate days active since first order
     */
    private function calculateDaysActive($firstOrderDate)
    {
        if (!$firstOrderDate) return 0;
        
        $first = new DateTime($firstOrderDate);
        $now = new DateTime();
        return $now->diff($first)->days;
    }
    
    /**
     * Handle routing for different endpoints
     */
    public function handleRequest()
    {
        $path = $_SERVER['REQUEST_URI'];
        $method = $_SERVER['REQUEST_METHOD'];
        
        // Remove query string
        $path = strtok($path, '?');
        
        switch ($path) {
            case '/api/customer/profile':
                echo $this->getCustomerProfile();
                break;
                
            case '/api/homepage/content':
                echo $this->getHomepageContent();
                break;
                
            case '/api/customer/inbox':
                if ($method === 'GET') {
                    echo $this->getInboxMessages();
                } elseif ($method === 'POST') {
                    echo $this->sendInboxMessage();
                }
                break;
                
            case '/api/customer/recommendations':
                echo $this->getRecommendations();
                break;
                
            case '/api/health':
                echo $this->jsonResponse(['status' => 'healthy', 'timestamp' => date('c')]);
                break;
                
            default:
                http_response_code(404);
                echo $this->jsonResponse(['error' => 'Endpoint not found'], 404);
        }
    }
}

// Handle the request if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $controller = new WebsiteIntegrationController();
    $controller->handleRequest();
}