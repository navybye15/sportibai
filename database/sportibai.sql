-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 27, 2026 at 02:17 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.3.35

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sportibai`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `name`, `is_active`) VALUES
(1, 'Ball Sports', 1),
(2, 'Fitness', 1),
(3, 'Apparel', 1),
(4, 'Accessories', 1),
(5, 'Beverages', 0),
(6, 'Footwear', 1);

-- --------------------------------------------------------

--
-- Table structure for table `customer`
--

CREATE TABLE `customer` (
  `customer_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `address` varchar(255) NOT NULL,
  `province` varchar(100) NOT NULL DEFAULT '',
  `postal_code` varchar(10) NOT NULL DEFAULT '',
  `landmark` text NOT NULL DEFAULT '',
  `phone` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer`
--

INSERT INTO `customer` (`customer_id`, `user_id`, `first_name`, `last_name`, `address`, `province`, `postal_code`, `landmark`, `phone`) VALUES
(1, 1, 'Ivan', 'Cap', '51 TAGUIG simundac compound t9', 'Metro manila', '1881', '7 eleven tup taguig', '09932482922'),
(2, 3, 'Steph', 'Teluk', '23213 ako nalng sana street. t9 compound', 'Metro manila', '1831', 'TUP Taguig', '09931058935'),
(3, 4, 'Kyla', 'Capanayy', '51 Manuel L. Quezon', 'Metro Manila', '1770', '', '09193812391'),
(4, 9, 'jp', 'chan', 'langka 8 st north signal village', 'taguig city', '1630', 'near future champs', '09944804722'),
(5, 10, 'Navi mapagmaha;', 'Capanay', '51 lower bicutan street taguig city', 'metro manila', '1771', 'tup taguig', '09931045743'),
(6, 11, 'dating', 'tayo', 'datinng tayo street muntinlupa city', 'metro manila', '1232', 'tup taguig', '09123219312');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `expense_id` int(11) NOT NULL,
  `expense_date` date NOT NULL,
  `description` varchar(150) NOT NULL,
  `category` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`expense_id`, `expense_date`, `description`, `category`, `amount`) VALUES
(1, '2026-10-06', 'Volleyball ball', 'Ball', 45000.00);

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `item_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `brand` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock` int(11) NOT NULL DEFAULT 0,
  `image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`item_id`, `category_id`, `name`, `brand`, `description`, `price`, `cost_price`, `stock`, `image`, `is_active`) VALUES
(1, 1, 'Basketball Size 7', 'Spalding', 'Outdoor rubber basketball', 1200.00, 0.00, 20, NULL, 0),
(2, 1, 'Badminton Racket', 'Yonex', 'Lightweight beginner racket', 1500.00, 0.00, 15, NULL, 0),
(3, 2, 'Yoga Mat 6mm', 'Generic', 'Non-slip yoga mat', 450.00, 0.00, 30, NULL, 0),
(4, 2, 'Dumbbell Set 10kg', 'Generic', 'Adjustable dumbbell pair', 900.00, 0.00, 12, NULL, 0),
(5, 3, 'Dri-Fit Jersey', 'Nike', 'Breathable sports jersey', 800.00, 0.00, 25, NULL, 0),
(6, 6, 'Running Shoes', 'Adidas', 'Lightweight running shoes', 3200.00, 0.00, 21, NULL, 0),
(7, 4, 'Water Bottle 1L', 'Generic', 'BPA-free bottle', 250.00, 0.00, 40, NULL, 0),
(8, 4, 'Gym Duffel Bag', 'Puma', 'Medium gym bag', 1100.00, 0.00, 18, NULL, 0),
(9, 1, 'MIKASA V330W Volleyball', 'MIKASA', 'Volleyball ball', 4500.00, 0.00, 5, 'item_6ac43efe5f0113.32206362.jpg', 0),
(10, 4, 'MIKASA V200W Volleyball', 'MIKASA', 'Volleyball ball', 4500.00, 0.00, 0, 'item_6acc2951c14451.83666521.jpg', 0),
(11, 1, 'MIKASA V200W Volleyball', 'Mikasa', 'The Mikasa V200W is the official Size 5 indoor game ball approved by the FIVB (International Volleyball Federation).Key Specifications & Features• Design: 18-panel aerodynamic design in a signature blue and yellow colorway.• Surface Technology: Features a Double-Dimpled microfiber composite cover that stabilizes the flight path and provides cushioned ball control.• Anti-Sweat: Built with Nano Balloon Silica anti-sweat functionality to keep the surface from becoming slippery during intense matches.• Size & Weight: Official Size 5 (diameter of 65–67 cm, weight of 260–280 g).• Usage: Standard official game ball used in major international competitions, including the Olympic Games, FIVB World Championships, and the Volleyball Nations League (VNL).', 4500.00, 3300.00, 24, 'item_6accaec8f33738.51452293.jpg', 1),
(12, 1, 'MIKASA V300W Volleyball', 'Mikasa', 'The Mikasa V300W is an official Size 5 indoor volleyball approved by the FIVB (Fédération Internationale de Volleyball).Key Specifications• Size & Weight: Official Size 5 (circumference: 65–67 cm, weight: 260–280 g).• Construction: Laminated, 18-panel aerodynamically balanced design.• Material: Super composite cover made from original microfiber and PU (co-developed with Kuraray).• Color: Yellow and blue.Key Features• Single Dimple Technology: Features a textured single-dimpled microfibre surface that minimizes air resistance, stabilizes flight paths, and maximizes grip.• Anti-Sweat Functionality: Built with sweat-absorbing materials (Nano Balloon Silica) to maintain a dry, controllable surface during long rallies.• Soft Touch: Engineered to provide a softer, more pleasant touch compared to traditional leather balls.• V200W Comparison: While the flagship V200W uses a double-dimple structure, the V300W uses a single-dimple structure, making it a popular choice for high-level training, club play, and official competitions.', 5500.00, 4300.00, 22, 'item_6accaf00340dd6.28282897.jpg', 1),
(13, 4, 'MIZUNO Knee pads', 'Mizuno', 'Mizuno is widely regarded as one of the top brands for volleyball knee pads, providing specialized protection, sweat management, and flexibility for high-impact indoor sports.Depending on whether you need low-profile mobility, maximum cushioning, or standard club-level protection, you can explore the top models below.• Features: Built with VS-1 shock-absorbing technology that resists compression set and an Intercool ventilation system to reduce heat and moisture.• Fit & Style: Employs a standard sleeve cut with full patella, lateral, and medial coverage.• Best For: Advanced players seeking optimal breathability and elite impact dampening.', 450.00, 349.00, 24, 'item_6accb07c994810.25917629.jpg', 1),
(14, 4, 'MIZUNO Elbow pads', 'Mizuno', 'Mizuno elbow pads is heavily favored by indoor volleyball players to protect against low-level floor impacts, burns, and scrapes during floor defense like digging and diving. They typically feature impact-absorbing EVA foam padding combined with flexible, breathable fabrics to allow full freedom of arm movement.• Design: Short-style guard (approx. 13 cm in total length) focusing purely on the elbow joint.• Padding: Features a 3-part contoured EVA foam structure for flexible, multi-angle side protection without stiffness.• Fit: Typically sold as unisex \"Free Size\" fitting elbow circumferences between 22–28 cm.', 350.00, 248.99, 23, 'item_6accb10a696c67.39211921.jpg', 1),
(15, 3, 'NIKE Stride Aero Fit', 'NIke', 'Nike Stride Aero-FIT is a premium lineup of high-performance men\'s running apparel powered by Nike\'s innovative Aero-FIT cooling technology. Released globally as an upgrade to the standard Stride line, this collection focuses on engineered, full-garment airflow to prevent heat buildup before you even start sweating.• High-Crimp Yarn: Engineered at the stitch level to naturally build space between the fabric and your skin, preventing the garment from clinging.• Elliptical Mesh Zones: High-heat zones feature mapped, visible mesh windows that maximize ventilation as you move.• Sustainability: Aero-FIT serves as Nike\'s first elite performance apparel line crafted from 100% respun textile waste.', 1099.00, 848.99, 13, 'item_6accb174a1e0c7.35245775.jpg', 1),
(16, 3, 'NIKE Running T-shirt', 'Nike', 'Nike Running T-shirt is a premium lineup of high-performance men\'s running apparel powered by Nike\'s innovative Aero-FIT cooling technology. Released globally as an upgrade to the standard Stride line, this collection focuses on engineered, full-garment airflow to prevent heat buildup before you even start sweating.• High-Crimp Yarn: Engineered at the stitch level to naturally build space between the fabric and your skin, preventing the garment from clinging.• Elliptical Mesh Zones: High-heat zones feature mapped, visible mesh windows that maximize ventilation as you move.• Sustainability: Aero-FIT serves as Nike\'s first elite performance apparel line crafted from 100% respun textile waste.', 1599.00, 1099.00, 15, 'item_6accb1b421c0b2.82190226.jpg', 1),
(17, 3, 'NIKE Aeroswift Dri-fit', 'Nike', 'AI Mode Conversation: NIKE Aeroswift Dri-fitNIKE Aeroswift Dri-fitThe Nike AeroSwift Dri-FIT ADV collection represents Nike\'s pinnacle racing and elite performance apparel line, meticulously engineered for maximum speed, distraction-free comfort, and advanced moisture management. Combining high-performance microfiber constructions with data-driven ventilation holes placed in high-heat zones, this collection helps runners stay cool, dry, and exceptionally light on race day.', 2099.00, 1699.00, 12, 'item_6accb1e3686e04.46171000.jpg', 1),
(18, 3, 'NIKE Stride T-shirt', 'Nike', 'NIKE Stride T-shirtThe NIKE Stride T-shirt collection represents Nike\'s pinnacle racing and elite performance apparel line, meticulously engineered for maximum speed, distraction-free comfort, and advanced moisture management. Combining high-performance microfiber constructions with data-driven ventilation holes placed in high-heat zones, this collection helps runners stay cool, dry, and exceptionally light on race day.', 899.00, 600.00, 41, 'item_6accb228556ec6.83471309.jpg', 1),
(19, 3, 'shoes', 'Nike', '', 1299.00, 999.00, 5, '', 0),
(20, 1, 'Basketball Spalding TF-150', 'Spalding', 'The Spalding Varsity TF-150 is an all-surface, durable rubber basketball engineered for outdoor playgrounds, concrete courts, and indoor training. You can view official product details on the Spalding Philippines Store or browse specs on the Spalding Australia Portal.', 999.00, 699.00, 12, 'item_6adff2d161fae6.77513626.jpg', 1);

-- --------------------------------------------------------

--
-- Table structure for table `item_images`
--

CREATE TABLE `item_images` (
  `image_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `item_images`
--

INSERT INTO `item_images` (`image_id`, `item_id`, `image_path`, `sort_order`, `created_at`) VALUES
(4, 18, 'item_gallery_6acf8a65d8fce8.90078783.jpg', 0, '2026-10-14 13:57:57'),
(5, 18, 'item_gallery_6acf8a65da4663.51321045.png', 1, '2026-10-14 13:57:57'),
(7, 18, 'item_gallery_6acf8a65dca3c5.24706518.jpg', 3, '2026-10-14 13:57:57'),
(8, 20, 'item_gallery_6adff2d16325a4.68002130.jpg', 0, '2026-10-27 00:39:45'),
(9, 20, 'item_gallery_6adff2d16401b2.74436001.jpg', 1, '2026-10-27 00:39:45');

-- --------------------------------------------------------

--
-- Table structure for table `item_sizes`
--

CREATE TABLE `item_sizes` (
  `size_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `size_label` varchar(30) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `item_sizes`
--

INSERT INTO `item_sizes` (`size_id`, `item_id`, `size_label`, `stock`, `created_at`) VALUES
(1, 5, 'XS', 5, '2026-10-17 06:29:17'),
(2, 5, 'S', 4, '2026-10-17 06:29:17'),
(3, 5, 'M', 4, '2026-10-17 06:29:17'),
(4, 5, 'L', 4, '2026-10-17 06:29:17'),
(5, 5, 'XL', 4, '2026-10-17 06:29:17'),
(6, 5, '2XL', 4, '2026-10-17 06:29:17'),
(13, 15, 'XS', 3, '2026-10-17 06:29:17'),
(14, 15, 'S', 3, '2026-10-17 06:29:17'),
(15, 15, 'M', 2, '2026-10-17 06:29:17'),
(16, 15, 'L', 2, '2026-10-17 06:29:17'),
(17, 15, 'XL', 1, '2026-10-17 06:29:17'),
(18, 15, '2XL', 2, '2026-10-17 06:29:17'),
(19, 16, 'XS', 3, '2026-10-17 06:29:17'),
(20, 16, 'S', 3, '2026-10-17 06:29:17'),
(21, 16, 'M', 3, '2026-10-17 06:29:17'),
(22, 16, 'L', 2, '2026-10-17 06:29:17'),
(23, 16, 'XL', 2, '2026-10-17 06:29:17'),
(24, 16, '2XL', 2, '2026-10-17 06:29:17'),
(25, 17, 'XS', 2, '2026-10-17 06:29:17'),
(26, 17, 'S', 2, '2026-10-17 06:29:17'),
(27, 17, 'M', 2, '2026-10-17 06:29:17'),
(28, 17, 'L', 2, '2026-10-17 06:29:17'),
(29, 17, 'XL', 2, '2026-10-17 06:29:17'),
(30, 17, '2XL', 2, '2026-10-17 06:29:17'),
(31, 18, 'XS', 20, '2026-10-17 06:29:17'),
(32, 18, 'S', 6, '2026-10-17 06:29:17'),
(33, 18, 'M', 4, '2026-10-17 06:29:17'),
(34, 18, 'L', 4, '2026-10-17 06:29:17'),
(35, 18, 'XL', 3, '2026-10-17 06:29:17'),
(36, 18, '2XL', 4, '2026-10-17 06:29:17'),
(37, 6, 'EU 39', 2, '2026-10-17 06:54:07'),
(38, 6, 'EU 40', 3, '2026-10-17 06:54:07'),
(39, 6, 'EU 41', 4, '2026-10-17 06:54:07'),
(40, 6, 'EU 42', 5, '2026-10-17 06:54:07'),
(41, 6, 'EU 43', 4, '2026-10-17 06:54:07'),
(42, 6, 'EU 44', 2, '2026-10-17 06:54:07'),
(43, 6, 'EU 45', 1, '2026-10-17 06:54:07');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `reference_number` varchar(20) DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `order_type` varchar(10) NOT NULL,
  `delivery_address` varchar(255) DEFAULT NULL,
  `contact_number` varchar(20) NOT NULL,
  `snapshot_name` varchar(150) DEFAULT NULL,
  `payment_method` varchar(10) NOT NULL DEFAULT 'COD',
  `total` decimal(10,2) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Pending',
  `date_ordered` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `reference_number`, `customer_id`, `order_type`, `delivery_address`, `contact_number`, `snapshot_name`, `payment_method`, `total`, `status`, `date_ordered`, `completed_at`) VALUES
(1, 'SB-0000001', 1, 'pickup', NULL, '09932482922', NULL, 'COD', 2700.00, 'Cancelled', '2026-10-06 00:08:09', NULL),
(2, 'SB-0000002', 1, 'pickup', NULL, '09932482922', NULL, 'COD', 90000.00, 'Completed', '2026-10-06 00:36:33', '2026-10-06 08:36:48'),
(3, 'SB-0000003', 1, 'pickup', NULL, '09932482922', NULL, 'COD', 9000.00, 'Completed', '2026-10-12 00:28:27', '2026-10-12 15:26:56'),
(4, 'SB-0000004', 1, 'delivery', 'erw', '09932482922', NULL, 'COD', 1200.00, 'Cancelled', '2026-10-12 01:01:17', NULL),
(5, 'SB-0000005', 3, 'pickup', NULL, '09193812391', 'Kyla Capanayy', 'COD', 1200.00, 'Cancelled', '2026-10-12 09:04:19', NULL),
(6, 'SB-0000006', 2, 'delivery', '23213 ako nalng sana street. t9 compound, Cebu City, Metro manila, Postal: 1831\nNote: TUP Taguig', '0912371245', 'Steph Teluk', 'COD', 6300.00, 'Pending', '2026-10-12 10:13:09', NULL),
(7, 'SB-0000007', 1, 'delivery', '51 TAGUIG simundac compound t9, Cebu City, Metro manila, Postal: 1881\nNote: 7 eleven tup taguig', '09932482922', 'Ivan Cap', 'COD', 2998.00, 'Cancelled', '2026-10-12 10:56:39', NULL),
(8, 'SB-0000008', 1, 'delivery', '51 TAGUIG simundac compound t9, Cebu City, Metro manila, Postal: 1881\nNote: 7 eleven tup taguig', '09932482922', 'Ivan Cap', 'COD', 899.00, 'Completed', '2026-10-12 13:13:12', '2026-10-14 22:00:18'),
(9, 'SB-0000009', 2, 'delivery', '23213 ako nalng sana street. t9 compound, Cebu City, Metro manila, Postal: 1831\nNote: TUP Taguig', '09931058935', 'Steph Teluk', 'COD', 3548.00, 'Shipped', '2026-10-13 00:40:30', NULL),
(10, 'SB-0000010', 2, 'delivery', '23213 ako nalng sana street. t9 compound, Cebu City, Metro manila, Postal: 1831\nNote: TUP Taguig', '09931058935', 'Steph Teluk', 'COD', 5500.00, 'Completed', '2026-10-14 07:02:41', '2026-10-14 15:03:47'),
(11, 'SB-0000011', 1, 'delivery', '51 TAGUIG simundac compound t9, Cebu City, Metro manila, Postal: 1881\nNote: 7 eleven tup taguig', '09932482922', 'Ivan Cap', 'COD', 2998.00, 'Completed', '2026-10-17 06:14:32', '2026-10-17 14:16:19'),
(12, 'SB-0000012', 4, 'delivery', 'langka 8 st north signal village, Cebu City, taguig city, Postal: 1630\nNote: near future champs', '09944804722', 'jp chan', 'COD', 5500.00, 'Completed', '2026-10-19 00:31:59', '2026-10-19 08:35:58'),
(13, 'SB-0000013', 1, 'delivery', '51 TAGUIG simundac compound t9, Cebu City, Metro manila, Postal: 1881\nNote: 7 eleven tup taguig', '09932482922', 'Ivan Cap', 'COD', 1998.00, 'Pending', '2026-10-26 11:20:24', NULL),
(14, 'SB-0000014', 5, 'delivery', '51 lower bicutan street taguig city, Cebu City, metro manila, Postal: 1771\nNote: tup taguig', '09931045743', 'Navi mapagmaha; Capanay', 'COD', 5499.00, 'Pending', '2026-10-27 01:00:09', NULL),
(15, 'SB-0000015', 1, 'delivery', '51 TAGUIG simundac compound t9, Cebu City, Metro manila, Postal: 1881\nNote: 7 eleven tup taguig', '09932482922', 'Ivan Cap', 'COD', 2099.00, 'Pending', '2026-10-27 01:03:26', NULL),
(16, 'SB-0000016', 5, 'delivery', '51 lower bicutan street taguig city, Cebu City, metro manila, Postal: 1771\nNote: tup taguig', '09931045743', 'Navi mapagmaha; Capanay', 'COD', 1898.00, 'Pending', '2026-10-27 01:04:46', NULL),
(17, 'SB-0000017', 6, 'delivery', 'datinng tayo street muntinlupa city, Cebu City, metro manila, Postal: 1232\nNote: tup taguig', '09123219312', 'dating tayo', 'COD', 999.00, 'Pending', '2026-10-27 01:07:30', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `selected_size` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `item_id`, `selected_size`, `quantity`, `price`) VALUES
(1, 1, 2, NULL, 1, 1500.00),
(2, 1, 1, NULL, 1, 1200.00),
(3, 2, 10, NULL, 2, 45000.00),
(4, 3, 10, NULL, 2, 4500.00),
(5, 4, 1, NULL, 1, 1200.00),
(6, 5, 1, NULL, 1, 1200.00),
(7, 6, 13, NULL, 1, 450.00),
(8, 6, 14, NULL, 1, 350.00),
(9, 6, 12, NULL, 1, 5500.00),
(10, 7, 18, NULL, 1, 899.00),
(11, 7, 17, NULL, 1, 2099.00),
(12, 8, 18, NULL, 1, 899.00),
(13, 9, 17, NULL, 1, 2099.00),
(14, 9, 15, NULL, 1, 1099.00),
(15, 9, 14, NULL, 1, 350.00),
(16, 10, 12, NULL, 1, 5500.00),
(17, 11, 17, NULL, 1, 2099.00),
(18, 11, 18, NULL, 1, 899.00),
(19, 12, 12, NULL, 1, 5500.00),
(20, 13, 18, 'S', 1, 899.00),
(21, 13, 15, 'XL', 1, 1099.00),
(22, 14, 20, NULL, 1, 999.00),
(23, 14, 11, NULL, 1, 4500.00),
(24, 15, 17, 'XS', 1, 2099.00),
(25, 16, 20, NULL, 1, 999.00),
(26, 16, 18, 'XL', 1, 899.00),
(27, 17, 20, NULL, 1, 999.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_status_log`
--

CREATE TABLE `order_status_log` (
  `log_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `status` varchar(30) NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `changed_by` varchar(10) NOT NULL DEFAULT 'system'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_status_log`
--

INSERT INTO `order_status_log` (`log_id`, `order_id`, `status`, `changed_at`, `changed_by`) VALUES
(1, 1, 'Pending', '2026-10-06 00:08:09', 'system'),
(2, 2, 'Pending', '2026-10-06 00:36:33', 'system'),
(3, 3, 'Pending', '2026-10-12 00:28:27', 'system'),
(4, 4, 'Pending', '2026-10-12 01:01:17', 'system'),
(8, 1, 'Cancelled', '2026-10-06 00:08:09', 'system'),
(9, 2, 'Completed', '2026-10-06 00:36:48', 'system'),
(10, 3, 'Completed', '2026-10-12 07:26:56', 'system'),
(11, 4, 'Cancelled', '2026-10-12 01:01:17', 'system'),
(15, 5, 'Pending', '2026-10-12 09:04:19', 'customer'),
(16, 5, 'Prepared', '2026-10-12 09:17:33', 'admin'),
(17, 5, 'Ready for Pick Up', '2026-10-12 09:17:43', 'admin'),
(18, 6, 'Pending', '2026-10-12 10:13:09', 'customer'),
(19, 5, 'Cancelled', '2026-10-12 10:40:12', 'admin'),
(20, 7, 'Pending', '2026-10-12 10:56:39', 'customer'),
(21, 8, 'Pending', '2026-10-12 13:13:12', 'customer'),
(22, 9, 'Pending', '2026-10-13 00:40:30', 'customer'),
(23, 10, 'Pending', '2026-10-14 07:02:41', 'customer'),
(24, 10, 'Prepared', '2026-10-14 07:03:17', 'admin'),
(25, 10, 'Ready to Ship', '2026-10-14 07:03:24', 'admin'),
(26, 10, 'Shipped', '2026-10-14 07:03:28', 'admin'),
(27, 10, 'Out for Delivery', '2026-10-14 07:03:33', 'admin'),
(28, 10, 'Delivered', '2026-10-14 07:03:39', 'admin'),
(29, 10, 'Completed', '2026-10-14 07:03:47', 'admin'),
(30, 7, 'Cancelled', '2026-10-14 13:04:12', 'customer'),
(31, 9, 'Prepared', '2026-10-14 13:58:21', 'admin'),
(32, 8, 'Prepared', '2026-10-14 13:58:56', 'admin'),
(33, 8, 'Ready to Ship', '2026-10-14 13:59:08', 'admin'),
(34, 8, 'Shipped', '2026-10-14 13:59:20', 'admin'),
(35, 8, 'Out for Delivery', '2026-10-14 13:59:30', 'admin'),
(36, 8, 'Delivered', '2026-10-14 13:59:48', 'admin'),
(37, 8, 'Completed', '2026-10-14 14:00:18', 'customer'),
(38, 11, 'Pending', '2026-10-17 06:14:32', 'customer'),
(39, 11, 'Prepared', '2026-10-17 06:15:00', 'admin'),
(40, 11, 'Ready to Ship', '2026-10-17 06:15:09', 'admin'),
(41, 11, 'Shipped', '2026-10-17 06:15:15', 'admin'),
(42, 9, 'Ready to Ship', '2026-10-17 06:15:32', 'admin'),
(43, 9, 'Shipped', '2026-10-17 06:15:51', 'admin'),
(44, 11, 'Out for Delivery', '2026-10-17 06:15:59', 'admin'),
(45, 11, 'Delivered', '2026-10-17 06:16:06', 'admin'),
(46, 11, 'Completed', '2026-10-17 06:16:19', 'admin'),
(47, 12, 'Pending', '2026-10-19 00:31:59', 'customer'),
(48, 12, 'Prepared', '2026-10-19 00:33:43', 'admin'),
(49, 12, 'Ready to Ship', '2026-10-19 00:34:15', 'admin'),
(50, 12, 'Shipped', '2026-10-19 00:34:52', 'admin'),
(51, 12, 'Out for Delivery', '2026-10-19 00:35:12', 'admin'),
(52, 12, 'Delivered', '2026-10-19 00:35:34', 'admin'),
(53, 12, 'Completed', '2026-10-19 00:35:58', 'customer'),
(54, 13, 'Pending', '2026-10-26 11:20:24', 'customer'),
(55, 14, 'Pending', '2026-10-27 01:00:09', 'customer'),
(56, 15, 'Pending', '2026-10-27 01:03:26', 'customer'),
(57, 16, 'Pending', '2026-10-27 01:04:46', 'customer'),
(58, 17, 'Pending', '2026-10-27 01:07:30', 'customer');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `review_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `comment` text NOT NULL,
  `review_image` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`review_id`, `item_id`, `user_id`, `rating`, `comment`, `review_image`, `created_at`, `updated_at`) VALUES
(1, 18, 1, 5, 'nice T shirt, it fits very well. Love this store, thank youu.', 'review_6ad314fbeed714.23729949.png,review_6ad314fbef4418.18416455.jpg,review_6ad314fbefa6d0.46080028.png', '2026-10-14 14:20:01', '2026-10-17 06:26:03'),
(5, 12, 3, 5, 'Thank you po so much nang marami, ako nalang sana, tayo nalang dalawa. sana malaman mo.', 'review_6ad325875e8ea9.39460562.jpg,review_6ad325875eebe2.60791030.jpg,review_6ad325875f4c49.54264558.jpg', '2026-10-17 07:36:39', '2026-10-17 07:36:39'),
(6, 12, 9, 5, 'super fast, i ordered 2 mins , and now its here !!', NULL, '2026-10-19 00:36:25', '2026-10-19 00:36:25');

-- --------------------------------------------------------

--
-- Table structure for table `stock_log`
--

CREATE TABLE `stock_log` (
  `log_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `change_qty` int(11) NOT NULL,
  `new_stock` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_log`
--

INSERT INTO `stock_log` (`log_id`, `item_id`, `change_qty`, `new_stock`, `reason`, `user_id`, `created_at`) VALUES
(1, 18, 3, 26, 'Manual Restock', 2, '2026-10-14 12:53:36'),
(2, 18, 15, 41, 'Restock (Size XS)', 2, '2026-10-17 07:21:11'),
(3, 20, 15, 15, 'Initial inventory count', 2, '2026-10-27 00:39:45');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(10) NOT NULL DEFAULT 'user',
  `status` varchar(10) NOT NULL DEFAULT 'active',
  `profile_photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `email`, `password`, `role`, `status`, `profile_photo`, `created_at`, `reset_token`, `reset_expires`) VALUES
(1, 'laumerso0@gmail.com', '$2y$10$xt7dzHjKn5EVZLrzvLBHQ.F0L9G5gWmp.ntr6IZrZJtO4JHnsq0oK', 'user', 'active', NULL, '2026-10-03 10:32:24', NULL, NULL),
(2, 'admin@sportibai.com', '$2y$10$hYUlVHiYi3AgC9e/R3qTB.W2ejNEyh7RG5NmbCv2z4QbsFGrqvBlW', 'admin', 'active', 'user_6ad3227a1bcd48.92575520.png', '2026-10-06 00:13:53', NULL, NULL),
(3, 'stephteluk@gmail.com', '$2y$10$KtMP2BCP9CQrdlzsFbGVB.9aGiA/lilWfhToVzYqiZdZ0.GdF/mnm', 'user', 'active', NULL, '2026-10-12 07:54:35', NULL, NULL),
(4, 'das@gmail.com', '$2y$10$nfU/BTjo5xjNilfDl4Rz/.M9LCA4fhznahKGdWweNQZQ59uAYXGTK', 'user', 'active', NULL, '2026-10-12 09:03:42', NULL, NULL),
(6, 'freudmark@gmail.com', '$2y$10$fwSTcRrlOIMZRfMir5CF0ObtKIC6Vg1v7exx.ufvtfFtHTrt23/xm', 'user', 'active', 'user_6acf74e6142312.35171356.png', '2026-10-14 12:26:14', NULL, NULL),
(7, 'rassdaf@gmail.com', '$2y$10$SxAhLOjrEkoC3h0Qe34i5eJoAUzBCk3T3k80.feicEh9Aq4WGbmUG', 'user', 'active', NULL, '2026-10-14 14:21:22', NULL, NULL),
(8, 'qwert@gmail.com', '$2y$10$BiW5/L/g/cWYbLNVe9q/0.Aj/Dma2kPBkb5FWcuflz3UckJ9TY/AO', 'user', 'active', NULL, '2026-10-14 14:21:49', NULL, NULL),
(9, 'jpchan@gmail.com', '$2y$10$kLB0Opymf56iznYPQbAYzObKEnmt.ksDFq/rVwFBScVJnMAeLBr8u', 'user', 'active', NULL, '2026-10-19 00:15:01', NULL, NULL),
(10, 'totoongtayo@gmail.com', '$2y$10$dILjJF0G8MrEvUVuKaE5l.WKWope9Dzn9BCblHa3dB7nqbrL8UvPa', 'user', 'active', 'user_6adff77286de69.46098727.png', '2026-10-27 00:21:05', NULL, NULL),
(11, 'datingtayo@gmai.com', '$2y$10$uZmyc.Nz1e4nvZ7r1nGH3uWbVs6daxcTuDvNdrwRMTlDnqNERRkT.', 'user', 'active', 'user_6adff9070cbae2.66758181.png', '2026-10-27 01:06:15', NULL, NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_item_reviews`
-- (See below for the actual view)
--
CREATE TABLE `vw_item_reviews` (
`review_id` int(11)
,`item_id` int(11)
,`user_id` int(11)
,`rating` tinyint(4)
,`comment` text
,`review_image` text
,`created_at` timestamp
,`updated_at` timestamp
,`user_email` varchar(100)
,`user_photo` varchar(255)
,`reviewer_name` varchar(50)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_order_details`
-- (See below for the actual view)
--
CREATE TABLE `vw_order_details` (
`order_id` int(11)
,`reference_number` varchar(20)
,`date_ordered` timestamp
,`order_status` varchar(30)
,`order_type` varchar(10)
,`payment_method` varchar(10)
,`grand_total` decimal(10,2)
,`customer_id` int(11)
,`customer_name` varchar(150)
,`user_id` int(11)
,`customer_email` varchar(100)
,`order_item_id` int(11)
,`item_id` int(11)
,`selected_size` varchar(50)
,`item_name` varchar(100)
,`item_brand` varchar(50)
,`item_image` varchar(255)
,`quantity` int(11)
,`unit_price` decimal(10,2)
,`subtotal` decimal(20,2)
);

-- --------------------------------------------------------

--
-- Structure for view `vw_item_reviews`
--
DROP TABLE IF EXISTS `vw_item_reviews`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_item_reviews`  AS SELECT `r`.`review_id` AS `review_id`, `r`.`item_id` AS `item_id`, `r`.`user_id` AS `user_id`, `r`.`rating` AS `rating`, `r`.`comment` AS `comment`, `r`.`review_image` AS `review_image`, `r`.`created_at` AS `created_at`, `r`.`updated_at` AS `updated_at`, `u`.`email` AS `user_email`, `u`.`profile_photo` AS `user_photo`, coalesce(nullif(trim(`c`.`first_name`),''),'Athlete') AS `reviewer_name` FROM ((`reviews` `r` join `users` `u` on(`r`.`user_id` = `u`.`user_id`)) left join `customer` `c` on(`u`.`user_id` = `c`.`user_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `vw_order_details`
--
DROP TABLE IF EXISTS `vw_order_details`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_order_details`  AS SELECT `o`.`order_id` AS `order_id`, `o`.`reference_number` AS `reference_number`, `o`.`date_ordered` AS `date_ordered`, `o`.`status` AS `order_status`, `o`.`order_type` AS `order_type`, `o`.`payment_method` AS `payment_method`, `o`.`total` AS `grand_total`, `o`.`customer_id` AS `customer_id`, `o`.`snapshot_name` AS `customer_name`, `c`.`user_id` AS `user_id`, `u`.`email` AS `customer_email`, `oi`.`order_item_id` AS `order_item_id`, `oi`.`item_id` AS `item_id`, `oi`.`selected_size` AS `selected_size`, `i`.`name` AS `item_name`, `i`.`brand` AS `item_brand`, `i`.`image` AS `item_image`, `oi`.`quantity` AS `quantity`, `oi`.`price` AS `unit_price`, `oi`.`quantity`* `oi`.`price` AS `subtotal` FROM ((((`orders` `o` left join `customer` `c` on(`o`.`customer_id` = `c`.`customer_id`)) left join `users` `u` on(`c`.`user_id` = `u`.`user_id`)) join `order_items` `oi` on(`o`.`order_id` = `oi`.`order_id`)) join `items` `i` on(`oi`.`item_id` = `i`.`item_id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`expense_id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `item_images`
--
ALTER TABLE `item_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `item_sizes`
--
ALTER TABLE `item_sizes`
  ADD PRIMARY KEY (`size_id`),
  ADD UNIQUE KEY `uq_item_size` (`item_id`,`size_label`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD UNIQUE KEY `uq_ref` (`reference_number`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `order_status_log`
--
ALTER TABLE `order_status_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD UNIQUE KEY `uq_item_user_review` (`item_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `stock_log`
--
ALTER TABLE `stock_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `customer`
--
ALTER TABLE `customer`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `expense_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `item_images`
--
ALTER TABLE `item_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `item_sizes`
--
ALTER TABLE `item_sizes`
  MODIFY `size_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_status_log`
--
ALTER TABLE `order_status_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `stock_log`
--
ALTER TABLE `stock_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `customer`
--
ALTER TABLE `customer`
  ADD CONSTRAINT `customer_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`);

--
-- Constraints for table `item_images`
--
ALTER TABLE `item_images`
  ADD CONSTRAINT `item_images_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE;

--
-- Constraints for table `item_sizes`
--
ALTER TABLE `item_sizes`
  ADD CONSTRAINT `item_sizes_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`);

--
-- Constraints for table `order_status_log`
--
ALTER TABLE `order_status_log`
  ADD CONSTRAINT `order_status_log_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_log`
--
ALTER TABLE `stock_log`
  ADD CONSTRAINT `stock_log_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
