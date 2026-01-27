<?php
/**
 * ============================================================
 * PHƯƠNG PHÁP 1 NÂNG CAO: MaKHDMS + Detect MaNVBH Change
 * ============================================================
 * 
 * Logic:
 * - 1 KH (MaKHDMS) = 1 bản ghi duy nhất
 * - Nếu MaNVBH thay đổi → XÓA cũ, INSERT mới (chuyển NVBH)
 * - Nếu MaNVBH không thay đổi → UPDATE các field khác
 * - Nếu chưa tồn tại → INSERT mới
 * 
 * ✅ Ưu điểm: Tự động phát hiện chuyển NVBH, UPDATE an toàn
 */

require_once 'config/database.php';

class GkhlModel {
    private $conn;
    private $table = "gkhl";
    private const PAGE_SIZE = 100;
    private const BATCH_SIZE = 100;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function importCSV($filePath) {
        try {
            if (!file_exists($filePath)) {
                return ['success' => false, 'error' => 'File không tồn tại'];
            }

            $this->conn->exec("SET FOREIGN_KEY_CHECKS=0");
            $this->conn->beginTransaction();
            
            $fileContent = file_get_contents($filePath);
            if (!mb_check_encoding($fileContent, 'UTF-8')) {
                $fileContent = mb_convert_encoding($fileContent, 'UTF-8', 'auto');
            }
            
            $rows = array_map(function($line) {
                return str_getcsv($line, ',', '"');
            }, explode("\n", $fileContent));
            
            if (empty($rows)) {
                $this->conn->exec("SET FOREIGN_KEY_CHECKS=1");
                return ['success' => false, 'error' => 'File CSV rỗng'];
            }

            $headerRow = $rows[0];
            $columnIndices = $this->parseGkhlHeader($headerRow);
            
            if (empty($columnIndices['MaKHDMS'])) {
                $this->conn->exec("SET FOREIGN_KEY_CHECKS=1");
                return ['success' => false, 'error' => 'Không tìm thấy cột "Mã KH DMS"'];
            }

            $isFirstRow = true;
            $insertedCount = 0;
            $updatedCount = 0;
            $deletedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;
            $batch = [];

            foreach ($rows as $rowNum => $row) {
                if (empty($row)) continue;
                
                if ($isFirstRow) {
                    $isFirstRow = false;
                    continue;
                }

                // Parse dữ liệu từ CSV
                $maKHDMS = isset($columnIndices['MaKHDMS']) && !empty(trim($row[$columnIndices['MaKHDMS']] ?? '')) 
                    ? trim($row[$columnIndices['MaKHDMS']]) : null;
                
                if (empty($maKHDMS)) {
                    $skippedCount++;
                    continue;
                }

                $maNVBH = isset($columnIndices['MaNVBH']) && !empty(trim($row[$columnIndices['MaNVBH']] ?? '')) 
                    ? trim($row[$columnIndices['MaNVBH']]) : null;
                $tenNVBH = isset($columnIndices['TenNVBH']) && !empty(trim($row[$columnIndices['TenNVBH']] ?? '')) 
                    ? trim($row[$columnIndices['TenNVBH']]) : null;
                $tenQuay = isset($columnIndices['TenQuay']) && !empty(trim($row[$columnIndices['TenQuay']] ?? '')) 
                    ? trim($row[$columnIndices['TenQuay']]) : null;
                $tenChuCuaHang = isset($columnIndices['TenChuCuaHang']) && !empty(trim($row[$columnIndices['TenChuCuaHang']] ?? '')) 
                    ? trim($row[$columnIndices['TenChuCuaHang']]) : null;
                $ngaySinh = isset($columnIndices['NgaySinh']) 
                    ? $this->cleanNumber($row[$columnIndices['NgaySinh']] ?? '', true) : null;
                $thangSinh = isset($columnIndices['ThangSinh']) 
                    ? $this->cleanNumber($row[$columnIndices['ThangSinh']] ?? '', true) : null;
                $namSinh = isset($columnIndices['NamSinh']) 
                    ? $this->cleanNumber($row[$columnIndices['NamSinh']] ?? '') : null;
                $sdtZalo = isset($columnIndices['SDTZalo']) && !empty(trim($row[$columnIndices['SDTZalo']] ?? '')) 
                    ? trim($row[$columnIndices['SDTZalo']]) : null;
                $sdtDaDinhDanh = isset($columnIndices['SDTDaDinhDanh']) && !empty(trim($row[$columnIndices['SDTDaDinhDanh']] ?? '')) 
                    ? trim($row[$columnIndices['SDTDaDinhDanh']]) : null;
                $khopSDT = isset($columnIndices['KhopSDT']) 
                    ? $this->convertYN($row[$columnIndices['KhopSDT']] ?? '') : null;
                $dangKyChuongTrinh = isset($columnIndices['DangKyChuongTrinh']) && !empty(trim($row[$columnIndices['DangKyChuongTrinh']] ?? '')) 
                    ? trim($row[$columnIndices['DangKyChuongTrinh']]) : null;
                $dangKyMucDoanhSo = isset($columnIndices['DangKyMucDoanhSo']) && !empty(trim($row[$columnIndices['DangKyMucDoanhSo']] ?? '')) 
                    ? trim($row[$columnIndices['DangKyMucDoanhSo']]) : null;
                $dangKyTrungBay = isset($columnIndices['DangKyTrungBay']) && !empty(trim($row[$columnIndices['DangKyTrungBay']] ?? '')) 
                    ? trim($row[$columnIndices['DangKyTrungBay']]) : null;

                $data = [
                    'maNVBH' => $maNVBH,
                    'tenNVBH' => $tenNVBH,
                    'maKHDMS' => $maKHDMS,
                    'tenQuay' => $tenQuay,
                    'tenChuCuaHang' => $tenChuCuaHang,
                    'ngaySinh' => $ngaySinh,
                    'thangSinh' => $thangSinh,
                    'namSinh' => $namSinh,
                    'sdtZalo' => $sdtZalo,
                    'sdtDaDinhDanh' => $sdtDaDinhDanh,
                    'khopSDT' => $khopSDT,
                    'dangKyChuongTrinh' => $dangKyChuongTrinh,
                    'dangKyMucDoanhSo' => $dangKyMucDoanhSo,
                    'dangKyTrungBay' => $dangKyTrungBay
                ];

                $batch[] = $data;

                if (count($batch) >= self::BATCH_SIZE) {
                    $result = $this->executeBatch($batch);
                    $insertedCount += $result['inserted'];
                    $updatedCount += $result['updated'];
                    $deletedCount += $result['deleted'];
                    $errorCount += $result['errors'];
                    $batch = [];
                    gc_collect_cycles();
                }
            }

            if (!empty($batch)) {
                $result = $this->executeBatch($batch);
                $insertedCount += $result['inserted'];
                $updatedCount += $result['updated'];
                $deletedCount += $result['deleted'];
                $errorCount += $result['errors'];
            }

            $this->conn->commit();
            $this->conn->exec("SET FOREIGN_KEY_CHECKS=1");

            return [
                'success' => true,
                'inserted' => $insertedCount,
                'updated' => $updatedCount,
                'deleted' => $deletedCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount
            ];
        } catch (Exception $e) {
            $this->conn->exec("SET FOREIGN_KEY_CHECKS=1");
            $this->conn->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * ============================================================
     * PHƯƠNG PHÁP 1 NÂNG CAO: Detect MaNVBH Change
     * ============================================================
     * 
     * Quy tắc:
     * 1. Kiểm tra bản ghi với MaKHDMS đã tồn tại chưa
     * 2. Nếu tồn tại:
     *    a) Nếu MaNVBH KHÁC → XÓA cũ, INSERT mới (chuyển NVBH)
     *    b) Nếu MaNVBH GIỐNG → UPDATE các field khác
     * 3. Nếu chưa tồn tại → INSERT mới
     */
    private function executeBatch(&$batch) {
        $inserted = 0;
        $updated = 0;
        $deleted = 0;
        $errors = 0;

        foreach ($batch as $data) {
            try {
                // Bước 1: Kiểm tra bản ghi đã tồn tại chưa (dựa vào MaKHDMS)
                $checkSql = "SELECT id, MaNVBH FROM {$this->table} WHERE MaKHDMS = :maKHDMS LIMIT 1";
                $checkStmt = $this->conn->prepare($checkSql);
                $checkStmt->execute([':maKHDMS' => $data['maKHDMS']]);
                
                $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);

                if ($existingRecord) {
                    // Bản ghi đã tồn tại
                    $oldMaNVBH = $existingRecord['MaNVBH'];
                    $newMaNVBH = $data['maNVBH'];

                    // Kiểm tra MaNVBH có thay đổi không
                    if ($oldMaNVBH !== $newMaNVBH) {
                        // ❌ MaNVBH KHÁC → XÓA cũ, INSERT mới
                        
                        // Xóa bản ghi cũ
                        $deleteSql = "DELETE FROM {$this->table} WHERE MaKHDMS = :maKHDMS";
                        $deleteStmt = $this->conn->prepare($deleteSql);
                        
                        if ($deleteStmt->execute([':maKHDMS' => $data['maKHDMS']])) {
                            $deleted++;
                            
                            // Insert bản ghi mới với MaNVBH mới
                            $insertSql = "INSERT INTO {$this->table} (
                                MaNVBH, TenNVBH, MaKHDMS, TenQuay, TenChuCuaHang,
                                NgaySinh, ThangSinh, NamSinh, SDTZalo, SDTDaDinhDanh,
                                KhopSDT, DangKyChuongTrinh, DangKyMucDoanhSo, DangKyTrungBay
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                            
                            $insertStmt = $this->conn->prepare($insertSql);
                            if ($insertStmt->execute([
                                $data['maNVBH'], $data['tenNVBH'], $data['maKHDMS'], 
                                $data['tenQuay'], $data['tenChuCuaHang'],
                                $data['ngaySinh'], $data['thangSinh'], $data['namSinh'], 
                                $data['sdtZalo'], $data['sdtDaDinhDanh'],
                                $data['khopSDT'], $data['dangKyChuongTrinh'], 
                                $data['dangKyMucDoanhSo'], $data['dangKyTrungBay']
                            ])) {
                                $inserted++;
                            } else {
                                $errors++;
                            }
                        } else {
                            $errors++;
                        }
                    } else {
                        // ✅ MaNVBH GIỐNG → Chỉ UPDATE các field khác
                        $updateSql = "UPDATE {$this->table} SET
                            TenNVBH = :tenNVBH,
                            TenQuay = :tenQuay,
                            TenChuCuaHang = :tenChuCuaHang,
                            NgaySinh = :ngaySinh,
                            ThangSinh = :thangSinh,
                            NamSinh = :namSinh,
                            SDTZalo = :sdtZalo,
                            SDTDaDinhDanh = :sdtDaDinhDanh,
                            KhopSDT = :khopSDT,
                            DangKyChuongTrinh = :dangKyChuongTrinh,
                            DangKyMucDoanhSo = :dangKyMucDoanhSo,
                            DangKyTrungBay = :dangKyTrungBay
                        WHERE MaKHDMS = :maKHDMS";
                        
                        $updateStmt = $this->conn->prepare($updateSql);
                        if ($updateStmt->execute([
                            ':tenNVBH' => $data['tenNVBH'],
                            ':tenQuay' => $data['tenQuay'],
                            ':tenChuCuaHang' => $data['tenChuCuaHang'],
                            ':ngaySinh' => $data['ngaySinh'],
                            ':thangSinh' => $data['thangSinh'],
                            ':namSinh' => $data['namSinh'],
                            ':sdtZalo' => $data['sdtZalo'],
                            ':sdtDaDinhDanh' => $data['sdtDaDinhDanh'],
                            ':khopSDT' => $data['khopSDT'],
                            ':dangKyChuongTrinh' => $data['dangKyChuongTrinh'],
                            ':dangKyMucDoanhSo' => $data['dangKyMucDoanhSo'],
                            ':dangKyTrungBay' => $data['dangKyTrungBay'],
                            ':maKHDMS' => $data['maKHDMS']
                        ])) {
                            $updated++;
                        } else {
                            $errors++;
                        }
                    }
                } else {
                    // Bản ghi chưa tồn tại → INSERT mới
                    $insertSql = "INSERT INTO {$this->table} (
                        MaNVBH, TenNVBH, MaKHDMS, TenQuay, TenChuCuaHang,
                        NgaySinh, ThangSinh, NamSinh, SDTZalo, SDTDaDinhDanh,
                        KhopSDT, DangKyChuongTrinh, DangKyMucDoanhSo, DangKyTrungBay
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $insertStmt = $this->conn->prepare($insertSql);
                    if ($insertStmt->execute([
                        $data['maNVBH'], $data['tenNVBH'], $data['maKHDMS'], 
                        $data['tenQuay'], $data['tenChuCuaHang'],
                        $data['ngaySinh'], $data['thangSinh'], $data['namSinh'], 
                        $data['sdtZalo'], $data['sdtDaDinhDanh'],
                        $data['khopSDT'], $data['dangKyChuongTrinh'], 
                        $data['dangKyMucDoanhSo'], $data['dangKyTrungBay']
                    ])) {
                        $inserted++;
                    } else {
                        $errors++;
                    }
                }
            } catch (Exception $e) {
                $errors++;
            }
        }

        return ['inserted' => $inserted, 'updated' => $updated, 'deleted' => $deleted, 'errors' => $errors];
    }

    // ========== CÁC METHOD CÒN LẠI (giữ nguyên) ==========

    public function getAll($filters = [], $page = 1) {
        $page = max(1, (int)$page);
        $offset = ($page - 1) * self::PAGE_SIZE;
        
        $conditions = [];
        $params = [];
        
        if (!empty($filters['ma_nvbh'])) {
            $conditions[] = "MaNVBH = :ma_nvbh";
            $params[':ma_nvbh'] = $filters['ma_nvbh'];
        }
        
        if (!empty($filters['ma_kh_dms'])) {
            $conditions[] = "MaKHDMS LIKE :ma_kh_dms";
            $params[':ma_kh_dms'] = '%' . $filters['ma_kh_dms'] . '%';
        }
        
        if (isset($filters['khop_sdt']) && $filters['khop_sdt'] !== '') {
            $khopValue = $filters['khop_sdt'] === '1' ? 'Y' : 'N';
            $conditions[] = "KhopSDT = :khop_sdt";
            $params[':khop_sdt'] = $khopValue;
        }
        
        if (!empty($filters['nam_sinh'])) {
            $conditions[] = "NamSinh = :nam_sinh";
            $params[':nam_sinh'] = $filters['nam_sinh'];
        }
        
        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        $sql = "SELECT * FROM {$this->table} 
                {$whereClause}
                ORDER BY MaKHDMS 
                LIMIT :limit OFFSET :offset";
        
        $params[':limit'] = self::PAGE_SIZE;
        $params[':offset'] = $offset;
        
        $stmt = $this->conn->prepare($sql);
        
        foreach ($params as $key => $value) {
            if ($key === ':limit' || $key === ':offset') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFilteredCount($filters = []) {
        $conditions = [];
        $params = [];
        
        if (!empty($filters['ma_nvbh'])) {
            $conditions[] = "MaNVBH = :ma_nvbh";
            $params[':ma_nvbh'] = $filters['ma_nvbh'];
        }
        
        if (!empty($filters['ma_kh_dms'])) {
            $conditions[] = "MaKHDMS LIKE :ma_kh_dms";
            $params[':ma_kh_dms'] = '%' . $filters['ma_kh_dms'] . '%';
        }
        
        if (isset($filters['khop_sdt']) && $filters['khop_sdt'] !== '') {
            $khopValue = $filters['khop_sdt'] === '1' ? 'Y' : 'N';
            $conditions[] = "KhopSDT = :khop_sdt";
            $params[':khop_sdt'] = $khopValue;
        }
        
        if (!empty($filters['nam_sinh'])) {
            $conditions[] = "NamSinh = :nam_sinh";
            $params[':nam_sinh'] = $filters['nam_sinh'];
        }
        
        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        $sql = "SELECT COUNT(*) as total FROM {$this->table} {$whereClause}";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    public function getSaleStaff() {
        $sql = "SELECT DISTINCT MaNVBH, TenNVBH FROM {$this->table} 
                WHERE MaNVBH IS NOT NULL 
                ORDER BY MaNVBH 
                LIMIT 200";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBirthYears() {
        $sql = "SELECT DISTINCT NamSinh FROM {$this->table} 
                WHERE NamSinh IS NOT NULL 
                ORDER BY NamSinh DESC 
                LIMIT 50";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getTotalCount() {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getPhoneMatchCount() {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE KhopSDT = 'Y'";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    // ========== PRIVATE METHODS ==========

    private function parseGkhlHeader($headerRow) {
        $indices = [];
        
        $expectedColumns = [
            'MaNVBH', 'TenNVBH', 'MaKHDMS', 'TenQuay', 'TenChuCuaHang',
            'NgaySinh', 'ThangSinh', 'NamSinh', 'SDTZalo', 'SDTDaDinhDanh',
            'KhopSDT', 'DangKyChuongTrinh', 'DangKyMucDoanhSo', 'DangKyTrungBay'
        ];
        
        if (count($headerRow) >= 14) {
            foreach ($expectedColumns as $idx => $columnName) {
                $indices[$columnName] = $idx;
            }
            return $indices;
        }
        
        foreach ($headerRow as $index => $header) {
            $normalized = $this->normalizeHeader($header);
            
            if (preg_match('/ma.*nvbh/', $normalized)) $indices['MaNVBH'] = $index;
            if (preg_match('/ten.*nvbh/', $normalized)) $indices['TenNVBH'] = $index;
            if (preg_match('/ma.*kh.*dms/', $normalized)) $indices['MaKHDMS'] = $index;
            if (preg_match('/ten.*quay/', $normalized)) if (!isset($indices['TenQuay'])) $indices['TenQuay'] = $index;
            if (preg_match('/ten.*chu/', $normalized)) $indices['TenChuCuaHang'] = $index;
            if (preg_match('/^ngay.*sinh$/', $normalized)) $indices['NgaySinh'] = $index;
            if (preg_match('/^thang.*sinh$/', $normalized)) $indices['ThangSinh'] = $index;
            if (preg_match('/^nam.*sinh$/', $normalized)) $indices['NamSinh'] = $index;
            if (preg_match('/zalo/', $normalized)) if (!isset($indices['SDTZalo'])) $indices['SDTZalo'] = $index;
            if (preg_match('/dinh.*danh/', $normalized) && !preg_match('/khop/', $normalized)) $indices['SDTDaDinhDanh'] = $index;
            if (preg_match('/khop/', $normalized)) $indices['KhopSDT'] = $index;
            if (preg_match('/chuong.*trinh/', $normalized)) $indices['DangKyChuongTrinh'] = $index;
            if (preg_match('/muc.*doanh/', $normalized)) $indices['DangKyMucDoanhSo'] = $index;
            if (preg_match('/trung.*bay/', $normalized) || preg_match('/quang.*cao/', $normalized)) $indices['DangKyTrungBay'] = $index;
        }

        return $indices;
    }

    private function normalizeHeader($header) {
        $normalized = strtolower(trim($header));
        $normalized = preg_replace('/[À Á Ả Ã Ạ Ă Ằ Ắ Ẳ Ẵ Ặ Â Ầ Ấ Ẩ Ẫ Ậ]/u', 'a', $normalized);
        $normalized = preg_replace('/[È É Ẻ Ẽ Ẹ Ê Ề Ế Ể Ễ Ệ]/u', 'e', $normalized);
        $normalized = preg_replace('/[Ì Í Ỉ Ĩ Ị]/u', 'i', $normalized);
        $normalized = preg_replace('/[Ò Ó Ỏ Õ Ọ Ô Ồ Ố Ổ Ỗ Ộ Ơ Ờ Ớ Ở Ỡ Ợ]/u', 'o', $normalized);
        $normalized = preg_replace('/[Ù Ú Ủ Ũ Ụ Ư Ừ Ứ Ử Ữ Ự]/u', 'u', $normalized);
        $normalized = preg_replace('/[Ỳ Ý Ỷ Ỹ Ỵ]/u', 'y', $normalized);
        $normalized = preg_replace('/[Đ]/u', 'd', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        return $normalized;
    }

    private function convertYN($value) {
        if (empty($value) || $value === '' || $value === 'NULL') return null;
        
        $cleaned = strtoupper(trim($value));
        
        if ($cleaned === 'Y' || $cleaned === 'YES' || $cleaned === '1') return 'Y';
        if ($cleaned === 'N' || $cleaned === 'NO' || $cleaned === '0') return 'N';
        
        return null;
    }

    private function cleanNumber($value, $asTinyInt = false) {
        if (empty($value) || $value === '' || $value === 'NULL') return null;
        
        $cleaned = str_replace([',', ' ', '.'], '', trim($value));
        
        if (is_numeric($cleaned)) {
            return $asTinyInt ? (int)$cleaned : $cleaned;
        }
        
        return null;
    }
}
?>