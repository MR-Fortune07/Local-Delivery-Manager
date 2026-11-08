SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";




-- Database: "phoenix"
--

-- --------------------------------------------------------

--
-- Table structure for table "audit_log"
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `actor` varchar(160) NOT NULL,
  `action` varchar(120) NOT NULL,
  `detail` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `actor`, `action`, `detail`, `created_at`) VALUES
(76, 'Admin User', 'Deleted account', 'Jack Rand', '2026-11-08 04:10:53');

-- --------------------------------------------------------

--
-- Table structure for table `client_cards`
--

CREATE TABLE `client_cards` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `card_holder` varchar(120) NOT NULL,
  `brand` varchar(20) NOT NULL DEFAULT 'Card',
  `last4` char(4) NOT NULL,
  `expiry_month` tinyint(4) NOT NULL,
  `expiry_year` smallint(6) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `driver_documents`
--

CREATE TABLE `driver_documents` (
  `id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `id_document` varchar(255) DEFAULT NULL,
  `license_document` varchar(255) NOT NULL,
  `car_reg_document` varchar(255) NOT NULL,
  `passport_document` varchar(255) DEFAULT NULL,
  `police_clearance_document` varchar(255) DEFAULT NULL,
  `bank_proof_document` varchar(255) DEFAULT NULL,
  `status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `reject_reason` varchar(255) DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_otps`
--

CREATE TABLE `password_reset_otps` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `consumed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `platform_config`
--

CREATE TABLE `platform_config` (
  `id` int(11) NOT NULL DEFAULT 1,
  `base_fare` decimal(6,2) NOT NULL DEFAULT 25.00,
  `rate_per_km` decimal(6,2) NOT NULL DEFAULT 12.00,
  `commission_pct` decimal(5,2) NOT NULL DEFAULT 10.00,
  `driver_base_salary` decimal(8,2) NOT NULL DEFAULT 7000.00,
  `maintenance_mode` tinyint(1) NOT NULL DEFAULT 0,
  `announcement` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `platform_config`
--

INSERT INTO `platform_config` (`id`, `base_fare`, `rate_per_km`, `commission_pct`, `driver_base_salary`, `maintenance_mode`, `announcement`) VALUES
(1, '25.00', '12.00', '10.08', '7000.00', 0, '');

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `description` text NOT NULL,
  `pickup_lat` decimal(10,7) NOT NULL,
  `pickup_lng` decimal(10,7) NOT NULL,
  `pickup_addr` varchar(255) DEFAULT NULL,
  `dropoff_lat` decimal(10,7) NOT NULL,
  `dropoff_lng` decimal(10,7) NOT NULL,
  `dropoff_addr` varchar(255) DEFAULT NULL,
  `pickup_photo` varchar(255) DEFAULT NULL,
  `delivery_photo` varchar(255) DEFAULT NULL,
  `status` enum('pending','accepted','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `distance_km` decimal(6,2) DEFAULT NULL,
  `fare` decimal(8,2) DEFAULT NULL,
  `commission` decimal(8,2) DEFAULT NULL,
  `payout` decimal(8,2) DEFAULT NULL,
  `flagged` tinyint(1) NOT NULL DEFAULT 0,
  `report_reason` varchar(255) DEFAULT NULL,
  `reported_by` varchar(160) DEFAULT NULL,
  `flag_target` varchar(10) DEFAULT NULL,
  `refunded` tinyint(1) NOT NULL DEFAULT 0,
  `paid` tinyint(1) NOT NULL DEFAULT 0,
  `payment_method` varchar(40) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `accepted_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `salary_payments`
--

CREATE TABLE `salary_payments` (
  `id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `deliveries_count` int(11) NOT NULL DEFAULT 0,
  `commission_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `base_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `paid_by` int(11) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `role` enum('client','driver','admin') NOT NULL,
  `admin_tier` enum('super','support') DEFAULT NULL,
  `name` varchar(80) NOT NULL,
  `surname` varchar(80) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `id_number` varchar(13) DEFAULT NULL,
  `account_number` varchar(20) DEFAULT NULL,
  `bank_name` varchar(60) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `nationality` varchar(60) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `car_model` varchar(120) DEFAULT NULL,
  `car_reg` varchar(40) DEFAULT NULL,
  `base_salary` decimal(8,2) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `suspended` tinyint(1) NOT NULL DEFAULT 0,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role`, `admin_tier`, `name`, `surname`, `phone`, `email`, `id_number`, `account_number`, `bank_name`, `address`, `date_of_birth`, `nationality`, `password_hash`, `car_model`, `car_reg`, `base_salary`, `profile_pic`, `must_change_password`, `suspended`, `verified`, `created_at`) VALUES
(1, 'admin', 'super', 'Admin', 'User', '0000000000', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$12$38r5JH2QgbSYZZClG5XPWOHAzCr4tyHoBxJNeE7Skw6CVTccW/wgi', NULL, NULL, NULL, NULL, 0, 0, 1, '2026-11-01 18:17:05');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `client_cards`
--
ALTER TABLE `client_cards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cards_client` (`client_id`);

--
-- Indexes for table `driver_documents`
--
ALTER TABLE `driver_documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `driver_id` (`driver_id`),
  ADD KEY `fk_docs_reviewer` (`reviewed_by`),
  ADD KEY `idx_docs_driver` (`driver_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_messages_sender` (`sender_id`),
  ADD KEY `idx_messages_request` (`request_id`);

--
-- Indexes for table `password_reset_otps`
--
ALTER TABLE `password_reset_otps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_otp_user` (`user_id`);

--
-- Indexes for table `platform_config`
--
ALTER TABLE `platform_config`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_requests_status` (`status`),
  ADD KEY `idx_requests_client` (`client_id`),
  ADD KEY `idx_requests_driver` (`driver_id`);

--
-- Indexes for table `salary_payments`
--
ALTER TABLE `salary_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_salary_admin` (`paid_by`),
  ADD KEY `idx_salary_driver` (`driver_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `uq_users_id_number` (`id_number`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT for table `client_cards`
--
ALTER TABLE `client_cards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `driver_documents`
--
ALTER TABLE `driver_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `password_reset_otps`
--
ALTER TABLE `password_reset_otps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `salary_payments`
--
ALTER TABLE `salary_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `client_cards`
--
ALTER TABLE `client_cards`
  ADD CONSTRAINT `fk_cards_client` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `driver_documents`
--
ALTER TABLE `driver_documents`
  ADD CONSTRAINT `fk_docs_driver` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_docs_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_reset_otps`
--
ALTER TABLE `password_reset_otps`
  ADD CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `requests`
--
ALTER TABLE `requests`
  ADD CONSTRAINT `fk_requests_client` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_requests_driver` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `salary_payments`
--
ALTER TABLE `salary_payments`
  ADD CONSTRAINT `fk_salary_admin` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_salary_driver` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;
