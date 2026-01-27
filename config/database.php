<?php
class Database {
    private $host = "localhost";
    private $db_name = "data_hoalinh";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                
                // ✅ TỐI ƯU: Persistent connections (tái sử dụng kết nối)
                PDO::ATTR_PERSISTENT => true,
                
                // ✅ TỐI ƯU: Buffered queries
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                
                // ✅ TỐI ƯU: Timeout settings
                PDO::ATTR_TIMEOUT => 30,
            ];
            
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                $options
            );
            
        } catch(PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            die("Lỗi kết nối database. Vui lòng kiểm tra cấu hình.");
        }
        return $this->conn;
    }
}
?>