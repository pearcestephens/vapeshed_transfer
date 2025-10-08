#!/usr/bin/env php
<?php
declare(strict_types=1);
/**
 * Backup and Restore Tool
 * 
 * Database and file backup/restore utility.
 * 
 * Usage:
 *   php bin/backup.php create                    - Create full backup
 *   php bin/backup.php create --db-only          - Database only
 *   php bin/backup.php create --files-only       - Files only
 *   php bin/backup.php list                      - List all backups
 *   php bin/backup.php restore <backup_id>       - Restore backup
 *   php bin/backup.php cleanup --days=30         - Remove backups older than N days
 *   php bin/backup.php verify <backup_id>        - Verify backup integrity
 * 
 * @version 1.0.0
 * @date 2025-10-07
 */

require_once __DIR__ . '/../app/bootstrap.php';

use Unified\Support\{Pdo, Logger, NeuroContext};

$logger = new Logger('backup');
$command = $argv[1] ?? 'help';

// Parse options
$dbOnly = in_array('--db-only', $argv);
$filesOnly = in_array('--files-only', $argv);
$daysOpt = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--days=')) {
        $daysOpt = (int) substr($arg, 7);
    }
}

// Backup directory
$backupDir = defined('STORAGE_PATH') ? STORAGE_PATH . '/backups' : ROOT_PATH . '/var/backups';

if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0775, true);
}

switch ($command) {
    case 'create':
        echo "📦 Creating backup...\n\n";
        
        $backupId = date('Y-m-d_His');
        $backupPath = $backupDir . '/' . $backupId;
        
        if (!mkdir($backupPath, 0775, true)) {
            echo "❌ Failed to create backup directory\n";
            exit(1);
        }
        
        $manifest = [
            'id' => $backupId,
            'created_at' => date('c'),
            'type' => 'full',
            'files' => [],
        ];
        
        // Database backup
        if (!$filesOnly) {
            echo "🗄️  Backing up database...\n";
            
            try {
                $db = Pdo::instance();
                $config = require CONFIG_PATH . '/database.php';
                
                $dbFile = $backupPath . '/database.sql';
                $host = $config['host'] ?? 'localhost';
                $name = $config['database'] ?? '';
                $user = $config['username'] ?? '';
                $pass = $config['password'] ?? '';
                
                // Use mysqldump if available
                $mysqldump = shell_exec('which mysqldump');
                
                if ($mysqldump && trim($mysqldump) !== '') {
                    $cmd = sprintf(
                        'mysqldump -h%s -u%s -p%s %s > %s 2>&1',
                        escapeshellarg($host),
                        escapeshellarg($user),
                        escapeshellarg($pass),
                        escapeshellarg($name),
                        escapeshellarg($dbFile)
                    );
                    
                    exec($cmd, $output, $returnCode);
                    
                    if ($returnCode === 0 && file_exists($dbFile)) {
                        $size = filesize($dbFile);
                        echo "  ✅ Database backup created (" . formatBytes($size) . ")\n";
                        
                        // Compress
                        $compressed = gzencode(file_get_contents($dbFile), 9);
                        file_put_contents($dbFile . '.gz', $compressed);
                        unlink($dbFile);
                        
                        $manifest['files'][] = [
                            'path' => 'database.sql.gz',
                            'size' => strlen($compressed),
                            'type' => 'database',
                        ];
                        
                        echo "  ✅ Database compressed\n";
                    } else {
                        echo "  ❌ mysqldump failed\n";
                        echo "  Output: " . implode("\n", $output) . "\n";
                    }
                } else {
                    echo "  ⚠️  mysqldump not available, skipping database backup\n";
                }
                
            } catch (\Exception $e) {
                echo "  ❌ Database backup failed: " . $e->getMessage() . "\n";
            }
        }
        
        // Files backup
        if (!$dbOnly) {
            echo "\n📁 Backing up files...\n";
            
            $filesToBackup = [
                'config' => CONFIG_PATH,
                'storage' => defined('STORAGE_PATH') ? STORAGE_PATH : null,
                'logs' => defined('STORAGE_PATH') ? STORAGE_PATH . '/logs' : null,
            ];
            
            foreach ($filesToBackup as $name => $path) {
                if ($path === null || !is_dir($path)) continue;
                
                echo "  Backing up $name...\n";
                
                $tarFile = $backupPath . '/' . $name . '.tar.gz';
                $cmd = sprintf(
                    'tar -czf %s -C %s . 2>&1',
                    escapeshellarg($tarFile),
                    escapeshellarg($path)
                );
                
                exec($cmd, $output, $returnCode);
                
                if ($returnCode === 0 && file_exists($tarFile)) {
                    $size = filesize($tarFile);
                    echo "    ✅ $name backed up (" . formatBytes($size) . ")\n";
                    
                    $manifest['files'][] = [
                        'path' => $name . '.tar.gz',
                        'size' => $size,
                        'type' => 'files',
                    ];
                } else {
                    echo "    ⚠️  $name backup failed\n";
                }
            }
        }
        
        // Save manifest
        file_put_contents(
            $backupPath . '/manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT)
        );
        
        echo "\n✅ Backup complete: $backupId\n";
        echo "📂 Location: $backupPath\n";
        
        $totalSize = array_sum(array_column($manifest['files'], 'size'));
        echo "💾 Total size: " . formatBytes($totalSize) . "\n";
        
        // Log backup
        $logger->info('Backup created', NeuroContext::cli('backup', [
            'backup_id' => $backupId,
            'type' => $dbOnly ? 'db_only' : ($filesOnly ? 'files_only' : 'full'),
            'files_count' => count($manifest['files']),
            'total_size' => $totalSize,
        ]));
        
        break;
        
    case 'list':
        echo "📋 Available backups:\n\n";
        
        $backups = glob($backupDir . '/*/manifest.json');
        
        if (empty($backups)) {
            echo "No backups found\n";
            exit(0);
        }
        
        usort($backups, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        echo sprintf("%-20s %-20s %-10s %s\n", "Backup ID", "Created", "Size", "Type");
        echo str_repeat("-", 70) . "\n";
        
        foreach ($backups as $manifestFile) {
            $manifest = json_decode(file_get_contents($manifestFile), true);
            $totalSize = array_sum(array_column($manifest['files'] ?? [], 'size'));
            
            echo sprintf(
                "%-20s %-20s %-10s %s\n",
                $manifest['id'] ?? 'unknown',
                substr($manifest['created_at'] ?? '', 0, 19),
                formatBytes($totalSize),
                $manifest['type'] ?? 'unknown'
            );
        }
        
        break;
        
    case 'restore':
        $backupId = $argv[2] ?? null;
        
        if ($backupId === null) {
            echo "❌ Error: Backup ID required\n";
            echo "Usage: php bin/backup.php restore <backup_id>\n";
            exit(1);
        }
        
        $backupPath = $backupDir . '/' . $backupId;
        $manifestFile = $backupPath . '/manifest.json';
        
        if (!file_exists($manifestFile)) {
            echo "❌ Error: Backup not found: $backupId\n";
            exit(1);
        }
        
        echo "⚠️  WARNING: This will restore data from backup!\n";
        echo "Backup: $backupId\n";
        echo "\nAre you sure? Type 'yes' to continue: ";
        
        $confirm = trim(fgets(STDIN));
        
        if (strtolower($confirm) !== 'yes') {
            echo "❌ Restore cancelled\n";
            exit(0);
        }
        
        echo "\n🔄 Restoring backup...\n\n";
        
        $manifest = json_decode(file_get_contents($manifestFile), true);
        
        foreach ($manifest['files'] as $file) {
            if ($file['type'] === 'database') {
                echo "🗄️  Restoring database...\n";
                
                $sqlGz = $backupPath . '/' . $file['path'];
                if (!file_exists($sqlGz)) {
                    echo "  ❌ Database file not found\n";
                    continue;
                }
                
                // Decompress
                $sql = gzdecode(file_get_contents($sqlGz));
                $tempSql = sys_get_temp_dir() . '/restore_' . uniqid() . '.sql';
                file_put_contents($tempSql, $sql);
                
                // Restore
                $config = require CONFIG_PATH . '/database.php';
                $host = $config['host'] ?? 'localhost';
                $name = $config['database'] ?? '';
                $user = $config['username'] ?? '';
                $pass = $config['password'] ?? '';
                
                $cmd = sprintf(
                    'mysql -h%s -u%s -p%s %s < %s 2>&1',
                    escapeshellarg($host),
                    escapeshellarg($user),
                    escapeshellarg($pass),
                    escapeshellarg($name),
                    escapeshellarg($tempSql)
                );
                
                exec($cmd, $output, $returnCode);
                unlink($tempSql);
                
                if ($returnCode === 0) {
                    echo "  ✅ Database restored\n";
                } else {
                    echo "  ❌ Database restore failed\n";
                    echo "  Output: " . implode("\n", $output) . "\n";
                }
            }
        }
        
        echo "\n✅ Restore complete\n";
        
        // Log restore
        $logger->info('Backup restored', NeuroContext::cli('backup', [
            'backup_id' => $backupId,
            'restored_at' => date('c'),
        ]));
        
        break;
        
    case 'cleanup':
        $days = $daysOpt ?? 30;
        
        echo "🧹 Cleaning up backups older than $days days...\n\n";
        
        $cutoff = time() - ($days * 86400);
        $removed = 0;
        $backups = glob($backupDir . '/*', GLOB_ONLYDIR);
        
        foreach ($backups as $backup) {
            $mtime = filemtime($backup);
            
            if ($mtime < $cutoff) {
                echo "  Removing: " . basename($backup) . "\n";
                
                // Remove directory recursively
                $cmd = sprintf('rm -rf %s', escapeshellarg($backup));
                exec($cmd, $output, $returnCode);
                
                if ($returnCode === 0) {
                    $removed++;
                }
            }
        }
        
        echo "\n✅ Removed $removed backups\n";
        
        // Log cleanup
        $logger->info('Backup cleanup completed', NeuroContext::cli('backup', [
            'days_threshold' => $days,
            'removed_count' => $removed,
        ]));
        
        break;
        
    case 'verify':
        $backupId = $argv[2] ?? null;
        
        if ($backupId === null) {
            echo "❌ Error: Backup ID required\n";
            exit(1);
        }
        
        $backupPath = $backupDir . '/' . $backupId;
        $manifestFile = $backupPath . '/manifest.json';
        
        if (!file_exists($manifestFile)) {
            echo "❌ Error: Backup not found\n";
            exit(1);
        }
        
        echo "🔍 Verifying backup: $backupId\n\n";
        
        $manifest = json_decode(file_get_contents($manifestFile), true);
        $errors = 0;
        
        foreach ($manifest['files'] as $file) {
            $path = $backupPath . '/' . $file['path'];
            
            if (!file_exists($path)) {
                echo "  ❌ Missing: " . $file['path'] . "\n";
                $errors++;
            } else {
                $size = filesize($path);
                if ($size !== $file['size']) {
                    echo "  ⚠️  Size mismatch: " . $file['path'] . " (expected " . $file['size'] . ", got $size)\n";
                    $errors++;
                } else {
                    echo "  ✅ Valid: " . $file['path'] . "\n";
                }
            }
        }
        
        if ($errors === 0) {
            echo "\n✅ Backup is valid\n";
            exit(0);
        } else {
            echo "\n❌ Backup has $errors errors\n";
            exit(1);
        }
        
        break;
        
    case 'help':
    default:
        echo "Backup and Restore Tool\n\n";
        echo "Usage:\n";
        echo "  php bin/backup.php create                    - Create full backup\n";
        echo "  php bin/backup.php create --db-only          - Database only\n";
        echo "  php bin/backup.php create --files-only       - Files only\n";
        echo "  php bin/backup.php list                      - List all backups\n";
        echo "  php bin/backup.php restore <backup_id>       - Restore backup\n";
        echo "  php bin/backup.php cleanup --days=30         - Remove old backups\n";
        echo "  php bin/backup.php verify <backup_id>        - Verify backup\n";
        echo "  php bin/backup.php help                      - Show this help\n";
        break;
}

function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}

exit(0);
