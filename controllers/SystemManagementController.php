<?php
/**
 * ============================================
 * SYSTEM MANAGEMENT - DATA & CACHE CONTROLLER
 * ============================================
 */

require_once 'config/database.php';
require_once 'middleware/AuthMiddleware.php';

class SystemManagementController {
    private $conn;
    private $redis;
    
    private const REDIS_HOST = '127.0.0.1';
    private const REDIS_PORT = 6379;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->connectRedis();
    }
    
    private function connectRedis() {
        try {
            $this->redis = new Redis();
            $this->redis->connect(self::REDIS_HOST, self::REDIS_PORT, 2.5);
            $this->redis->ping();
        } catch (Exception $e) {
            $this->redis = null;
        }
    }
    
    /**
     * ============================================
     * MAIN INDEX - DISPLAY MANAGEMENT INTERFACE
     * ============================================
     */
    public function index() {
        AuthMiddleware::requireAdmin();
        
        $stats = $this->getSystemStats();
        
        require_once 'views/system/management.php';
    }
    
    /**
     * ============================================
     * GET SYSTEM STATISTICS
     * ============================================
     */
    private function getSystemStats() {
        $stats = [
            'tables' => [],
            'redis' => [
                'connected' => $this->redis !== null,
                'total_keys' => 0,
                'memory_used' => 0
            ]
        ];
        
        // Get table statistics
        $tables = [
        'dsnv' => 'Danh Sách Nhân Viên', // Thêm dòng này
        'orderdetail' => 'Order Detail',
        'dskh' => 'Danh Sách Khách Hàng',
        'gkhl' => 'Gắn Kết Hoa Linh',
        'summary_anomaly_results' => 'Kết Quả Phát Hiện BT',
        'summary_report_cache' => 'Cache Báo Cáo',
        'summary_nhanvien_report_cache' => 'Cache Báo Cáo NV',
        'summary_nhanvien_kpi_cache' => 'Cache KPI NV'
    ];
        
        foreach ($tables as $table => $name) {
            $stats['tables'][$table] = [
                'name' => $name,
                'count' => $this->getTableRowCount($table),
                'size' => $this->getTableSize($table)
            ];
        }
        
        // Get Redis statistics
        if ($this->redis) {
            try {
                $info = $this->redis->info();
                $stats['redis']['total_keys'] = $this->redis->dbSize();
                $stats['redis']['memory_used'] = $info['used_memory_human'] ?? 'N/A';
            } catch (Exception $e) {
                // Silent fail
            }
        }
        
        return $stats;
    }
    
    private function getTableRowCount($table) {
        try {
            $sql = "SELECT COUNT(*) as count FROM `{$table}`";
            $stmt = $this->conn->query($sql);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    private function getTableSize($table) {
        try {
            $sql = "SELECT 
                        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                    FROM information_schema.TABLES 
                    WHERE table_schema = DATABASE() 
                    AND table_name = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$table]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['size_mb'] ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * ============================================
     * CLEAR DATA HANDLERS
     * ============================================
     */
    
    /**
     * Clear all data from a table
     */
    public function clearTableAll() {
        AuthMiddleware::requireAdmin();
        header('Content-Type: application/json');
        
        $table = $_POST['table'] ?? '';
        $allowedTables = [
        'dsnv', // Thêm dòng này
        'orderdetail', 'dskh', 'gkhl', 
        'summary_anomaly_results', 
        'summary_report_cache',
        'summary_nhanvien_report_cache',
        'summary_nhanvien_kpi_cache'
    ];
        
        if (!in_array($table, $allowedTables)) {
            echo json_encode(['success' => false, 'error' => 'Bảng không hợp lệ']);
            exit;
        }
        
        try {
            $sql = "TRUNCATE TABLE `{$table}`";
            $this->conn->exec($sql);
            
            $this->logActivity('clear_table_all', [
                'table' => $table,
                'user' => $_SESSION['username']
            ]);
            
            echo json_encode([
                'success' => true, 
                'message' => "Đã xóa toàn bộ dữ liệu bảng {$table}"
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false, 
                'error' => 'Lỗi: ' . $e->getMessage()
            ]);
        }
        exit;
    }
    
    /**
     * Clear data by date range
     */
    public function clearTableByDate() {
        AuthMiddleware::requireAdmin();
        header('Content-Type: application/json');
        
        $table = $_POST['table'] ?? '';
        $year = (int)($_POST['year'] ?? 0);
        $month = isset($_POST['month']) ? (int)$_POST['month'] : null;
        $day = isset($_POST['day']) ? (int)$_POST['day'] : null;
        
        $allowedTables = [
        'dsnv' => 'ngay_vao_cty', // Thêm dòng này: Xóa dựa trên ngày vào công ty
        'orderdetail' => 'OrderDate',
        'summary_anomaly_results' => 'calculated_at',
        'summary_report_cache' => 'calculated_at',
        'summary_nhanvien_report_cache' => 'calculated_at',
        'summary_nhanvien_kpi_cache' => 'calculated_at'
    ];
        
        if (!isset($allowedTables[$table])) {
            echo json_encode(['success' => false, 'error' => 'Bảng không hỗ trợ xóa theo ngày']);
            exit;
        }
        
        $dateColumn = $allowedTables[$table];
        
        try {
            $conditions = [];
            $params = [];
            
            if ($year > 0) {
                $conditions[] = "YEAR({$dateColumn}) = ?";
                $params[] = $year;
            }
            
            if ($month !== null && $month > 0) {
                $conditions[] = "MONTH({$dateColumn}) = ?";
                $params[] = $month;
            }
            
            if ($day !== null && $day > 0) {
                $conditions[] = "DAY({$dateColumn}) = ?";
                $params[] = $day;
            }
            
            if (empty($conditions)) {
                echo json_encode(['success' => false, 'error' => 'Vui lòng chọn ít nhất năm']);
                exit;
            }
            
            $sql = "DELETE FROM `{$table}` WHERE " . implode(' AND ', $conditions);
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            $deletedRows = $stmt->rowCount();
            
            $dateStr = "Năm {$year}";
            if ($month) $dateStr .= " Tháng {$month}";
            if ($day) $dateStr .= " Ngày {$day}";
            
            $this->logActivity('clear_table_by_date', [
                'table' => $table,
                'date' => $dateStr,
                'rows_deleted' => $deletedRows,
                'user' => $_SESSION['username']
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => "Đã xóa {$deletedRows} bản ghi từ {$table} ({$dateStr})"
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Lỗi: ' . $e->getMessage()
            ]);
        }
        exit;
    }
    
    /**
     * ============================================
     * REDIS CACHE HANDLERS
     * ============================================
     */
    
    /**
     * Clear all Redis cache
     */
    public function clearRedisAll() {
        AuthMiddleware::requireAdmin();
        header('Content-Type: application/json');
        
        if (!$this->redis) {
            echo json_encode(['success' => false, 'error' => 'Redis không kết nối']);
            exit;
        }
        
        try {
            $keysCount = $this->redis->dbSize();
            $this->redis->flushDB();
            
            $this->logActivity('clear_redis_all', [
                'keys_deleted' => $keysCount,
                'user' => $_SESSION['username']
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => "Đã xóa {$keysCount} keys từ Redis"
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Lỗi: ' . $e->getMessage()
            ]);
        }
        exit;
    }
    
    /**
     * Clear Redis by pattern
     */
    public function clearRedisByPattern() {
        AuthMiddleware::requireAdmin();
        header('Content-Type: application/json');
        
        if (!$this->redis) {
            echo json_encode(['success' => false, 'error' => 'Redis không kết nối']);
            exit;
        }
        
        $pattern = $_POST['pattern'] ?? '';
        if (empty($pattern)) {
            echo json_encode(['success' => false, 'error' => 'Pattern không hợp lệ']);
            exit;
        }
        
        try {
            $keys = $this->redis->keys($pattern);
            $deletedCount = 0;
            
            foreach ($keys as $key) {
                if ($this->redis->del($key)) {
                    $deletedCount++;
                }
            }
            
            $this->logActivity('clear_redis_pattern', [
                'pattern' => $pattern,
                'keys_deleted' => $deletedCount,
                'user' => $_SESSION['username']
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => "Đã xóa {$deletedCount} keys từ Redis (pattern: {$pattern})"
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Lỗi: ' . $e->getMessage()
            ]);
        }
        exit;
    }
    
    /**
     * Get Redis keys list
     */
    public function getRedisKeys() {
        AuthMiddleware::requireAdmin();
        header('Content-Type: application/json');
        
        if (!$this->redis) {
            echo json_encode(['success' => false, 'error' => 'Redis không kết nối']);
            exit;
        }
        
        try {
            $pattern = $_GET['pattern'] ?? '*';
            $keys = $this->redis->keys($pattern);
            
            $keysList = [];
            foreach (array_slice($keys, 0, 100) as $key) {
                $ttl = $this->redis->ttl($key);
                $keysList[] = [
                    'key' => $key,
                    'type' => $this->redis->type($key),
                    'ttl' => $ttl,
                    'size' => strlen($this->redis->get($key) ?? '')
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
        exit;
    }
    
    /**
     * ============================================
     * UTILITY METHODS
     * ============================================
     */
    
    private function logActivity($action, $details = []) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => $action,
            'details' => $details
        ];
        
        error_log("System Management: " . json_encode($logData));
    }

    public function getCacheSummary() {
    AuthMiddleware::requireAdmin();
    header('Content-Type: application/json');
    
    if (!$this->redis) {
        echo json_encode(['success' => false, 'error' => 'Redis không kết nối']);
        exit;
    }
    
    try {
        $patterns = [
            'anomaly:report:*',
            'report:cache:*',
            'nhanvien:report:*',
            'nhanvien:kpi:*'
        ];
        
        $summary = [];
        
        foreach ($patterns as $pattern) {
            $keys = $this->redis->keys($pattern);
            $totalSize = 0;
            
            foreach ($keys as $key) {
                try {
                    $value = $this->redis->get($key);
                    if ($value !== false) {
                        $totalSize += strlen($value);
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
            
            $summary[$pattern] = [
                'count' => count($keys),
                'size' => $totalSize
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $summary
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Lỗi: ' . $e->getMessage()
        ]);
    }
    exit;
}

public function deleteRedisKey() {
    AuthMiddleware::requireAdmin();
    header('Content-Type: application/json');
    
    if (!$this->redis) {
        echo json_encode(['success' => false, 'error' => 'Redis không kết nối']);
        exit;
    }
    
    $key = $_POST['key'] ?? '';
    if (empty($key)) {
        echo json_encode(['success' => false, 'error' => 'Key không hợp lệ']);
        exit;
    }
    
    try {
        $result = $this->redis->del($key);
        
        if ($result) {
            $this->logActivity('delete_redis_key', [
                'key' => $key,
                'user' => $_SESSION['username']
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => "Đã xóa key: {$key}"
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Key không tồn tại hoặc đã bị xóa'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Lỗi: ' . $e->getMessage()
        ]);
    }
    exit;
}

public function getRedisKeysEnhanced() {
    AuthMiddleware::requireAdmin();
    header('Content-Type: application/json');
    
    if (!$this->redis) {
        echo json_encode(['success' => false, 'error' => 'Redis không kết nối']);
        exit;
    }
    
    try {
        $pattern = $_GET['pattern'] ?? '*';
        $keys = $this->redis->keys($pattern);
        
        $keysList = [];
        foreach (array_slice($keys, 0, 200) as $key) { // Increased limit to 200
            try {
                $ttl = $this->redis->ttl($key);
                $type = $this->redis->type($key);
                
                // Get size based on type
                $size = 0;
                switch ($type) {
                    case Redis::REDIS_STRING:
                        $value = $this->redis->get($key);
                        $size = strlen($value ?? '');
                        break;
                    case Redis::REDIS_LIST:
                        $size = $this->redis->lLen($key) * 100; // Estimate
                        break;
                    case Redis::REDIS_SET:
                        $size = $this->redis->sCard($key) * 100; // Estimate
                        break;
                    case Redis::REDIS_ZSET:
                        $size = $this->redis->zCard($key) * 100; // Estimate
                        break;
                    case Redis::REDIS_HASH:
                        $size = $this->redis->hLen($key) * 100; // Estimate
                        break;
                }
                
                $keysList[] = [
                    'key' => $key,
                    'type' => $this->getRedisTypeName($type),
                    'ttl' => $ttl,
                    'size' => $size
                ];
            } catch (Exception $e) {
                continue;
            }
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
    exit;
}

private function getRedisTypeName($type) {
    $types = [
        Redis::REDIS_STRING => 'string',
        Redis::REDIS_LIST => 'list',
        Redis::REDIS_SET => 'set',
        Redis::REDIS_ZSET => 'zset',
        Redis::REDIS_HASH => 'hash',
        Redis::REDIS_NOT_FOUND => 'none'
    ];
    return $types[$type] ?? 'unknown';
}

public function clearRedisByPatternSafe() {
    AuthMiddleware::requireAdmin();
    header('Content-Type: application/json');
    
    if (!$this->redis) {
        echo json_encode(['success' => false, 'error' => 'Redis không kết nối']);
        exit;
    }
    
    $pattern = $_POST['pattern'] ?? '';
    if (empty($pattern)) {
        echo json_encode(['success' => false, 'error' => 'Pattern không hợp lệ']);
        exit;
    }
    
    // Safety check: Don't allow dangerous patterns
    $dangerousPatterns = ['*', '?*', '*:*'];
    if (in_array($pattern, $dangerousPatterns) && $pattern === '*') {
        echo json_encode([
            'success' => false, 
            'error' => 'Sử dụng "Xóa Toàn Bộ Redis Cache" cho pattern này'
        ]);
        exit;
    }
    
    try {
        $keys = $this->redis->keys($pattern);
        $deletedCount = 0;
        
        // Use pipeline for better performance
        $this->redis->multi(Redis::PIPELINE);
        foreach ($keys as $key) {
            $this->redis->del($key);
            $deletedCount++;
        }
        $this->redis->exec();
        
        $this->logActivity('clear_redis_pattern_safe', [
            'pattern' => $pattern,
            'keys_deleted' => $deletedCount,
            'user' => $_SESSION['username']
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => "Đã xóa {$deletedCount} keys từ Redis (pattern: {$pattern})"
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Lỗi: ' . $e->getMessage()
        ]);
    }
    exit;
}

public function getCacheStats() {
    AuthMiddleware::requireAdmin();
    header('Content-Type: application/json');
    
    if (!$this->redis) {
        echo json_encode(['success' => false, 'error' => 'Redis không kết nối']);
        exit;
    }
    
    try {
        $stats = [
            'total_keys' => $this->redis->dbSize(),
            'memory' => $this->redis->info('memory'),
            'by_type' => []
        ];
        
        // Categorize keys by pattern
        $patterns = [
            'anomaly' => 'anomaly:*',
            'report' => 'report:*',
            'nhanvien' => 'nhanvien:*',
            'other' => '*'
        ];
        
        foreach ($patterns as $category => $pattern) {
            $keys = $this->redis->keys($pattern);
            $stats['by_type'][$category] = count($keys);
        }
        
        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Lỗi: ' . $e->getMessage()
        ]);
    }
    exit;
}
}
?>