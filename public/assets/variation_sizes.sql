-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Nov 02, 2025 at 12:36 AM
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
-- Table structure for table `variation_sizes`
--

CREATE TABLE `variation_sizes` (
  `id` int(11) NOT NULL,
  `variation_id` int(11) NOT NULL,
  `size` varchar(32) NOT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `variation_sizes`
--

INSERT INTO `variation_sizes` (`id`, `variation_id`, `size`, `stock_quantity`, `created_at`) VALUES
(3, 2, '46', 2, '2025-11-02 06:48:13'),
(4, 2, '48', 3, '2025-11-02 06:48:13'),
(5, 3, '44', 5, '2025-11-02 07:15:20'),
(6, 3, '46', 2, '2025-11-02 07:15:20');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `variation_sizes`
--
ALTER TABLE `variation_sizes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `variation_id` (`variation_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `variation_sizes`
--
ALTER TABLE `variation_sizes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `variation_sizes`
--
ALTER TABLE `variation_sizes`
  ADD CONSTRAINT `variation_sizes_ibfk_1` FOREIGN KEY (`variation_id`) REFERENCES `product_variations` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
