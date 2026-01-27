-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 30, 2025 at 02:09 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `data_hoalinh`
--

-- --------------------------------------------------------

--
-- Table structure for table `dskh`
--

CREATE TABLE `dskh` (
  `MaKH` varchar(50) NOT NULL,
  `Area` varchar(100) DEFAULT NULL,
  `MaGSBH` varchar(50) DEFAULT NULL,
  `MaNPP` varchar(50) DEFAULT NULL,
  `MaNVBH` varchar(50) DEFAULT NULL,
  `TenNVBH` varchar(150) DEFAULT NULL,
  `TenKH` varchar(200) DEFAULT NULL,
  `PhanLoaiNhomKH` varchar(100) DEFAULT NULL,
  `LoaiKH` varchar(100) DEFAULT NULL,
  `DiaChi` varchar(255) DEFAULT NULL,
  `QuanHuyen` varchar(100) DEFAULT NULL,
  `Tinh` varchar(100) DEFAULT NULL,
  `Location` varchar(255) DEFAULT NULL,
  `MaSoThue` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gkhl`
--

CREATE TABLE `gkhl` (
  `id` int(11) NOT NULL,
  `MaNVBH` varchar(50) DEFAULT NULL,
  `TenNVBH` varchar(150) DEFAULT NULL,
  `MaKHDMS` varchar(50) DEFAULT NULL,
  `TenQuay` varchar(200) DEFAULT NULL,
  `TenChuCuaHang` varchar(200) DEFAULT NULL,
  `NgaySinh` tinyint(4) DEFAULT NULL,
  `ThangSinh` tinyint(4) DEFAULT NULL,
  `NamSinh` smallint(6) DEFAULT NULL,
  `SDTZalo` varchar(20) DEFAULT NULL,
  `SDTDaDinhDanh` varchar(20) DEFAULT NULL,
  `KhopSDT` enum('Y','N') DEFAULT NULL,
  `DangKyChuongTrinh` varchar(200) DEFAULT NULL,
  `DangKyMucDoanhSo` varchar(200) DEFAULT NULL,
  `DangKyTrungBay` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_history`
--

CREATE TABLE `login_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `login_time` datetime DEFAULT current_timestamp(),
  `logout_time` datetime DEFAULT NULL,
  `session_token` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orderdetail`
--

CREATE TABLE `orderdetail` (
  `ID` int(11) NOT NULL,
  `OrderNumber` varchar(50) NOT NULL,
  `OrderDate` date NOT NULL,
  `CustCode` varchar(50) DEFAULT NULL,
  `CustType` varchar(50) DEFAULT NULL,
  `DistCode` varchar(50) DEFAULT NULL,
  `DSRCode` varchar(50) DEFAULT NULL,
  `DistGroup` varchar(50) DEFAULT NULL,
  `DSRTypeProvince` varchar(50) DEFAULT NULL,
  `ProductSaleType` varchar(50) DEFAULT NULL,
  `ProductCode` varchar(50) DEFAULT NULL,
  `Qty` int(11) DEFAULT NULL,
  `TotalSchemeAmount` decimal(18,2) DEFAULT NULL,
  `TotalGrossAmount` decimal(18,2) DEFAULT NULL,
  `TotalNetAmount` decimal(18,2) DEFAULT NULL,
  `RptMonth` tinyint(4) DEFAULT NULL,
  `RptYear` smallint(6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role_switch_log`
--

CREATE TABLE `role_switch_log` (
  `id` int(11) NOT NULL,
  `admin_user_id` int(11) NOT NULL,
  `switched_to_role` enum('admin','user','viewer') DEFAULT NULL,
  `switched_at` datetime DEFAULT current_timestamp(),
  `switched_back_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `summary_anomaly_results`
--

CREATE TABLE `summary_anomaly_results` (
  `id` int(11) NOT NULL,
  `customer_code` varchar(50) NOT NULL,
  `customer_name` varchar(200) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `total_score` decimal(10,2) DEFAULT 0.00,
  `risk_level` enum('critical','high','medium','low','normal') DEFAULT 'normal',
  `anomaly_count` int(11) DEFAULT 0,
  `total_sales` decimal(18,2) DEFAULT 0.00,
  `total_orders` int(11) DEFAULT 0,
  `total_qty` int(11) DEFAULT 0,
  `gkhl_status` enum('Y','N') DEFAULT 'N',
  `anomaly_details` text DEFAULT NULL,
  `calculated_for_years` varchar(50) DEFAULT NULL,
  `calculated_for_months` varchar(50) DEFAULT NULL,
  `calculated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cache_key` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `summary_nhanvien_kpi_cache`
--

CREATE TABLE `summary_nhanvien_kpi_cache` (
  `id` int(11) NOT NULL,
  `cache_key` varchar(100) NOT NULL,
  `tu_ngay` date NOT NULL,
  `den_ngay` date NOT NULL,
  `product_filter` varchar(10) DEFAULT NULL,
  `threshold_n` int(11) NOT NULL,
  `data` longtext NOT NULL,
  `employee_count` int(11) DEFAULT 0,
  `critical_count` int(11) DEFAULT 0,
  `warning_count` int(11) DEFAULT 0,
  `calculated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `summary_nhanvien_report_cache`
--

CREATE TABLE `summary_nhanvien_report_cache` (
  `id` int(11) NOT NULL,
  `cache_key` varchar(100) NOT NULL,
  `thang` varchar(7) NOT NULL,
  `tu_ngay` date NOT NULL,
  `den_ngay` date NOT NULL,
  `data_type` enum('employees','stats_month','stats_range') NOT NULL DEFAULT 'employees',
  `data` longtext NOT NULL,
  `employee_count` int(11) DEFAULT 0,
  `suspect_count` int(11) DEFAULT 0,
  `calculated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `summary_report_cache`
--

CREATE TABLE `summary_report_cache` (
  `id` int(11) NOT NULL,
  `cache_key` varchar(100) NOT NULL,
  `data_type` enum('summary','stats','detail') NOT NULL DEFAULT 'summary',
  `data` longtext NOT NULL,
  `filters` text DEFAULT NULL,
  `record_count` int(11) DEFAULT 0,
  `calculated_for_years` varchar(50) DEFAULT NULL,
  `calculated_for_months` varchar(50) DEFAULT NULL,
  `calculated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','user','viewer') DEFAULT 'user',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `after_user_insert` AFTER INSERT ON `users` FOR EACH ROW BEGIN
    IF NEW.role = 'user' THEN
        INSERT INTO user_permissions (user_id, permission_key, permission_value) VALUES
        (NEW.id, 'view_reports', 1),
        (NEW.id, 'export_data', 1),
        (NEW.id, 'advanced_filters', 0),
        (NEW.id, 'view_anomaly', 0);
    ELSEIF NEW.role = 'viewer' THEN
        INSERT INTO user_permissions (user_id, permission_key, permission_value) VALUES
        (NEW.id, 'view_reports', 1),
        (NEW.id, 'export_data', 0),
        (NEW.id, 'advanced_filters', 0),
        (NEW.id, 'view_anomaly', 0);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `permission_key` varchar(50) NOT NULL,
  `permission_value` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `dskh`
--
ALTER TABLE `dskh`
  ADD PRIMARY KEY (`MaKH`),
  ADD KEY `idx_ma_kh` (`MaKH`),
  ADD KEY `idx_tinh` (`Tinh`),
  ADD KEY `idx_quan_huyen` (`QuanHuyen`),
  ADD KEY `idx_loai_kh` (`LoaiKH`),
  ADD KEY `idx_tinh_quan` (`Tinh`,`QuanHuyen`),
  ADD KEY `idx_ma_nvbh` (`MaNVBH`),
  ADD KEY `idx_ma_gsbh` (`MaGSBH`);

--
-- Indexes for table `gkhl`
--
ALTER TABLE `gkhl`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_MaNVBH` (`MaNVBH`),
  ADD KEY `idx_MaKHDMS` (`MaKHDMS`),
  ADD KEY `idx_ma_kh_dms` (`MaKHDMS`),
  ADD KEY `idx_ma_nvbh` (`MaNVBH`),
  ADD KEY `idx_khop_sdt` (`KhopSDT`),
  ADD KEY `idx_nam_sinh` (`NamSinh`);

--
-- Indexes for table `login_history`
--
ALTER TABLE `login_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_login_time` (`login_time`);

--
-- Indexes for table `orderdetail`
--
ALTER TABLE `orderdetail`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `fk_orderdetail_dskh` (`CustCode`),
  ADD KEY `idx_rpt_month_year` (`RptMonth`,`RptYear`),
  ADD KEY `idx_cust_code` (`CustCode`),
  ADD KEY `idx_cust_rpt` (`CustCode`,`RptMonth`,`RptYear`),
  ADD KEY `idx_order_date` (`OrderDate`),
  ADD KEY `idx_main_query` (`RptMonth`,`RptYear`,`CustCode`),
  ADD KEY `idx_dsr_code` (`DSRCode`),
  ADD KEY `idx_dsr_date` (`DSRCode`,`OrderDate`),
  ADD KEY `idx_dsr_rpt` (`DSRCode`,`RptYear`,`RptMonth`),
  ADD KEY `idx_anomaly_detection` (`CustCode`,`RptYear`,`RptMonth`,`OrderDate`,`TotalNetAmount`),
  ADD KEY `idx_product_code` (`CustCode`,`ProductCode`(2),`RptYear`,`RptMonth`),
  ADD KEY `idx_product_analysis` (`CustCode`,`ProductCode`,`RptYear`,`RptMonth`),
  ADD KEY `idx_date_range` (`OrderDate`,`CustCode`,`TotalNetAmount`);

--
-- Indexes for table `role_switch_log`
--
ALTER TABLE `role_switch_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_user` (`admin_user_id`);

--
-- Indexes for table `summary_anomaly_results`
--
ALTER TABLE `summary_anomaly_results`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_customer_cache` (`customer_code`,`cache_key`),
  ADD KEY `idx_customer_code` (`customer_code`),
  ADD KEY `idx_risk_level` (`risk_level`),
  ADD KEY `idx_total_score` (`total_score`),
  ADD KEY `idx_calculated_at` (`calculated_at`),
  ADD KEY `idx_cache_key` (`cache_key`);

--
-- Indexes for table `summary_nhanvien_kpi_cache`
--
ALTER TABLE `summary_nhanvien_kpi_cache`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cache_key` (`cache_key`),
  ADD KEY `idx_date_range` (`tu_ngay`,`den_ngay`),
  ADD KEY `idx_calculated_at` (`calculated_at`);

--
-- Indexes for table `summary_nhanvien_report_cache`
--
ALTER TABLE `summary_nhanvien_report_cache`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cache_key_type` (`cache_key`,`data_type`),
  ADD KEY `idx_cache_key` (`cache_key`),
  ADD KEY `idx_thang` (`thang`),
  ADD KEY `idx_date_range` (`tu_ngay`,`den_ngay`),
  ADD KEY `idx_calculated_at` (`calculated_at`);

--
-- Indexes for table `summary_report_cache`
--
ALTER TABLE `summary_report_cache`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cache_key_type` (`cache_key`,`data_type`),
  ADD KEY `idx_cache_key` (`cache_key`),
  ADD KEY `idx_data_type` (`data_type`),
  ADD KEY `idx_calculated_at` (`calculated_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_permission` (`user_id`,`permission_key`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `gkhl`
--
ALTER TABLE `gkhl`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_history`
--
ALTER TABLE `login_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orderdetail`
--
ALTER TABLE `orderdetail`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `role_switch_log`
--
ALTER TABLE `role_switch_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `summary_anomaly_results`
--
ALTER TABLE `summary_anomaly_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `summary_nhanvien_kpi_cache`
--
ALTER TABLE `summary_nhanvien_kpi_cache`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `summary_nhanvien_report_cache`
--
ALTER TABLE `summary_nhanvien_report_cache`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `summary_report_cache`
--
ALTER TABLE `summary_report_cache`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `login_history`
--
ALTER TABLE `login_history`
  ADD CONSTRAINT `login_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_switch_log`
--
ALTER TABLE `role_switch_log`
  ADD CONSTRAINT `role_switch_log_ibfk_1` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
