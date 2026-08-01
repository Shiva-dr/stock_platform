--
-- This is the complete and final schema for the NEPSE Stock Platform.
-- It creates the database, all tables, and inserts initial data.
--

-- Create the database if it doesn't already exist, and select it for use.
CREATE DATABASE IF NOT EXISTS stock_platform;
USE stock_platform;

-- Drop tables in reverse order of dependency to avoid foreign key errors.
DROP TABLE IF EXISTS `predictions`;
DROP TABLE IF EXISTS `companies`;
DROP TABLE IF EXISTS `users`;

-- --------------------------------------------------------

--
-- Table structure for table `users`
-- This table stores user login information and type.
--
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('user','admin') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert a default admin user for testing.
-- The password is 'admin123'. You can generate a new hash if needed.
INSERT INTO `users` (`id`, `name`, `email`, `password`, `user_type`, `created_at`) VALUES
(1, 'Admin User', 'admin@example.com', '$2y$10$Euv6pCvj93l2uA.sJkApS.yD1QzY7w/uXgC/tH.8i4vGgZ.wB6b5u', 'admin', NOW());


-- --------------------------------------------------------

--
-- Table structure for table `companies`
-- This table stores the NEPSE companies we are tracking.
--
CREATE TABLE `companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `ticker` varchar(10) NOT NULL,
  `sector` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticker` (`ticker`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert the sample NEPSE companies.
INSERT INTO `companies` (`name`, `ticker`, `sector`) VALUES
('Nabil Bank Limited', 'NABIL', 'Commercial Banks'),
('Nepal Investment Mega Bank Limited', 'NIMB', 'Commercial Banks'),
('Himalayan Distillery Limited', 'HDL', 'Manufacturing And Processing'),
('Nepal Reinsurance Company Limited', 'NRIC', 'Non-Life Insurance'),
('Shivam Cements Limited', 'SHIVM', 'Manufacturing And Processing'),
('Nepal Telecom', 'NTC', 'Others');

-- --------------------------------------------------------

--
-- Table structure for table `predictions`
-- This table will log every prediction made by a user.
--
CREATE TABLE `predictions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `prediction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `predicted_price` decimal(10,2) NOT NULL,
  `last_known_price` decimal(10,2) NOT NULL,
  `model_type` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `predictions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `predictions_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;