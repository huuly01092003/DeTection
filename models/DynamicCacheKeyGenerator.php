<?php
/**
 * ============================================
 * DYNAMIC REDIS CACHE KEY GENERATOR - ENHANCED
 * ============================================
 * 
 * Tạo Redis Key linh hoạt cho mọi tổ hợp filter
 */

class DynamicCacheKeyGenerator {
    
    /**
     * Tạo Redis Key từ bộ filter động
     * 
     * @param array $filters [
     *   'years' => [2024, 2025],
     *   'months' => [1,2,3],
     *   'province' => 'Hồ Chí Minh', 
     *   'gkhl_status' => '1' // '1'=Yes, '0'=No, ''=All
     * ]
     * @return string Redis key
     */
    public static function generate(array $filters): string {
        $normalized = self::normalizeFilters($filters);
        
        $parts = [
            'anomaly',
            'report',
            self::buildYearSegment($normalized['years']),
            self::buildMonthSegment($normalized['months']),
            self::buildProvinceSegment($normalized['province']),
            self::buildGkhlSegment($normalized['gkhl_status'])
        ];
        
        return implode(':', $parts);
    }
    
    /**
     * Chuẩn hóa và validate filters
     */
    private static function normalizeFilters(array $filters): array {
        // CRITICAL: Ensure consistent handling of province parameter
        $province = '';
        if (isset($filters['province']) && $filters['province'] !== '') {
            $province = trim($filters['province']);
        } elseif (isset($filters['ma_tinh_tp']) && $filters['ma_tinh_tp'] !== '') {
            $province = trim($filters['ma_tinh_tp']);
        }
        
        return [
            'years' => self::normalizeYears($filters['years'] ?? []),
            'months' => self::normalizeMonths($filters['months'] ?? []),
            'province' => $province,
            'gkhl_status' => isset($filters['gkhl_status']) && $filters['gkhl_status'] !== '' 
                ? (string)$filters['gkhl_status'] 
                : ''
        ];
    }
    
    private static function normalizeYears($years): array {
        if (!is_array($years)) $years = [$years];
        $years = array_map('intval', array_filter($years));
        sort($years);
        return array_unique($years);
    }
    
    private static function normalizeMonths($months): array {
        if (!is_array($months)) $months = [$months];
        $months = array_map('intval', array_filter($months));
        $months = array_filter($months, fn($m) => $m >= 1 && $m <= 12);
        sort($months);
        return array_unique($months);
    }
    
    /**
     * Year Segment Builder
     * - Đơn: y2024
     * - Liên tục: y2024-2025
     * - Rời rạc: y2023_2025
     * - Tất cả: yall
     */
    private static function buildYearSegment(array $years): string {
        if (empty($years)) return 'yall';
        
        if (count($years) === 1) {
            return 'y' . $years[0];
        }
        
        // Kiểm tra liên tục
        if (self::isConsecutive($years)) {
            return 'y' . min($years) . '-' . max($years);
        }
        
        // Rời rạc
        return 'y' . implode('_', $years);
    }
    
    /**
     * Month Segment Builder
     * - Đơn: m01, m12
     * - Liên tục: m01-03, m10-12
     * - Rời rạc: m01_03_06_09
     * - Tất cả 12 tháng: mall
     */
    private static function buildMonthSegment(array $months): string {
        if (empty($months)) return 'mall';
        
        // Nếu đủ 12 tháng từ 1-12
        if (count($months) === 12 && min($months) === 1 && max($months) === 12) {
            return 'mall';
        }
        
        if (count($months) === 1) {
            return 'm' . str_pad($months[0], 2, '0', STR_PAD_LEFT);
        }
        
        // Kiểm tra liên tục
        if (self::isConsecutive($months)) {
            return 'm' . str_pad(min($months), 2, '0', STR_PAD_LEFT) . 
                   '-' . str_pad(max($months), 2, '0', STR_PAD_LEFT);
        }
        
        // Rời rạc
        return 'm' . implode('_', array_map(function($m) {
            return str_pad($m, 2, '0', STR_PAD_LEFT);
        }, $months));
    }
    
    /**
     * Province Segment Builder
     * - Có tỉnh: p_ho_chi_minh, p_ha_noi
     * - Tất cả: pall
     */
    private static function buildProvinceSegment(string $province): string {
        if (empty($province)) return 'pall';
        return 'p_' . self::slugify($province);
    }
    
    /**
     * GKHL Status Segment Builder
     * - Đã GKHL: gyes
     * - Chưa GKHL: gno
     * - Tất cả: gall
     */
    private static function buildGkhlSegment(string $gkhlStatus): string {
        if ($gkhlStatus === '' || $gkhlStatus === null) return 'gall';
        return $gkhlStatus === '1' ? 'gyes' : 'gno';
    }
    
    /**
     * Kiểm tra mảng số có liên tục không
     */
    private static function isConsecutive(array $numbers): bool {
        if (count($numbers) <= 1) return true;
        
        for ($i = 1; $i < count($numbers); $i++) {
            if ($numbers[$i] !== $numbers[$i-1] + 1) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Convert tiếng Việt thành slug (không dấu)
     */
    private static function slugify(string $text): string {
        $text = mb_strtolower($text, 'UTF-8');
        
        $vietnamese = [
            'à'=>'a','á'=>'a','ả'=>'a','ã'=>'a','ạ'=>'a',
            'ă'=>'a','ằ'=>'a','ắ'=>'a','ẳ'=>'a','ẵ'=>'a','ặ'=>'a',
            'â'=>'a','ầ'=>'a','ấ'=>'a','ẩ'=>'a','ẫ'=>'a','ậ'=>'a',
            'đ'=>'d',
            'è'=>'e','é'=>'e','ẻ'=>'e','ẽ'=>'e','ẹ'=>'e',
            'ê'=>'e','ề'=>'e','ế'=>'e','ể'=>'e','ễ'=>'e','ệ'=>'e',
            'ì'=>'i','í'=>'i','ỉ'=>'i','ĩ'=>'i','ị'=>'i',
            'ò'=>'o','ó'=>'o','ỏ'=>'o','õ'=>'o','ọ'=>'o',
            'ô'=>'o','ồ'=>'o','ố'=>'o','ổ'=>'o','ỗ'=>'o','ộ'=>'o',
            'ơ'=>'o','ờ'=>'o','ớ'=>'o','ở'=>'o','ỡ'=>'o','ợ'=>'o',
            'ù'=>'u','ú'=>'u','ủ'=>'u','ũ'=>'u','ụ'=>'u',
            'ư'=>'u','ừ'=>'u','ứ'=>'u','ử'=>'u','ữ'=>'u','ự'=>'u',
            'ỳ'=>'y','ý'=>'y','ỷ'=>'y','ỹ'=>'y','ỵ'=>'y'
        ];
        
        $text = strtr($text, $vietnamese);
        $text = preg_replace('/[^a-z0-9]+/', '_', $text);
        return trim($text, '_');
    }
    
    /**
     * ============================================
     * REVERSE PARSING: Redis Key → Filters
     * ============================================
     */
    public static function parseKey(string $key): array {
        $parts = explode(':', $key);
        
        if (count($parts) < 6 || $parts[0] !== 'anomaly' || $parts[1] !== 'report') {
            throw new InvalidArgumentException("Invalid Redis key format: $key");
        }
        
        return [
            'years' => self::parseYearSegment($parts[2]),
            'months' => self::parseMonthSegment($parts[3]),
            'province' => self::parseProvinceSegment($parts[4]),
            'gkhl_status' => self::parseGkhlSegment($parts[5])
        ];
    }
    
    private static function parseYearSegment(string $segment): array {
        if ($segment === 'yall') return [];
        
        $segment = substr($segment, 1); // Bỏ chữ 'y'
        
        if (strpos($segment, '-') !== false) {
            // y2024-2026 → [2024,2025,2026]
            list($start, $end) = explode('-', $segment);
            return range((int)$start, (int)$end);
        }
        
        if (strpos($segment, '_') !== false) {
            // y2023_2025 → [2023,2025]
            return array_map('intval', explode('_', $segment));
        }
        
        // y2024 → [2024]
        return [(int)$segment];
    }
    
    private static function parseMonthSegment(string $segment): array {
        if ($segment === 'mall') return range(1, 12);
        
        $segment = substr($segment, 1); // Bỏ chữ 'm'
        
        if (strpos($segment, '-') !== false) {
            // m01-03 → [1,2,3]
            list($start, $end) = explode('-', $segment);
            return range((int)$start, (int)$end);
        }
        
        if (strpos($segment, '_') !== false) {
            // m01_03_06 → [1,3,6]
            return array_map('intval', explode('_', $segment));
        }
        
        // m01 → [1]
        return [(int)$segment];
    }
    
    private static function parseProvinceSegment(string $segment): string {
        if ($segment === 'pall') return '';
        return substr($segment, 2); // Bỏ "p_"
    }
    
    private static function parseGkhlSegment(string $segment): string {
        if ($segment === 'gall') return '';
        return $segment === 'gyes' ? '1' : '0';
    }
    
    /**
     * ============================================
     * UTILITY: Tìm tất cả keys liên quan
     * ============================================
     */
    public static function findRelatedKeys(Redis $redis, array $filters): array {
        $pattern = 'anomaly:report:*';
        $allKeys = $redis->keys($pattern);
        
        $relatedKeys = [];
        
        foreach ($allKeys as $key) {
            try {
                $keyFilters = self::parseKey($key);
                
                // Kiểm tra có overlap không
                if (self::hasOverlap($filters, $keyFilters)) {
                    $relatedKeys[] = $key;
                }
            } catch (Exception $e) {
                continue;
            }
        }
        
        return $relatedKeys;
    }
    
    /**
     * Kiểm tra 2 bộ filter có giao nhau không
     */
    private static function hasOverlap(array $filters1, array $filters2): bool {
        // Kiểm tra years
        if (!empty($filters1['years']) && !empty($filters2['years'])) {
            if (empty(array_intersect($filters1['years'], $filters2['years']))) {
                return false;
            }
        }
        
        // Kiểm tra months
        if (!empty($filters1['months']) && !empty($filters2['months'])) {
            if (empty(array_intersect($filters1['months'], $filters2['months']))) {
                return false;
            }
        }
        
        // Kiểm tra province
        if (!empty($filters1['province']) && !empty($filters2['province'])) {
            if ($filters1['province'] !== $filters2['province']) {
                return false;
            }
        }
        
        // Kiểm tra gkhl_status
        if ($filters1['gkhl_status'] !== '' && $filters2['gkhl_status'] !== '') {
            if ($filters1['gkhl_status'] !== $filters2['gkhl_status']) {
                return false;
            }
        }
        
        return true;
    }
}