-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 13, 2025 at 08:19 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `aquanest`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`admin_id`, `username`, `password`, `name`, `email`, `created_at`) VALUES
(1, 'admin', '$2y$10$iLt0ty0M3xTzZkIWSHXRt.NzbjDjEOQaN.4mqJyGGGwjRkY8tggWW', 'admin', 'admin@gmail.com', '2025-05-12 06:07:17'),
(2, 'dany', '$2y$10$s8UGs.1rEWBSGNIOIxJaCuBKRmQxzURpDzXftx9eMP0UvxLOVzNAa', 'dany', 'dany@gmail.com', '2025-05-04 09:49:05'),
(3, 'fatihul', '$2y$10$qh3Ly0GqUVu6EnvTcwk3B.NFiVx3zHvk4avaKfMvRbC9kG3NDg7d6', 'Fatihuldanyy', 'danyfatihul@gmail.com', '2025-05-15 17:09:59');

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password`, `email`, `last_login`, `is_active`, `created_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@aquanest.com', NULL, 1, '2025-05-25 07:10:13');

-- --------------------------------------------------------

--
-- Table structure for table `couriers`
--

CREATE TABLE `couriers` (
  `courier_id` int(11) NOT NULL,
  `courier_name` varchar(100) NOT NULL,
  `courier_phone` varchar(20) NOT NULL,
  `vehicle_type` varchar(50) DEFAULT NULL,
  `vehicle_number` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `vehicle_brand` varchar(50) DEFAULT NULL,
  `vehicle_model` varchar(50) DEFAULT NULL,
  `vehicle_plate` varchar(20) NOT NULL,
  `status` enum('available','on_delivery','off_duty') DEFAULT 'available',
  `photo` varchar(255) DEFAULT NULL,
  `joined_date` date NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `couriers`
--

INSERT INTO `couriers` (`courier_id`, `courier_name`, `courier_phone`, `vehicle_type`, `vehicle_number`, `is_active`, `created_at`, `email`, `address`, `vehicle_brand`, `vehicle_model`, `vehicle_plate`, `status`, `photo`, `joined_date`, `updated_at`) VALUES
(17, 'Budi Santoso', '081234567890', 'motor', 'B1234ABC', 1, '2025-06-02 09:32:20', 'budi.santoso@aquanest.com', 'Jl. Raya Bekasi Timur No. 123, Bekasi', 'Honda', 'Beat', 'B1234ABC', 'available', NULL, '2025-06-02', '2025-06-02 09:32:20'),
(18, 'Ahmad Wijaya', '082345678901', 'motor', 'B5678DEF', 1, '2025-06-02 09:32:20', 'ahmad.wijaya@aquanest.com', 'Jl. Kemang Pratama Raya No. 45, Bekasi', 'Yamaha', 'Vario', 'B5678DEF', 'on_delivery', NULL, '2025-06-02', '2025-06-10 06:28:54'),
(19, 'Dedi Kurniawan', '083456789012', 'motor', 'B9012GHI', 1, '2025-06-02 09:32:20', 'dedi.k@aquanest.com', 'Jl. Pekayon Jaya No. 67, Bekasi', 'Honda', 'Scoopy', 'B9012GHI', 'on_delivery', NULL, '2025-06-02', '2025-06-02 09:32:20'),
(20, 'Rizki Pratama', '084567890123', 'motor', 'B3456JKL', 1, '2025-06-02 09:32:20', 'rizki.p@aquanest.com', 'Jl. Kalimalang No. 89, Bekasi', 'Suzuki', 'Nex', 'B3456JKL', 'on_delivery', NULL, '2025-06-02', '2025-06-02 14:09:55'),
(21, 'Slamet Riyadi', '085678901234', 'mobil', 'B7890MNO', 1, '2025-06-02 09:32:20', 'slamet.r@aquanest.com', 'Jl. Pondok Gede Raya No. 101, Bekasi', 'Toyota', 'Avanza', 'B7890MNO', 'available', NULL, '2025-06-02', '2025-06-02 09:32:20'),
(22, 'Eko Prasetyo', '086789012345', 'mobil', 'B2345PQR', 1, '2025-06-02 09:32:20', 'eko.prasetyo@aquanest.com', 'Jl. Jatiasih No. 112, Bekasi', 'Daihatsu', 'Xenia', 'B2345PQR', 'off_duty', NULL, '2025-06-02', '2025-06-02 09:32:20'),
(23, 'Hendra Gunawan', '087890123456', 'pickup', 'B6789STU', 1, '2025-06-02 09:32:20', 'hendra.g@aquanest.com', 'Jl. Mustika Jaya No. 134, Bekasi', 'Mitsubishi', 'L300', 'B6789STU', 'on_delivery', NULL, '2025-06-02', '2025-06-02 14:24:25'),
(24, 'Joko Widodo', '088901234567', 'pickup', 'B0123VWX', 1, '2025-06-02 09:32:20', 'joko.w@aquanest.com', 'Jl. Harapan Indah No. 156, Bekasi', 'Suzuki', 'Carry', 'B0123VWX', 'on_delivery', NULL, '2025-06-02', '2025-06-02 14:10:46'),
(25, 'Agus Setiawan', '089012345678', 'motor', 'B4567YZA', 1, '2025-06-02 09:32:20', 'agus.s@aquanest.com', 'Jl. Tambun No. 178, Bekasi', 'Honda', 'PCX', 'B4567YZA', 'off_duty', NULL, '2025-06-02', '2025-06-02 11:12:39'),
(26, 'Fahmi Rahman', '080123456789', 'motor', 'B8901BCD', 0, '2025-06-02 09:32:20', 'fahmi.r@aquanest.com', 'Jl. Cibitung No. 190, Bekasi', 'Yamaha', 'NMAX', 'B8901BCD', 'off_duty', NULL, '2025-06-02', '2025-06-02 11:12:51');

-- --------------------------------------------------------

--
-- Table structure for table `courier_deliveries`
--

CREATE TABLE `courier_deliveries` (
  `delivery_id` int(11) NOT NULL,
  `courier_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `assigned_at` datetime NOT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `status` enum('assigned','on_the_way','delivered','failed') DEFAULT 'assigned',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courier_deliveries`
--

INSERT INTO `courier_deliveries` (`delivery_id`, `courier_id`, `order_id`, `assigned_at`, `started_at`, `completed_at`, `status`, `notes`) VALUES
(13, 20, 14, '0000-00-00 00:00:00', NULL, NULL, 'assigned', NULL),
(14, 24, 25, '0000-00-00 00:00:00', NULL, NULL, 'assigned', NULL),
(15, 23, 22, '0000-00-00 00:00:00', NULL, NULL, 'assigned', NULL),
(16, 18, 26, '2025-06-10 13:28:54', NULL, NULL, 'assigned', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`customer_id`, `name`, `email`, `phone`, `address`, `created_at`, `updated_at`) VALUES
(1, 'dany', 'fatihuldanyy@gmail.com', '089530007056', 'tambun', '2025-05-04 09:44:17', '2025-05-04 09:44:17'),
(2, 'akmal', 'akmal@gmail.com', '0812345677', 'utara', '2025-05-04 12:58:12', '2025-05-04 12:58:12'),
(3, 'arui', 'sgdg@gmail.com', '00000088999', 'btr', '2025-05-05 00:40:59', '2025-05-05 00:40:59'),
(4, 'us', 'daus@gmail.com', '082112390046', 'bumi angrrek blok k 146', '2025-05-08 04:29:29', '2025-05-08 04:29:29'),
(5, 'teddy', 'teddy@gmail.com', '089743537', 'narogong', '2025-05-08 05:51:41', '2025-05-08 05:51:41'),
(6, 'rere', 'rere@gmail.com', '081315193513', 'cibitung pride till i dye', '2025-05-08 14:57:52', '2025-05-08 14:57:52'),
(7, 'Andra', 'ananadaandraadrianto@gmail.com', '0822', 'testttt', '2025-05-09 02:10:57', '2025-05-09 02:10:57'),
(8, 'qaaaaaaaaa', '', 'aaa', 'aaaa', '2025-05-09 02:14:24', '2025-05-09 02:14:24'),
(9, 'hgtdtyy', '', '0876543456789', 'mnbxgu', '2025-05-09 02:24:58', '2025-05-09 02:24:58'),
(10, 'neng', 'fatihuldanyy@gmail.com', '089530007056', 'tambu utara', '2025-05-12 05:55:21', '2025-05-12 05:55:21'),
(11, 'albi', 'albi@gmail.com', '08976542277', 'bekasi', '2025-05-18 07:25:22', '2025-05-18 07:25:22'),
(12, 'habi', 'adasda@gmail.com', '998752678923', 'bekasi utara', '2025-05-21 16:15:50', '2025-05-21 16:15:50'),
(13, 'fatihull', 'fatihuldanyy@gmail.com', '1324252532', 'tambun', '2025-05-24 02:58:00', '2025-05-24 02:58:00'),
(14, 'rusdi', 'rusdi@gmail.com', '081212435235', 'kampung cibuntu 17520', '2025-05-26 16:20:46', '2025-05-26 16:20:46'),
(15, 'amel', 'amel@gmail.com', '081223334553', 'griya family 2', '2025-05-31 07:24:51', '2025-05-31 07:24:51'),
(16, 'fatihulll', 'fatihul@gmail.com', '089530007056', 'bumi anggrek', '2025-05-31 08:01:53', '2025-05-31 08:01:53'),
(17, 'ihsannn', 'fatihuldanyy@gmail.com', '0897577443', 'tambun utara', '2025-05-31 17:37:11', '2025-05-31 17:37:11'),
(18, 'fais', 'fais@gmail.com', '089746367282', 'jgjgjgf', '2025-05-31 17:50:52', '2025-05-31 17:50:52'),
(19, 'arkan', 'arkan@gmail.com', '08717273722', 'tambun', '2025-05-31 17:57:14', '2025-05-31 17:57:14'),
(20, 'dododo', 'asdasd@gmail.com', '080102812', 'dasdad', '2025-05-31 19:18:12', '2025-05-31 19:18:12'),
(21, 'daus', 'daus@gmail.com', '08954857472', 'bumi anggrek', '2025-06-01 13:10:47', '2025-06-01 13:10:47'),
(24, 'akmal', 'akmal@gmail.com', '0875678176723', 'tambun', '2025-06-02 13:21:53', '2025-06-02 13:21:53'),
(25, 'firly', 'firly@gmail.com', '089742424242', 'tambun', '2025-06-10 06:28:13', '2025-06-10 06:28:13');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','processing','delivered','cancelled') DEFAULT 'pending',
  `payment_status` varchar(50) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_proof` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `notification_sent` tinyint(1) DEFAULT 0,
  `last_notification_date` timestamp NULL DEFAULT NULL,
  `notification_count` int(11) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `courier_id` int(11) DEFAULT NULL,
  `estimated_delivery` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `customer_id`, `order_date`, `total_amount`, `status`, `payment_status`, `payment_method`, `payment_proof`, `notes`, `notification_sent`, `last_notification_date`, `notification_count`, `updated_at`, `courier_id`, `estimated_delivery`, `created_at`) VALUES
(1, 1, '2025-05-04 09:44:17', 19500.00, 'delivered', 'unpaid', 'cash', NULL, 'cepeet ya', 1, NULL, 0, '2025-05-25 07:10:12', NULL, NULL, '2025-05-31 05:44:11'),
(2, 2, '2025-05-04 12:58:12', 6000.00, 'pending', 'unpaid', 'cash', NULL, 'gc', 0, NULL, 0, '2025-05-25 07:10:12', NULL, NULL, '2025-05-31 05:44:11'),
(3, 3, '2025-05-05 00:40:59', 19500.00, 'pending', 'paid', 'transfer', NULL, 'buru kirim', 0, NULL, 0, '2025-05-25 07:10:12', NULL, NULL, '2025-05-31 05:44:11'),
(4, 4, '2025-05-08 04:29:29', 46000.00, 'pending', 'unpaid', 'cash', NULL, 'cepet ga pake lama', 0, NULL, 0, '2025-05-25 07:10:12', NULL, NULL, '2025-05-31 05:44:11'),
(5, 5, '2025-05-08 05:51:41', 46000.00, 'pending', 'paid', 'transfer', NULL, 'gc', 0, NULL, 0, '2025-05-25 07:10:12', NULL, NULL, '2025-05-31 05:44:11'),
(6, 6, '2025-05-08 14:57:52', 5000.00, 'cancelled', 'unpaid', 'transfer', NULL, 'gc bang', 1, NULL, 0, '2025-05-25 13:17:49', NULL, NULL, '2025-05-31 05:44:11'),
(7, 7, '2025-05-09 02:10:57', 46000.00, 'pending', 'unpaid', 'transfer', NULL, 'gc', 0, NULL, 0, '2025-05-25 07:10:12', NULL, NULL, '2025-05-31 05:44:11'),
(8, 8, '2025-05-09 02:14:24', 46000.00, 'pending', 'unpaid', 'cash', NULL, '', 0, NULL, 0, '2025-05-25 07:10:12', NULL, NULL, '2025-05-31 05:44:11'),
(9, 9, '2025-05-09 02:24:58', 46000.00, 'processing', 'unpaid', 'cash', NULL, 'bhjggkjhk', 1, NULL, 0, '2025-05-25 07:10:12', NULL, NULL, '2025-05-31 05:44:11'),
(10, 10, '2025-05-12 05:55:21', 5000.00, 'pending', 'pending', 'cash', NULL, 'cepet ga pake lama', 0, NULL, 0, '2025-05-25 07:10:12', NULL, NULL, '2025-05-31 05:44:11'),
(11, 11, '2025-05-18 07:25:22', 5000.00, 'confirmed', 'paid', '', NULL, 'gc', 0, NULL, 0, '2025-05-25 07:10:12', NULL, NULL, '2025-05-31 05:44:11'),
(12, 12, '2025-05-21 16:15:50', 59000.00, 'pending', 'unpaid', '', NULL, 'gc', 0, NULL, 0, '2025-05-25 07:10:12', NULL, NULL, '2025-05-31 05:44:11'),
(13, 13, '2025-05-24 02:58:00', 5000.00, 'pending', 'unpaid', '', NULL, '', 0, NULL, 0, '2025-05-25 07:10:12', NULL, NULL, '2025-05-31 05:44:11'),
(14, 14, '2025-05-26 16:20:46', 92000.00, 'processing', 'verified', '', NULL, 'tolong antarkan besok ya', 0, NULL, 0, '2025-06-02 14:17:11', NULL, NULL, '2025-05-31 05:44:11'),
(15, 15, '2025-05-31 07:24:51', 23000.00, 'pending', '', '', NULL, 'tolong cepet ya', 0, NULL, 0, '2025-05-31 07:24:51', NULL, '2025-05-31 08:00:00', '2025-05-31 07:24:51'),
(16, 16, '2025-05-31 08:01:53', 55000.00, 'pending', '', '', NULL, 'tolong cepetan', 0, NULL, 0, '2025-05-31 08:01:53', NULL, '2025-05-31 08:00:00', '2025-05-31 08:01:53'),
(17, 17, '2025-05-31 17:37:11', 23000.00, 'pending', '', '', NULL, 'tolong di percepat', 0, NULL, 0, '2025-05-31 17:37:11', NULL, '0000-00-00 00:00:00', '2025-05-31 17:37:11'),
(18, 18, '2025-05-31 17:50:52', 23000.00, 'pending', '', '', NULL, 'faafa', 0, NULL, 0, '2025-05-31 17:50:52', NULL, '0000-00-00 00:00:00', '2025-05-31 17:50:52'),
(19, 19, '2025-05-31 17:57:14', 5000.00, 'confirmed', 'paid', 'bank_transfer', 'payment_19_1748716685.jpg', 'gc', 0, NULL, 0, '2025-05-31 18:38:49', NULL, '0000-00-00 00:00:00', '2025-05-31 17:57:14'),
(21, 20, '2025-05-31 19:18:12', 55000.00, 'pending', 'waiting', 'bank_transfer', 'payment_21_1748719135.jpg', 'dasda', 0, NULL, 0, '2025-05-31 19:18:55', NULL, '0000-00-00 00:00:00', '2025-05-31 19:18:12'),
(22, 21, '2025-06-01 13:10:47', 95000.00, 'processing', 'verified', 'bank_transfer', 'payment_22_1748783464.jpeg', 'gc', 0, NULL, 0, '2025-06-02 14:24:25', NULL, '2025-06-01 08:00:00', '2025-06-01 13:10:47'),
(25, 24, '2025-06-02 13:21:53', 59000.00, 'processing', 'paid', 'bank_transfer', 'payment_25_1748873224.jpg', 'gc', 0, NULL, 0, '2025-06-02 14:25:42', NULL, '2025-06-02 08:00:00', '2025-06-02 13:21:53'),
(26, 25, '2025-06-10 06:28:13', 55000.00, 'pending', 'paid', 'qris', NULL, 'qc', 0, NULL, 0, '2025-06-10 06:28:54', 18, '2025-06-10 08:00:00', '2025-06-10 06:28:13');

--
-- Triggers `orders`
--
DELIMITER $$
CREATE TRIGGER `after_order_insert` AFTER INSERT ON `orders` FOR EACH ROW BEGIN
    -- Add initial tracking step
    INSERT INTO order_tracking_history (order_id, title, description, is_completed, is_current)
    VALUES (NEW.order_id, 'Pesanan Dibuat', 'Pesanan Anda telah diterima oleh sistem kami', TRUE, TRUE);
    
    -- Set estimated delivery time (3 hours from order)
    IF NEW.estimated_delivery IS NULL THEN
        UPDATE orders SET estimated_delivery = DATE_ADD(NOW(), INTERVAL 3 HOUR) WHERE order_id = NEW.order_id;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_payment_update` AFTER UPDATE ON `orders` FOR EACH ROW BEGIN
    IF NEW.payment_status = 'paid' AND OLD.payment_status != 'paid' THEN
        -- Update previous step as not current
        UPDATE order_tracking_history 
        SET is_current = FALSE 
        WHERE order_id = NEW.order_id;
        
        -- Add new tracking step
        INSERT INTO order_tracking_history (order_id, title, description, is_completed, is_current)
        VALUES (NEW.order_id, 'Pembayaran Berhasil', 'Pembayaran telah dikonfirmasi', TRUE, TRUE);
    END IF;
    
    -- If courier assigned
    IF NEW.courier_id IS NOT NULL AND OLD.courier_id IS NULL THEN
        -- Update previous step as not current
        UPDATE order_tracking_history 
        SET is_current = FALSE 
        WHERE order_id = NEW.order_id;
        
        -- Add new tracking step
        INSERT INTO order_tracking_history (order_id, title, description, is_completed, is_current)
        VALUES (NEW.order_id, 'Pesanan Diproses', 'Pesanan Anda sedang disiapkan', TRUE, TRUE);
    END IF;
    
    -- If status changes to on_delivery
    IF NEW.status = 'on_delivery' AND OLD.status != 'on_delivery' THEN
        -- Update previous step as not current
        UPDATE order_tracking_history 
        SET is_current = FALSE 
        WHERE order_id = NEW.order_id;
        
        -- Add new tracking step
        INSERT INTO order_tracking_history (order_id, title, description, is_completed, is_current)
        VALUES (NEW.order_id, 'Dalam Pengiriman', 'Kurir sedang menuju lokasi Anda', TRUE, TRUE);
    END IF;
    
    -- If status changes to delivered
    IF NEW.status = 'delivered' AND OLD.status != 'delivered' THEN
        -- Update previous step as not current
        UPDATE order_tracking_history 
        SET is_current = FALSE 
        WHERE order_id = NEW.order_id;
        
        -- Add new tracking step
        INSERT INTO order_tracking_history (order_id, title, description, is_completed, is_current)
        VALUES (NEW.order_id, 'Pesanan Tiba', 'Pesanan telah diterima oleh pelanggan', TRUE, TRUE);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`item_id`, `order_id`, `product_id`, `quantity`, `price`, `subtotal`) VALUES
(1, 1, 4, 1, 19500.00, 19500.00),
(2, 2, 2, 1, 6000.00, 6000.00),
(3, 3, 4, 1, 19500.00, 19500.00),
(4, 4, 5, 1, 46000.00, 46000.00),
(5, 5, 5, 1, 46000.00, 46000.00),
(6, 6, 4, 1, 5000.00, 5000.00),
(7, 7, 5, 1, 46000.00, 46000.00),
(8, 8, 5, 1, 46000.00, 46000.00),
(9, 9, 5, 1, 46000.00, 46000.00),
(10, 10, 4, 1, 5000.00, 5000.00),
(11, 11, 4, 1, 5000.00, 5000.00),
(12, 12, 3, 1, 59000.00, 59000.00),
(13, 13, 4, 1, 5000.00, 5000.00),
(14, 14, 5, 2, 46000.00, 92000.00),
(15, 15, 10, 1, 23000.00, 23000.00),
(16, 16, 9, 1, 55000.00, 55000.00),
(17, 17, 10, 1, 23000.00, 23000.00),
(18, 18, 10, 1, 23000.00, 23000.00),
(19, 19, 11, 1, 5000.00, 5000.00),
(20, 21, 9, 1, 55000.00, 55000.00),
(21, 22, 2, 1, 95000.00, 95000.00),
(24, 25, 3, 1, 59000.00, 59000.00),
(25, 26, 9, 1, 55000.00, 55000.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_notifications`
--

CREATE TABLE `order_notifications` (
  `notification_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `notification_type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('success','failed') DEFAULT 'success',
  `error_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `order_summary`
-- (See below for the actual view)
--
CREATE TABLE `order_summary` (
`order_id` int(11)
,`created_at` datetime
,`customer_name` varchar(100)
,`customer_phone` varchar(20)
,`total_amount` decimal(10,2)
,`payment_method` varchar(50)
,`payment_status` varchar(50)
,`order_status` varchar(10)
,`courier_name` varchar(100)
,`courier_phone` varchar(20)
,`total_items` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `order_tracking`
--

CREATE TABLE `order_tracking` (
  `tracking_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `location` text DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_tracking_history`
--

CREATE TABLE `order_tracking_history` (
  `history_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_completed` tinyint(1) DEFAULT 0,
  `is_current` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_tracking_history`
--

INSERT INTO `order_tracking_history` (`history_id`, `order_id`, `title`, `description`, `is_completed`, `is_current`, `created_at`) VALUES
(1, 15, 'Pesanan Dibuat', 'Pesanan Anda telah diterima oleh sistem kami', 1, 1, '2025-05-31 07:24:51'),
(2, 15, 'Pesanan Dibuat', 'Pesanan Anda telah diterima oleh sistem kami', 1, 0, '2025-05-31 07:24:51'),
(3, 15, 'Menunggu Pembayaran', 'Silakan lakukan pembayaran sesuai metode yang dipilih', 0, 1, '2025-05-31 07:24:51'),
(4, 16, 'Pesanan Dibuat', 'Pesanan Anda telah diterima oleh sistem kami', 1, 1, '2025-05-31 08:01:53'),
(5, 16, 'Pesanan Dibuat', 'Pesanan Anda telah diterima oleh sistem kami', 1, 0, '2025-05-31 08:01:53'),
(6, 16, 'Menunggu Pembayaran', 'Silakan lakukan pembayaran sesuai metode yang dipilih', 0, 1, '2025-05-31 08:01:53'),
(7, 17, 'Pesanan Dibuat', 'Pesanan Anda telah diterima oleh sistem kami', 1, 1, '2025-05-31 17:37:11'),
(8, 17, 'Pesanan Dibuat', 'Pesanan Anda telah diterima oleh sistem kami', 1, 0, '2025-05-31 17:37:11'),
(9, 17, 'Menunggu Pembayaran', 'Silakan lakukan pembayaran sesuai metode yang dipilih', 0, 1, '2025-05-31 17:37:11'),
(10, 18, 'Pesanan Dibuat', 'Pesanan Anda telah diterima oleh sistem kami', 1, 1, '2025-05-31 17:50:52'),
(11, 18, 'Pesanan Dibuat', 'Pesanan Anda telah diterima oleh sistem kami', 1, 0, '2025-05-31 17:50:52'),
(12, 18, 'Menunggu Pembayaran', 'Silakan lakukan pembayaran sesuai metode yang dipilih', 0, 1, '2025-05-31 17:50:52'),
(13, 19, 'Pesanan Dibuat', 'Pesanan Anda telah diterima oleh sistem kami', 1, 0, '2025-05-31 17:57:14'),
(14, 19, 'Pesanan Dibuat', 'Pesanan Anda telah diterima oleh sistem kami', 1, 0, '2025-05-31 17:57:15'),
(15, 19, 'Menunggu Pembayaran', 'Silakan lakukan pembayaran sesuai metode yang dipilih', 0, 0, '2025-05-31 17:57:15'),
(17, 19, 'Pembayaran Menunggu Konfirmasi', 'Bukti pembayaran telah diterima, menunggu verifikasi admin', 1, 0, '2025-05-31 18:38:05'),
(18, 19, 'Pembayaran Berhasil', 'Pembayaran telah dikonfirmasi', 1, 1, '2025-05-31 18:38:49'),
(19, 21, 'Pesanan Dibuat', 'Pesanan Anda telah diterima oleh sistem kami', 1, 0, '2025-05-31 19:18:12'),
(20, 21, 'Pesanan Dibuat', 'Pesanan Anda telah diterima oleh sistem kami', 1, 0, '2025-05-31 19:18:12'),
(21, 21, 'Menunggu Pembayaran', 'Silakan lakukan pembayaran sesuai metode yang dipilih', 0, 0, '2025-05-31 19:18:12'),
(22, 21, 'Pembayaran Menunggu Konfirmasi', 'Bukti pembayaran telah diterima, menunggu verifikasi admin', 1, 1, '2025-05-31 19:18:55'),
(23, 22, 'Pesanan Dibuat', 'Pesanan Anda telah diterima oleh sistem kami', 1, 0, '2025-06-01 13:10:47'),
(24, 22, 'Pesanan Dibuat', 'Pesanan Anda telah diterima oleh sistem kami', 1, 0, '2025-06-01 13:10:47'),
(25, 22, 'Menunggu Pembayaran', 'Silakan lakukan pembayaran sesuai metode yang dipilih', 0, 0, '2025-06-01 13:10:47'),
(26, 22, 'Pembayaran Menunggu Konfirmasi', 'Bukti pembayaran telah diterima, menunggu verifikasi admin', 1, 0, '2025-06-01 13:11:04'),
(31, 25, 'Pesanan Dibuat', 'Pesanan Anda telah diterima oleh sistem kami', 1, 0, '2025-06-02 13:21:53'),
(32, 25, 'Pesanan Dibuat', 'Pesanan Anda telah diterima oleh sistem kami', 1, 0, '2025-06-02 13:21:53'),
(33, 25, 'Menunggu Pembayaran', 'Silakan lakukan pembayaran sesuai metode yang dipilih', 0, 0, '2025-06-02 13:21:53'),
(34, 25, 'Pembayaran Menunggu Konfirmasi', 'Bukti pembayaran telah diterima, menunggu verifikasi admin', 1, 0, '2025-06-02 13:22:03'),
(35, 25, 'Pembayaran Menunggu Konfirmasi', 'Bukti pembayaran telah diterima, menunggu verifikasi admin', 1, 0, '2025-06-02 14:07:04'),
(36, 25, 'Pesanan Tiba', 'Pesanan telah diterima oleh pelanggan', 1, 0, '2025-06-02 14:13:11'),
(37, 22, 'Pembayaran Berhasil', 'Pembayaran telah dikonfirmasi', 1, 1, '2025-06-02 14:23:40'),
(38, 25, 'Pembayaran Berhasil', 'Pembayaran telah dikonfirmasi', 1, 1, '2025-06-02 14:25:42'),
(39, 26, 'Pesanan Dibuat', 'Pesanan Anda telah diterima oleh sistem kami', 1, 0, '2025-06-10 06:28:13'),
(40, 26, 'Pesanan Dibuat', 'Pesanan Anda telah diterima oleh sistem kami', 1, 0, '2025-06-10 06:28:13'),
(41, 26, 'Menunggu Pembayaran', 'Silakan lakukan pembayaran sesuai metode yang dipilih', 0, 0, '2025-06-10 06:28:13'),
(42, 26, 'Pembayaran Berhasil', 'Pembayaran telah dikonfirmasi', 1, 0, '2025-06-10 06:28:54'),
(43, 26, 'Pembayaran Berhasil', 'Pembayaran QRIS telah dikonfirmasi', 1, 0, '2025-06-10 06:28:54'),
(44, 26, 'Pesanan Diproses', 'Pesanan Anda sedang disiapkan', 1, 0, '2025-06-10 06:28:54'),
(45, 26, 'Kurir Ditugaskan', 'Kurir:  ()', 1, 1, '2025-06-10 06:28:54');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset`
--

CREATE TABLE `password_reset` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expiry` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_reset`
--

INSERT INTO `password_reset` (`id`, `user_id`, `token`, `expiry`, `created_at`) VALUES
(4, 1, '873db75d0e7dbcb19153510161dfc0106b68e9561d36057e85045c2bd73da9da', '2025-05-20 19:31:57', '2025-05-20 16:31:57');

-- --------------------------------------------------------

--
-- Table structure for table `payment_logs`
--

CREATE TABLE `payment_logs` (
  `log_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `proof_file` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `name`, `description`, `price`, `image`, `stock`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Air Galon 19L', 'Air minum dalam kemasan galon ukuran 19 liter', 18000.00, '1747114174_GALON 19 L.jpg', 100, 1, '2025-05-04 06:40:01', '2025-05-13 05:29:34'),
(2, 'Air Botol 1500 ML', 'Air minum dalam kemasan botol ukuran 1.5 liter', 95000.00, '1747114164_BOTOL 1,5 L.jpg', 199, 1, '2025-05-04 06:40:01', '2025-06-01 13:10:47'),
(3, 'Air Botol 600ml', 'Air minum dalam kemasan botol ukuran 600 ml', 59000.00, '1747114152_BOTOL 600 ML.jpg', 148, 1, '2025-05-04 06:40:01', '2025-06-02 13:21:53'),
(4, 'Air Galon Isi Ulang', 'Air minum dalam kemasan galon ukuran 19 liter', 5000.00, '1747022941_GALON ISI ULANG 19 LITER.jpg', 99, 1, '2025-05-04 06:40:01', '2025-05-24 02:58:00'),
(5, 'Air Gelas Kemasan 240ML', 'Air minum dalam kemasan gelas ukuran 240ml(1dus)', 46000.00, '1747022343_1746540447_GELAS 240 ML.jpg', 28, 1, '2025-05-06 14:19:20', '2025-05-26 16:20:46'),
(6, 'Aquanest Galon 19L', 'Air mineral kemasan galon 19 liter', 25000.00, 'galon-19l.jpg', 100, 1, '2025-05-31 05:40:59', '2025-05-31 05:40:59'),
(7, 'Aquanest Botol 600ml (Dus)', 'Air mineral botol 600ml isi 24 botol', 45000.00, 'botol-600ml-dus.jpg', 50, 1, '2025-05-31 05:40:59', '2025-05-31 05:40:59'),
(8, 'Aquanest Botol 330ml (Dus)', 'Air mineral botol 330ml isi 24 botol', 35000.00, 'botol-330ml-dus.jpg', 75, 1, '2025-05-31 05:40:59', '2025-05-31 05:40:59'),
(9, 'Aquanest Botol 1500ml (Dus)', 'Air mineral botol 1500ml isi 12 botol', 55000.00, 'botol-1500ml-dus.jpg', 37, 1, '2025-05-31 05:40:59', '2025-06-10 06:28:13'),
(10, 'Cleo botol mini 220ml (botol)', 'Air mineral botol mini 220ml isi 24 cup', 23000.00, '1748675999_cleo.jpg', 97, 1, '2025-05-31 05:40:59', '2025-05-31 17:50:52'),
(11, 'Air Galon Isi Ulang', 'Isi ulang galon 19 liter (galon dari pelanggan)', 5000.00, '1748675834_kisspng-water-bottles-gallon-bottled-water-5d31c4f37f0e01.0129566915635427715204.jpg', 199, 1, '2025-05-31 05:40:59', '2025-05-31 17:57:15'),
(14, 'Galon 19L Aqua', NULL, 20000.00, NULL, 100, 1, '2025-06-02 13:19:58', '2025-06-02 13:19:58'),
(15, 'Galon 19L Le Minerale', NULL, 18000.00, NULL, 100, 1, '2025-06-02 13:19:58', '2025-06-02 13:19:58'),
(16, 'Botol 600ml Aqua (1 Dus)', NULL, 35000.00, NULL, 50, 1, '2025-06-02 13:19:58', '2025-06-02 13:19:58'),
(17, 'Botol 1500ml Aqua (1 Dus)', NULL, 45000.00, NULL, 50, 1, '2025-06-02 13:19:58', '2025-06-02 13:19:58');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `name`, `phone`, `address`, `created_at`) VALUES
(1, 'akmal', 'akmalirsyad137@gmail.com', '$2y$10$gaFeBHp3WptTNSWGVm1oeOwoFgcBJ6T.l.YoGPHPnpf9jc34eh2la', 'akmal', NULL, NULL, '2025-05-10 13:36:48'),
(4, 'fatihul', 'fatihuldanyy@gmail.com', '$2y$10$jLSdqJVuB3EKPxtfUH/NZOGtLok.f.tEoxQaJSP/UrP.COe6OaEM6', 'Fatihuldanyy', NULL, NULL, '2025-05-15 23:51:02'),
(6, 'se', 'se@gmail.com', '$2y$10$sTA2TpBZnCtYFazDe64vwuRwwFEH0OCymOlv6ef0jCw82SqA9TB3a', 'se', NULL, NULL, '2025-05-21 13:23:15');

-- --------------------------------------------------------

--
-- Structure for view `order_summary`
--
DROP TABLE IF EXISTS `order_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `order_summary`  AS SELECT `o`.`order_id` AS `order_id`, ifnull(`o`.`created_at`,current_timestamp()) AS `created_at`, `c`.`name` AS `customer_name`, `c`.`phone` AS `customer_phone`, `o`.`total_amount` AS `total_amount`, ifnull(`o`.`payment_method`,'cash') AS `payment_method`, ifnull(`o`.`payment_status`,'pending') AS `payment_status`, ifnull(`o`.`status`,'pending') AS `order_status`, `cr`.`courier_name` AS `courier_name`, `cr`.`courier_phone` AS `courier_phone`, count(`oi`.`item_id`) AS `total_items` FROM (((`orders` `o` join `customers` `c` on(`o`.`customer_id` = `c`.`customer_id`)) left join `couriers` `cr` on(`o`.`courier_id` = `cr`.`courier_id`)) left join `order_items` `oi` on(`o`.`order_id` = `oi`.`order_id`)) GROUP BY `o`.`order_id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `couriers`
--
ALTER TABLE `couriers`
  ADD PRIMARY KEY (`courier_id`),
  ADD UNIQUE KEY `vehicle_plate` (`vehicle_plate`),
  ADD KEY `idx_courier_status` (`status`),
  ADD KEY `idx_courier_active` (`is_active`);

--
-- Indexes for table `courier_deliveries`
--
ALTER TABLE `courier_deliveries`
  ADD PRIMARY KEY (`delivery_id`),
  ADD KEY `idx_delivery_status` (`status`),
  ADD KEY `idx_delivery_courier` (`courier_id`),
  ADD KEY `idx_delivery_order` (`order_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `idx_orders_courier` (`courier_id`),
  ADD KEY `idx_orders_status` (`status`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `order_notifications`
--
ALTER TABLE `order_notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `order_tracking`
--
ALTER TABLE `order_tracking`
  ADD PRIMARY KEY (`tracking_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `order_tracking_history`
--
ALTER TABLE `order_tracking_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `idx_tracking_order` (`order_id`);

--
-- Indexes for table `password_reset`
--
ALTER TABLE `password_reset`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payment_logs`
--
ALTER TABLE `payment_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `couriers`
--
ALTER TABLE `couriers`
  MODIFY `courier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `courier_deliveries`
--
ALTER TABLE `courier_deliveries`
  MODIFY `delivery_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `order_notifications`
--
ALTER TABLE `order_notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_tracking`
--
ALTER TABLE `order_tracking`
  MODIFY `tracking_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_tracking_history`
--
ALTER TABLE `order_tracking_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `password_reset`
--
ALTER TABLE `password_reset`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `payment_logs`
--
ALTER TABLE `payment_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `courier_deliveries`
--
ALTER TABLE `courier_deliveries`
  ADD CONSTRAINT `courier_deliveries_ibfk_1` FOREIGN KEY (`courier_id`) REFERENCES `couriers` (`courier_id`),
  ADD CONSTRAINT `courier_deliveries_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_courier` FOREIGN KEY (`courier_id`) REFERENCES `couriers` (`courier_id`),
  ADD CONSTRAINT `fk_orders_courier` FOREIGN KEY (`courier_id`) REFERENCES `couriers` (`courier_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`courier_id`) REFERENCES `couriers` (`courier_id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);

--
-- Constraints for table `order_notifications`
--
ALTER TABLE `order_notifications`
  ADD CONSTRAINT `order_notifications_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);

--
-- Constraints for table `order_tracking`
--
ALTER TABLE `order_tracking`
  ADD CONSTRAINT `order_tracking_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;

--
-- Constraints for table `order_tracking_history`
--
ALTER TABLE `order_tracking_history`
  ADD CONSTRAINT `order_tracking_history_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;

--
-- Constraints for table `password_reset`
--
ALTER TABLE `password_reset`
  ADD CONSTRAINT `password_reset_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_logs`
--
ALTER TABLE `payment_logs`
  ADD CONSTRAINT `payment_logs_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
