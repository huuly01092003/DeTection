<?php
require_once 'config/database.php';
require_once 'models/DynamicCacheKeyGenerator.php'; // ✅ Sử dụng lại generator từ Anomaly

class OrderDetailModel {
    private $conn;
    private $redis;
    private $table = "orderdetail";
    
    private const PAGE_SIZE = 100;
    private const BATCH_SIZE = 500;
    private const REDIS_HOST = '127.0.0.1';
    private const REDIS_PORT = 6379;
    private const REDIS_TTL = 3600; // ✅ 1 giờ cho báo cáo thường (ngắn hơn anomaly)

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
       
    }
    
    /**
     * ✅ KẾT NỐI REDIS
     */


    
private function convertDate($dateValue) {
        if (empty($dateValue) || $dateValue === 'NULL') return null;
        
        $dateValue = trim($dateValue);
        
        if (is_numeric($dateValue)) {
            $unixDate = ($dateValue - 25569) * 86400;
            return date('Y-m-d', $unixDate);
        }
        
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dateValue, $matches)) {
            return $matches[3] . '-' . sprintf('%02d', $matches[1]) . '-' . sprintf('%02d', $matches[2]);
        }
        
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)) {
            return $dateValue;
        }
        
        $timestamp = strtotime($dateValue);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
        
        return null;
    }

    private function cleanNumber($value, $asInteger = false) {
        if (empty($value) || $value === '' || $value === 'NULL') {
            return null;
        }
        
        $cleaned = str_replace([',', ' '], '', trim($value));
        
        if (is_numeric($cleaned)) {
            return $asInteger ? (int)$cleaned : (float)$cleaned;
        }
        
        return null;
    }

    private function executeBatch(&$stmt, $batch) {
        $inserted = 0;
        $errors = 0;
        
        foreach ($batch as $data) {
            try {
                if (!$stmt->execute($data)) {
                    $errors++;
                } else {
                    $inserted++;
                }
            } catch (Exception $e) {
                $errors++;
                error_log("OrderDetail Exception: " . $e->getMessage());
            }
        }
        
        return ['inserted' => $inserted, 'errors' => $errors];
    }
    /**
     * ✅ XÓA CACHE (dùng khi import data mới)
     */
    public function clearReportCache() {
        if (!$this->redis) return false;
        
        try {
            $pattern = 'report:*';
            $keys = $this->redis->keys($pattern);
            
            if (!empty($keys)) {
                $this->redis->del($keys);
                return count($keys);
            }
            
            return 0;
        } catch (Exception $e) {
            error_log("Redis clear cache error: " . $e->getMessage());
            return false;
        }
    }

    // ============================================
    // CÁC HÀM CŨ GIỮ NGUYÊN
    // ============================================

    public function importCSV($filePath) {
        try {
            if (!file_exists($filePath)) {
                return ['success' => false, 'error' => 'File không tồn tại'];
            }

            $this->conn->beginTransaction();
            
            $insertedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;
            $batch = [];
            $isFirstRow = true;
            $lineCount = 0;

            $handle = fopen($filePath, 'r');
            if ($handle === false) {
                return ['success' => false, 'error' => 'Không thể mở file'];
            }

            $sql = "INSERT IGNORE INTO {$this->table} (
                OrderNumber, OrderDate, CustCode, CustType, DistCode, DSRCode,
                DistGroup, DSRTypeProvince, ProductSaleType, ProductCode, Qty,
                TotalSchemeAmount, TotalGrossAmount, TotalNetAmount, RptMonth, RptYear
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                return ['success' => false, 'error' => 'Lỗi prepare SQL: ' . implode(' | ', $this->conn->errorInfo())];
            }

            while (($line = fgets($handle)) !== false) {
                $lineCount++;
                $line = trim($line);

                if (empty($line)) continue;

                $row = str_getcsv($line, ',', '"');

                if ($isFirstRow) {
                    $isFirstRow = false;
                    continue;
                }

                if (count($row) < 16) {
                    $skippedCount++;
                    continue;
                }

                $startIndex = 0;
                if (!empty($row[0]) && is_numeric(trim($row[0])) && trim($row[0]) < 10000) {
                    $startIndex = 0;
                }

                $orderNumber = !empty(trim($row[$startIndex + 0])) ? trim($row[$startIndex + 0]) : null;
                $orderDate = $this->convertDate($row[$startIndex + 1]);
                $custCode = !empty(trim($row[$startIndex + 2])) ? trim($row[$startIndex + 2]) : null;
                $custType = !empty(trim($row[$startIndex + 3])) ? trim($row[$startIndex + 3]) : null;
                $distCode = !empty(trim($row[$startIndex + 4])) ? trim($row[$startIndex + 4]) : null;
                $dsrCode = !empty(trim($row[$startIndex + 5])) ? trim($row[$startIndex + 5]) : null;
                $distGroup = !empty(trim($row[$startIndex + 6])) ? trim($row[$startIndex + 6]) : null;
                $dsrTypeProvince = !empty(trim($row[$startIndex + 7])) ? trim($row[$startIndex + 7]) : null;
                $productSaleType = !empty(trim($row[$startIndex + 8])) ? trim($row[$startIndex + 8]) : null;
                $productCode = !empty(trim($row[$startIndex + 9])) ? trim($row[$startIndex + 9]) : null;
                $qty = $this->cleanNumber($row[$startIndex + 10], true);
                $totalSchemeAmount = $this->cleanNumber($row[$startIndex + 11]);
                $totalGrossAmount = $this->cleanNumber($row[$startIndex + 12]);
                $totalNetAmount = $this->cleanNumber($row[$startIndex + 13]);
                $rptMonth = $this->cleanNumber($row[$startIndex + 14], true);
                $rptYear = $this->cleanNumber($row[$startIndex + 15], true);

                if (empty($orderNumber) || empty($custCode) || empty($orderDate)) {
                    $skippedCount++;
                    continue;
                }

                if (empty($rptMonth) || empty($rptYear) || $rptMonth < 1 || $rptMonth > 12) {
                    $skippedCount++;
                    continue;
                }

                $data = [
                    $orderNumber, $orderDate, $custCode, $custType, $distCode, $dsrCode,
                    $distGroup, $dsrTypeProvince, $productSaleType, $productCode, $qty,
                    $totalSchemeAmount, $totalGrossAmount, $totalNetAmount, $rptMonth, $rptYear
                ];

                $batch[] = $data;

                if (count($batch) >= self::BATCH_SIZE) {
                    $result = $this->executeBatch($stmt, $batch);
                    $insertedCount += $result['inserted'];
                    $errorCount += $result['errors'];
                    $batch = [];
                    
                    if ($lineCount % 5000 === 0) {
                        gc_collect_cycles();
                    }
                }
            }

            fclose($handle);

            if (!empty($batch)) {
                $result = $this->executeBatch($stmt, $batch);
                $insertedCount += $result['inserted'];
                $errorCount += $result['errors'];
            }

            $this->conn->commit();
            
            // ✅ XÓA CACHE SAU KHI IMPORT
            $clearedKeys = $this->clearReportCache();
            
            return [
                'success' => true, 
                'inserted' => $insertedCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount,
                'total_lines' => $lineCount,
                'cache_cleared' => $clearedKeys
            ];
        } catch (Exception $e) {
            if (isset($handle)) fclose($handle);
            $this->conn->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    
}
?>