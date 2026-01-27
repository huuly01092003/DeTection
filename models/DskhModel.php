<?php
require_once 'config/database.php';

class DskhModel {
    private $conn;
    private $table = "dskh";
    private const PAGE_SIZE = 50;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function importCSV($filePath) {
        try {
            $this->conn->beginTransaction();
            
            $fileContent = file_get_contents($filePath);
            if (!mb_check_encoding($fileContent, 'UTF-8')) {
                $fileContent = mb_convert_encoding($fileContent, 'UTF-8', 'auto');
            }
            
            $rows = array_map(function($line) {
                return str_getcsv($line, ',', '"');
            }, explode("\n", $fileContent));
            
            $isFirstRow = true;
            $insertedCount = 0;
            
            // ✅ Thêm cột MaSoThue
            $sql = "INSERT INTO {$this->table} (
                MaKH, Area, MaGSBH, MaNPP, MaNVBH, TenNVBH,
                TenKH, PhanLoaiNhomKH, LoaiKH, DiaChi, QuanHuyen, Tinh, Location, MaSoThue
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                Area = VALUES(Area),
                MaGSBH = VALUES(MaGSBH),
                MaNPP = VALUES(MaNPP),
                MaNVBH = VALUES(MaNVBH),
                TenNVBH = VALUES(TenNVBH),
                TenKH = VALUES(TenKH),
                PhanLoaiNhomKH = VALUES(PhanLoaiNhomKH),
                LoaiKH = VALUES(LoaiKH),
                DiaChi = VALUES(DiaChi),
                QuanHuyen = VALUES(QuanHuyen),
                Tinh = VALUES(Tinh),
                Location = VALUES(Location),
                MaSoThue = VALUES(MaSoThue)";
            
            $stmt = $this->conn->prepare($sql);
            
            foreach ($rows as $row) {
                if (empty($row) || count($row) < 13) {
                    continue;
                }
                
                if ($isFirstRow) {
                    $isFirstRow = false;
                    continue;
                }
                
                $data = [];
                for ($i = 0; $i < 14; $i++) {
                    $data[$i] = !empty(trim($row[$i] ?? '')) ? trim($row[$i]) : null;
                }
                
                if (empty($data[0])) {
                    continue;
                }
                
                $stmt->execute($data);
                
                if ($stmt->rowCount() > 0) {
                    $insertedCount++;
                }
            }
            
            $this->conn->commit();
            
            return ['success' => true, 'inserted' => $insertedCount];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getAll($filters = [], $page = 1) {
        $page = max(1, (int)$page);
        $offset = ($page - 1) * self::PAGE_SIZE;
        
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if (!empty($filters['tinh'])) {
            $sql .= " AND Tinh = :tinh";
            $params[':tinh'] = $filters['tinh'];
        }
        
        if (!empty($filters['quan_huyen'])) {
            $sql .= " AND QuanHuyen = :quan_huyen";
            $params[':quan_huyen'] = $filters['quan_huyen'];
        }
        
        if (!empty($filters['ma_kh'])) {
            $sql .= " AND MaKH LIKE :ma_kh";
            $params[':ma_kh'] = '%' . $filters['ma_kh'] . '%';
        }
        
        if (!empty($filters['loai_kh'])) {
            $sql .= " AND LoaiKH = :loai_kh";
            $params[':loai_kh'] = $filters['loai_kh'];
        }
        
        $sql .= " ORDER BY MaKH LIMIT :limit OFFSET :offset";
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
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if (!empty($filters['tinh'])) {
            $sql .= " AND Tinh = :tinh";
            $params[':tinh'] = $filters['tinh'];
        }
        
        if (!empty($filters['quan_huyen'])) {
            $sql .= " AND QuanHuyen = :quan_huyen";
            $params[':quan_huyen'] = $filters['quan_huyen'];
        }
        
        if (!empty($filters['ma_kh'])) {
            $sql .= " AND MaKH LIKE :ma_kh";
            $params[':ma_kh'] = '%' . $filters['ma_kh'] . '%';
        }
        
        if (!empty($filters['loai_kh'])) {
            $sql .= " AND LoaiKH = :loai_kh";
            $params[':loai_kh'] = $filters['loai_kh'];
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    public function getProvinces() {
        $sql = "SELECT DISTINCT Tinh FROM {$this->table} WHERE Tinh IS NOT NULL ORDER BY Tinh";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getDistricts($tinh = null) {
        $sql = "SELECT DISTINCT QuanHuyen FROM {$this->table} WHERE QuanHuyen IS NOT NULL";
        $params = [];
        
        if ($tinh) {
            $sql .= " AND Tinh = :tinh";
            $params[':tinh'] = $tinh;
        }
        
        $sql .= " ORDER BY QuanHuyen";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getCustomerTypes() {
        $sql = "SELECT DISTINCT LoaiKH FROM {$this->table} WHERE LoaiKH IS NOT NULL ORDER BY LoaiKH";
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
}
?>