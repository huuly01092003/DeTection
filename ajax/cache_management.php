<?php
/**
 * ============================================
 * AJAX CACHE MANAGEMENT HANDLER
 * ============================================
 * File: ajax/cache_management.php
 * 
 * Xử lý các thao tác quản lý cache từ giao diện users
 */

session_start();

require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../config/database.php';

// Kiểm tra quyền admin
AuthMiddleware::requireAdmin();

// Set JSON header
header('Content-Type: application/json');

// Kết nối Redis
function connectRedis() {
    try {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379, 2.5);
        $redis->ping();
        return $redis;
    } catch (Exception $e) {
        return null;
    }
}

// Lấy action
$action = $_POST['action'] ?? '';

try {
    $database = new Database();
    $conn = $database->getConnection();
    $redis = connectRedis();
    
    switch ($action) {
        
        // ============================================
        // XÓA MỘT CACHE KEY CỤ THỂ
        // ============================================
        case 'delete_key':
            $table = $_POST['table'] ?? '';
            $cacheKey = $_POST['cache_key'] ?? '';
            
            if (empty($table) || empty($cacheKey)) {
                echo json_encode(['success' => false, 'error' => 'Thiếu thông tin table hoặc cache_key']);
                exit;
            }
            
            // Validate table name
            $allowedTables = [
                'summary_anomaly_results',
                'summary_report_cache',
                'summary_nhanvien_report_cache',
                'summary_nhanvien_kpi_cache'
            ];
            
            if (!in_array($table, $allowedTables)) {
                echo json_encode(['success' => false, 'error' => 'Bảng không hợp lệ']);
                exit;
            }
            
            // Xóa từ database
            $sql = "DELETE FROM `{$table}` WHERE cache_key = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$cacheKey]);
            $deletedRows = $stmt->rowCount();
            
            // Xóa từ Redis nếu có
            if ($redis) {
                try {
                    $redis->del($cacheKey);
                } catch (Exception $e) {
                    // Silent fail
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => "Đã xóa {$deletedRows} records với cache key: {$cacheKey}"
            ]);
            break;
        
        // ============================================
        // XÓA TOÀN BỘ CACHE CỦA MỘT BẢNG
        // ============================================
        case 'clear_table':
            $table = $_POST['table'] ?? '';
            
            if (empty($table)) {
                echo json_encode(['success' => false, 'error' => 'Thiếu thông tin table']);
                exit;
            }
            
            // Validate table name
            $allowedTables = [
                'summary_anomaly_results',
                'summary_report_cache',
                'summary_nhanvien_report_cache',
                'summary_nhanvien_kpi_cache'
            ];
            
            if (!in_array($table, $allowedTables)) {
                echo json_encode(['success' => false, 'error' => 'Bảng không hợp lệ']);
                exit;
            }
            
            // Lấy danh sách cache keys trước khi xóa để xóa từ Redis
            $keysSql = "SELECT DISTINCT cache_key FROM `{$table}` WHERE cache_key IS NOT NULL";
            $keysStmt = $conn->query($keysSql);
            $cacheKeys = $keysStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Xóa từ database
            $sql = "TRUNCATE TABLE `{$table}`";
            $conn->exec($sql);
            
            // Xóa từ Redis
            $redisDeleted = 0;
            if ($redis && !empty($cacheKeys)) {
                try {
                    foreach ($cacheKeys as $key) {
                        if ($redis->del($key)) {
                            $redisDeleted++;
                        }
                    }
                } catch (Exception $e) {
                    // Silent fail
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => "Đã xóa toàn bộ cache trong bảng {$table} (Database: " . count($cacheKeys) . " keys, Redis: {$redisDeleted} keys)"
            ]);
            break;
        
        // ============================================
        // XÓA TOÀN BỘ REDIS CACHE
        // ============================================
        case 'clear_redis':
            if (!$redis) {
                echo json_encode(['success' => false, 'error' => 'Redis không kết nối']);
                exit;
            }
            
            try {
                $keysCount = $redis->dbSize();
                $redis->flushDB();
                
                echo json_encode([
                    'success' => true,
                    'message' => "Đã xóa {$keysCount} keys từ Redis"
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Lỗi khi xóa Redis: ' . $e->getMessage()
                ]);
            }
            break;
        
        // ============================================
        // LẤY THỐNG KÊ REDIS
        // ============================================
        case 'get_redis_stats':
            if (!$redis) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Redis không kết nối',
                    'data' => [
                        'total_keys' => 0,
                        'memory_used' => 'N/A',
                        'connected' => false
                    ]
                ]);
                exit;
            }
            
            try {
                $info = $redis->info();
                $totalKeys = $redis->dbSize();
                $memoryUsed = $info['used_memory_human'] ?? 'N/A';
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'total_keys' => $totalKeys,
                        'memory_used' => $memoryUsed,
                        'connected' => true
                    ]
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Lỗi khi lấy thông tin Redis: ' . $e->getMessage()
                ]);
            }
            break;
        
        // ============================================
        // LẤY DANH SÁCH REDIS KEYS
        // ============================================
        case 'get_redis_keys':
            if (!$redis) {
                echo json_encode(['success' => false, 'error' => 'Redis không kết nối']);
                exit;
            }
            
            try {
                $pattern = $_POST['pattern'] ?? '*';
                $keys = $redis->keys($pattern);
                
                $keysList = [];
                foreach (array_slice($keys, 0, 100) as $key) {
                    $ttl = $redis->ttl($key);
                    $type = $redis->type($key);
                    
                    $size = 0;
                    if ($type == Redis::REDIS_STRING) {
                        $value = $redis->get($key);
                        $size = strlen($value ?? '');
                    }
                    
                    $keysList[] = [
                        'key' => $key,
                        'type' => $type,
                        'ttl' => $ttl,
                        'size' => $size
                    ];
                }
                
                echo json_encode([
                    'success' => true,
                    'total' => count($keys),
                    'showing' => count($keysList),
                    'keys' => $keysList
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Lỗi: ' . $e->getMessage()
                ]);
            }
            break;
        
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Action không hợp lệ'
            ]);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Lỗi hệ thống: ' . $e->getMessage()
    ]);
}