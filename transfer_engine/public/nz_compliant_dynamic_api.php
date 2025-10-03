<?php
/**
 * NZ Compliance-First Dynamic Website API
 * 
 * Provides AI-powered personalized content while respecting NZ vaping advertising laws
 * Features: Customer verification, personalized dashboards, compliant messaging
 * 
 * Author: AI Enhanced System
 * Created: 2025-09-26
 */

require_once __DIR__ . '/../scripts/website_ai_enhancement_engine.php';

class NZCompliantDynamicWebsiteAPI {
    
    private $vapeShedDb;
    private $aiEngine;
    
    public function __construct() {
        $this->vapeShedDb = $this->connectToVapeShedSQL();
        $this->aiEngine = new WebsiteAIEnhancementEngine();
    }
    
    private function connectToVapeShedSQL() {
        try {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $connection = mysqli_init();
            mysqli_options($connection, MYSQLI_OPT_CONNECT_TIMEOUT, 5);
            
            $connected = mysqli_real_connect(
                $connection,
                getenv('VS_DB_HOST') ?: '127.0.0.1',
                getenv('VS_DB_USER') ?: 'dvaxgvsxmz',
                getenv('VS_DB_PASS') ?: '49X95DwdPf',
                getenv('VS_DB_NAME') ?: 'dvaxgvsxmz'
            );
            
            if (!$connected) {
                throw new Exception("VapeShed connection failed: " . mysqli_connect_error());
            }
            
            mysqli_set_charset($connection, "utf8mb4");
            return $connection;
            
        } catch (Exception $e) {
            error_log("VapeShed database connection failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check customer verification status for NZ compliance
     */
    public function checkCustomerVerificationStatus($customerId) {
        if (!$this->vapeShedDb) return ['error' => 'Database connection failed'];
        
        try {
            // Check if customer has made at least 1 purchase (verified customer)
            $result = mysqli_query($this->vapeShedDb, "
                SELECT 
                    c.id,
                    c.first_name,
                    c.last_name,
                    c.email,
                    COUNT(o.order_id) as total_orders,
                    SUM(o.final_total) as lifetime_value,
                    MAX(o.order_created) as last_order_date,
                    c.date_created as customer_since
                FROM customers c
                LEFT JOIN orders o ON c.id = o.customer_id AND o.order_status NOT IN (5, 6)
                WHERE c.id = " . intval($customerId) . "
                GROUP BY c.id
            ");
            
            $customer = mysqli_fetch_assoc($result);
            
            if (!$customer) {
                return ['verified' => false, 'error' => 'Customer not found'];
            }
            
            $isVerified = $customer['total_orders'] > 0;
            
            return [
                'verified' => $isVerified,
                'customer' => [
                    'id' => $customer['id'],
                    'name' => $customer['first_name'] . ' ' . $customer['last_name'],
                    'email' => $customer['email'],
                    'total_orders' => $customer['total_orders'],
                    'lifetime_value' => $customer['lifetime_value'],
                    'last_order' => $customer['last_order_date'],
                    'customer_since' => $customer['customer_since']
                ],
                'content_level' => $isVerified ? 'full_access' : 'restricted',
                'compliance_note' => $isVerified ? 
                    'Full product information available - verified customer' : 
                    'Limited product information - purchase required for full access'
            ];
            
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate personalized customer dashboard data
     */
    public function generatePersonalizedDashboard($customerId) {
        $verificationStatus = $this->checkCustomerVerificationStatus($customerId);
        
        if (!$verificationStatus['verified']) {
            return [
                'access_level' => 'restricted',
                'message' => 'Complete your first purchase to unlock personalized statistics and recommendations',
                'available_features' => ['basic_browsing', 'search', 'age_verification']
            ];
        }
        
        return [
            'access_level' => 'full',
            'customer_profile' => $verificationStatus['customer'],
            'personal_statistics' => $this->generatePersonalStatistics($customerId),
            'favorite_brands' => $this->analyzeFavoriteBrands($customerId),
            'personalized_recommendations' => $this->generatePersonalizedRecommendations($customerId),
            'purchase_insights' => $this->generatePurchaseInsights($customerId),
            'loyalty_metrics' => $this->calculateLoyaltyMetrics($customerId),
            'website_inbox_messages' => $this->getWebsiteInboxMessages($customerId),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    private function generatePersonalStatistics($customerId) {
        if (!$this->vapeShedDb) return ['error' => 'Database connection failed'];
        
        try {
            // Customer's vaping journey statistics
            $result = mysqli_query($this->vapeShedDb, "
                SELECT 
                    COUNT(DISTINCT o.order_id) as total_orders,
                    SUM(op.product_qty) as total_products_purchased,
                    COUNT(DISTINCT op.product_id) as unique_products_tried,
                    SUM(o.final_total) as total_spent,
                    AVG(o.final_total) as avg_order_value,
                    MIN(o.order_created) as first_order_date,
                    MAX(o.order_created) as latest_order_date,
                    DATEDIFF(NOW(), MIN(o.order_created)) as customer_journey_days
                FROM orders o
                JOIN orders_products op ON o.order_id = op.order_id
                WHERE o.customer_id = " . intval($customerId) . "
                  AND o.order_status NOT IN (5, 6)
            ");
            
            $stats = mysqli_fetch_assoc($result);
            
            // Monthly spending pattern
            $result = mysqli_query($this->vapeShedDb, "
                SELECT 
                    DATE_FORMAT(order_created, '%Y-%m') as month,
                    COUNT(*) as orders_count,
                    SUM(final_total) as monthly_spending
                FROM orders
                WHERE customer_id = " . intval($customerId) . "
                  AND order_status NOT IN (5, 6)
                  AND order_created >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(order_created, '%Y-%m')
                ORDER BY month DESC
            ");
            
            $monthly_pattern = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $monthly_pattern[] = $row;
            }
            
            return [
                'vaping_journey' => $stats,
                'monthly_spending_pattern' => $monthly_pattern,
                'customer_tier' => $this->calculateCustomerTier($stats['total_spent'], $stats['total_orders']),
                'savings_achieved' => $this->calculateSavingsVsTraditional($stats['total_products_purchased'])
            ];
            
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    private function analyzeFavoriteBrands($customerId) {
        if (!$this->vapeShedDb) return ['error' => 'Database connection failed'];
        
        try {
            // Analyze purchase patterns to identify favorite brands/categories
            $result = mysqli_query($this->vapeShedDb, "
                SELECT 
                    CASE 
                        WHEN op.product_name LIKE '%IGET%' OR op.product_name LIKE '%I GET%' THEN 'IGET'
                        WHEN op.product_name LIKE '%Vuse%' THEN 'Vuse'
                        WHEN op.product_name LIKE '%Just Juice%' THEN 'Just Juice'
                        WHEN op.product_name LIKE '%Geekvape%' THEN 'Geekvape'
                        WHEN op.product_name LIKE '%Vaporesso%' THEN 'Vaporesso'
                        WHEN op.product_name LIKE '%SMOK%' THEN 'SMOK'
                        WHEN op.product_name LIKE '%Disposvape%' THEN 'Disposvape'
                        WHEN op.product_name LIKE '%Nasty%' THEN 'Nasty Juice'
                        ELSE 'Other'
                    END as brand,
                    COUNT(*) as purchase_count,
                    SUM(op.product_qty) as total_quantity,
                    SUM(op.product_price * op.product_qty) as brand_spending,
                    AVG(op.product_price) as avg_price_paid,
                    MAX(o.order_created) as last_purchase
                FROM orders o
                JOIN orders_products op ON o.order_id = op.order_id
                WHERE o.customer_id = " . intval($customerId) . "
                  AND o.order_status NOT IN (5, 6)
                GROUP BY brand
                ORDER BY brand_spending DESC
            ");
            
            $brand_preferences = [];
            $total_spending = 0;
            while ($row = mysqli_fetch_assoc($result)) {
                $brand_preferences[] = $row;
                $total_spending += $row['brand_spending'];
            }
            
            // Calculate brand loyalty percentages
            foreach ($brand_preferences as &$brand) {
                $brand['loyalty_percentage'] = $total_spending > 0 ? 
                    round(($brand['brand_spending'] / $total_spending) * 100, 1) : 0;
            }
            
            // Product category analysis
            $result = mysqli_query($this->vapeShedDb, "
                SELECT 
                    CASE 
                        WHEN op.product_name LIKE '%disposable%' OR op.product_name LIKE '%puff%' THEN 'Disposable Vapes'
                        WHEN op.product_name LIKE '%juice%' OR op.product_name LIKE '%liquid%' OR op.product_name LIKE '%salt%' THEN 'E-Liquids'
                        WHEN op.product_name LIKE '%coil%' OR op.product_name LIKE '%pod%' OR op.product_name LIKE '%cartridge%' THEN 'Coils & Pods'
                        WHEN op.product_name LIKE '%kit%' OR op.product_name LIKE '%mod%' OR op.product_name LIKE '%device%' THEN 'Vape Kits & Devices'
                        ELSE 'Accessories'
                    END as category,
                    COUNT(*) as purchase_count,
                    SUM(op.product_qty) as quantity_purchased,
                    SUM(op.product_price * op.product_qty) as category_spending
                FROM orders o
                JOIN orders_products op ON o.order_id = op.order_id
                WHERE o.customer_id = " . intval($customerId) . "
                  AND o.order_status NOT IN (5, 6)
                GROUP BY category
                ORDER BY category_spending DESC
            ");
            
            $category_preferences = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $row['preference_percentage'] = $total_spending > 0 ? 
                    round(($row['category_spending'] / $total_spending) * 100, 1) : 0;
                $category_preferences[] = $row;
            }
            
            return [
                'favorite_brands' => $brand_preferences,
                'category_preferences' => $category_preferences,
                'dominant_brand' => $brand_preferences[0]['brand'] ?? 'Various',
                'preferred_category' => $category_preferences[0]['category'] ?? 'Various'
            ];
            
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    private function generatePersonalizedRecommendations($customerId) {
        if (!$this->vapeShedDb) return ['error' => 'Database connection failed'];
        
        try {
            // AI-powered recommendations based on purchase history and trends
            
            // Find similar customers (collaborative filtering)
            $result = mysqli_query($this->vapeShedDb, "
                SELECT 
                    p.product_id,
                    p.name,
                    p.sku,
                    COUNT(*) as popularity_score,
                    AVG(op.product_price) as avg_price
                FROM customers c1
                JOIN orders o1 ON c1.id = o1.customer_id
                JOIN orders_products op1 ON o1.order_id = op1.order_id
                JOIN customers c2 ON c1.id != c2.id  -- Find other customers
                JOIN orders o2 ON c2.id = o2.customer_id AND o2.order_status NOT IN (5, 6)
                JOIN orders_products op2 ON o2.order_id = op2.order_id
                JOIN products p ON op2.product_id = p.product_id
                WHERE c1.id = " . intval($customerId) . "
                  AND op1.product_id = op2.product_id  -- Customers who bought same products
                  AND p.product_id NOT IN (
                      -- Exclude products customer already purchased
                      SELECT DISTINCT op3.product_id 
                      FROM orders o3 
                      JOIN orders_products op3 ON o3.order_id = op3.order_id
                      WHERE o3.customer_id = " . intval($customerId) . "
                  )
                GROUP BY p.product_id
                ORDER BY popularity_score DESC
                LIMIT 15
            ");
            
            $collaborative_recommendations = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $collaborative_recommendations[] = $row;
            }
            
            // Trending products in customer's favorite categories
            $favoriteCategories = $this->analyzeFavoriteBrands($customerId);
            $dominantBrand = $favoriteCategories['dominant_brand'] ?? '';
            
            $result = mysqli_query($this->vapeShedDb, "
                SELECT 
                    p.product_id,
                    p.name,
                    p.sku,
                    COUNT(pv.id) as recent_views,
                    COUNT(DISTINCT pv.user) as unique_viewers
                FROM products p
                LEFT JOIN products_viewed pv ON p.product_id = pv.product_id 
                WHERE pv.time_visited >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                  AND p.name LIKE '%" . mysqli_real_escape_string($this->vapeShedDb, $dominantBrand) . "%'
                  AND p.product_id NOT IN (
                      SELECT DISTINCT op.product_id 
                      FROM orders o 
                      JOIN orders_products op ON o.order_id = op.order_id
                      WHERE o.customer_id = " . intval($customerId) . "
                  )
                GROUP BY p.product_id
                ORDER BY recent_views DESC
                LIMIT 10
            ");
            
            $trending_in_brand = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $trending_in_brand[] = $row;
            }
            
            return [
                'based_on_similar_customers' => $collaborative_recommendations,
                'trending_in_your_brand' => $trending_in_brand,
                'recommendation_strategy' => 'AI collaborative filtering + brand preference analysis',
                'personalization_strength' => count($collaborative_recommendations) > 10 ? 'high' : 'medium'
            ];
            
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    private function generatePurchaseInsights($customerId) {
        if (!$this->vapeShedDb) return ['error' => 'Database connection failed'];
        
        try {
            // Purchase timing patterns
            $result = mysqli_query($this->vapeShedDb, "
                SELECT 
                    DAYNAME(order_created) as day_of_week,
                    HOUR(order_created) as hour_of_day,
                    COUNT(*) as order_count,
                    AVG(final_total) as avg_spend
                FROM orders
                WHERE customer_id = " . intval($customerId) . "
                  AND order_status NOT IN (5, 6)
                GROUP BY DAYOFWEEK(order_created), HOUR(order_created)
                ORDER BY order_count DESC
                LIMIT 10
            ");
            
            $timing_patterns = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $timing_patterns[] = $row;
            }
            
            // Reorder predictions based on purchase history
            $result = mysqli_query($this->vapeShedDb, "
                SELECT 
                    p.product_id,
                    p.name,
                    COUNT(*) as purchase_frequency,
                    AVG(DATEDIFF(o2.order_created, o1.order_created)) as avg_reorder_days,
                    MAX(o1.order_created) as last_purchased,
                    DATEDIFF(NOW(), MAX(o1.order_created)) as days_since_last_order
                FROM orders o1
                JOIN orders_products op1 ON o1.order_id = op1.order_id
                JOIN products p ON op1.product_id = p.product_id
                LEFT JOIN orders o2 ON o1.customer_id = o2.customer_id AND o2.order_created > o1.order_created
                WHERE o1.customer_id = " . intval($customerId) . "
                  AND o1.order_status NOT IN (5, 6)
                GROUP BY p.product_id
                HAVING purchase_frequency >= 2
                ORDER BY (days_since_last_order / NULLIF(avg_reorder_days, 0)) DESC
                LIMIT 10
            ");
            
            $reorder_predictions = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $reorder_likelihood = $row['avg_reorder_days'] > 0 ? 
                    min(($row['days_since_last_order'] / $row['avg_reorder_days']) * 100, 100) : 0;
                $row['reorder_likelihood'] = round($reorder_likelihood, 1);
                $reorder_predictions[] = $row;
            }
            
            return [
                'favorite_shopping_times' => $timing_patterns,
                'reorder_predictions' => $reorder_predictions,
                'next_likely_purchase' => $reorder_predictions[0] ?? null
            ];
            
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    private function calculateLoyaltyMetrics($customerId) {
        $verification = $this->checkCustomerVerificationStatus($customerId);
        if (!$verification['verified']) {
            return ['tier' => 'new_customer'];
        }
        
        $customer = $verification['customer'];
        $tier = $this->calculateCustomerTier($customer['lifetime_value'], $customer['total_orders']);
        
        // Calculate loyalty rewards points (example: $1 = 1 point)
        $loyaltyPoints = floor($customer['lifetime_value']);
        
        return [
            'current_tier' => $tier,
            'lifetime_value' => $customer['lifetime_value'],
            'total_orders' => $customer['total_orders'],
            'loyalty_points' => $loyaltyPoints,
            'next_tier_requirement' => $this->getNextTierRequirement($tier),
            'customer_since' => $customer['customer_since'],
            'days_active' => floor((time() - strtotime($customer['customer_since'])) / 86400)
        ];
    }
    
    private function getWebsiteInboxMessages($customerId) {
        if (!$this->vapeShedDb) return ['error' => 'Database connection failed'];
        
        try {
            // Check if website inbox messages table exists, if not create structure for it
            $result = mysqli_query($this->vapeShedDb, "SHOW TABLES LIKE 'customer_inbox_messages'");
            
            if (mysqli_num_rows($result) == 0) {
                // Create inbox messages table structure (example)
                return [
                    'messages' => [],
                    'unread_count' => 0,
                    'note' => 'Website inbox system ready for implementation'
                ];
            }
            
            // If table exists, fetch messages
            $result = mysqli_query($this->vapeShedDb, "
                SELECT 
                    id,
                    subject,
                    message,
                    message_type,
                    created_at,
                    read_status,
                    priority
                FROM customer_inbox_messages
                WHERE customer_id = " . intval($customerId) . "
                ORDER BY created_at DESC
                LIMIT 20
            ");
            
            $messages = [];
            $unreadCount = 0;
            while ($row = mysqli_fetch_assoc($result)) {
                if ($row['read_status'] == 0) $unreadCount++;
                $messages[] = $row;
            }
            
            return [
                'messages' => $messages,
                'unread_count' => $unreadCount,
                'total_messages' => count($messages)
            ];
            
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    private function calculateCustomerTier($lifetimeValue, $totalOrders) {
        if ($lifetimeValue >= 2000 || $totalOrders >= 50) {
            return 'VIP';
        } elseif ($lifetimeValue >= 1000 || $totalOrders >= 20) {
            return 'Gold';
        } elseif ($lifetimeValue >= 500 || $totalOrders >= 10) {
            return 'Silver';
        } elseif ($lifetimeValue >= 100 || $totalOrders >= 3) {
            return 'Bronze';
        } else {
            return 'New Customer';
        }
    }
    
    private function getNextTierRequirement($currentTier) {
        $requirements = [
            'New Customer' => ['spend' => 100, 'orders' => 3, 'next_tier' => 'Bronze'],
            'Bronze' => ['spend' => 500, 'orders' => 10, 'next_tier' => 'Silver'],
            'Silver' => ['spend' => 1000, 'orders' => 20, 'next_tier' => 'Gold'],
            'Gold' => ['spend' => 2000, 'orders' => 50, 'next_tier' => 'VIP'],
            'VIP' => ['spend' => null, 'orders' => null, 'next_tier' => 'Maximum Level Achieved']
        ];
        
        return $requirements[$currentTier] ?? null;
    }
    
    private function calculateSavingsVsTraditional($totalProducts) {
        // Rough calculation: assume average cigarette equivalent savings
        $avgCigarettePacksReplaced = $totalProducts * 2; // Rough estimate
        $avgPackPrice = 35; // NZ cigarette pack price estimate
        $estimatedSavings = $avgCigarettePacksReplaced * $avgPackPrice;
        
        return [
            'estimated_cigarette_packs_avoided' => $avgCigarettePacksReplaced,
            'estimated_savings_nzd' => $estimatedSavings,
            'note' => 'Estimated savings compared to traditional cigarettes'
        ];
    }
    
    /**
     * Website Inbox Message System (NZ Compliant)
     */
    public function sendWebsiteInboxMessage($customerId, $subject, $message, $messageType = 'info', $priority = 'normal') {
        if (!$this->vapeShedDb) return ['error' => 'Database connection failed'];
        
        try {
            // Create message in customer's website inbox
            $result = mysqli_query($this->vapeShedDb, "
                INSERT INTO customer_inbox_messages 
                (customer_id, subject, message, message_type, priority, created_at, read_status)
                VALUES (
                    " . intval($customerId) . ",
                    '" . mysqli_real_escape_string($this->vapeShedDb, $subject) . "',
                    '" . mysqli_real_escape_string($this->vapeShedDb, $message) . "',
                    '" . mysqli_real_escape_string($this->vapeShedDb, $messageType) . "',
                    '" . mysqli_real_escape_string($this->vapeShedDb, $priority) . "',
                    NOW(),
                    0
                )
            ");
            
            return [
                'success' => true,
                'message_id' => mysqli_insert_id($this->vapeShedDb),
                'note' => 'Message delivered to customer website inbox'
            ];
            
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * NZ Compliant Dynamic Homepage Content
     */
    public function getDynamicHomepageContent($customerId = null, $sessionToken = null) {
        $isVerifiedCustomer = false;
        $personalizationData = null;
        
        if ($customerId) {
            $verification = $this->checkCustomerVerificationStatus($customerId);
            $isVerifiedCustomer = $verification['verified'];
            
            if ($isVerifiedCustomer) {
                $personalizationData = $this->generatePersonalizedRecommendations($customerId);
            }
        }
        
        // Get trending products (different content based on verification)
        $trending = $this->aiEngine->generateBusinessActionIntelligence()['trending_products_now'] ?? [];
        
        return [
            'compliance_level' => $isVerifiedCustomer ? 'full_access' : 'restricted',
            'content_type' => $isVerifiedCustomer ? 'personalized' : 'general',
            'hero_section' => [
                'show_images' => $isVerifiedCustomer,
                'content' => $isVerifiedCustomer ? 
                    'Welcome back! Here are your personalized recommendations' :
                    'Quality vaping products for verified customers'
            ],
            'featured_products' => $isVerifiedCustomer ? 
                ($personalizationData['based_on_similar_customers'] ?? []) :
                ['message' => 'Complete purchase to unlock personalized recommendations'],
            'trending_section' => $isVerifiedCustomer ? $trending : 
                ['message' => 'Trending products available after verification'],
            'personalization_strength' => $isVerifiedCustomer ? 'high' : 'none',
            'compliance_note' => 'Content served according to NZ vaping advertising regulations'
        ];
    }
}

// API Route Handler
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $api = new NZCompliantDynamicWebsiteAPI();
    $endpoint = $_GET['endpoint'] ?? '';
    
    header('Content-Type: application/json');
    
    switch ($endpoint) {
        case 'customer-dashboard':
            $customerId = intval($_GET['customer_id'] ?? 0);
            echo json_encode($api->generatePersonalizedDashboard($customerId));
            break;
            
        case 'verification-status':
            $customerId = intval($_GET['customer_id'] ?? 0);
            echo json_encode($api->checkCustomerVerificationStatus($customerId));
            break;
            
        case 'dynamic-homepage':
            $customerId = intval($_GET['customer_id'] ?? 0);
            echo json_encode($api->getDynamicHomepageContent($customerId));
            break;
            
        case 'inbox-messages':
            $customerId = intval($_GET['customer_id'] ?? 0);
            echo json_encode($api->getWebsiteInboxMessages($customerId));
            break;
            
        default:
            echo json_encode(['error' => 'Invalid endpoint']);
    }
    exit;
}

// CLI Testing
if (php_sapi_name() === 'cli') {
    echo "🎯 NZ COMPLIANT DYNAMIC WEBSITE API\n";
    echo "===================================\n\n";
    
    $api = new NZCompliantDynamicWebsiteAPI();
    
    // Test with a sample customer ID
    echo "📊 Testing Customer Dashboard (Customer ID: 1):\n";
    $dashboard = $api->generatePersonalizedDashboard(1);
    echo json_encode($dashboard, JSON_PRETTY_PRINT);
    
    echo "\n\n🏠 Testing Dynamic Homepage Content:\n";
    $homepage = $api->getDynamicHomepageContent(1);
    echo json_encode($homepage, JSON_PRETTY_PRINT);
}
?>