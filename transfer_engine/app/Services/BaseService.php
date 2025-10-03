<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Base Service Class
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 * @description Base service class with common functionality
 */
abstract class BaseService
{
    protected PDO $db;
    protected array $config;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->config = [];
    }
    
    /**
     * Log error with context
     */
    protected function logError(string $message, \Exception $e): void
    {
        error_log("ERROR [{$message}]: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    }
    
    /**
     * Log activity
     */
    protected function logActivity(string $type, array $data): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_log (type, data, created_at) 
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$type, json_encode($data)]);
        } catch (\Exception $e) {
            $this->logError('Failed to log activity', $e);
        }
    }
    
    /**
     * Execute shell command safely
     */
    protected function executeCommand(string $command): array
    {
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);
        
        return [
            'success' => $returnCode === 0,
            'output' => implode("\n", $output),
            'return_code' => $returnCode
        ];
    }
    
    /**
     * Check if process is running
     */
    protected function isProcessRunning(string $processName): bool
    {
        $result = $this->executeCommand("pgrep -f '$processName'");
        return $result['success'] && !empty(trim($result['output']));
    }
}