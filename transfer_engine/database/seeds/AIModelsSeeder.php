<?php

/**
 * AI Models Database Seeder
 * 
 * Seeds the database with:
 * - Sample historical transfer data for AI training
 * - Sample inventory snapshots for forecasting
 * - Configuration for AI models
 * - Baseline statistics for anomaly detection
 * 
 * @package     VapeShed Transfer Engine
 * @subpackage  Database\Seeds
 * @version     1.0.0
 * @author      Ecigdis Limited Engineering Team
 * @copyright   2025 Ecigdis Limited
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use App\Core\Database;
use App\Core\Logger;

class AIModelsSeeder
{
    private Database $db;
    private Logger $logger;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger('seeder');
    }
    
    /**
     * Run the seeder
     */
    public function run(): void
    {
        echo "🤖 Starting AI Models Database Seeder...\n\n";
        
        try {
            $this->seedAIConfig();
            $this->seedHistoricalData();
            $this->seedInventorySnapshots();
            $this->seedPatternCache();
            
            echo "\n✅ AI Models Database Seeding Complete!\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "Summary:\n";
            echo "  - AI configuration seeded\n";
            echo "  - 90 days of historical transfer data\n";
            echo "  - Daily inventory snapshots\n";
            echo "  - Pattern cache initialized\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            
        } catch (Exception $e) {
            echo "\n❌ Seeding Failed: " . $e->getMessage() . "\n";
            $this->logger->error("AI seeding failed", ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Seed AI configuration
     */
    private function seedAIConfig(): void
    {
        echo "📋 Seeding AI Configuration...\n";
        
        $configs = [
            [
                'key' => 'ai.forecasting.enabled',
                'value' => '1',
                'description' => 'Enable AI forecasting service'
            ],
            [
                'key' => 'ai.forecasting.min_data_points',
                'value' => '30',
                'description' => 'Minimum data points required for forecasting'
            ],
            [
                'key' => 'ai.forecasting.default_horizon',
                'value' => '30',
                'description' => 'Default forecast horizon in days'
            ],
            [
                'key' => 'ai.anomaly_detection.enabled',
                'value' => '1',
                'description' => 'Enable anomaly detection'
            ],
            [
                'key' => 'ai.anomaly_detection.z_score_threshold',
                'value' => '3.0',
                'description' => 'Z-score threshold for anomaly detection'
            ],
            [
                'key' => 'ai.pattern_recognition.enabled',
                'value' => '1',
                'description' => 'Enable pattern recognition'
            ],
            [
                'key' => 'ai.optimization.enabled',
                'value' => '1',
                'description' => 'Enable optimization engine'
            ],
            [
                'key' => 'ai.cache_ttl',
                'value' => '3600',
                'description' => 'AI cache TTL in seconds'
            ]
        ];
        
        foreach ($configs as $config) {
            $sql = "
                INSERT INTO system_config (config_key, config_value, description, created_at)
                VALUES (:key, :value, :description, NOW())
                ON DUPLICATE KEY UPDATE 
                    config_value = :value,
                    description = :description,
                    updated_at = NOW()
            ";
            
            $this->db->query($sql, [
                'key' => $config['key'],
                'value' => $config['value'],
                'description' => $config['description']
            ]);
        }
        
        echo "  ✓ AI configuration seeded\n";
    }
    
    /**
     * Seed historical transfer data for AI training
     */
    private function seedHistoricalData(): void
    {
        echo "📊 Seeding Historical Transfer Data (90 days)...\n";
        
        // Get existing stores and products
        $stores = $this->db->query("SELECT store_id FROM stores LIMIT 10");
        $products = $this->db->query("SELECT product_id, name, sku FROM products LIMIT 50");
        
        if (empty($stores) || empty($products)) {
            echo "  ⚠️  No stores or products found. Skipping historical data.\n";
            return;
        }
        
        $storeIds = array_column($stores, 'store_id');
        $productData = $products;
        
        // Get admin user
        $adminUser = $this->db->query("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1");
        $createdBy = $adminUser[0]['user_id'] ?? 1;
        
        $transfersCreated = 0;
        $itemsCreated = 0;
        
        // Generate 90 days of historical data
        for ($day = 90; $day >= 0; $day--) {
            $date = date('Y-m-d', strtotime("-{$day} days"));
            
            // Random number of transfers per day (3-10)
            $dailyTransfers = rand(3, 10);
            
            for ($i = 0; $i < $dailyTransfers; $i++) {
                // Random stores
                $fromStoreId = $storeIds[array_rand($storeIds)];
                $toStoreId = $storeIds[array_rand($storeIds)];
                
                // Ensure different stores
                while ($toStoreId == $fromStoreId) {
                    $toStoreId = $storeIds[array_rand($storeIds)];
                }
                
                // Random time on the day
                $hour = rand(8, 17); // Business hours
                $minute = rand(0, 59);
                $createdAt = "{$date} {$hour}:{$minute}:00";
                
                // Create transfer
                $reference = 'TR-' . date('Ymd', strtotime($createdAt)) . '-' . str_pad($transfersCreated + 1, 4, '0', STR_PAD_LEFT);
                
                $transferSql = "
                    INSERT INTO transfers (
                        reference, 
                        from_store_id, 
                        to_store_id, 
                        status, 
                        created_by, 
                        created_at,
                        approved_at,
                        completed_at
                    ) VALUES (
                        :reference, 
                        :from_store_id, 
                        :to_store_id, 
                        'completed', 
                        :created_by, 
                        :created_at,
                        DATE_ADD(:created_at, INTERVAL FLOOR(RAND() * 24) HOUR),
                        DATE_ADD(:created_at, INTERVAL FLOOR(RAND() * 72) HOUR)
                    )
                ";
                
                $this->db->query($transferSql, [
                    'reference' => $reference,
                    'from_store_id' => $fromStoreId,
                    'to_store_id' => $toStoreId,
                    'created_by' => $createdBy,
                    'created_at' => $createdAt
                ]);
                
                $transferId = $this->db->lastInsertId();
                $transfersCreated++;
                
                // Add 2-8 items per transfer
                $itemCount = rand(2, 8);
                
                for ($j = 0; $j < $itemCount; $j++) {
                    $product = $productData[array_rand($productData)];
                    
                    // Random quantity (1-50)
                    $quantity = rand(1, 50);
                    
                    // Random unit price ($5-$100)
                    $unitPrice = rand(500, 10000) / 100;
                    
                    $itemSql = "
                        INSERT INTO transfer_items (
                            transfer_id,
                            product_id,
                            quantity,
                            unit_price,
                            created_at
                        ) VALUES (
                            :transfer_id,
                            :product_id,
                            :quantity,
                            :unit_price,
                            :created_at
                        )
                    ";
                    
                    $this->db->query($itemSql, [
                        'transfer_id' => $transferId,
                        'product_id' => $product['product_id'],
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'created_at' => $createdAt
                    ]);
                    
                    $itemsCreated++;
                }
            }
            
            // Progress indicator
            if ($day % 10 == 0) {
                echo "  • Generated data for day -$day...\n";
            }
        }
        
        echo "  ✓ Created {$transfersCreated} transfers with {$itemsCreated} items\n";
    }
    
    /**
     * Seed inventory snapshots
     */
    private function seedInventorySnapshots(): void
    {
        echo "📦 Seeding Inventory Snapshots...\n";
        
        $stores = $this->db->query("SELECT store_id FROM stores LIMIT 10");
        $products = $this->db->query("SELECT product_id FROM products LIMIT 50");
        
        if (empty($stores) || empty($products)) {
            echo "  ⚠️  No stores or products found. Skipping inventory snapshots.\n";
            return;
        }
        
        $storeIds = array_column($stores, 'store_id');
        $productIds = array_column($products, 'product_id');
        
        $snapshotsCreated = 0;
        
        // Generate daily snapshots for last 90 days
        for ($day = 90; $day >= 0; $day--) {
            $date = date('Y-m-d', strtotime("-{$day} days"));
            
            foreach ($storeIds as $storeId) {
                foreach ($productIds as $productId) {
                    // Generate realistic inventory levels (0-200 with trend)
                    $baseQuantity = rand(20, 100);
                    $trend = ($day / 90) * rand(-20, 20); // Slight trend over time
                    $noise = rand(-10, 10); // Daily variation
                    
                    $quantity = max(0, $baseQuantity + $trend + $noise);
                    
                    $sql = "
                        INSERT INTO inventory_snapshots (
                            store_id,
                            product_id,
                            quantity,
                            snapshot_date,
                            created_at
                        ) VALUES (
                            :store_id,
                            :product_id,
                            :quantity,
                            :snapshot_date,
                            :snapshot_date
                        )
                        ON DUPLICATE KEY UPDATE
                            quantity = :quantity
                    ";
                    
                    $this->db->query($sql, [
                        'store_id' => $storeId,
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'snapshot_date' => $date
                    ]);
                    
                    $snapshotsCreated++;
                }
            }
            
            if ($day % 15 == 0) {
                echo "  • Generated snapshots for day -$day...\n";
            }
        }
        
        echo "  ✓ Created {$snapshotsCreated} inventory snapshots\n";
    }
    
    /**
     * Seed pattern cache (for faster initial loads)
     */
    private function seedPatternCache(): void
    {
        echo "🔍 Initializing Pattern Cache...\n";
        
        // This would normally be generated by PatternRecognition service
        // For seeding, we create a simplified version
        
        $cacheData = [
            'route_patterns' => json_encode([
                [
                    'from_store_id' => 1,
                    'to_store_id' => 2,
                    'frequency' => 45,
                    'pattern_type' => 'high_frequency'
                ]
            ]),
            'temporal_patterns' => json_encode([
                'peak_hours' => [10, 11, 14, 15],
                'peak_days' => ['Tuesday', 'Wednesday', 'Thursday']
            ]),
            'last_analysis' => date('Y-m-d H:i:s')
        ];
        
        foreach ($cacheData as $key => $value) {
            $sql = "
                INSERT INTO cache (
                    cache_key,
                    cache_value,
                    expires_at,
                    created_at
                ) VALUES (
                    :key,
                    :value,
                    DATE_ADD(NOW(), INTERVAL 1 HOUR),
                    NOW()
                )
                ON DUPLICATE KEY UPDATE
                    cache_value = :value,
                    expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR),
                    updated_at = NOW()
            ";
            
            $this->db->query($sql, [
                'key' => "ai:pattern:{$key}",
                'value' => $value
            ]);
        }
        
        echo "  ✓ Pattern cache initialized\n";
    }
}

// Run seeder if executed directly
if (php_sapi_name() === 'cli') {
    $seeder = new AIModelsSeeder();
    $seeder->run();
}
