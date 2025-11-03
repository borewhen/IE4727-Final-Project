-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Nov 03, 2025 at 04:37 AM
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
-- Database: `stirling`
--

-- --------------------------------------------------------

--
-- Table structure for table `variation_images`
--

CREATE TABLE `variation_images` (
  `id` int(11) NOT NULL,
  `variation_id` int(11) NOT NULL,
  `image_filename` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `variation_images`
--

INSERT INTO `variation_images` (`id`, `variation_id`, `image_filename`, `sort_order`, `created_at`) VALUES
(1, 2, 'assets/images/products/stirlings-blazer/2/img_69068e2ded3ef4.94023519.jpg', 0, '2025-11-02 06:48:13'),
(2, 3, 'assets/images/products/miznons-checkered-blazer/3/img_69069488a42bd0.50304460.jpg', 0, '2025-11-02 07:15:20'),
(3, 4, 'assets/images/products/kamakura-shirt/4/img_69081e2b073621.82521524.jpg', 0, '2025-11-03 11:14:51'),
(4, 4, 'assets/images/products/kamakura-shirt/4/img_69081e2b086304.48033206.jpg', 0, '2025-11-03 11:14:51'),
(5, 4, 'assets/images/products/kamakura-shirt/4/img_69081e2b08c875.64247383.jpg', 0, '2025-11-03 11:14:51'),
(6, 5, 'assets/images/products/kamakura-shirt/5/img_69081e2b09fab4.54652313.jpg', 0, '2025-11-03 11:14:51'),
(7, 6, 'assets/images/products/joseph-cheaney-oxfords/6/img_6908206bf100f3.82273216.jpg', 0, '2025-11-03 11:24:27'),
(8, 6, 'assets/images/products/joseph-cheaney-oxfords/6/img_6908206bf122b5.91784356.jpg', 0, '2025-11-03 11:24:27'),
(9, 6, 'assets/images/products/joseph-cheaney-oxfords/6/img_6908206bf146a6.56424877.jpg', 0, '2025-11-03 11:24:27'),
(10, 6, 'assets/images/products/joseph-cheaney-oxfords/6/img_6908206bf163b3.01208576.jpg', 0, '2025-11-03 11:24:27'),
(11, 6, 'assets/images/products/joseph-cheaney-oxfords/6/img_6908206bf17908.49413410.jpg', 0, '2025-11-03 11:24:27'),
(12, 7, 'assets/images/products/joseph-cheaney-oxfords/7/img_6908206bf19f37.94130483.jpg', 0, '2025-11-03 11:24:27'),
(13, 7, 'assets/images/products/joseph-cheaney-oxfords/7/img_6908206bf1b662.83405192.jpg', 0, '2025-11-03 11:24:27'),
(14, 7, 'assets/images/products/joseph-cheaney-oxfords/7/img_6908206bf1d466.51964347.jpg', 0, '2025-11-03 11:24:27'),
(15, 7, 'assets/images/products/joseph-cheaney-oxfords/7/img_6908206bf1ebc1.77565902.jpg', 0, '2025-11-03 11:24:27'),
(16, 7, 'assets/images/products/joseph-cheaney-oxfords/7/img_6908206bf20197.04757939.jpg', 0, '2025-11-03 11:24:27'),
(17, 8, 'assets/images/products/haru-loafers/8/img_690821a42bb7e0.40630042.jpg', 0, '2025-11-03 11:29:40'),
(18, 8, 'assets/images/products/haru-loafers/8/img_690821a42bff54.37251952.jpg', 0, '2025-11-03 11:29:40'),
(19, 8, 'assets/images/products/haru-loafers/8/img_690821a42c3d66.73598202.jpg', 0, '2025-11-03 11:29:40'),
(20, 8, 'assets/images/products/haru-loafers/8/img_690821a42c7637.47056749.jpg', 0, '2025-11-03 11:29:40'),
(21, 8, 'assets/images/products/haru-loafers/8/img_690821a42cd462.98173482.jpg', 0, '2025-11-03 11:29:40'),
(22, 9, 'assets/images/products/haru-loafers/9/img_690821a42d9866.00082481.jpg', 0, '2025-11-03 11:29:40'),
(23, 10, 'assets/images/products/haru-loafers/10/img_690821a42df655.95921641.jpg', 0, '2025-11-03 11:29:40'),
(24, 11, 'assets/images/products/echizenya-cotton-pants/11/img_69082317dbbcd1.76995365.jpg', 0, '2025-11-03 11:35:51'),
(25, 12, 'assets/images/products/echizenya-cotton-pants/12/img_69082317dcea52.17521766.jpg', 0, '2025-11-03 11:35:51');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `variation_images`
--
ALTER TABLE `variation_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `variation_id` (`variation_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `variation_images`
--
ALTER TABLE `variation_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `variation_images`
--
ALTER TABLE `variation_images`
  ADD CONSTRAINT `variation_images_ibfk_1` FOREIGN KEY (`variation_id`) REFERENCES `product_variations` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
