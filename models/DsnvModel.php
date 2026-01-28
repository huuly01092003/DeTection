<?php
/**
 * ============================================================
 * DSNV MODEL - Giống 100% GkhlModel về cấu trúc
 * ============================================================
 * 
 * Logic:
 * - 1 NV (MaNV) = 1 bản ghi duy nhất
 * - Nếu dữ liệu thay đổi → UPDATE
 * - Nếu chưa tồn tại → INSERT mới
 */

require_once __DIR__ . '/../config/database.php';

class DsnvModel {
    private $conn;
    private $table = "dsnv";
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
        
        // 1. Đọc nội dung file
        $fileContent = file_get_contents($filePath);
        
        // 2. Xử lý ký tự BOM (Quan trọng nhất - sửa lỗi không tìm thấy Mã NV)
        $bom = pack('H*','EFBBBF');
        $fileContent = preg_replace("/^$bom/", '', $fileContent);

        // 3. Đảm bảo mã hóa UTF-8
        if (!mb_check_encoding($fileContent, 'UTF-8')) {
            $fileContent = mb_convert_encoding($fileContent, 'UTF-8', 'auto');
        }
        
        // 4. Tách các dòng và parse CSV
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $fileContent));
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $rows[] = str_getcsv($line, ',', '"');
            }
        }

        if (empty($rows)) {
            return ['success' => false, 'error' => 'File CSV không có dữ liệu'];
        }

        // 5. Lấy và nhận diện Header
        $headerRow = $rows[0];
        $indices = $this->mapHeaders($headerRow);

        // Kiểm tra cột bắt buộc
        if (!isset($indices['MaNV'])) {
            return [
                'success' => false, 
                'error' => "Không tìm thấy cột 'Mã Nhân Viên' hoặc 'Mã NV' trong file CSV.<br>Headers tìm thấy: " . implode(', ', $headerRow)
            ];
        }

        $inserted = 0;
        $updated = 0;
        $errors = 0;
        $skipped = 0;

        // 6. Xử lý dữ liệu từ dòng thứ 2 (index 1)
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            
            // Lấy Mã NV và làm sạch
            $ma_nv = isset($indices['MaNV']) ? trim($row[$indices['MaNV']]) : '';
            
            if (empty($ma_nv)) {
                $skipped++;
                continue;
            }

            try {
                // Hàm helper để chuẩn hóa ngày tháng cho MySQL (d/m/Y -> Y-m-d)
                $formatDate = function($dateStr) {
                    if (empty(trim($dateStr))) return null;
                    $dateStr = str_replace('/', '-', trim($dateStr));
                    $timestamp = strtotime($dateStr);
                    return $timestamp ? date('Y-m-d', $timestamp) : null;
                };

                $data = [
                    ':ma_nv'         => $ma_nv,
                    ':ho_ten'        => isset($indices['HoTen']) ? trim($row[$indices['HoTen']]) : '',
                    ':gioi_tinh'     => isset($indices['GioiTinh']) ? trim($row[$indices['GioiTinh']]) : null,
                    ':ngay_sinh'     => isset($indices['NgaySinh']) ? $formatDate($row[$indices['NgaySinh']]) : null,
                    ':sdt_ca_nhan'   => isset($indices['SDTCaNhan']) ? trim($row[$indices['SDTCaNhan']]) : null,
                    ':bo_phan'       => isset($indices['BoPhan']) ? trim($row[$indices['BoPhan']]) : null,
                    ':chuc_vu'       => isset($indices['ChucVu']) ? trim($row[$indices['ChucVu']]) : null,
                    ':base_tinh'     => isset($indices['BaseTinh']) ? trim($row[$indices['BaseTinh']]) : null,
                    ':khu_vuc'       => isset($indices['KhuVuc']) ? trim($row[$indices['KhuVuc']]) : null,
                    ':kenh_ban_hang' => isset($indices['KenhBanHang']) ? trim($row[$indices['KenhBanHang']]) : null,
                    ':ngay_vao_cty'  => isset($indices['NgayVaoCty']) ? $formatDate($row[$indices['NgayVaoCty']]) : null,
                    ':trang_thai'    => isset($indices['TrangThai']) ? trim($row[$indices['TrangThai']]) : 'Đang làm việc',
                    ':ma_nv_ql'      => isset($indices['MaNVQL']) ? trim($row[$indices['MaNVQL']]) : null,
                    ':ten_nv_ql'     => isset($indices['TenNVQL']) ? trim($row[$indices['TenNVQL']]) : null
                ];

                // Câu lệnh UPSERT (INSERT hoặc UPDATE nếu trùng Primary Key)
                $sql = "INSERT INTO dsnv (
                            ma_nv, ho_ten, gioi_tinh, ngay_sinh, sdt_ca_nhan, 
                            bo_phan, chuc_vu, base_tinh, khu_vuc, kenh_ban_hang, 
                            ngay_vao_cty, trang_thai, ma_nv_ql, ten_nv_ql
                        ) VALUES (
                            :ma_nv, :ho_ten, :gioi_tinh, :ngay_sinh, :sdt_ca_nhan, 
                            :bo_phan, :chuc_vu, :base_tinh, :khu_vuc, :kenh_ban_hang, 
                            :ngay_vao_cty, :trang_thai, :ma_nv_ql, :ten_nv_ql
                        ) ON DUPLICATE KEY UPDATE 
                            ho_ten = VALUES(ho_ten),
                            gioi_tinh = VALUES(gioi_tinh),
                            ngay_sinh = VALUES(ngay_sinh),
                            sdt_ca_nhan = VALUES(sdt_ca_nhan),
                            bo_phan = VALUES(bo_phan),
                            chuc_vu = VALUES(chuc_vu),
                            base_tinh = VALUES(base_tinh),
                            khu_vuc = VALUES(khu_vuc),
                            kenh_ban_hang = VALUES(kenh_ban_hang),
                            ngay_vao_cty = VALUES(ngay_vao_cty),
                            trang_thai = VALUES(trang_thai),
                            ma_nv_ql = VALUES(ma_nv_ql),
                            ten_nv_ql = VALUES(ten_nv_ql)";

                $stmt = $this->conn->prepare($sql);
                $stmt->execute($data);

                if ($stmt->rowCount() == 1) $inserted++;
                else $updated++;

            } catch (Exception $e) {
                $errors++;
                error_log("Import row error: " . $e->getMessage());
            }
        }

        $this->conn->commit();
        $this->conn->exec("SET FOREIGN_KEY_CHECKS=1");

        return [
            'success' => true,
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors
        ];

    } catch (Exception $e) {
        $this->conn->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

    /**
     * ============================================================
     * Execute Batch - INSERT hoặc UPDATE
     * ============================================================
     */
    private function executeBatch(&$batch) {
        $inserted = 0;
        $updated = 0;
        $errors = 0;

        foreach ($batch as $data) {
            try {
                // Kiểm tra bản ghi đã tồn tại chưa
                $checkSql = "SELECT id FROM {$this->table} WHERE ma_nv = :maNV LIMIT 1";
                $checkStmt = $this->conn->prepare($checkSql);
                $checkStmt->execute([':maNV' => $data['maNV']]);
                
                $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);

                if ($existingRecord) {
                    // UPDATE
                    $updateSql = "UPDATE {$this->table} SET
                        ho_ten = :hoTen,
                        gioi_tinh = :gioiTinh,
                        ngay_sinh = :ngaySinh,
                        sdt_ca_nhan = :sdtCaNhan,
                        bo_phan = :boPhan,
                        chuc_vu = :chucVu,
                        base_tinh = :baseTinh,
                        khu_vuc = :khuVuc,
                        kenh_ban_hang = :kenhBanHang,
                        ngay_vao_cty = :ngayVaoCty,
                        trang_thai = :trangThai,
                        ma_nv_ql = :maNVQL,
                        ten_nv_ql = :tenNVQL
                    WHERE ma_nv = :maNV";
                    
                    $updateStmt = $this->conn->prepare($updateSql);
                    if ($updateStmt->execute([
                        ':hoTen' => $data['hoTen'],
                        ':gioiTinh' => $data['gioiTinh'],
                        ':ngaySinh' => $data['ngaySinh'],
                        ':sdtCaNhan' => $data['sdtCaNhan'],
                        ':boPhan' => $data['boPhan'],
                        ':chucVu' => $data['chucVu'],
                        ':baseTinh' => $data['baseTinh'],
                        ':khuVuc' => $data['khuVuc'],
                        ':kenhBanHang' => $data['kenhBanHang'],
                        ':ngayVaoCty' => $data['ngayVaoCty'],
                        ':trangThai' => $data['trangThai'],
                        ':maNVQL' => $data['maNVQL'],
                        ':tenNVQL' => $data['tenNVQL'],
                        ':maNV' => $data['maNV']
                    ])) {
                        $updated++;
                    } else {
                        $errors++;
                    }
                } else {
                    // INSERT
                    $insertSql = "INSERT INTO {$this->table} (
                        ma_nv, ho_ten, gioi_tinh, ngay_sinh, sdt_ca_nhan,
                        bo_phan, chuc_vu, base_tinh, khu_vuc, kenh_ban_hang,
                        ngay_vao_cty, trang_thai, ma_nv_ql, ten_nv_ql
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $insertStmt = $this->conn->prepare($insertSql);
                    if ($insertStmt->execute([
                        $data['maNV'], $data['hoTen'], $data['gioiTinh'],
                        $data['ngaySinh'], $data['sdtCaNhan'], $data['boPhan'],
                        $data['chucVu'], $data['baseTinh'], $data['khuVuc'],
                        $data['kenhBanHang'], $data['ngayVaoCty'], $data['trangThai'],
                        $data['maNVQL'], $data['tenNVQL']
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

        return ['inserted' => $inserted, 'updated' => $updated, 'errors' => $errors];
    }

    public function getAll($filters = [], $page = 1) {
        $page = max(1, (int)$page);
        $offset = ($page - 1) * self::PAGE_SIZE;
        
        $conditions = [];
        $params = [];
        
        if (!empty($filters['bo_phan'])) {
            $conditions[] = "bo_phan = :bo_phan";
            $params[':bo_phan'] = $filters['bo_phan'];
        }
        
        if (!empty($filters['chuc_vu'])) {
            $conditions[] = "chuc_vu = :chuc_vu";
            $params[':chuc_vu'] = $filters['chuc_vu'];
        }
        
        if (!empty($filters['base_tinh'])) {
            $conditions[] = "base_tinh = :base_tinh";
            $params[':base_tinh'] = $filters['base_tinh'];
        }
        
        if (!empty($filters['trang_thai'])) {
            $conditions[] = "trang_thai = :trang_thai";
            $params[':trang_thai'] = $filters['trang_thai'];
        }
        
        if (!empty($filters['search'])) {
            $conditions[] = "(ho_ten LIKE :search OR ma_nv LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        $sql = "SELECT * FROM {$this->table} 
                {$whereClause}
                ORDER BY ma_nv 
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
        
        if (!empty($filters['bo_phan'])) {
            $conditions[] = "bo_phan = :bo_phan";
            $params[':bo_phan'] = $filters['bo_phan'];
        }
        
        if (!empty($filters['chuc_vu'])) {
            $conditions[] = "chuc_vu = :chuc_vu";
            $params[':chuc_vu'] = $filters['chuc_vu'];
        }
        
        if (!empty($filters['base_tinh'])) {
            $conditions[] = "base_tinh = :base_tinh";
            $params[':base_tinh'] = $filters['base_tinh'];
        }
        
        if (!empty($filters['trang_thai'])) {
            $conditions[] = "trang_thai = :trang_thai";
            $params[':trang_thai'] = $filters['trang_thai'];
        }
        
        if (!empty($filters['search'])) {
            $conditions[] = "(ho_ten LIKE :search OR ma_nv LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        $sql = "SELECT COUNT(*) as total FROM {$this->table} {$whereClause}";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    public function getDepartments() {
        $sql = "SELECT DISTINCT bo_phan FROM {$this->table} 
                WHERE bo_phan IS NOT NULL 
                ORDER BY bo_phan 
                LIMIT 200";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getPositions() {
        $sql = "SELECT DISTINCT chuc_vu FROM {$this->table} 
                WHERE chuc_vu IS NOT NULL 
                ORDER BY chuc_vu 
                LIMIT 200";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getProvinces() {
        $sql = "SELECT DISTINCT base_tinh FROM {$this->table} 
                WHERE base_tinh IS NOT NULL 
                ORDER BY base_tinh 
                LIMIT 200";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getStatuses() {
        $sql = "SELECT DISTINCT trang_thai FROM {$this->table} 
                WHERE trang_thai IS NOT NULL 
                ORDER BY trang_thai";
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

    public function getActiveCount() {
    // Đếm cả nhân viên Chính thức và Thử việc là đang làm việc
    $sql = "SELECT COUNT(*) FROM {$this->table} WHERE trang_thai IN ('Chính thức', 'Thử việc')";
    $stmt = $this->conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchColumn();
}

    // ========== PRIVATE METHODS ==========

    private function parseDsnvHeader($headerRow) {
        $indices = [];
        
        // Map các tên cột tiếng Việt sang internal keys
        $columnMapping = [
            'Mã Nhân Viên' => 'MaNV',
            'Mã NV' => 'MaNV',
            'ma_nv' => 'MaNV',
            'MaNV' => 'MaNV',
            
            'Họ Và Tên' => 'HoTen',
            'Họ Tên' => 'HoTen',
            'ho_ten' => 'HoTen',
            'HoTen' => 'HoTen',
            
            'Giới Tính' => 'GioiTinh',
            'gioi_tinh' => 'GioiTinh',
            'GioiTinh' => 'GioiTinh',
            
            'Ngày Sinh' => 'NgaySinh',
            'ngay_sinh' => 'NgaySinh',
            'NgaySinh' => 'NgaySinh',
            
            'Số Điện Thoại Cá Nhân' => 'SDTCaNhan',
            'Số Điện Thoại Cá nhân' => 'SDTCaNhan',
            'SĐT Cá Nhân' => 'SDTCaNhan',
            'sdt_ca_nhan' => 'SDTCaNhan',
            'SDTCaNhan' => 'SDTCaNhan',
            
            'Bộ Phận' => 'BoPhan',
            'bo_phan' => 'BoPhan',
            'BoPhan' => 'BoPhan',
            
            'Chức Vụ' => 'ChucVu',
            'chuc_vu' => 'ChucVu',
            'ChucVu' => 'ChucVu',
            
            'Base (Tỉnh)' => 'BaseTinh',
            'Base Tỉnh' => 'BaseTinh',
            'Base(Tỉnh)' => 'BaseTinh',
            'base_tinh' => 'BaseTinh',
            'BaseTinh' => 'BaseTinh',
            
            'Khu Vực' => 'KhuVuc',
            'khu_vuc' => 'KhuVuc',
            'KhuVuc' => 'KhuVuc',
            
            'Kênh Bán Hàng' => 'KenhBanHang',
            'Kênh Bán' => 'KenhBanHang',
            'kenh_ban_hang' => 'KenhBanHang',
            'KenhBanHang' => 'KenhBanHang',
            
            'Ngày Vào Công Ty' => 'NgayVaoCty',
            'Ngày Vào CTY' => 'NgayVaoCty',
            'ngay_vao_cty' => 'NgayVaoCty',
            'NgayVaoCty' => 'NgayVaoCty',
            
            'Trạng Thái' => 'TrangThai',
            'trang_thai' => 'TrangThai',
            'TrangThai' => 'TrangThai',
            
            'Mã NV Quản Lý' => 'MaNVQL',
            'Mã NV QL' => 'MaNVQL',
            'Mã NV Quản Lý Trực tiếp' => 'MaNVQL',
            'ma_nv_ql' => 'MaNVQL',
            'MaNVQL' => 'MaNVQL',
            
            'Tên NV Quản Lý' => 'TenNVQL',
            'Tên NV QL' => 'TenNVQL',
            'Tên NV Quản Lý Trực tiếp' => 'TenNVQL',
            'ten_nv_ql' => 'TenNVQL',
            'TenNVQL' => 'TenNVQL'
        ];
        
        // Parse header row
        foreach ($headerRow as $index => $header) {
            $header = trim($header);
            
            // Direct mapping
            if (isset($columnMapping[$header])) {
                $indices[$columnMapping[$header]] = $index;
                continue;
            }
            
            // Normalized matching
            $normalized = $this->normalizeHeader($header);
            
            // Match patterns for common variations
            if (preg_match('/^ma\s*nhan\s*vien/i', $normalized) || 
                preg_match('/^ma\s*nv(?!\s*quan|\s*ql)/i', $normalized)) {
                $indices['MaNV'] = $index;
            }
            elseif (preg_match('/ho.*ten/i', $normalized)) {
                $indices['HoTen'] = $index;
            }
            elseif (preg_match('/gioi.*tinh/i', $normalized)) {
                $indices['GioiTinh'] = $index;
            }
            elseif (preg_match('/ngay.*sinh/i', $normalized)) {
                $indices['NgaySinh'] = $index;
            }
            elseif (preg_match('/sdt|so.*dien.*thoai.*ca.*nhan/i', $normalized)) {
                $indices['SDTCaNhan'] = $index;
            }
            elseif (preg_match('/bo.*phan/i', $normalized)) {
                $indices['BoPhan'] = $index;
            }
            elseif (preg_match('/chuc.*vu/i', $normalized)) {
                $indices['ChucVu'] = $index;
            }
            elseif (preg_match('/base|tinh/i', $normalized)) {
                $indices['BaseTinh'] = $index;
            }
            elseif (preg_match('/khu.*vuc/i', $normalized)) {
                $indices['KhuVuc'] = $index;
            }
            elseif (preg_match('/kenh.*ban/i', $normalized)) {
                $indices['KenhBanHang'] = $index;
            }
            elseif (preg_match('/ngay.*vao/i', $normalized)) {
                $indices['NgayVaoCty'] = $index;
            }
            elseif (preg_match('/trang.*thai/i', $normalized)) {
                $indices['TrangThai'] = $index;
            }
            elseif (preg_match('/ma.*nv.*(quan.*ly|ql)/i', $normalized)) {
                $indices['MaNVQL'] = $index;
            }
            elseif (preg_match('/ten.*nv.*(quan.*ly|ql)/i', $normalized)) {
                $indices['TenNVQL'] = $index;
            }
        }

        return $indices;
    }

    

    private function removeBOM($text) {
    $bom = pack('H*','EFBBBF');
    $text = preg_replace("/^$bom/", '', $text);
    return $text;
}

/**
     * Nhận diện chỉ số cột dựa trên tên Header
     */
    private function mapHeaders($headerRow) {
        $indices = [];
        foreach ($headerRow as $index => $header) {
            // Chuẩn hóa tên cột để so sánh (xóa dấu, xóa khoảng trắng, viết thường)
            $normalized = $this->normalizeHeader($header);

            if (preg_match('/(ma.*nv|ma.*nhan.*vien)/i', $normalized) && !isset($indices['MaNV'])) {
                $indices['MaNV'] = $index;
            } 
            elseif (preg_match('/(ho.*ten|ten.*nv|nhan.*vien)/i', $normalized) && !isset($indices['HoTen'])) {
                $indices['HoTen'] = $index;
            }
            elseif (preg_match('/(gioi.*tinh)/i', $normalized)) {
                $indices['GioiTinh'] = $index;
            }
            elseif (preg_match('/(ngay.*sinh)/i', $normalized)) {
                $indices['NgaySinh'] = $index;
            }
            elseif (preg_match('/(sdt|dien.*thoai|ca.*nhan)/i', $normalized)) {
                $indices['SDTCaNhan'] = $index;
            }
            elseif (preg_match('/(bo.*phan|phong.*ban)/i', $normalized)) {
                $indices['BoPhan'] = $index;
            }
            elseif (preg_match('/(chuc.*vu)/i', $normalized)) {
                $indices['ChucVu'] = $index;
            }
            elseif (preg_match('/(base|tinh)/i', $normalized)) {
                $indices['BaseTinh'] = $index;
            }
            elseif (preg_match('/(khu.*vuc)/i', $normalized)) {
                $indices['KhuVuc'] = $index;
            }
            elseif (preg_match('/(kenh.*ban)/i', $normalized)) {
                $indices['KenhBanHang'] = $index;
            }
            elseif (preg_match('/(ngay.*vao|ngay.*lam)/i', $normalized)) {
                $indices['NgayVaoCty'] = $index;
            }
            elseif (preg_match('/(trang.*thai)/i', $normalized)) {
                $indices['TrangThai'] = $index;
            }
            elseif (preg_match('/ma.*nv.*(quan.*ly|ql)/i', $normalized)) {
                $indices['MaNVQL'] = $index;
            }
            elseif (preg_match('/ten.*nv.*(quan.*ly|ql)/i', $normalized)) {
                $indices['TenNVQL'] = $index;
            }
        }
        return $indices;
    }

    /**
     * Hàm chuẩn hóa chuỗi: Xóa BOM, xóa dấu Tiếng Việt, chuyển về chữ thường
     */
    private function normalizeHeader($header) {
        // 1. Xóa ký tự BOM nếu còn sót lại
        $bom = pack('H*','EFBBBF');
        $header = preg_replace("/^$bom/", '', $header);
        
        // 2. Chuyển về chữ thường
        $str = mb_strtolower(trim($header), 'UTF-8');
        
        // 3. Loại bỏ dấu Tiếng Việt để so sánh chính xác hơn
        $unicode = array(
            'a'=>'á|à|ả|ã|ạ|ă|ắ|ặ|ằ|ẳ|ẵ|â|ấ|ầ|ẩ|ẫ|ậ',
            'd'=>'đ',
            'e'=>'é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ',
            'i'=>'í|ì|ỉ|ĩ|ị',
            'o'=>'ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ',
            'u'=>'ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự',
            'y'=>'ý|ỳ|ỷ|ỹ|ỵ',
        );
        foreach($unicode as $nonUnicode=>$uni){
            $str = preg_replace("/($uni)/i", $nonUnicode, $str);
        }
        
        // 4. Xóa các ký tự đặc biệt, chỉ giữ lại chữ cái và số
        $str = preg_replace('/[^a-z0-0\s]/', '', $str);
        
        return $str;
    }
}
?>