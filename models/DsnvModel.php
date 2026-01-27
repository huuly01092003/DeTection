<?php
require_once __DIR__ . '/../config/database.php';

class DsnvModel {
    private $db;
    private $cache;
    private const PAGE_SIZE = 50;
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->cache = RedisCache::getInstance();
    }
    
    /**
     * Import CSV data with smart detection for INSERT, UPDATE, DELETE
     * Returns array with counts: inserted, updated, deleted, unchanged
     */
    public function importFromCSV($filePath) {
        $stats = [
            'inserted' => 0,
            'updated' => 0,
            'deleted' => 0,
            'unchanged' => 0,
            'errors' => []
        ];
        
        if (!file_exists($filePath)) {
            throw new Exception("File not found: $filePath");
        }
        
        // Parse CSV
        $csvData = [];
        $handle = fopen($filePath, 'r');
        $headers = fgetcsv($handle); // First row is headers
        
        // Map headers (handle Vietnamese headers)
        $headerMap = $this->mapHeaders($headers);
        
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($headerMap)) continue; // Skip incomplete rows
            
            $record = [];
            foreach ($headerMap as $index => $dbColumn) {
                $record[$dbColumn] = trim($row[$index]);
            }
            
            // Skip if no ma_nv
            if (empty($record['ma_nv'])) continue;
            
            $csvData[$record['ma_nv']] = $record;
        }
        fclose($handle);
        
        // Get existing data from DB
        $existingData = $this->getAllEmployeesKeyedByMaNV();
        
        // Compare and determine actions
        $csvKeys = array_keys($csvData);
        $dbKeys = array_keys($existingData);
        
        // Records to INSERT (in CSV but not in DB)
        $toInsert = array_diff($csvKeys, $dbKeys);
        
        // Records to DELETE (in DB but not in CSV)
        $toDelete = array_diff($dbKeys, $csvKeys);
        
        // Records to UPDATE or leave UNCHANGED (in both)
        $toCheck = array_intersect($csvKeys, $dbKeys);
        
        // Begin transaction
        $this->db->beginTransaction();
        
        try {
            // INSERT new records
            foreach ($toInsert as $maNV) {
                $this->insertEmployee($csvData[$maNV]);
                $stats['inserted']++;
            }
            
            // DELETE removed records
            foreach ($toDelete as $maNV) {
                $this->deleteEmployee($maNV);
                $stats['deleted']++;
            }
            
            // UPDATE changed records
            foreach ($toCheck as $maNV) {
                $csvRecord = $csvData[$maNV];
                $dbRecord = $existingData[$maNV];
                
                // Check if manager changed (important business logic)
                $managerChanged = ($csvRecord['ma_nv_ql'] !== $dbRecord['ma_nv_ql']);
                
                if ($this->hasChanges($csvRecord, $dbRecord)) {
                    $this->updateEmployee($csvRecord, $managerChanged);
                    $stats['updated']++;
                } else {
                    $stats['unchanged']++;
                }
            }
            
            $this->db->commit();
            
            // Clear cache
            $this->cache->clear('dsnv:*');
            
            return $stats;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Map CSV headers to database columns
     */
    private function mapHeaders($headers) {
        $map = [
            'Mã NV' => 'ma_nv',
            'Họ tên' => 'ho_ten',
            'Giới tính' => 'gioi_tinh',
            'Ngày sinh' => 'ngay_sinh',
            'SĐT cá nhân' => 'sdt_ca_nhan',
            'Bộ phận' => 'bo_phan',
            'Chức vụ' => 'chuc_vu',
            'Base tỉnh' => 'base_tinh',
            'Khu vực' => 'khu_vuc',
            'Kênh bán hàng' => 'kenh_ban_hang',
            'Ngày vào CTY' => 'ngay_vao_cty',
            'Trạng thái' => 'trang_thai',
            'Mã NV QL' => 'ma_nv_ql',
            'Tên NV QL' => 'ten_nv_ql'
        ];
        
        $headerMap = [];
        foreach ($headers as $index => $header) {
            $header = trim($header);
            if (isset($map[$header])) {
                $headerMap[$index] = $map[$header];
            } else {
                // Try direct match
                $headerMap[$index] = strtolower(str_replace(' ', '_', $header));
            }
        }
        
        return $headerMap;
    }
    
    /**
     * Get all employees keyed by ma_nv
     */
    private function getAllEmployeesKeyedByMaNV() {
        $stmt = $this->db->query("SELECT * FROM dsnv");
        $result = [];
        while ($row = $stmt->fetch()) {
            $result[$row['ma_nv']] = $row;
        }
        return $result;
    }
    
    /**
     * Check if CSV record differs from DB record
     */
    private function hasChanges($csvRecord, $dbRecord) {
        $fieldsToCheck = ['ho_ten', 'gioi_tinh', 'ngay_sinh', 'sdt_ca_nhan', 
                          'bo_phan', 'chuc_vu', 'base_tinh', 'khu_vuc', 
                          'kenh_ban_hang', 'ngay_vao_cty', 'trang_thai', 
                          'ma_nv_ql', 'ten_nv_ql'];
        
        foreach ($fieldsToCheck as $field) {
            if (($csvRecord[$field] ?? '') !== ($dbRecord[$field] ?? '')) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Insert new employee
     */
    private function insertEmployee($data) {
        $sql = "INSERT INTO dsnv (
                    ma_nv, ho_ten, gioi_tinh, ngay_sinh, sdt_ca_nhan, 
                    bo_phan, chuc_vu, base_tinh, khu_vuc, kenh_ban_hang, 
                    ngay_vao_cty, trang_thai, ma_nv_ql, ten_nv_ql
                ) VALUES (
                    :ma_nv, :ho_ten, :gioi_tinh, :ngay_sinh, :sdt_ca_nhan,
                    :bo_phan, :chuc_vu, :base_tinh, :khu_vuc, :kenh_ban_hang,
                    :ngay_vao_cty, :trang_thai, :ma_nv_ql, :ten_nv_ql
                )";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($this->prepareData($data));
    }
    
    /**
     * Update existing employee
     */
    private function updateEmployee($data, $managerChanged = false) {
        $sql = "UPDATE dsnv SET 
                    ho_ten = :ho_ten,
                    gioi_tinh = :gioi_tinh,
                    ngay_sinh = :ngay_sinh,
                    sdt_ca_nhan = :sdt_ca_nhan,
                    bo_phan = :bo_phan,
                    chuc_vu = :chuc_vu,
                    base_tinh = :base_tinh,
                    khu_vuc = :khu_vuc,
                    kenh_ban_hang = :kenh_ban_hang,
                    ngay_vao_cty = :ngay_vao_cty,
                    trang_thai = :trang_thai,
                    ma_nv_ql = :ma_nv_ql,
                    ten_nv_ql = :ten_nv_ql
                WHERE ma_nv = :ma_nv";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($this->prepareData($data));
    }
    
    /**
     * Delete employee by ma_nv
     */
    private function deleteEmployee($maNV) {
        $stmt = $this->db->prepare("DELETE FROM dsnv WHERE ma_nv = :ma_nv");
        return $stmt->execute(['ma_nv' => $maNV]);
    }
    
    /**
     * Prepare data for binding
     */
    private function prepareData($data) {
        return [
            'ma_nv' => $data['ma_nv'] ?? '',
            'ho_ten' => $data['ho_ten'] ?? '',
            'gioi_tinh' => $data['gioi_tinh'] ?? '',
            'ngay_sinh' => $this->formatDate($data['ngay_sinh'] ?? ''),
            'sdt_ca_nhan' => $data['sdt_ca_nhan'] ?? '',
            'bo_phan' => $data['bo_phan'] ?? '',
            'chuc_vu' => $data['chuc_vu'] ?? '',
            'base_tinh' => $data['base_tinh'] ?? '',
            'khu_vuc' => $data['khu_vuc'] ?? '',
            'kenh_ban_hang' => $data['kenh_ban_hang'] ?? '',
            'ngay_vao_cty' => $this->formatDate($data['ngay_vao_cty'] ?? ''),
            'trang_thai' => $data['trang_thai'] ?? '',
            'ma_nv_ql' => $data['ma_nv_ql'] ?? '',
            'ten_nv_ql' => $data['ten_nv_ql'] ?? ''
        ];
    }
    
    /**
     * Format date from DD/MM/YYYY to YYYY-MM-DD
     */
    private function formatDate($dateStr) {
        if (empty($dateStr)) return null;
        
        // Try DD/MM/YYYY format
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dateStr, $matches)) {
            return sprintf('%04d-%02d-%02d', $matches[3], $matches[2], $matches[1]);
        }
        
        // Try YYYY-MM-DD format (already correct)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            return $dateStr;
        }
        
        return null;
    }
    
    /**
     * Get employees with pagination and filters
     */
    public function getEmployees($page = 1, $filters = []) {
        $offset = ($page - 1) * PAGE_SIZE;
        
        // Build cache key
        $cacheKey = 'dsnv:list:' . md5(serialize(['page' => $page, 'filters' => $filters]));
        
        // Try cache first
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        // Build WHERE clause
        $where = [];
        $params = [];
        
        if (!empty($filters['bo_phan'])) {
            $where[] = "bo_phan = :bo_phan";
            $params['bo_phan'] = $filters['bo_phan'];
        }
        
        if (!empty($filters['chuc_vu'])) {
            $where[] = "chuc_vu = :chuc_vu";
            $params['chuc_vu'] = $filters['chuc_vu'];
        }
        
        if (!empty($filters['base_tinh'])) {
            $where[] = "base_tinh = :base_tinh";
            $params['base_tinh'] = $filters['base_tinh'];
        }
        
        if (!empty($filters['trang_thai'])) {
            $where[] = "trang_thai = :trang_thai";
            $params['trang_thai'] = $filters['trang_thai'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(ho_ten LIKE :search OR ma_nv LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        $whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Get total count
        $countSQL = "SELECT COUNT(*) as total FROM dsnv $whereSQL";
        $stmt = $this->db->prepare($countSQL);
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];
        
        // Get data
        $dataSQL = "SELECT * FROM dsnv $whereSQL ORDER BY ma_nv LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($dataSQL);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', PAGE_SIZE, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = [
            'data' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'total_pages' => ceil($total / PAGE_SIZE),
            'page_size' => PAGE_SIZE
        ];
        
        // Cache result
        $this->cache->set($cacheKey, $result);
        
        return $result;
    }
    
    /**
     * Get statistics
     */
    public function getStats() {
        $cacheKey = 'dsnv:stats';
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        $sql = "SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN trang_thai = 'Đang làm' THEN 1 END) as active,
                    COUNT(CASE WHEN trang_thai = 'Nghỉ việc' THEN 1 END) as inactive,
                    COUNT(DISTINCT bo_phan) as departments
                FROM dsnv";
        
        $stmt = $this->db->query($sql);
        $stats = $stmt->fetch();
        
        $this->cache->set($cacheKey, $stats);
        return $stats;
    }
    
    /**
     * Get unique values for filters
     */
    public function getFilterOptions() {
        $cacheKey = 'dsnv:filter_options';
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        $result = [
            'bo_phan' => $this->getDistinctValues('bo_phan'),
            'chuc_vu' => $this->getDistinctValues('chuc_vu'),
            'base_tinh' => $this->getDistinctValues('base_tinh'),
            'trang_thai' => $this->getDistinctValues('trang_thai')
        ];
        
        $this->cache->set($cacheKey, $result);
        return $result;
    }
    
    private function getDistinctValues($column) {
        $stmt = $this->db->query("SELECT DISTINCT $column FROM dsnv WHERE $column IS NOT NULL AND $column != '' ORDER BY $column");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Export to CSV
     */
    public function exportToCSV() {
        $sql = "SELECT * FROM dsnv ORDER BY ma_nv";
        $stmt = $this->db->query($sql);
        
        $filename = 'dsnv_export_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = '/tmp/' . $filename;
        
        $fp = fopen($filepath, 'w');
        
        // Write headers
        fputcsv($fp, [
            'Mã NV', 'Họ tên', 'Giới tính', 'Ngày sinh', 'SĐT cá nhân',
            'Bộ phận', 'Chức vụ', 'Base tỉnh', 'Khu vực', 'Kênh bán hàng',
            'Ngày vào CTY', 'Trạng thái', 'Mã NV QL', 'Tên NV QL'
        ]);
        
        // Write data
        while ($row = $stmt->fetch()) {
            fputcsv($fp, [
                $row['ma_nv'],
                $row['ho_ten'],
                $row['gioi_tinh'],
                $row['ngay_sinh'],
                $row['sdt_ca_nhan'],
                $row['bo_phan'],
                $row['chuc_vu'],
                $row['base_tinh'],
                $row['khu_vuc'],
                $row['kenh_ban_hang'],
                $row['ngay_vao_cty'],
                $row['trang_thai'],
                $row['ma_nv_ql'],
                $row['ten_nv_ql']
            ]);
        }
        
        fclose($fp);
        return $filepath;
    }
}
?>