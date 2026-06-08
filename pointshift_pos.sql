-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 29, 2025 at 04:59 PM
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
-- Database: `pointshift_pos`
--

-- --------------------------------------------------------

--
-- Table structure for table `backups`
--

CREATE TABLE `backups` (
  `id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `backup_logs`
--

CREATE TABLE `backup_logs` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  `encrypted` tinyint(1) DEFAULT 0,
  `size` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `backup_logs`
--

INSERT INTO `backup_logs` (`id`, `filename`, `created_at`, `encrypted`, `size`) VALUES
(1, 'backup_2025-10-25_22-35-30.sql.enc', '2025-10-25 22:35:31', 1, 67642),
(2, 'backup_2025-10-25_22-37-41.sql.enc', '2025-10-25 22:37:42', 1, 67806),
(3, 'backup_2025-10-25_22-40-48.sql.enc', '2025-10-25 22:40:49', 1, 67898),
(4, 'backup_2025-10-26_01-16-19.sql.enc', '2025-10-26 01:16:20', 1, 68086),
(5, 'backup_2025-10-26_10-35-29.sql.enc', '2025-10-26 10:35:30', 1, 68366),
(6, 'backup_2025-10-26_12-35-16.sql.enc', '2025-10-26 12:35:17', 1, 69306),
(7, 'backup_2025-10-26_20-44-24.sql.enc', '2025-10-26 20:44:25', 1, 80270),
(8, 'backup_2025-10-27_10-05-32.sql.enc', '2025-10-27 10:05:33', 1, 84178),
(9, 'backup_2025-10-29_13-57-47.sql.enc', '2025-10-29 13:57:48', 1, 95478),
(10, 'backup_2025-10-29_18-06-15.sql.enc', '2025-10-29 18:06:16', 1, 98242),
(11, 'backup_2025-10-29_21-42-22.sql.enc', '2025-10-29 21:42:23', 1, 99650),
(12, 'backup_2025-10-29_21-42-27.sql.enc', '2025-10-29 21:42:28', 1, 99746),
(13, 'backup_2025-10-29_21-54-47.sql.enc', '2025-10-29 21:54:48', 1, 100186);

-- --------------------------------------------------------

--
-- Table structure for table `backup_retention`
--

CREATE TABLE `backup_retention` (
  `id` int(11) NOT NULL,
  `days_to_keep` int(11) NOT NULL DEFAULT 30,
  `last_run` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`) VALUES
(8, 'Noodles', 'Various types of noodles and pasta dishes', '2025-10-08 11:11:30'),
(9, 'Beverages', 'Drinks, juices, and refreshments', '2025-10-08 11:11:30'),
(10, 'Snacks', 'Light snacks and finger foods', '2025-10-08 11:11:30'),
(11, 'Rice Meals', 'Meals served with rice', '2025-10-08 11:11:30'),
(12, 'Desserts', 'Sweet treats and desserts', '2025-10-08 11:11:30'),
(13, 'Breakfast', 'Breakfast items and all-day breakfast', '2025-10-08 11:11:30');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_logs`
--

CREATE TABLE `inventory_logs` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` enum('Restock','Edit','Delete') NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_reports`
--

CREATE TABLE `inventory_reports` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `change_type` enum('Added','Removed') NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0 COMMENT 'Legacy column - same as quantity_changed',
  `quantity_changed` int(11) NOT NULL DEFAULT 0 COMMENT 'Absolute amount of stock added or removed',
  `previous_quantity` int(11) DEFAULT NULL COMMENT 'Stock quantity before the change',
  `new_quantity` int(11) DEFAULT NULL COMMENT 'Stock quantity after the change',
  `remarks` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_reports`
--

INSERT INTO `inventory_reports` (`id`, `date`, `product_id`, `user_id`, `change_type`, `quantity`, `quantity_changed`, `previous_quantity`, `new_quantity`, `remarks`, `created_at`) VALUES
(2, '2025-10-19', 22, NULL, 'Added', 1, 0, NULL, NULL, 'q', '2025-10-20 14:37:33'),
(3, '2025-10-22', 77, NULL, 'Added', 7, 7, 50, 57, 'Stock added by staff. Previous: 50, New: 57', '2025-10-22 10:44:43'),
(4, '2025-10-22', 14, 4, 'Added', 6, 6, 11, 17, 'yeah Previous: 11, New: 17', '2025-10-22 14:18:44'),
(5, '2025-10-23', 14, NULL, 'Removed', 8, 8, 17, 9, 'Stock removed by admin. Previous: 17, New: 9', '2025-10-23 02:34:21'),
(6, '2025-10-23', 79, NULL, 'Added', 4, 4, 11, 15, 'Stock added by admin. Previous: 11, New: 15', '2025-10-23 09:34:14'),
(7, '2025-10-23', 79, NULL, 'Added', 3, 3, 9, 12, 'Stock added by admin. Previous: 9, New: 12', '2025-10-23 09:53:58'),
(8, '2025-10-23', 79, 4, 'Added', 3, 3, 9, 12, 'Stock added by staff. Previous: 9, New: 12', '2025-10-23 09:55:33'),
(9, '2025-10-23', 79, 4, 'Added', 4, 4, 9, 13, 'Stock added by staff. Previous: 9, New: 13', '2025-10-23 10:10:09'),
(10, '2025-10-23', 79, NULL, 'Added', 2, 2, 9, 11, 'Stock added by admin. Previous: 9, New: 11', '2025-10-23 10:35:44'),
(11, '2025-10-23', 79, 4, 'Added', 2, 2, 9, 11, 'Stock added by staff. Previous: 9, New: 11', '2025-10-23 10:40:10'),
(12, '2025-10-23', 14, NULL, 'Added', 3, 3, 9, 12, 'Stock added by admin. Previous: 9, New: 12', '2025-10-23 11:01:40'),
(13, '2025-10-23', 79, NULL, 'Added', 5, 5, 7, 12, 'Stock added by admin. Previous: 7, New: 12', '2025-10-23 11:03:06'),
(14, '2025-10-23', 79, NULL, 'Added', 2, 2, 9, 11, 'Stock added by admin. Previous: 9, New: 11', '2025-10-23 11:11:53'),
(15, '2025-10-23', 79, NULL, 'Added', 2, 2, 9, 11, 'Stock added by admin. Previous: 9, New: 11', '2025-10-23 11:12:59'),
(16, '2025-10-23', 79, NULL, 'Added', 2, 2, 9, 11, 'Stock added by admin. Previous: 9, New: 11', '2025-10-23 14:02:44'),
(17, '2025-10-23', 79, NULL, 'Added', 2, 2, 9, 11, 'Stock added by admin. Previous: 9, New: 11', '2025-10-23 14:06:05'),
(18, '2025-10-23', 79, NULL, 'Added', 2, 2, 9, 11, 'Stock added by admin. Previous: 9, New: 11', '2025-10-23 14:10:35'),
(19, '2025-10-23', 79, NULL, 'Added', 6, 6, 9, 15, 'Stock added by admin. Previous: 9, New: 15', '2025-10-23 14:15:24'),
(20, '2025-10-23', 79, NULL, 'Added', 6, 6, 9, 15, 'Stock added by admin. Previous: 9, New: 15', '2025-10-23 14:24:04'),
(21, '2025-10-23', 79, NULL, 'Added', 5, 5, 9, 14, 'Stock added by admin. Previous: 9, New: 14', '2025-10-23 14:30:38'),
(22, '2025-10-23', 79, NULL, 'Added', 6, 6, 9, 15, 'Stock added by admin. Previous: 9, New: 15', '2025-10-23 14:38:49'),
(23, '2025-10-23', 79, NULL, 'Added', 3, 3, 9, 12, 'Stock added by admin. Previous: 9, New: 12', '2025-10-23 14:46:43'),
(24, '2025-10-23', 79, 4, 'Added', 5, 5, 9, 14, 'Stock added by staff. Previous: 9, New: 14', '2025-10-23 14:49:32'),
(25, '2025-10-23', 75, 4, 'Added', 9, 9, 4, 13, 'Stock added by staff. Previous: 4, New: 13', '2025-10-23 14:55:20'),
(26, '2025-10-23', 24, NULL, 'Added', 6, 6, 9, 15, 'Stock added by admin. Previous: 9, New: 15', '2025-10-23 14:56:29'),
(27, '2025-10-23', 24, NULL, 'Added', 5, 5, 10, 15, 'Stock added by admin. Previous: 10, New: 15', '2025-10-23 14:59:50'),
(28, '2025-10-23', 24, NULL, 'Added', 3, 3, 10, 13, 'Stock added by admin. Previous: 10, New: 13', '2025-10-23 15:09:17'),
(29, '2025-10-23', 24, NULL, 'Added', 2, 2, 10, 12, 'Stock added by admin. Previous: 10, New: 12', '2025-10-23 15:13:09'),
(30, '2025-10-24', 80, 4, 'Added', 15, 15, 0, 15, 'Stock added by staff. Previous: 0, New: 15', '2025-10-24 02:57:16'),
(31, '2025-10-24', 80, 4, 'Added', 3, 3, 9, 12, 'Stock added by staff. Previous: 9, New: 12', '2025-10-24 08:39:04'),
(32, '2025-10-24', 80, 4, 'Added', 4, 4, 12, 16, 'Stock added by staff. Previous: 12, New: 16', '2025-10-24 09:01:59'),
(33, '2025-10-24', 80, 4, 'Added', 3, 3, 10, 13, 'Stock added by staff. Previous: 10, New: 13', '2025-10-24 09:14:57'),
(34, '2025-10-26', 59, NULL, 'Removed', 27, 27, 36, 9, 'Stock removed by admin. Previous: 36, New: 9', '2025-10-26 02:12:39'),
(35, '2025-10-26', 80, 7, 'Removed', 30, 30, 34, 4, 'Stock removed by staff. Previous: 34, New: 4', '2025-10-26 04:17:08');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `recipient_id` int(11) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `parent_message_id` int(11) DEFAULT NULL COMMENT 'For threaded conversations',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `recipient_id`, `subject`, `message`, `parent_message_id`, `is_read`, `created_at`, `updated_at`) VALUES
(1, 6, 1, 'yy', 'yy', NULL, 1, '2025-10-14 12:16:41', '2025-10-29 01:52:23'),
(6, 6, 1, 'problem', 'yy', NULL, 1, '2025-10-22 13:50:25', '2025-10-29 01:52:22');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'unread',
  `product_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `shown` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `message`, `type`, `status`, `product_id`, `created_at`, `user_id`, `shown`) VALUES
(469, 'Low Stock Alert: FUDGEE Bar has only 9 items left.', 'low_stock', 'read', 79, '2025-10-23 22:06:33', NULL, 0),
(470, 'Low Stock Alert: FUDGEE Bar has only 9 items left.', 'low_stock', 'read', 79, '2025-10-23 22:11:48', NULL, 0),
(471, 'Low Stock Alert: FUDGEE Bar has only 9 items left.', 'low_stock', 'read', 79, '2025-10-23 22:15:46', NULL, 0),
(472, 'Low Stock Alert: FUDGEE Bar has only 9 items left.', 'low_stock', 'read', 79, '2025-10-23 22:24:26', NULL, 0),
(473, 'Test notification - Low stock alert', 'low_stock', 'read', 1, '2025-10-23 22:25:55', NULL, 0),
(474, 'Test notification - Out of stock alert', 'out_of_stock', 'read', 2, '2025-10-23 22:25:55', NULL, 0),
(475, 'Test notification - Transaction completed', 'transaction', 'read', NULL, '2025-10-23 22:25:55', NULL, 0),
(476, 'Low Stock Alert: FUDGEE Bar has only 9 items left.', 'low_stock', 'read', 79, '2025-10-23 22:30:55', NULL, 0),
(477, 'Low Stock Alert: FUDGEE Bar has only 9 items left.', 'low_stock', 'read', 79, '2025-10-23 22:39:13', NULL, 0),
(478, 'Low Stock Alert: FUDGEE Bar has only 9 items left.', 'low_stock', 'read', 79, '2025-10-23 22:47:01', NULL, 0),
(479, 'Low Stock Alert: FUDGEE Bar has only 10 items left.', 'low_stock', 'read', 79, '2025-10-23 22:50:46', NULL, 0),
(480, 'Low Stock Alert: Leche Flan has only 9 items left.', 'low_stock', 'read', 75, '2025-10-23 22:55:40', NULL, 0),
(481, 'Low Stock Alert: Garden Hose has only 10 items left.', 'low_stock', 'read', 24, '2025-10-23 22:56:45', NULL, 0),
(482, 'Low Stock Alert: Garden Hose has only 10 items left.', 'low_stock', 'read', 24, '2025-10-23 23:00:05', NULL, 0),
(483, 'Low Stock Alert: Garden Hose has only 10 items left.', 'low_stock', 'read', 24, '2025-10-23 23:09:38', NULL, 0),
(484, 'Low Stock Alert: Garden Hose has only 9 items left.', 'low_stock', 'read', 24, '2025-10-23 23:13:25', NULL, 0),
(485, 'Expired: flip expired on 2025-10-22', 'expiry', 'read', 78, '2025-10-24 11:33:53', NULL, 0),
(486, 'Expired: FUDGEE Bar expired on 2025-10-23', 'expiry', 'read', 79, '2025-10-24 11:33:53', NULL, 0),
(487, 'Expiring soon: Longsilog expires on 2025-10-24', 'expiry', 'read', 77, '2025-10-24 11:35:06', NULL, 0),
(488, 'Low Stock Alert: Great Taste Premium Classic 3 in 1 Coffee has only 9 items left.', 'low_stock', 'read', 80, '2025-10-24 16:37:54', NULL, 0),
(489, 'Low Stock Alert: Great Taste Premium Classic 3 in 1 Coffee has only 10 items left.', 'low_stock', 'read', 80, '2025-10-24 17:02:27', NULL, 0),
(490, 'Low Stock Alert: Great Taste Premium Classic 3 in 1 Coffee has only 9 items left.', 'low_stock', 'read', 80, '2025-10-24 17:15:46', NULL, 0),
(491, 'Low Stock Alert: Beef Udon has only 9 items left.', 'low_stock', 'read', 59, '2025-10-26 10:12:39', NULL, 0),
(492, 'Expiry Alert: Product \'Beef Udon\' will expire on 2025-10-28 (in 2 days).', 'expiry', 'read', 59, '2025-10-26 10:12:39', NULL, 0),
(495, 'Low Stock Alert: Great Taste Premium Classic 3 in 1 Coffee has only 4 items left.', 'low_stock', 'read', 80, '2025-10-26 12:17:09', NULL, 0),
(496, 'Product \'Great Taste Premium Classic 3 in 1 Coffee\' has expired on 2025-10-24.', 'expiry', 'read', 80, '2025-10-26 12:26:15', NULL, 0),
(497, 'Product \'Great Taste Premium Classic 3 in 1 Coffee\' has expired on 2025-10-25.', 'expiry', 'read', 80, '2025-10-26 12:38:24', NULL, 0),
(498, 'Product \'Great Taste Premium Classic 3 in 1 Coffee\' has expired on 2025-10-24.', 'expiry', 'read', 80, '2025-10-26 12:44:56', NULL, 0),
(499, 'Product \'Great Taste Premium Classic 3 in 1 Coffee\' has expired on 2025-10-24.', 'expiry', 'read', 80, '2025-10-26 12:49:11', NULL, 0),
(500, 'Transaction completed: #ORD-20251026-867 - Total: 20.1376 (cash)', 'transaction', 'read', NULL, '2025-10-26 13:05:43', NULL, 0),
(501, 'Product \'Great Taste Premium Classic 3 in 1 Coffee\' has expired on 2025-10-24.', 'expiry', 'read', 80, '2025-10-26 20:31:26', NULL, 0),
(502, 'Product \'Great Taste Premium Classic 3 in 1 Coffee\' has expired on 2025-10-25.', 'expiry', 'read', 80, '2025-10-26 20:31:54', NULL, 0),
(503, 'Product \'Great Taste Premium Classic 3 in 1 Coffee\' has expired on 2025-10-25.', 'expiry', 'read', 80, '2025-10-26 20:31:59', NULL, 0),
(504, 'Transaction completed: #ORD-20251026-510 - Total: 30.2064 (cash)', 'transaction', 'read', NULL, '2025-10-26 20:57:50', NULL, 0),
(505, 'Transaction completed: #ORD-20251026-184 - Total: 20.1376 (cash)', 'transaction', 'read', NULL, '2025-10-26 21:13:54', NULL, 0),
(506, 'Transaction completed: #ORD-20251026-112 - Total: 20.1376 (cash)', 'transaction', 'read', NULL, '2025-10-26 21:15:49', NULL, 0),
(507, 'Transaction completed: #ORD-20251026-486 - Total: 20.1376 (cash)', 'transaction', 'read', NULL, '2025-10-26 21:19:13', NULL, 0),
(508, 'New account request: new (ehhehem7@gmail.com) - pending approval.', 'info', 'read', NULL, '2025-10-29 10:28:35', NULL, 0),
(509, 'New account request: ako (ehhehem7@gmail.com) - pending approval.', 'info', 'read', NULL, '2025-10-29 11:22:55', NULL, 0),
(510, 'New account request: ako (dummyacc45f@gmail.com) - pending approval.', 'info', 'read', NULL, '2025-10-29 11:40:52', NULL, 0),
(511, 'New account request: ako (dummyacc45f@gmail.com) - pending approval.', 'info', 'read', NULL, '2025-10-29 11:52:32', NULL, 0),
(512, 'New account request: russel (dummyacc45f@gmail.com) - pending approval.', 'info', 'read', NULL, '2025-10-29 12:01:46', NULL, 0),
(513, 'New account request: cashier (dummyacc45f@gmail.com) - pending approval.', 'info', 'read', NULL, '2025-10-29 12:05:24', NULL, 0),
(514, 'New account request: russel (dummyacc45f@gmail.com) - pending approval.', 'info', 'read', NULL, '2025-10-29 12:08:35', NULL, 0),
(515, 'Account approved: russel ako (dummyacc45f@gmail.com)', 'success', 'read', NULL, '2025-10-29 12:08:49', NULL, 0),
(516, 'Account approved: russel ako (dummyacc45f@gmail.com)', 'success', 'read', NULL, '2025-10-29 12:08:54', NULL, 0),
(517, 'Account approved: russel ako (dummyacc45f@gmail.com)', 'success', 'read', NULL, '2025-10-29 12:08:59', NULL, 0),
(518, 'Account approved: russel ako (dummyacc45f@gmail.com)', 'success', 'read', NULL, '2025-10-29 12:09:04', NULL, 0),
(519, 'Transaction completed: #ORD-20251029-806 - Total: 20.1376 (gcash)', 'transaction', 'read', NULL, '2025-10-29 17:08:50', NULL, 0),
(520, 'New account request: cashier (dummyacc45f@gmail.com) - pending approval.', 'info', 'read', NULL, '2025-10-29 21:51:06', NULL, 0),
(521, 'Account approved: cashier ako (dummyacc45f@gmail.com)', 'success', 'read', NULL, '2025-10-29 21:52:11', NULL, 0),
(522, 'New account request: cashier (dummyacc45f@gmail.com) - pending approval.', 'info', 'read', NULL, '2025-10-29 23:08:40', NULL, 0),
(523, 'Account approved: cashier ako (dummyacc45f@gmail.com)', 'success', 'read', NULL, '2025-10-29 23:08:57', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT 'cash',
  `amount_received` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('pending','completed') DEFAULT 'pending',
  `payment_info_encrypted` text DEFAULT NULL,
  `payment_info_iv` text DEFAULT NULL,
  `payment_info_tag` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `user_id`, `total_amount`, `subtotal`, `discount_percent`, `discount_amount`, `tax_amount`, `payment_method`, `amount_received`, `created_at`, `updated_at`, `status`, `payment_info_encrypted`, `payment_info_iv`, `payment_info_tag`) VALUES
(5, 'ORD-20251017-482', 6, 51519.99, 45999.99, 0.00, 0.00, 5520.00, 'cash', 60000.00, '2025-10-17 09:33:33', '2025-10-17 09:33:33', 'completed', NULL, NULL, NULL),
(6, 'ORD-20251017-985', 6, 8959.98, 7999.98, 0.00, 0.00, 960.00, 'cash', 10000.00, '2025-10-17 09:34:34', '2025-10-17 09:34:34', 'completed', NULL, NULL, NULL),
(7, 'ORD-20251019-524', 6, 223.99, 199.99, 0.00, 0.00, 24.00, 'cash', 1000.00, '2025-10-18 16:07:06', '2025-10-18 16:07:06', 'completed', NULL, NULL, NULL),
(8, 'ORD-20251019-809', 6, 167.94, 149.95, 0.00, 0.00, 17.99, 'cash', 200.00, '2025-10-18 23:55:41', '2025-10-18 23:55:41', 'completed', NULL, NULL, NULL),
(13, 'ORD-20251017-053', 4, 33.60, 30.00, 0.00, 0.00, 3.60, 'cash', 50.00, '2025-10-17 01:52:40', '2025-10-19 05:15:21', 'completed', NULL, NULL, NULL),
(14, 'ORD-20251017-956', 4, 201.60, 180.00, 0.00, 0.00, 21.60, 'cash', 1000.00, '2025-10-17 01:53:19', '2025-10-19 05:15:21', 'completed', NULL, NULL, NULL),
(15, 'ORD-20251017-238', 4, 168.00, 150.00, 0.00, 0.00, 18.00, 'cash', 200.00, '2025-10-17 02:03:20', '2025-10-19 05:15:21', 'completed', NULL, NULL, NULL),
(16, 'ORD-20251017-368', 4, 974.40, 870.00, 0.00, 0.00, 104.40, 'cash', 1003.00, '2025-10-17 02:31:34', '2025-10-19 05:15:21', 'completed', NULL, NULL, NULL),
(17, 'ORD-20251019-433', 6, 10.07, 8.99, 0.00, 0.00, 1.08, 'cash', 50.00, '2025-10-19 05:33:55', '2025-10-19 05:33:55', 'completed', NULL, NULL, NULL),
(18, 'ORD-20251020-234', 6, 30.21, 26.97, 0.00, 0.00, 3.24, 'cash', 50.00, '2025-10-20 14:27:31', '2025-10-20 14:27:31', 'completed', NULL, NULL, NULL),
(19, 'ORD-20251020-880', 6, 336.00, 300.00, 0.00, 0.00, 36.00, 'gcash', 500.00, '2025-10-20 14:27:44', '2025-10-20 14:27:44', 'completed', NULL, NULL, NULL),
(20, 'ORD-20251020-774', 6, 448.00, 400.00, 0.00, 0.00, 48.00, 'gcash', 500.00, '2025-10-20 14:41:30', '2025-10-20 14:41:30', 'completed', NULL, NULL, NULL),
(21, 'ORD-20251020-254', 6, 100.80, 90.00, 0.00, 0.00, 10.80, 'gcash', 200.00, '2025-10-20 14:41:43', '2025-10-20 14:41:43', 'completed', NULL, NULL, NULL),
(22, 'ORD-20251020-934', 6, 403.20, 360.00, 0.00, 0.00, 43.20, 'gcash', 500.00, '2025-10-20 14:49:18', '2025-10-20 14:49:18', 'completed', NULL, NULL, NULL),
(23, 'ORD-20251021-921', 6, 30.21, 26.97, 0.00, 0.00, 3.24, 'cash', 50.00, '2025-10-21 11:27:36', '2025-10-21 11:27:36', 'completed', NULL, NULL, NULL),
(24, 'ORD-20251021-450', 6, 301.27, 268.99, 0.00, 0.00, 32.28, 'gcash', 500.00, '2025-10-21 11:28:23', '2025-10-21 11:28:23', 'completed', NULL, NULL, NULL),
(25, 'ORD-20251022-932', 6, 21503.87, 19199.88, 0.00, 0.00, 2303.99, 'cash', 25000.00, '2025-10-22 12:24:17', '2025-10-22 12:24:17', 'completed', NULL, NULL, NULL),
(26, 'ORD-20251022-548', 6, 633.80, 565.89, 0.00, 0.00, 67.91, 'cash', 1000.00, '2025-10-22 12:30:34', '2025-10-22 12:30:34', 'completed', NULL, NULL, NULL),
(27, 'ORD-20251022-876', 6, 29119.78, 25999.80, 0.00, 0.00, 3119.98, 'cash', 30000.00, '2025-10-22 12:49:30', '2025-10-22 12:49:30', 'completed', NULL, NULL, NULL),
(28, 'ORD-20251023-921', 6, 1008.00, 900.00, 0.00, 0.00, 108.00, 'cash', 1100.00, '2025-10-23 02:31:56', '2025-10-23 02:31:56', 'completed', NULL, NULL, NULL),
(29, 'ORD-20251023-790', 6, 268.80, 240.00, 0.00, 0.00, 28.80, 'cash', 500.00, '2025-10-23 02:41:30', '2025-10-23 02:41:30', 'completed', NULL, NULL, NULL),
(30, 'ORD-20251023-248', 6, 67.20, 60.00, 0.00, 0.00, 7.20, 'cash', 100.00, '2025-10-23 02:45:21', '2025-10-23 02:45:21', 'completed', NULL, NULL, NULL),
(31, 'ORD-20251023-427', 6, 4032.00, 3600.00, 0.00, 0.00, 432.00, 'cash', 5000.00, '2025-10-23 09:20:43', '2025-10-23 09:20:43', 'completed', NULL, NULL, NULL),
(32, 'ORD-20251023-085', 6, 35.84, 32.00, 0.00, 0.00, 3.84, 'cash', 50.00, '2025-10-23 09:30:10', '2025-10-23 09:30:10', 'completed', NULL, NULL, NULL),
(33, 'ORD-20251023-917', 6, 53.76, 48.00, 0.00, 0.00, 5.76, 'cash', 100.00, '2025-10-23 09:34:39', '2025-10-23 09:34:39', 'completed', NULL, NULL, NULL),
(34, 'ORD-20251023-334', 6, 26.88, 24.00, 0.00, 0.00, 2.88, 'cash', 50.00, '2025-10-23 09:54:19', '2025-10-23 09:54:19', 'completed', NULL, NULL, NULL),
(35, 'ORD-20251023-262', 6, 26.88, 24.00, 0.00, 0.00, 2.88, 'cash', 50.00, '2025-10-23 09:55:46', '2025-10-23 09:55:46', 'completed', NULL, NULL, NULL),
(36, 'ORD-20251023-122', 6, 35.84, 32.00, 0.00, 0.00, 3.84, 'cash', 50.00, '2025-10-23 10:10:31', '2025-10-23 10:10:31', 'completed', NULL, NULL, NULL),
(37, 'ORD-20251023-386', 6, 17.92, 16.00, 0.00, 0.00, 1.92, 'cash', 50.00, '2025-10-23 10:35:58', '2025-10-23 10:35:58', 'completed', NULL, NULL, NULL),
(38, 'ORD-20251023-600', 6, 35.84, 32.00, 0.00, 0.00, 3.84, 'cash', 50.00, '2025-10-23 10:43:31', '2025-10-23 10:43:31', 'completed', NULL, NULL, NULL),
(39, 'ORD-20251023-306', 6, 26.88, 24.00, 0.00, 0.00, 2.88, 'cash', 50.00, '2025-10-23 11:03:18', '2025-10-23 11:03:18', 'completed', NULL, NULL, NULL),
(40, 'ORD-20251023-032', 6, 17.92, 16.00, 0.00, 0.00, 1.92, 'cash', 20.00, '2025-10-23 11:12:04', '2025-10-23 11:12:04', 'completed', NULL, NULL, NULL),
(41, 'ORD-20251023-779', 6, 17.92, 16.00, 0.00, 0.00, 1.92, 'cash', 20.00, '2025-10-23 11:13:09', '2025-10-23 11:13:09', 'completed', NULL, NULL, NULL),
(44, 'ORD-20251023-068', 6, 17.92, 16.00, 0.00, 0.00, 1.92, 'cash', 20.00, '2025-10-23 14:03:03', '2025-10-23 14:03:03', 'completed', NULL, NULL, NULL),
(45, 'ORD-20251023-265', 6, 17.92, 16.00, 0.00, 0.00, 1.92, 'cash', 50.00, '2025-10-23 14:06:33', '2025-10-23 14:06:33', 'completed', NULL, NULL, NULL),
(46, 'ORD-20251023-216', 6, 17.92, 16.00, 0.00, 0.00, 1.92, 'cash', 20.00, '2025-10-23 14:11:48', '2025-10-23 14:11:48', 'completed', NULL, NULL, NULL),
(47, 'ORD-20251023-052', 6, 53.76, 48.00, 0.00, 0.00, 5.76, 'cash', 100.00, '2025-10-23 14:15:46', '2025-10-23 14:15:46', 'completed', NULL, NULL, NULL),
(48, 'ORD-20251023-690', 6, 53.76, 48.00, 0.00, 0.00, 5.76, 'cash', 100.00, '2025-10-23 14:24:26', '2025-10-23 14:24:26', 'completed', NULL, NULL, NULL),
(49, 'ORD-20251023-756', 6, 44.80, 40.00, 0.00, 0.00, 4.80, 'cash', 50.00, '2025-10-23 14:30:55', '2025-10-23 14:30:55', 'completed', NULL, NULL, NULL),
(50, 'ORD-20251023-057', 6, 53.76, 48.00, 0.00, 0.00, 5.76, 'cash', 100.00, '2025-10-23 14:39:13', '2025-10-23 14:39:13', 'completed', NULL, NULL, NULL),
(51, 'ORD-20251023-698', 6, 161.28, 144.00, 0.00, 0.00, 17.28, 'cash', 200.00, '2025-10-23 14:47:01', '2025-10-23 14:47:01', 'completed', NULL, NULL, NULL),
(52, 'ORD-20251023-927', 6, 203.78, 181.95, 0.00, 0.00, 21.83, 'cash', 500.00, '2025-10-23 14:50:46', '2025-10-23 14:50:46', 'completed', NULL, NULL, NULL),
(53, 'ORD-20251023-279', 6, 268.80, 240.00, 0.00, 0.00, 28.80, 'cash', 500.00, '2025-10-23 14:55:40', '2025-10-23 14:55:40', 'completed', NULL, NULL, NULL),
(54, 'ORD-20251023-113', 6, 257.54, 229.95, 0.00, 0.00, 27.59, 'cash', 500.00, '2025-10-23 14:56:45', '2025-10-23 14:56:45', 'completed', NULL, NULL, NULL),
(55, 'ORD-20251023-312', 6, 257.54, 229.95, 0.00, 0.00, 27.59, 'cash', 500.00, '2025-10-23 15:00:05', '2025-10-23 15:00:05', 'completed', NULL, NULL, NULL),
(56, 'ORD-20251023-217', 6, 154.53, 137.97, 0.00, 0.00, 16.56, 'cash', 200.00, '2025-10-23 15:09:38', '2025-10-23 15:09:38', 'completed', NULL, NULL, NULL),
(57, 'ORD-20251023-700', 6, 154.53, 137.97, 0.00, 0.00, 16.56, 'cash', 200.00, '2025-10-23 15:13:25', '2025-10-23 15:13:25', 'completed', NULL, NULL, NULL),
(58, 'ORD-20251024-331', 6, 20.16, 18.00, 0.00, 0.00, 2.16, 'cash', 50.00, '2025-10-24 08:37:54', '2025-10-24 08:37:54', 'completed', NULL, NULL, NULL),
(59, 'ORD-20251024-093', 6, 20.16, 18.00, 0.00, 0.00, 2.16, 'cash', 50.00, '2025-10-24 09:02:27', '2025-10-24 09:02:27', 'completed', NULL, NULL, NULL),
(60, 'ORD-20251024-006', 6, 13.44, 12.00, 0.00, 0.00, 1.44, 'cash', 20.00, '2025-10-24 09:15:46', '2025-10-24 09:15:46', 'completed', NULL, NULL, NULL),
(61, 'ORD-20251026-867', 6, 20.14, 17.98, 0.00, 0.00, 2.16, 'cash', 50.00, '2025-10-26 05:05:43', '2025-10-26 05:05:43', 'completed', NULL, NULL, NULL),
(62, 'ORD-20251026-510', 6, 30.21, 26.97, 0.00, 0.00, 3.24, 'cash', 50.00, '2025-10-26 12:57:50', '2025-10-26 12:57:50', 'completed', NULL, NULL, NULL),
(63, 'ORD-20251026-184', 6, 20.14, 17.98, 0.00, 0.00, 2.16, 'cash', 50.00, '2025-10-26 13:13:54', '2025-10-26 13:13:54', 'completed', NULL, NULL, NULL),
(64, 'ORD-20251026-112', 6, 20.14, 17.98, 0.00, 0.00, 2.16, 'cash', 50.00, '2025-10-26 13:15:49', '2025-10-26 13:15:49', 'completed', NULL, NULL, NULL),
(65, 'ORD-20251026-486', 6, 20.14, 17.98, 0.00, 0.00, 2.16, 'cash', 50.00, '2025-10-26 13:19:13', '2025-10-26 13:19:13', 'completed', NULL, NULL, NULL),
(66, 'ORD-20251029-806', 6, 20.14, 17.98, 0.00, 0.00, 2.16, 'gcash', 50.00, '2025-10-29 09:08:50', '2025-10-29 09:08:50', 'completed', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `total_price`) VALUES
(12, 5, 14, 1, 45999.99, 45999.99),
(13, 6, 21, 2, 3999.99, 7999.98),
(14, 7, 30, 1, 199.99, 199.99),
(15, 8, 25, 5, 29.99, 149.95),
(16, 17, 22, 1, 8.99, 8.99),
(17, 18, 22, 3, 8.99, 26.97),
(18, 19, 59, 2, 150.00, 300.00),
(19, 20, 71, 1, 130.00, 130.00),
(20, 20, 63, 1, 30.00, 30.00),
(21, 20, 70, 1, 90.00, 90.00),
(22, 20, 68, 1, 60.00, 60.00),
(23, 20, 74, 2, 45.00, 90.00),
(24, 21, 63, 3, 30.00, 90.00),
(25, 22, 70, 4, 90.00, 360.00),
(26, 23, 22, 3, 8.99, 26.97),
(27, 24, 22, 1, 8.99, 8.99),
(28, 24, 71, 2, 130.00, 260.00),
(29, 25, 29, 12, 1599.99, 19199.88),
(30, 26, 75, 1, 60.00, 60.00),
(31, 26, 24, 11, 45.99, 505.89),
(32, 27, 20, 20, 1299.99, 25999.80),
(33, 28, 75, 15, 60.00, 900.00),
(34, 29, 75, 4, 60.00, 240.00),
(35, 30, 75, 1, 60.00, 60.00),
(36, 31, 60, 20, 180.00, 3600.00),
(37, 32, 79, 4, 8.00, 32.00),
(38, 33, 79, 6, 8.00, 48.00),
(39, 34, 79, 3, 8.00, 24.00),
(40, 35, 79, 3, 8.00, 24.00),
(41, 36, 79, 4, 8.00, 32.00),
(42, 37, 79, 2, 8.00, 16.00),
(43, 38, 79, 4, 8.00, 32.00),
(44, 39, 79, 3, 8.00, 24.00),
(45, 40, 79, 2, 8.00, 16.00),
(46, 41, 79, 2, 8.00, 16.00),
(47, 44, 79, 2, 8.00, 16.00),
(48, 45, 79, 2, 8.00, 16.00),
(49, 46, 79, 2, 8.00, 16.00),
(50, 47, 79, 6, 8.00, 48.00),
(51, 48, 79, 6, 8.00, 48.00),
(52, 49, 79, 5, 8.00, 40.00),
(53, 50, 79, 6, 8.00, 48.00),
(54, 51, 79, 3, 8.00, 24.00),
(55, 51, 68, 2, 60.00, 120.00),
(56, 52, 79, 4, 8.00, 32.00),
(57, 52, 25, 5, 29.99, 149.95),
(58, 53, 75, 4, 60.00, 240.00),
(59, 54, 24, 5, 45.99, 229.95),
(60, 55, 24, 5, 45.99, 229.95),
(61, 56, 24, 3, 45.99, 137.97),
(62, 57, 24, 3, 45.99, 137.97),
(63, 58, 80, 6, 3.00, 18.00),
(64, 59, 80, 6, 3.00, 18.00),
(65, 60, 80, 4, 3.00, 12.00),
(66, 61, 22, 2, 8.99, 17.98),
(67, 62, 22, 3, 8.99, 26.97),
(68, 63, 22, 2, 8.99, 17.98),
(69, 64, 22, 2, 8.99, 17.98),
(70, 65, 22, 2, 8.99, 17.98),
(71, 66, 22, 2, 8.99, 17.98);

-- --------------------------------------------------------

--
-- Table structure for table `payment_qrcodes`
--

CREATE TABLE `payment_qrcodes` (
  `id` int(11) NOT NULL,
  `payment_method` varchar(50) NOT NULL DEFAULT 'gcash',
  `qr_code_path` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_qrcodes`
--

INSERT INTO `payment_qrcodes` (`id`, `payment_method`, `qr_code_path`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'gcash', 'uploads/qrcodes/gcash_qr_1761728897.png', 'GCash Payment QR Code', 1, '2025-10-20 14:32:07', '2025-10-29 09:08:17'),
(2, 'gcash', '', 'GCash Payment QR Code', 1, '2025-10-20 14:33:10', '2025-10-20 14:33:10'),
(6, 'gcash', 'uploads/qrcodes/gcash_qr_1760971815.png', 'GCash Payment QR Code', 1, '2025-10-20 14:50:15', '2025-10-20 14:50:15'),
(7, 'gcash', 'uploads/qrcodes/gcash_qr_1761197865.png', 'GCash Payment QR Code', 1, '2025-10-23 05:37:45', '2025-10-23 05:37:45');

-- --------------------------------------------------------

--
-- Table structure for table `pos_messages`
--

CREATE TABLE `pos_messages` (
  `id` int(11) NOT NULL,
  `msg_id` varchar(128) NOT NULL,
  `kiosk_key` varchar(128) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `processed` tinyint(1) NOT NULL DEFAULT 0,
  `processed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pos_messages`
--

INSERT INTO `pos_messages` (`id`, `msg_id`, `kiosk_key`, `payload`, `processed`, `processed_by`, `created_at`, `processed_at`) VALUES
(1, 'posmsg_1761479683409_krfjfse', NULL, '{\"id\":80,\"name\":\"Great Taste Premium Classic 3 in 1 Coffee\",\"sku\":\"GTPC-3IN1-001\",\"category_id\":9,\"price\":3,\"stock_quantity\":4,\"low_stock_threshold\":10,\"barcode\":\"4800016012358\",\"expiry\":\"2025-10-24\",\"description\":\"Instant coffee mix with creamy and rich taste, produced by Universal Robina Corporation.\",\"status\":\"active\",\"created_at\":\"2025-10-24 10:53:13\",\"updated_at\":\"2025-10-26 12:49:11\",\"last_updated_by\":7,\"name_encrypted\":null,\"name_iv\":null,\"name_tag\":null,\"sku_encrypted\":null,\"sku_iv\":null,\"sku_tag\":null,\"barcode_encrypted\":null,\"barcode_iv\":null,\"barcode_tag\":null,\"description_encrypted\":null,\"description_iv\":null,\"description_tag\":null,\"category_name\":\"Beverages\"}', 1, 6, '2025-10-26 11:54:43', '2025-10-26 11:54:44'),
(2, 'posmsg_1761479694693_46h4cvm', NULL, '{\"id\":80,\"name\":\"Great Taste Premium Classic 3 in 1 Coffee\",\"sku\":\"GTPC-3IN1-001\",\"category_id\":9,\"price\":3,\"stock_quantity\":4,\"low_stock_threshold\":10,\"barcode\":\"4800016012358\",\"expiry\":\"2025-10-24\",\"description\":\"Instant coffee mix with creamy and rich taste, produced by Universal Robina Corporation.\",\"status\":\"active\",\"created_at\":\"2025-10-24 10:53:13\",\"updated_at\":\"2025-10-26 12:49:11\",\"last_updated_by\":7,\"name_encrypted\":null,\"name_iv\":null,\"name_tag\":null,\"sku_encrypted\":null,\"sku_iv\":null,\"sku_tag\":null,\"barcode_encrypted\":null,\"barcode_iv\":null,\"barcode_tag\":null,\"description_encrypted\":null,\"description_iv\":null,\"description_tag\":null,\"category_name\":\"Beverages\"}', 1, 6, '2025-10-26 11:54:54', '2025-10-26 11:54:56'),
(3, 'posmsg_1761479766995_nhwhpmk', NULL, '{\"id\":80,\"name\":\"Great Taste Premium Classic 3 in 1 Coffee\",\"sku\":\"GTPC-3IN1-001\",\"category_id\":9,\"price\":3,\"stock_quantity\":4,\"low_stock_threshold\":10,\"barcode\":\"4800016012358\",\"expiry\":\"2025-10-24\",\"description\":\"Instant coffee mix with creamy and rich taste, produced by Universal Robina Corporation.\",\"status\":\"active\",\"created_at\":\"2025-10-24 10:53:13\",\"updated_at\":\"2025-10-26 12:49:11\",\"last_updated_by\":7,\"name_encrypted\":null,\"name_iv\":null,\"name_tag\":null,\"sku_encrypted\":null,\"sku_iv\":null,\"sku_tag\":null,\"barcode_encrypted\":null,\"barcode_iv\":null,\"barcode_tag\":null,\"description_encrypted\":null,\"description_iv\":null,\"description_tag\":null,\"category_name\":\"Beverages\"}', 1, 6, '2025-10-26 11:56:07', '2025-10-26 11:56:08'),
(4, 'posmsg_1761479768866_s0yvjbi', NULL, '{\"id\":80,\"name\":\"Great Taste Premium Classic 3 in 1 Coffee\",\"sku\":\"GTPC-3IN1-001\",\"category_id\":9,\"price\":3,\"stock_quantity\":4,\"low_stock_threshold\":10,\"barcode\":\"4800016012358\",\"expiry\":\"2025-10-24\",\"description\":\"Instant coffee mix with creamy and rich taste, produced by Universal Robina Corporation.\",\"status\":\"active\",\"created_at\":\"2025-10-24 10:53:13\",\"updated_at\":\"2025-10-26 12:49:11\",\"last_updated_by\":7,\"name_encrypted\":null,\"name_iv\":null,\"name_tag\":null,\"sku_encrypted\":null,\"sku_iv\":null,\"sku_tag\":null,\"barcode_encrypted\":null,\"barcode_iv\":null,\"barcode_tag\":null,\"description_encrypted\":null,\"description_iv\":null,\"description_tag\":null,\"category_name\":\"Beverages\"}', 1, 6, '2025-10-26 11:56:08', '2025-10-26 11:56:10'),
(5, 'posmsg_1761479867607_6it8hau', NULL, '{\"id\":80,\"name\":\"Great Taste Premium Classic 3 in 1 Coffee\",\"sku\":\"GTPC-3IN1-001\",\"category_id\":9,\"price\":3,\"stock_quantity\":4,\"low_stock_threshold\":10,\"barcode\":\"4800016012358\",\"expiry\":\"2025-10-24\",\"description\":\"Instant coffee mix with creamy and rich taste, produced by Universal Robina Corporation.\",\"status\":\"active\",\"created_at\":\"2025-10-24 10:53:13\",\"updated_at\":\"2025-10-26 12:49:11\",\"last_updated_by\":7,\"name_encrypted\":null,\"name_iv\":null,\"name_tag\":null,\"sku_encrypted\":null,\"sku_iv\":null,\"sku_tag\":null,\"barcode_encrypted\":null,\"barcode_iv\":null,\"barcode_tag\":null,\"description_encrypted\":null,\"description_iv\":null,\"description_tag\":null,\"category_name\":\"Beverages\"}', 1, 6, '2025-10-26 11:57:47', '2025-10-26 11:57:47'),
(6, 'posmsg_1761479886609_ni0v9gs', NULL, '{\"id\":80,\"name\":\"Great Taste Premium Classic 3 in 1 Coffee\",\"sku\":\"GTPC-3IN1-001\",\"category_id\":9,\"price\":3,\"stock_quantity\":4,\"low_stock_threshold\":10,\"barcode\":\"4800016012358\",\"expiry\":\"2025-10-24\",\"description\":\"Instant coffee mix with creamy and rich taste, produced by Universal Robina Corporation.\",\"status\":\"active\",\"created_at\":\"2025-10-24 10:53:13\",\"updated_at\":\"2025-10-26 12:49:11\",\"last_updated_by\":7,\"name_encrypted\":null,\"name_iv\":null,\"name_tag\":null,\"sku_encrypted\":null,\"sku_iv\":null,\"sku_tag\":null,\"barcode_encrypted\":null,\"barcode_iv\":null,\"barcode_tag\":null,\"description_encrypted\":null,\"description_iv\":null,\"description_tag\":null,\"category_name\":\"Beverages\"}', 1, 6, '2025-10-26 11:58:06', '2025-10-26 11:58:07'),
(7, 'posmsg_1761480179267_u2duin8', NULL, '{\"id\":80,\"name\":\"Great Taste Premium Classic 3 in 1 Coffee\",\"sku\":\"GTPC-3IN1-001\",\"category_id\":9,\"price\":3,\"stock_quantity\":4,\"low_stock_threshold\":10,\"barcode\":\"4800016012358\",\"expiry\":\"2025-10-24\",\"description\":\"Instant coffee mix with creamy and rich taste, produced by Universal Robina Corporation.\",\"status\":\"active\",\"created_at\":\"2025-10-24 10:53:13\",\"updated_at\":\"2025-10-26 12:49:11\",\"last_updated_by\":7,\"name_encrypted\":null,\"name_iv\":null,\"name_tag\":null,\"sku_encrypted\":null,\"sku_iv\":null,\"sku_tag\":null,\"barcode_encrypted\":null,\"barcode_iv\":null,\"barcode_tag\":null,\"description_encrypted\":null,\"description_iv\":null,\"description_tag\":null,\"category_name\":\"Beverages\"}', 1, 6, '2025-10-26 12:02:59', '2025-10-26 12:02:59'),
(8, 'posmsg_1761525652753_lomhcwf', NULL, '{\"id\":80,\"name\":\"Great Taste Premium Classic 3 in 1 Coffee\",\"sku\":\"GTPC-3IN1-001\",\"category_id\":9,\"price\":\"3.00\",\"stock_quantity\":12,\"low_stock_threshold\":10,\"barcode\":\"4800016012358\",\"expiry\":\"2025-10-25\",\"description\":\"Instant coffee mix with creamy and rich taste, produced by Universal Robina Corporation.\",\"status\":\"active\",\"created_at\":\"2025-10-24 10:53:13\",\"updated_at\":\"2025-10-26 20:31:54\",\"last_updated_by\":7,\"name_encrypted\":null,\"name_iv\":null,\"name_tag\":null,\"sku_encrypted\":null,\"sku_iv\":null,\"sku_tag\":null,\"barcode_encrypted\":null,\"barcode_iv\":null,\"barcode_tag\":null,\"description_encrypted\":null,\"description_iv\":null,\"description_tag\":null,\"category_name\":\"Beverages\"}', 1, 6, '2025-10-27 00:40:52', '2025-10-27 00:41:32'),
(9, 'posmsg_1761525730430_c7zvjka', NULL, '{\"id\":80,\"name\":\"Great Taste Premium Classic 3 in 1 Coffee\",\"sku\":\"GTPC-3IN1-001\",\"category_id\":9,\"price\":3,\"stock_quantity\":12,\"low_stock_threshold\":10,\"barcode\":\"4800016012358\",\"expiry\":\"2025-10-25\",\"description\":\"Instant coffee mix with creamy and rich taste, produced by Universal Robina Corporation.\",\"status\":\"active\",\"created_at\":\"2025-10-24 10:53:13\",\"updated_at\":\"2025-10-26 20:31:54\",\"last_updated_by\":7,\"name_encrypted\":null,\"name_iv\":null,\"name_tag\":null,\"sku_encrypted\":null,\"sku_iv\":null,\"sku_tag\":null,\"barcode_encrypted\":null,\"barcode_iv\":null,\"barcode_tag\":null,\"description_encrypted\":null,\"description_iv\":null,\"description_tag\":null,\"category_name\":\"Beverages\"}', 1, 6, '2025-10-27 00:42:10', '2025-10-27 00:42:10'),
(10, 'posmsg_1761530847926_hkvis9h', NULL, '{\"id\":80,\"name\":\"Great Taste Premium Classic 3 in 1 Coffee\",\"sku\":\"GTPC-3IN1-001\",\"category_id\":9,\"price\":3,\"stock_quantity\":13,\"low_stock_threshold\":10,\"barcode\":\"4800016012358\",\"expiry\":\"2025-10-25\",\"description\":\"Instant coffee mix with creamy and rich taste, produced by Universal Robina Corporation.\",\"status\":\"active\",\"created_at\":\"2025-10-24 10:53:13\",\"updated_at\":\"2025-10-27 10:06:51\",\"last_updated_by\":7,\"name_encrypted\":null,\"name_iv\":null,\"name_tag\":null,\"sku_encrypted\":null,\"sku_iv\":null,\"sku_tag\":null,\"barcode_encrypted\":null,\"barcode_iv\":null,\"barcode_tag\":null,\"description_encrypted\":null,\"description_iv\":null,\"description_tag\":null,\"category_name\":\"Beverages\"}', 1, 6, '2025-10-27 02:07:27', '2025-10-27 02:07:29'),
(11, 'posmsg_1761535662865_44snfcx', NULL, '{\"id\":80,\"name\":\"Great Taste Premium Classic 3 in 1 Coffee\",\"sku\":\"GTPC-3IN1-001\",\"category_id\":9,\"price\":3,\"stock_quantity\":15,\"low_stock_threshold\":10,\"barcode\":\"4800016012358\",\"expiry\":\"2025-10-25\",\"description\":\"Instant coffee mix with creamy and rich taste, produced by Universal Robina Corporation.\",\"status\":\"active\",\"created_at\":\"2025-10-24 10:53:13\",\"updated_at\":\"2025-10-27 11:26:49\",\"last_updated_by\":7,\"name_encrypted\":null,\"name_iv\":null,\"name_tag\":null,\"sku_encrypted\":null,\"sku_iv\":null,\"sku_tag\":null,\"barcode_encrypted\":null,\"barcode_iv\":null,\"barcode_tag\":null,\"description_encrypted\":null,\"description_iv\":null,\"description_tag\":null,\"category_name\":\"Beverages\"}', 1, 6, '2025-10-27 03:27:42', '2025-10-27 03:27:43'),
(12, 'posmsg_1761535674964_eivogjr', NULL, '{\"id\":80,\"name\":\"Great Taste Premium Classic 3 in 1 Coffee\",\"sku\":\"GTPC-3IN1-001\",\"category_id\":9,\"price\":3,\"stock_quantity\":15,\"low_stock_threshold\":10,\"barcode\":\"4800016012358\",\"expiry\":\"2025-10-25\",\"description\":\"Instant coffee mix with creamy and rich taste, produced by Universal Robina Corporation.\",\"status\":\"active\",\"created_at\":\"2025-10-24 10:53:13\",\"updated_at\":\"2025-10-27 11:26:49\",\"last_updated_by\":7,\"name_encrypted\":null,\"name_iv\":null,\"name_tag\":null,\"sku_encrypted\":null,\"sku_iv\":null,\"sku_tag\":null,\"barcode_encrypted\":null,\"barcode_iv\":null,\"barcode_tag\":null,\"description_encrypted\":null,\"description_iv\":null,\"description_tag\":null,\"category_name\":\"Beverages\"}', 1, 6, '2025-10-27 03:27:54', '2025-10-27 03:27:55'),
(13, 'posmsg_1761535675771_qqpgrpq', NULL, '{\"id\":80,\"name\":\"Great Taste Premium Classic 3 in 1 Coffee\",\"sku\":\"GTPC-3IN1-001\",\"category_id\":9,\"price\":3,\"stock_quantity\":15,\"low_stock_threshold\":10,\"barcode\":\"4800016012358\",\"expiry\":\"2025-10-25\",\"description\":\"Instant coffee mix with creamy and rich taste, produced by Universal Robina Corporation.\",\"status\":\"active\",\"created_at\":\"2025-10-24 10:53:13\",\"updated_at\":\"2025-10-27 11:26:49\",\"last_updated_by\":7,\"name_encrypted\":null,\"name_iv\":null,\"name_tag\":null,\"sku_encrypted\":null,\"sku_iv\":null,\"sku_tag\":null,\"barcode_encrypted\":null,\"barcode_iv\":null,\"barcode_tag\":null,\"description_encrypted\":null,\"description_iv\":null,\"description_tag\":null,\"category_name\":\"Beverages\"}', 1, 6, '2025-10-27 03:27:55', '2025-10-27 03:27:55'),
(14, 'posmsg_1761535676603_m70u7ij', NULL, '{\"id\":80,\"name\":\"Great Taste Premium Classic 3 in 1 Coffee\",\"sku\":\"GTPC-3IN1-001\",\"category_id\":9,\"price\":3,\"stock_quantity\":15,\"low_stock_threshold\":10,\"barcode\":\"4800016012358\",\"expiry\":\"2025-10-25\",\"description\":\"Instant coffee mix with creamy and rich taste, produced by Universal Robina Corporation.\",\"status\":\"active\",\"created_at\":\"2025-10-24 10:53:13\",\"updated_at\":\"2025-10-27 11:26:49\",\"last_updated_by\":7,\"name_encrypted\":null,\"name_iv\":null,\"name_tag\":null,\"sku_encrypted\":null,\"sku_iv\":null,\"sku_tag\":null,\"barcode_encrypted\":null,\"barcode_iv\":null,\"barcode_tag\":null,\"description_encrypted\":null,\"description_iv\":null,\"description_tag\":null,\"category_name\":\"Beverages\"}', 1, 6, '2025-10-27 03:27:56', '2025-10-27 03:27:57'),
(15, 'posmsg_1761535679134_n6oz78s', NULL, '{\"id\":80,\"name\":\"Great Taste Premium Classic 3 in 1 Coffee\",\"sku\":\"GTPC-3IN1-001\",\"category_id\":9,\"price\":3,\"stock_quantity\":15,\"low_stock_threshold\":10,\"barcode\":\"4800016012358\",\"expiry\":\"2025-10-25\",\"description\":\"Instant coffee mix with creamy and rich taste, produced by Universal Robina Corporation.\",\"status\":\"active\",\"created_at\":\"2025-10-24 10:53:13\",\"updated_at\":\"2025-10-27 11:26:49\",\"last_updated_by\":7,\"name_encrypted\":null,\"name_iv\":null,\"name_tag\":null,\"sku_encrypted\":null,\"sku_iv\":null,\"sku_tag\":null,\"barcode_encrypted\":null,\"barcode_iv\":null,\"barcode_tag\":null,\"description_encrypted\":null,\"description_iv\":null,\"description_tag\":null,\"category_name\":\"Beverages\"}', 1, 6, '2025-10-27 03:27:59', '2025-10-27 03:27:59'),
(16, 'posmsg_1761745230055_ehpb7g3', NULL, '{\"id\":80,\"name\":\"Great Taste Premium Classic 3 in 1 Coffee\",\"sku\":\"GTPC-3IN1-001\",\"category_id\":9,\"price\":3,\"stock_quantity\":15,\"low_stock_threshold\":10,\"barcode\":\"4800016012358\",\"expiry\":\"2025-10-25\",\"description\":\"Instant coffee mix with creamy and rich taste, produced by Universal Robina Corporation.\",\"status\":\"active\",\"created_at\":\"2025-10-24 10:53:13\",\"updated_at\":\"2025-10-27 11:26:49\",\"last_updated_by\":7,\"name_encrypted\":null,\"name_iv\":null,\"name_tag\":null,\"sku_encrypted\":null,\"sku_iv\":null,\"sku_tag\":null,\"barcode_encrypted\":null,\"barcode_iv\":null,\"barcode_tag\":null,\"description_encrypted\":null,\"description_iv\":null,\"description_tag\":null,\"category_name\":\"Beverages\"}', 1, 6, '2025-10-29 13:40:30', '2025-10-29 13:40:33');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `sku` varchar(64) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `low_stock_threshold` int(11) DEFAULT 10,
  `barcode` varchar(100) DEFAULT NULL,
  `expiry` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_updated_by` int(11) DEFAULT NULL,
  `name_encrypted` text DEFAULT NULL,
  `name_iv` text DEFAULT NULL,
  `name_tag` text DEFAULT NULL,
  `sku_encrypted` text DEFAULT NULL,
  `sku_iv` text DEFAULT NULL,
  `sku_tag` text DEFAULT NULL,
  `barcode_encrypted` text DEFAULT NULL,
  `barcode_iv` text DEFAULT NULL,
  `barcode_tag` text DEFAULT NULL,
  `description_encrypted` text DEFAULT NULL,
  `description_iv` text DEFAULT NULL,
  `description_tag` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `sku`, `category_id`, `price`, `stock_quantity`, `low_stock_threshold`, `barcode`, `expiry`, `description`, `status`, `created_at`, `updated_at`, `last_updated_by`, `name_encrypted`, `name_iv`, `name_tag`, `sku_encrypted`, `sku_iv`, `sku_tag`, `barcode_encrypted`, `barcode_iv`, `barcode_tag`, `description_encrypted`, `description_iv`, `description_tag`) VALUES
(12, 'HP Printer', NULL, NULL, 4999.99, 5, 10, 'HP001', NULL, NULL, 'active', '2025-10-13 23:24:35', '2025-10-13 23:24:35', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(13, 'Canon Camera', NULL, NULL, 15999.99, 3, 10, 'CAN001', NULL, NULL, 'active', '2025-10-13 23:24:35', '2025-10-13 23:24:35', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(14, 'Apple iPhone', NULL, NULL, 45999.99, 12, 10, 'APL001', '2025-09-30', NULL, 'active', '2025-10-13 23:24:35', '2025-10-29 09:42:21', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(15, 'Samsung TV', NULL, NULL, 28999.99, 0, 10, 'SAM001', NULL, NULL, 'active', '2025-10-13 23:24:35', '2025-10-13 23:24:35', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(16, 'Dell Desktop', NULL, NULL, 35999.99, 8, 10, 'DEL001', NULL, NULL, 'active', '2025-10-13 23:24:35', '2025-10-13 23:24:35', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(17, 'Logitech Webcam', NULL, NULL, 2999.99, 20, 10, 'LOG001', NULL, NULL, 'active', '2025-10-13 23:24:35', '2025-10-13 23:24:35', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(18, 'Microsoft Office', NULL, NULL, 5999.99, 0, 10, 'MIC001', NULL, NULL, 'active', '2025-10-13 23:24:35', '2025-10-13 23:24:35', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(19, 'Gaming Chair', NULL, NULL, 12999.99, 6, 10, 'GAM001', NULL, NULL, 'active', '2025-10-13 23:24:35', '2025-10-13 23:24:35', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(20, 'Power Bank', NULL, NULL, 1299.99, 5, 10, 'POW001', NULL, NULL, 'active', '2025-10-13 23:24:35', '2025-10-22 12:49:30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(21, 'Bluetooth Speaker', NULL, NULL, 3999.99, 0, 10, 'BLU001', NULL, NULL, 'active', '2025-10-13 23:24:35', '2025-10-17 09:34:34', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(22, 'Car Air Freshener', NULL, NULL, 8.99, 58, 10, 'CAR001', NULL, NULL, 'active', '2025-10-13 23:24:35', '2025-10-29 09:08:50', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(23, 'Energy Drink', NULL, NULL, 2.99, 199, 10, 'ENG001', NULL, NULL, 'active', '2025-10-13 23:24:35', '2025-10-13 23:24:35', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(24, 'Garden Hose', NULL, NULL, 45.99, 9, 10, 'GAR001', NULL, NULL, 'active', '2025-10-13 23:24:35', '2025-10-23 15:13:25', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(25, 'Laptop Charger', NULL, NULL, 29.99, 39, 10, 'LAP002', NULL, NULL, 'active', '2025-10-13 23:24:35', '2025-10-23 14:50:46', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(26, 'USB Flash Drive', NULL, NULL, 200.00, 0, 10, 'USB002', NULL, NULL, 'active', '2025-10-13 23:24:35', '2025-10-13 23:24:35', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(27, 'T-Shirt Large', NULL, NULL, 15.99, 75, 10, 'TSH002', NULL, NULL, 'active', '2025-10-13 23:24:35', '2025-10-13 23:24:35', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(28, 'Coffee Beans 1kg', NULL, NULL, 299.99, 30, 10, 'COF001', '2025-11-08', NULL, 'active', '2025-10-13 23:24:35', '2025-10-29 09:40:03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(29, 'Wireless Earbuds', NULL, NULL, 1599.99, 3, 10, 'EAR001', NULL, NULL, 'active', '2025-10-13 23:24:35', '2025-10-22 12:24:17', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(30, 'Phone Case', NULL, NULL, 199.99, 99, 10, 'PHO001', NULL, NULL, 'active', '2025-10-13 23:24:35', '2025-10-18 16:07:06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(31, 'Notebook A4', NULL, NULL, 89.99, 200, 10, 'NOT001', NULL, NULL, 'active', '2025-10-13 23:24:35', '2025-10-13 23:24:35', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(58, 'Chicken Ramen', 'NOOD001', 8, 120.00, 50, 10, '1234567890123', NULL, 'Spicy chicken ramen bowl', 'active', '2025-10-08 11:13:38', '2025-10-08 11:13:38', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(59, 'Beef Udon', 'NOOD002', 8, 150.00, 9, 10, '1234567890124', '2025-10-28', 'Japanese beef udon noodles', 'active', '2025-10-08 11:13:38', '2025-10-26 02:12:39', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(60, 'Seafood Pasta', 'NOOD003', 8, 180.00, 9, 10, '1234567890125', NULL, 'Creamy seafood pasta', 'active', '2025-10-08 11:13:38', '2025-10-23 09:20:43', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(61, 'Pancit Canton', 'NOOD004', 8, 100.00, 53, 15, '1234567890126', NULL, 'Filipino stir-fried noodles', 'active', '2025-10-08 11:13:38', '2025-10-17 02:31:34', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(62, 'Iced Tea', 'BEV001', 9, 35.00, 100, 20, '2234567890123', NULL, 'Refreshing iced tea', 'active', '2025-10-08 11:13:38', '2025-10-08 11:13:38', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(63, 'Cola', 'BEV002', 9, 30.00, 115, 20, '2234567890124', '2025-11-07', 'Carbonated soft drink', 'active', '2025-10-08 11:13:38', '2025-10-29 09:40:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(64, 'Orange Juice', 'BEV003', 9, 45.00, 80, 15, '2234567890125', NULL, 'Fresh orange juice', 'active', '2025-10-08 11:13:38', '2025-10-08 11:13:38', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(65, 'Bottled Water', 'BEV004', 9, 20.00, 149, 30, '2234567890126', NULL, 'Mineral water 500ml', 'active', '2025-10-08 11:13:38', '2025-10-17 02:31:34', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(66, 'Coffee', 'BEV005', 9, 50.00, 70, 15, '2234567890127', NULL, 'Hot or iced coffee', 'active', '2025-10-08 11:13:38', '2025-10-08 11:13:38', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(67, 'Spring Rolls', 'SNACK001', 10, 50.00, 60, 10, '3234567890123', NULL, 'Vegetable spring rolls (3pcs)', 'active', '2025-10-08 11:13:38', '2025-10-08 11:13:38', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(68, 'French Fries', 'SNACK002', 10, 60.00, 47, 10, '3234567890124', NULL, 'Crispy french fries', 'active', '2025-10-08 11:13:38', '2025-10-23 14:47:01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(69, 'Nachos', 'SNACK003', 10, 80.00, 40, 10, '3234567890125', NULL, 'Nachos with cheese dip', 'active', '2025-10-08 11:13:38', '2025-10-08 11:13:38', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(70, 'Fried Rice', 'RICE001', 11, 90.00, 65, 10, '4234567890123', NULL, 'Classic fried rice', 'active', '2025-10-08 11:13:38', '2025-10-20 14:49:18', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(71, 'Chicken Adobo Rice', 'RICE002', 11, 130.00, 47, 10, '4234567890124', NULL, 'Filipino chicken adobo with rice', 'active', '2025-10-08 11:13:38', '2025-10-21 11:28:23', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(72, 'Pork Sisig Rice', 'RICE003', 11, 140.00, 45, 10, '4234567890125', NULL, 'Sizzling sisig with rice', 'active', '2025-10-08 11:13:38', '2025-10-08 11:13:38', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(73, 'Halo-Halo', 'DESS001', 12, 85.00, 30, 5, '5234567890123', NULL, 'Filipino mixed dessert', 'active', '2025-10-08 11:13:38', '2025-10-08 11:13:38', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(74, 'Ice Cream', 'DESS002', 12, 45.00, 38, 10, '5234567890124', '2025-11-05', 'Vanilla ice cream scoop', 'active', '2025-10-08 11:13:38', '2025-10-24 09:11:19', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(75, 'Leche Flan', 'DESS003', 12, 60.00, 9, 10, '5234567890125', NULL, 'Caramel custard dessert', 'active', '2025-10-08 11:13:38', '2025-10-23 14:55:40', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(76, 'Tapsilog', 'BFAST001', 13, 120.00, 40, 8, '6234567890123', NULL, 'Beef tapa, egg and rice', 'active', '2025-10-08 11:13:38', '2025-10-08 11:13:38', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(77, 'Longsilog', 'BFAST002', 13, 110.00, 57, 8, '6234567890124', '2025-10-24', 'Longganisa, egg and rice', 'active', '2025-10-08 11:13:38', '2025-10-24 03:35:02', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(78, 'flip', 'dhh799', 9, 24.00, 45, 5, '245677889', '2025-10-22', NULL, 'active', '2025-10-22 10:43:38', '2025-10-22 10:44:26', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(79, 'FUDGEE Bar', 'sku1233', 10, 8.00, 10, 10, '573578864', '2025-12-23', NULL, 'active', '2025-10-23 09:22:15', '2025-10-24 03:48:40', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(80, 'Great Taste Premium Classic 3 in 1 Coffee', 'GTPC-3IN1-001', 9, 3.00, 15, 10, '4800016012358', '2025-10-25', 'Instant coffee mix with creamy and rich taste, produced by Universal Robina Corporation.', 'active', '2025-10-24 02:53:13', '2025-10-27 03:26:49', 7, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_expiries`
--

CREATE TABLE `product_expiries` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `expiry_date` date NOT NULL,
  `quantity` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_expiries`
--

INSERT INTO `product_expiries` (`id`, `product_id`, `expiry_date`, `quantity`, `created_at`) VALUES
(1, 14, '2025-10-30', NULL, '2025-10-29 17:44:19'),
(2, 14, '2025-09-30', NULL, '2025-10-29 17:44:38'),
(3, 28, '2025-11-08', NULL, '2025-10-29 17:44:38'),
(4, 59, '2025-10-28', NULL, '2025-10-29 17:44:38'),
(5, 63, '2025-11-07', NULL, '2025-10-29 17:44:38'),
(6, 74, '2025-11-05', NULL, '2025-10-29 17:44:38'),
(7, 77, '2025-10-24', NULL, '2025-10-29 17:44:38'),
(8, 78, '2025-10-22', NULL, '2025-10-29 17:44:38'),
(9, 79, '2025-12-23', NULL, '2025-10-29 17:44:38'),
(10, 80, '2025-10-25', NULL, '2025-10-29 17:44:38'),
(17, 14, '2025-10-28', NULL, '2025-10-29 17:45:02'),
(18, 80, '2025-11-03', NULL, '2025-10-29 17:46:14'),
(19, 79, '2025-12-04', NULL, '2025-10-29 18:03:31');

-- --------------------------------------------------------

--
-- Table structure for table `restore_logs`
--

CREATE TABLE `restore_logs` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `restored_at` datetime NOT NULL,
  `restored_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `restore_logs`
--

INSERT INTO `restore_logs` (`id`, `filename`, `restored_at`, `restored_by`) VALUES
(1, 'backup_2025-10-24_21-43-11.sql', '2025-10-24 21:43:19', NULL),
(2, 'backup_2025-10-24_22-30-39.sql.enc', '2025-10-24 22:30:46', NULL),
(3, 'backup_2025-10-24_22-34-00.sql.enc', '2025-10-25 22:28:34', NULL),
(4, 'backup_2025-10-25_22-45-42.sql.enc', '2025-10-25 22:48:44', NULL),
(5, 'backup_2025-10-28_06-24-34.sql.enc', '2025-10-29 09:52:13', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `shifts`
--

CREATE TABLE `shifts` (
  `id` int(11) NOT NULL,
  `shift_name` varchar(100) NOT NULL,
  `shift_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `max_employees` int(11) DEFAULT 10,
  `status` enum('scheduled','in-progress','completed','cancelled') DEFAULT 'scheduled',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shifts`
--

INSERT INTO `shifts` (`id`, `shift_name`, `shift_date`, `start_time`, `end_time`, `description`, `location`, `max_employees`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Morning Shift', '2025-10-14', '08:00:00', '16:00:00', 'Regular morning shift', 'Main Store', 5, 'scheduled', 1, '2025-10-13 23:04:51', '2025-10-13 23:04:51'),
(2, 'Evening Shift', '2025-10-14', '16:00:00', '00:00:00', 'Regular evening shift', 'Main Store', 4, 'scheduled', 1, '2025-10-13 23:04:51', '2025-10-13 23:04:51'),
(3, 'Weekend Day Shift', '2025-10-16', '09:00:00', '17:00:00', 'Weekend coverage', 'Main Store', 6, 'scheduled', 1, '2025-10-13 23:04:51', '2025-10-13 23:04:51'),
(4, 'Night Shift', '2025-10-14', '00:00:00', '08:00:00', 'Overnight shift', 'Main Store', 3, 'scheduled', 1, '2025-10-13 23:04:51', '2025-10-13 23:04:51'),
(5, 'morning to night', '2025-10-19', '07:50:00', '20:50:00', '', '', 10, 'scheduled', 4, '2025-10-18 23:51:23', '2025-10-18 23:51:23');

-- --------------------------------------------------------

--
-- Table structure for table `shift_assignments`
--

CREATE TABLE `shift_assignments` (
  `id` int(11) NOT NULL,
  `shift_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('supervisor','regular') DEFAULT 'regular',
  `status` enum('assigned','confirmed','declined','completed','no-show') DEFAULT 'assigned',
  `notes` text DEFAULT NULL,
  `assigned_by` int(11) NOT NULL,
  `assigned_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shift_assignments`
--

INSERT INTO `shift_assignments` (`id`, `shift_id`, `user_id`, `role`, `status`, `notes`, `assigned_by`, `assigned_at`, `updated_at`) VALUES
(1, 3, 1, 'regular', 'assigned', NULL, 4, '2025-10-18 16:54:03', '2025-10-18 16:54:03'),
(2, 3, 6, 'regular', 'assigned', NULL, 4, '2025-10-18 16:54:03', '2025-10-18 16:54:03'),
(3, 3, 4, 'regular', 'assigned', NULL, 4, '2025-10-18 16:54:03', '2025-10-18 16:54:03'),
(5, 3, 2, 'regular', 'assigned', NULL, 4, '2025-10-18 16:54:03', '2025-10-18 16:54:03'),
(6, 5, 1, 'regular', 'assigned', NULL, 4, '2025-10-18 23:51:39', '2025-10-18 23:51:39'),
(7, 5, 6, 'regular', 'confirmed', NULL, 4, '2025-10-18 23:51:39', '2025-10-19 00:03:18'),
(8, 5, 4, 'regular', 'confirmed', NULL, 4, '2025-10-18 23:51:39', '2025-10-19 05:23:28'),
(10, 5, 2, 'regular', 'assigned', NULL, 4, '2025-10-18 23:51:39', '2025-10-18 23:51:39');

-- --------------------------------------------------------

--
-- Table structure for table `store_settings`
--

CREATE TABLE `store_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` varchar(50) DEFAULT 'text' COMMENT 'text, number, boolean, image',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `store_settings`
--

INSERT INTO `store_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `created_at`, `updated_at`) VALUES
(1, 'store_name', 'PointShift POS', 'text', '2025-10-13 23:05:50', '2025-10-13 23:05:50'),
(2, 'store_branch', 'Main Branch', 'text', '2025-10-13 23:05:50', '2025-10-13 23:05:50'),
(3, 'store_address', '123 Main Street, City, Country', 'text', '2025-10-13 23:05:50', '2025-10-13 23:05:50'),
(4, 'store_phone', '+1234567890', 'text', '2025-10-13 23:05:50', '2025-10-13 23:05:50'),
(5, 'store_email', 'dummyacc45f@gmail.com', 'text', '2025-10-13 23:05:50', '2025-10-29 02:52:32'),
(6, 'business_hours_open', '08:00', 'text', '2025-10-13 23:05:50', '2025-10-13 23:05:50'),
(7, 'business_hours_close', '20:00', 'text', '2025-10-13 23:05:50', '2025-10-13 23:05:50'),
(8, 'business_days', 'Monday to Sunday', 'text', '2025-10-13 23:05:50', '2025-10-13 23:05:50'),
(9, 'store_logo', '', 'image', '2025-10-13 23:05:50', '2025-10-13 23:05:50'),
(10, 'receipt_header', 'Thank you for your purchase!', 'text', '2025-10-13 23:05:50', '2025-10-13 23:05:50'),
(11, 'receipt_footer', 'Please come again!', 'text', '2025-10-13 23:05:50', '2025-10-13 23:05:50'),
(12, 'tax_rate', '12', 'number', '2025-10-13 23:05:50', '2025-10-13 23:05:50'),
(13, 'currency_symbol', '₱', 'text', '2025-10-13 23:05:50', '2025-10-26 12:36:58'),
(14, 'receipt_show_logo', '1', 'boolean', '2025-10-13 23:05:50', '2025-10-13 23:05:50'),
(15, 'receipt_show_cashier', '1', 'boolean', '2025-10-13 23:05:50', '2025-10-13 23:05:50'),
(58, 'smtp_host', 'smtp.gmail.com', 'text', '2025-10-25 14:29:12', '2025-10-25 14:29:12'),
(59, 'smtp_user', 'aone79381@gmail.com', 'text', '2025-10-25 14:29:12', '2025-10-25 14:29:12'),
(60, 'smtp_pass', 'ufnm fryo odng ocpt', 'text', '2025-10-25 14:29:12', '2025-10-25 14:29:12'),
(61, 'smtp_port', '587', 'text', '2025-10-25 14:29:12', '2025-10-25 14:29:12'),
(62, 'smtp_secure', 'tls', 'text', '2025-10-25 14:29:12', '2025-10-25 14:29:12'),
(63, 'admin_notification_email', 'dummyacc45f@gmail.com', 'text', '2025-10-25 14:31:57', '2025-10-25 14:31:57');

-- --------------------------------------------------------

--
-- Table structure for table `system_notifications`
--

CREATE TABLE `system_notifications` (
  `id` int(11) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'info',
  `status` varchar(20) DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) DEFAULT 'User',
  `last_name` varchar(50) DEFAULT '',
  `role` enum('admin','staff','cashier') NOT NULL DEFAULT 'staff',
  `status` enum('active','inactive','pending') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_activity` timestamp NULL DEFAULT NULL,
  `email_encrypted` text DEFAULT NULL,
  `email_iv` text DEFAULT NULL,
  `email_tag` text DEFAULT NULL,
  `phone_encrypted` text DEFAULT NULL,
  `phone_iv` text DEFAULT NULL,
  `phone_tag` text DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `email_verification_code` varchar(128) DEFAULT NULL,
  `email_verification_expires_at` datetime DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `first_name`, `last_name`, `role`, `status`, `created_at`, `updated_at`, `last_activity`, `email_encrypted`, `email_iv`, `email_tag`, `phone_encrypted`, `phone_iv`, `phone_tag`, `email_verified`, `email_verification_code`, `email_verification_expires_at`, `email_verified_at`) VALUES
(1, 'admin', 'admin@pointshift.com', 'admin1234', 'Admin', 'User', 'admin', 'active', '2025-09-29 22:35:36', '2025-10-26 02:20:06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(7, 'cuartorussel14', 'cuartorussel14@gmail.com', '$2y$10$lRmpC3eUCd7hQ5iEe9H3f.EUcexVR93I3BNdzEPFVokCMM3/KiUgG', 'russel', 'cuarto', 'staff', 'active', '2025-10-25 14:29:44', '2025-10-29 15:03:18', '2025-10-29 15:03:18', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL),
(8, 'adminmo', 'yoyeh99@gmail.com', '$2y$10$ZdKC2DqBwlqU9J8/.gz4kurJFCW6MW1/9Qn6ls/tGC2cZHqzoqU3y', 'admin', 'mo', 'admin', 'active', '2025-10-26 02:28:39', '2025-10-26 02:31:55', NULL, 'fv9EksNNI0/huAUyNOJY+f4=', 'L8iuOx+RnlbkmxUO', '7ARPAp6ejC6N1ZAbizsrSw==', NULL, NULL, NULL, 0, NULL, NULL, NULL),
(9, 'adminko', 'aone79381@gmail.com', '$2y$10$7a42w3ZUW/UwEvx49NvtduzcLnv.oMMChewGOY94dr2UhyshBYQku', 'admin', 'ko', 'admin', 'active', '2025-10-26 02:31:15', '2025-10-29 15:09:32', '2025-10-29 15:09:32', 'kuQvaUp3xaBJtt9p3FwvWTiOYA==', 'XifAGffMfJwL0MPd', 'xNbQv3mcMmZ+sEFRaxa7Kg==', NULL, NULL, NULL, 0, NULL, NULL, NULL),
(19, 'dummyacc45f', 'dummyacc45f@gmail.com', '$2y$10$iblxsi55W3WwI3yKxspXRerRJWyfWZOOlPRIGInhUNjGxYQgoymna', 'cashier', 'ako', 'staff', 'active', '2025-10-29 15:08:40', '2025-10-29 15:12:48', '2025-10-29 15:12:48', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `backups`
--
ALTER TABLE `backups`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `backup_logs`
--
ALTER TABLE `backup_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `backup_retention`
--
ALTER TABLE `backup_retention`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `inventory_reports`
--
ALTER TABLE `inventory_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `fk_inventory_reports_user` (`user_id`),
  ADD KEY `idx_inventory_reports_date` (`date`),
  ADD KEY `idx_inventory_reports_created` (`created_at`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `recipient_id` (`recipient_id`),
  ADD KEY `parent_message_id` (`parent_message_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_user_id` (`user_id`),
  ADD KEY `idx_notifications_status` (`status`),
  ADD KEY `idx_notifications_created_at` (`created_at`),
  ADD KEY `idx_notifications_status_shown` (`status`,`shown`),
  ADD KEY `idx_notifications_user` (`user_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `payment_qrcodes`
--
ALTER TABLE `payment_qrcodes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payment_method` (`payment_method`),
  ADD KEY `is_active` (`is_active`);

--
-- Indexes for table `pos_messages`
--
ALTER TABLE `pos_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_msg_id` (`msg_id`),
  ADD KEY `idx_processed` (`processed`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `fk_products_last_updated_by` (`last_updated_by`);

--
-- Indexes for table `product_expiries`
--
ALTER TABLE `product_expiries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `restore_logs`
--
ALTER TABLE `restore_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `restored_by` (`restored_by`);

--
-- Indexes for table `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `shift_date` (`shift_date`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `shift_assignments`
--
ALTER TABLE `shift_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_shift_user` (`shift_id`,`user_id`),
  ADD KEY `shift_id` (`shift_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `assigned_by` (`assigned_by`);

--
-- Indexes for table `store_settings`
--
ALTER TABLE `store_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `system_notifications`
--
ALTER TABLE `system_notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `backups`
--
ALTER TABLE `backups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `backup_logs`
--
ALTER TABLE `backup_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `backup_retention`
--
ALTER TABLE `backup_retention`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_reports`
--
ALTER TABLE `inventory_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=524;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `payment_qrcodes`
--
ALTER TABLE `payment_qrcodes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `pos_messages`
--
ALTER TABLE `pos_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `product_expiries`
--
ALTER TABLE `product_expiries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `restore_logs`
--
ALTER TABLE `restore_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `shifts`
--
ALTER TABLE `shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `shift_assignments`
--
ALTER TABLE `shift_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `store_settings`
--
ALTER TABLE `store_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- AUTO_INCREMENT for table `system_notifications`
--
ALTER TABLE `system_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=466;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD CONSTRAINT `fk_inventory_logs_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inventory_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `inventory_reports`
--
ALTER TABLE `inventory_reports`
  ADD CONSTRAINT `fk_inventory_reports_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_reports_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`parent_message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_last_updated_by` FOREIGN KEY (`last_updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `product_expiries`
--
ALTER TABLE `product_expiries`
  ADD CONSTRAINT `product_expiries_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `restore_logs`
--
ALTER TABLE `restore_logs`
  ADD CONSTRAINT `restore_logs_ibfk_1` FOREIGN KEY (`restored_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `shifts`
--
ALTER TABLE `shifts`
  ADD CONSTRAINT `shifts_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `shift_assignments`
--
ALTER TABLE `shift_assignments`
  ADD CONSTRAINT `shift_assignments_ibfk_1` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shift_assignments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shift_assignments_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
