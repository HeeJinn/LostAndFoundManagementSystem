-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 29, 2025 at 04:55 PM
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
-- Database: `lostandfound_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`) VALUES
(2, 'Books & Notes'),
(3, 'Clothing'),
(1, 'Electronics'),
(4, 'IDs & Cards'),
(5, 'Other');

-- --------------------------------------------------------

--
-- Table structure for table `claims`
--

CREATE TABLE `claims` (
  `claim_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL COMMENT 'Foreign key to the lost_items table',
  `claimer_id` int(11) NOT NULL COMMENT 'Foreign key to the reporters table (who is making this claim)',
  `claim_status` enum('pending','approved','denied') NOT NULL DEFAULT 'pending' COMMENT 'The current status of this claim request',
  `claim_date` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When the claim was submitted',
  `admin_notes` text DEFAULT NULL COMMENT 'Stores the reason for denial (from the modal)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `claims`
--

INSERT INTO `claims` (`claim_id`, `item_id`, `claimer_id`, `claim_status`, `claim_date`, `admin_notes`) VALUES
(1, 38, 12, 'approved', '2025-10-29 15:41:11', NULL),
(2, 39, 13, 'denied', '2025-10-29 15:42:18', '');

-- --------------------------------------------------------

--
-- Table structure for table `lost_items`
--

CREATE TABLE `lost_items` (
  `item_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `item_description` text DEFAULT NULL,
  `item_image_url` varchar(1024) DEFAULT NULL,
  `item_status` enum('found','claimed','archived') NOT NULL DEFAULT 'found',
  `found_location` varchar(255) DEFAULT NULL,
  `qr_code_data` varchar(255) NOT NULL,
  `reported_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `category_id` int(11) DEFAULT NULL,
  `reporter_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lost_items`
--

INSERT INTO `lost_items` (`item_id`, `item_name`, `item_description`, `item_image_url`, `item_status`, `found_location`, `qr_code_data`, `reported_at`, `category_id`, `reporter_id`) VALUES
(21, 'Black iPhone 13', 'Has a small crack on the top left corner. The lock screen is a picture of a golden retriever.', 'https://placehold.co/600x400/000000/FFFFFF?text=iPhone+13', 'found', 'University Library, 2nd Floor', 'upang-lf-item-101', '2025-10-22 01:15:00', 1, 3),
(23, 'Silver-rimmed Eyeglasses', 'Ray-Ban brand, comes with a black hard case.', 'https://placehold.co/600x400/ced4da/000000?text=Eyeglasses', 'claimed', 'Main Hall, near Room 104', 'upang-lf-item-103', '2025-10-20 08:45:00', 5, 5),
(24, 'Calculus Textbook', 'Introduction to Calculus, 8th Edition. Has some highlighting on the first few chapters.', 'https://placehold.co/600x400/28a745/FFFFFF?text=Textbook', 'found', 'HB 307', 'upang-lf-item-104', '2025-10-19 03:00:00', 2, 3),
(25, 'University ID Card', 'Belongs to student Juan Dela Cruz, ID number 03-2324-033735.', 'https://placehold.co/600x400/ffc107/000000?text=ID+Card', 'claimed', 'Gymnasium', 'upang-lf-item-105', '2025-10-18 09:20:00', 4, 1),
(26, 'Gray University Hoodie', 'Size medium, PHINMA-UPANG logo on the front. No other distinguishing marks.', 'https://placehold.co/600x400/6c757d/FFFFFF?text=Hoodie', 'archived', 'Student Center', 'upang-lf-item-106', '2025-09-15 06:00:00', 3, 4),
(27, 'Samsung Galaxy Buds', 'White wireless earbuds in a charging case. The case has a small scratch on the front.', 'https://placehold.co/600x400/f8f9fa/000000?text=Earbuds', 'found', 'Basketball Court', 'upang-lf-item-107', '2025-10-17 10:00:00', 1, 5),
(28, 'Black Leather Wallet', 'Contains a driver\'s license for Pedro Penduko and around 500 pesos in cash.', 'https://placehold.co/600x400/343a40/FFFFFF?text=Wallet', 'found', 'Parking Lot B', 'upang-lf-item-108', '2025-10-23 00:30:00', 5, 2),
(29, 'Set of Keys', 'A car key with a Toyota logo and three other house keys on a red lanyard.', 'https://placehold.co/600x400/dc3545/FFFFFF?text=Keys', 'claimed', 'Admin Office', 'upang-lf-item-109', '2025-10-16 05:10:00', 5, 1),
(30, 'Hydro Flask Water Bottle', 'Light blue, 32 oz size. Has a dent near the bottom and a sticker of a mountain range.', 'https://placehold.co/600x400/17a2b8/FFFFFF?text=Bottle', 'found', 'Soccer Field', 'upang-lf-item-110', '2025-10-24 02:00:00', 5, 4),
(31, 'Converse Shoe', 'asdasdasdasdasd asdasd asd ', 'item_images/68fdce7c9875e6.99056513.jpg', 'found', 'Canteen', '', '2025-10-26 07:32:12', 3, 6),
(37, 'Testing2', 'asdsadasdasd', 'item_images/68fdd2d04bd762.68987690.jpg', 'found', 'asasdadsad', 'item_qr_68fdd2d04c2b25.37454918', '2025-10-26 07:50:40', 1, 8),
(38, 'Try to claim', 'asdnkasjdhakjdh asjkhdaskjdhasjkdh asdhaskjdhasjkdaskjd asdhasjkdhask', 'item_images/68fdd324000514.32740137.png', 'claimed', 'asdas da sdas', 'item_qr_68fdd324008a98.60213769', '2025-10-26 07:52:04', 2, 9),
(39, 'Bracelet', 'Pianng daasal ni christian', 'item_images/68fe28c241cfe4.21536045.jpg', 'found', 'Cr ng boys', 'item_qr_68fe28c2428364.23432484', '2025-10-26 13:57:22', 5, 8),
(40, 'Mouse', 'asdasd asdasdasdasda dsadasda', 'item_images/68ff6f67d8bf69.38577146.png', 'archived', 'Entrance', 'item_qr_68ff6f67d98cd2.13408243', '2025-10-27 13:11:03', 4, 10),
(41, 'Room', 'asdasdasdasd as', 'item_images/6901d7ce772744.53881975.png', 'archived', 'asdasdasd ', 'item_qr_68ff6fad8dd251.13936170', '2025-10-27 13:12:13', 1, 11);

-- --------------------------------------------------------

--
-- Table structure for table `reporters`
--

CREATE TABLE `reporters` (
  `reporter_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reporters`
--

INSERT INTO `reporters` (`reporter_id`, `first_name`, `last_name`, `student_id`, `email`, `contact_number`) VALUES
(1, 'Juan', 'Dela Cruz', '03-2324-033735', 'j.delacruz@upang.edu.ph', '09171234567'),
(2, 'Maria', 'Santos', '03-2223-012345', 'm.santos@upang.edu.ph', '09181234568'),
(3, 'Pedro', 'Penduko', '03-2122-098765', 'p.penduko@upang.edu.ph', '09191234569'),
(4, 'Sofia', 'Reyes', '03-2425-054321', 's.reyes@upang.edu.ph', '09281234515'),
(5, 'Carlos', 'Garcia', '03-2021-011223', 'c.garcia@upang.edu.ph', '09391234521'),
(6, 'Kenley', 'Benitez', '032324033735', 'admin@gmail.com', '09123456781'),
(7, 'Jeano', 'Alos', '032324033734', 'jeano@gmail.com', '09123456789'),
(8, 'asdasdasd as', 'asddasdasd ', '032324033738', 'juan@gmail.com', '09123456789'),
(9, 'Juan Ponce', 'Dela Cruz', '032324033736', 'kese.benitez.up@phinmaed.com', '09123456714'),
(10, 'jilbert', 'salom', '032324033731', 'asdasdasd@gmail.com', '091234562123'),
(11, 'hihi', 'adasdas', 'asdasdasda', 'qwer@gmail.com', '09123456782'),
(12, 'kern', 'bbenm', '012312039123', 'knribnitez@gmail.com', '09123456781'),
(13, 'aasdasdsa ', 'asdasdasd', 'asasd', 'asdasd@gmail.com', 'asasdas');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `claims`
--
ALTER TABLE `claims`
  ADD PRIMARY KEY (`claim_id`),
  ADD KEY `item_id_fk` (`item_id`),
  ADD KEY `claimer_id_fk` (`claimer_id`);

--
-- Indexes for table `lost_items`
--
ALTER TABLE `lost_items`
  ADD PRIMARY KEY (`item_id`),
  ADD UNIQUE KEY `qr_code_data` (`qr_code_data`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `reporter_id` (`reporter_id`);

--
-- Indexes for table `reporters`
--
ALTER TABLE `reporters`
  ADD PRIMARY KEY (`reporter_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `student_id` (`student_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `claims`
--
ALTER TABLE `claims`
  MODIFY `claim_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `lost_items`
--
ALTER TABLE `lost_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `reporters`
--
ALTER TABLE `reporters`
  MODIFY `reporter_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `claims`
--
ALTER TABLE `claims`
  ADD CONSTRAINT `claims_fk_item` FOREIGN KEY (`item_id`) REFERENCES `lost_items` (`item_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `claims_fk_reporter` FOREIGN KEY (`claimer_id`) REFERENCES `reporters` (`reporter_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `lost_items`
--
ALTER TABLE `lost_items`
  ADD CONSTRAINT `lost_items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`),
  ADD CONSTRAINT `lost_items_ibfk_2` FOREIGN KEY (`reporter_id`) REFERENCES `reporters` (`reporter_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
