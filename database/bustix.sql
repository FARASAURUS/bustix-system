-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jun 14, 2025 at 04:02 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bustix`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `batalkanBooking` (IN `p_booking_id` INT, IN `p_user_id` INT, OUT `p_result` VARCHAR(100))   BEGIN
    DECLARE v_booking_status VARCHAR(20);
    DECLARE v_schedule_id INT;
    DECLARE v_total_seats INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_result = 'ERROR: Transaction failed';
    END;

    START TRANSACTION;

    SELECT booking_status, schedule_id, total_seats
    INTO v_booking_status, v_schedule_id, v_total_seats
    FROM bookings 
    WHERE id = p_booking_id AND user_id = p_user_id;

    IF v_booking_status IS NULL THEN
        SET p_result = 'ERROR: Booking not found';
        ROLLBACK;
    ELSEIF v_booking_status = 'cancelled' THEN
        SET p_result = 'ERROR: Booking already cancelled';
        ROLLBACK;
    ELSEIF v_booking_status = 'completed' THEN
        SET p_result = 'ERROR: Cannot cancel completed booking';
        ROLLBACK;
    ELSE
        UPDATE bookings 
        SET booking_status = 'cancelled', payment_status = 'refunded'
        WHERE id = p_booking_id;

        SET p_result = 'SUCCESS: Booking cancelled successfully';
        COMMIT;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `buatBooking` (IN `p_user_id` INT, IN `p_schedule_id` INT, IN `p_passenger_name` VARCHAR(100), IN `p_passenger_phone` VARCHAR(15), IN `p_seat_numbers` TEXT, IN `p_total_seats` INT, OUT `p_booking_code` VARCHAR(20), OUT `p_result` VARCHAR(100))   BEGIN
    DECLARE v_available_seats INT DEFAULT 0;
    DECLARE v_price DECIMAL(10,2) DEFAULT 0;
    DECLARE v_total_amount DECIMAL(10,2) DEFAULT 0;
    DECLARE v_schedule_date DATE;
    DECLARE v_departure_time TIME;
    DECLARE v_booking_id INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        GET DIAGNOSTICS CONDITION 1
            @sqlstate = RETURNED_SQLSTATE, 
            @errno = MYSQL_ERRNO, 
            @text = MESSAGE_TEXT;
        SET p_result = CONCAT('ERROR: ', @text);
    END;

    START TRANSACTION;
    
    -- Cek ketersediaan jadwal dan kursi
    SELECT available_seats, price, schedule_date, departure_time 
    INTO v_available_seats, v_price, v_schedule_date, v_departure_time
    FROM bus_schedules 
    WHERE id = p_schedule_id AND status = 'active';
    
    -- Validasi input
    IF p_total_seats <= 0 THEN
        SET p_result = 'ERROR: Invalid seat count';
        ROLLBACK;
    ELSEIF v_available_seats IS NULL THEN
        SET p_result = 'ERROR: Schedule not found or inactive';
        ROLLBACK;
    ELSEIF v_schedule_date < CURDATE() OR 
           (v_schedule_date = CURDATE() AND v_departure_time <= CURTIME()) THEN
        SET p_result = 'ERROR: Cannot book past schedule';
        ROLLBACK;
    ELSEIF v_available_seats < p_total_seats THEN
        SET p_result = CONCAT('ERROR: Only ', v_available_seats, ' seats available');
        ROLLBACK;
    ELSE
        -- Generate unique booking code
        SET p_booking_code = generateBookingCode();
        
        -- Calculate total amount
        SET v_total_amount = v_price * p_total_seats;
        
        -- Insert booking
        INSERT INTO bookings (
            user_id, schedule_id, booking_code, passenger_name, 
            passenger_phone, seat_numbers, total_seats, total_amount
        ) VALUES (
            p_user_id, p_schedule_id, p_booking_code, p_passenger_name,
            p_passenger_phone, p_seat_numbers, p_total_seats, v_total_amount
        );
        
        SET v_booking_id = LAST_INSERT_ID();
        
        -- Update available seats
        UPDATE bus_schedules 
        SET available_seats = available_seats - p_total_seats 
        WHERE id = p_schedule_id;
        
        SET p_result = CONCAT('SUCCESS: Booking created with ID ', v_booking_id);
        COMMIT;
    END IF;
END$$

--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `cekKetersediaan` (`p_schedule_id` INT, `p_required_seats` INT) RETURNS VARCHAR(50) CHARSET utf8mb4 DETERMINISTIC READS SQL DATA BEGIN
    DECLARE v_available_seats INT;
    DECLARE v_schedule_date DATE;
    DECLARE v_departure_time TIME;
    DECLARE v_status VARCHAR(20);
    
    SELECT available_seats, schedule_date, departure_time, status
    INTO v_available_seats, v_schedule_date, v_departure_time, v_status
    FROM bus_schedules 
    WHERE id = p_schedule_id;
    
    IF v_status != 'active' THEN
        RETURN 'INACTIVE';
    ELSEIF v_schedule_date < CURDATE() OR 
           (v_schedule_date = CURDATE() AND v_departure_time <= CURTIME()) THEN
        RETURN 'EXPIRED';
    ELSEIF v_available_seats < p_required_seats THEN
        RETURN 'INSUFFICIENT';
    ELSE
        RETURN 'AVAILABLE';
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `generateBookingCode` () RETURNS VARCHAR(20) CHARSET utf8mb4 DETERMINISTIC READS SQL DATA BEGIN
    DECLARE v_code VARCHAR(20);
    DECLARE v_exists INT DEFAULT 1;
    
    WHILE v_exists > 0 DO
        SET v_code = CONCAT('BT', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(FLOOR(RAND() * 10000), 4, '0'));
        SELECT COUNT(*) INTO v_exists FROM bookings WHERE booking_code = v_code;
    END WHILE;
    
    RETURN v_code;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `schedule_id` int NOT NULL,
  `booking_code` varchar(20) NOT NULL,
  `passenger_name` varchar(100) NOT NULL,
  `passenger_phone` varchar(15) NOT NULL,
  `seat_numbers` text NOT NULL,
  `total_seats` int NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `booking_status` enum('pending','confirmed','cancelled','completed') DEFAULT 'pending',
  `payment_status` enum('pending','paid','refunded') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `booking_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `user_id`, `schedule_id`, `booking_code`, `passenger_name`, `passenger_phone`, `seat_numbers`, `total_seats`, `total_amount`, `booking_status`, `payment_status`, `payment_method`, `booking_date`, `updated_at`) VALUES
(2, 2, 1, 'BT202506149838', 'zidan', '081110473041', '1', 1, '75000.00', 'cancelled', 'refunded', NULL, '2025-06-14 03:21:13', '2025-06-14 03:36:22'),
(3, 2, 2, 'BT202506141685', 'zidan rosyid', '08111047304', '1', 1, '75000.00', 'cancelled', 'refunded', NULL, '2025-06-14 03:36:53', '2025-06-14 03:38:49'),
(4, 2, 1, 'BT202506148765', 'zidan', '08111047304', '1', 1, '75000.00', 'cancelled', 'refunded', NULL, '2025-06-14 03:39:23', '2025-06-14 03:39:37'),
(5, 2, 1, 'BT202506140814', 'zidan', '08111047304', '1', 1, '75000.00', 'confirmed', 'paid', NULL, '2025-06-14 03:41:27', '2025-06-14 03:41:42');

--
-- Triggers `bookings`
--
DELIMITER $$
CREATE TRIGGER `booking_cancel_trigger` AFTER UPDATE ON `bookings` FOR EACH ROW BEGIN
    IF OLD.booking_status != 'cancelled' AND NEW.booking_status = 'cancelled' THEN
        -- Return seats to schedule
        UPDATE bus_schedules 
        SET available_seats = available_seats + NEW.total_seats 
        WHERE id = NEW.schedule_id;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `booking_create_trigger` AFTER INSERT ON `bookings` FOR EACH ROW BEGIN
    INSERT INTO booking_history (
        booking_id, action_type, new_data, action_by
    ) VALUES (
        NEW.id,
        'created',
        JSON_OBJECT(
            'booking_code', NEW.booking_code,
            'passenger_name', NEW.passenger_name,
            'total_amount', NEW.total_amount,
            'booking_status', NEW.booking_status,
            'payment_status', NEW.payment_status,
            'created_at', NEW.booking_date
        ),
        NEW.user_id
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `booking_delete_trigger` BEFORE DELETE ON `bookings` FOR EACH ROW BEGIN
    INSERT INTO booking_history (
        booking_id, action_type, old_data, action_by
    ) VALUES (
        OLD.id,
        'deleted',
        JSON_OBJECT(
            'booking_code', OLD.booking_code,
            'passenger_name', OLD.passenger_name,
            'total_amount', OLD.total_amount,
            'booking_status', OLD.booking_status,
            'payment_status', OLD.payment_status,
            'deleted_at', NOW()
        ),
        OLD.user_id
    );
    
    -- Return seats to schedule
    UPDATE bus_schedules 
    SET available_seats = available_seats + OLD.total_seats 
    WHERE id = OLD.schedule_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `booking_history_trigger` AFTER UPDATE ON `bookings` FOR EACH ROW BEGIN
    IF OLD.booking_status != NEW.booking_status OR OLD.payment_status != NEW.payment_status THEN
        INSERT INTO booking_history (
            booking_id, action_type, old_data, new_data, action_by
        ) VALUES (
            NEW.id,
            'updated',
            JSON_OBJECT(
                'booking_status', OLD.booking_status,
                'payment_status', OLD.payment_status,
                'updated_at', OLD.updated_at
            ),
            JSON_OBJECT(
                'booking_status', NEW.booking_status,
                'payment_status', NEW.payment_status,
                'updated_at', NEW.updated_at
            ),
            NEW.user_id
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `booking_history`
--

CREATE TABLE `booking_history` (
  `id` int NOT NULL,
  `booking_id` int NOT NULL,
  `action_type` enum('created','updated','cancelled','deleted') NOT NULL,
  `old_data` json DEFAULT NULL,
  `new_data` json DEFAULT NULL,
  `action_by` int DEFAULT NULL,
  `action_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `booking_history`
--

INSERT INTO `booking_history` (`id`, `booking_id`, `action_type`, `old_data`, `new_data`, `action_by`, `action_date`) VALUES
(1, 2, 'created', NULL, '{\"created_at\": \"2025-06-14 10:21:13.000000\", \"booking_code\": \"BT202506149838\", \"total_amount\": 75000.00, \"booking_status\": \"pending\", \"passenger_name\": \"zidan\", \"payment_status\": \"pending\"}', 2, '2025-06-14 03:21:13'),
(2, 2, 'updated', '{\"updated_at\": \"2025-06-14 10:21:13.000000\", \"booking_status\": \"pending\", \"payment_status\": \"pending\"}', '{\"updated_at\": \"2025-06-14 10:21:17.000000\", \"booking_status\": \"confirmed\", \"payment_status\": \"paid\"}', 2, '2025-06-14 03:21:17'),
(3, 2, 'updated', '{\"updated_at\": \"2025-06-14 10:21:17.000000\", \"booking_status\": \"confirmed\", \"payment_status\": \"paid\"}', '{\"updated_at\": \"2025-06-14 10:36:22.000000\", \"booking_status\": \"cancelled\", \"payment_status\": \"refunded\"}', 2, '2025-06-14 03:36:22'),
(4, 3, 'created', NULL, '{\"created_at\": \"2025-06-14 10:36:53.000000\", \"booking_code\": \"BT202506141685\", \"total_amount\": 75000.00, \"booking_status\": \"pending\", \"passenger_name\": \"zidan rosyid\", \"payment_status\": \"pending\"}', 2, '2025-06-14 03:36:53'),
(5, 3, 'updated', '{\"updated_at\": \"2025-06-14 10:36:53.000000\", \"booking_status\": \"pending\", \"payment_status\": \"pending\"}', '{\"updated_at\": \"2025-06-14 10:36:55.000000\", \"booking_status\": \"confirmed\", \"payment_status\": \"paid\"}', 2, '2025-06-14 03:36:55'),
(6, 3, 'updated', '{\"updated_at\": \"2025-06-14 10:36:55.000000\", \"booking_status\": \"confirmed\", \"payment_status\": \"paid\"}', '{\"updated_at\": \"2025-06-14 10:38:49.000000\", \"booking_status\": \"cancelled\", \"payment_status\": \"refunded\"}', 2, '2025-06-14 03:38:49'),
(7, 4, 'created', NULL, '{\"created_at\": \"2025-06-14 10:39:23.000000\", \"booking_code\": \"BT202506148765\", \"total_amount\": 75000.00, \"booking_status\": \"pending\", \"passenger_name\": \"zidan\", \"payment_status\": \"pending\"}', 2, '2025-06-14 03:39:23'),
(8, 4, 'updated', '{\"updated_at\": \"2025-06-14 10:39:23.000000\", \"booking_status\": \"pending\", \"payment_status\": \"pending\"}', '{\"updated_at\": \"2025-06-14 10:39:25.000000\", \"booking_status\": \"confirmed\", \"payment_status\": \"paid\"}', 2, '2025-06-14 03:39:25'),
(9, 4, 'updated', '{\"updated_at\": \"2025-06-14 10:39:25.000000\", \"booking_status\": \"confirmed\", \"payment_status\": \"paid\"}', '{\"updated_at\": \"2025-06-14 10:39:37.000000\", \"booking_status\": \"cancelled\", \"payment_status\": \"refunded\"}', 2, '2025-06-14 03:39:37'),
(10, 5, 'created', NULL, '{\"created_at\": \"2025-06-14 10:41:27.000000\", \"booking_code\": \"BT202506140814\", \"total_amount\": 75000.00, \"booking_status\": \"pending\", \"passenger_name\": \"zidan\", \"payment_status\": \"pending\"}', 2, '2025-06-14 03:41:27'),
(11, 5, 'updated', '{\"updated_at\": \"2025-06-14 10:41:27.000000\", \"booking_status\": \"pending\", \"payment_status\": \"pending\"}', '{\"updated_at\": \"2025-06-14 10:41:42.000000\", \"booking_status\": \"confirmed\", \"payment_status\": \"paid\"}', 2, '2025-06-14 03:41:42');

-- --------------------------------------------------------

--
-- Table structure for table `buses`
--

CREATE TABLE `buses` (
  `id` int NOT NULL,
  `bus_number` varchar(20) NOT NULL,
  `bus_type` varchar(50) NOT NULL,
  `capacity` int NOT NULL,
  `facilities` text,
  `status` enum('active','maintenance','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `buses`
--

INSERT INTO `buses` (`id`, `bus_number`, `bus_type`, `capacity`, `facilities`, `status`, `created_at`) VALUES
(1, 'B001', 'Executive', 40, 'AC, Reclining Seats, WiFi, Entertainment', 'active', '2025-06-14 03:19:49'),
(2, 'B002', 'Economy', 50, 'AC, Standard Seats', 'active', '2025-06-14 03:19:49'),
(3, 'B003', 'VIP', 30, 'AC, Luxury Seats, WiFi, Meals, Entertainment', 'active', '2025-06-14 03:19:49'),
(4, 'B004', 'Executive', 40, 'AC, Reclining Seats, WiFi', 'active', '2025-06-14 03:19:49');

-- --------------------------------------------------------

--
-- Table structure for table `bus_routes`
--

CREATE TABLE `bus_routes` (
  `id` int NOT NULL,
  `route_name` varchar(100) NOT NULL,
  `origin` varchar(50) NOT NULL,
  `destination` varchar(50) NOT NULL,
  `distance_km` int NOT NULL,
  `duration_hours` decimal(3,1) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `bus_routes`
--

INSERT INTO `bus_routes` (`id`, `route_name`, `origin`, `destination`, `distance_km`, `duration_hours`, `created_at`) VALUES
(1, 'Jakarta - Bandung', 'Jakarta', 'Bandung', 150, '3.0', '2025-06-14 03:19:49'),
(2, 'Jakarta - Yogyakarta', 'Jakarta', 'Yogyakarta', 560, '8.5', '2025-06-14 03:19:49'),
(3, 'Bandung - Surabaya', 'Bandung', 'Surabaya', 720, '12.0', '2025-06-14 03:19:49'),
(4, 'Jakarta - Semarang', 'Jakarta', 'Semarang', 450, '7.0', '2025-06-14 03:19:49');

-- --------------------------------------------------------

--
-- Table structure for table `bus_schedules`
--

CREATE TABLE `bus_schedules` (
  `id` int NOT NULL,
  `bus_id` int NOT NULL,
  `route_id` int NOT NULL,
  `departure_time` time NOT NULL,
  `arrival_time` time NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `available_seats` int NOT NULL,
  `schedule_date` date NOT NULL,
  `status` enum('active','cancelled','completed') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `bus_schedules`
--

INSERT INTO `bus_schedules` (`id`, `bus_id`, `route_id`, `departure_time`, `arrival_time`, `price`, `available_seats`, `schedule_date`, `status`, `created_at`) VALUES
(1, 1, 1, '08:00:00', '11:00:00', '75000.00', 39, '2025-06-15', 'active', '2025-06-14 03:19:49'),
(2, 1, 1, '14:00:00', '17:00:00', '75000.00', 40, '2025-06-15', 'active', '2025-06-14 03:19:49'),
(3, 2, 2, '09:00:00', '17:30:00', '150000.00', 50, '2025-06-15', 'active', '2025-06-14 03:19:49'),
(4, 3, 3, '20:00:00', '08:00:00', '200000.00', 30, '2025-06-15', 'active', '2025-06-14 03:19:49'),
(5, 4, 4, '10:00:00', '17:00:00', '120000.00', 40, '2025-06-15', 'active', '2025-06-14 03:19:49');

-- --------------------------------------------------------

--
-- Table structure for table `payment_transactions`
--

CREATE TABLE `payment_transactions` (
  `id` int NOT NULL,
  `booking_id` int NOT NULL,
  `transaction_code` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `payment_status` enum('pending','success','failed','cancelled') DEFAULT 'pending',
  `payment_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `payment_transactions`
--

INSERT INTO `payment_transactions` (`id`, `booking_id`, `transaction_code`, `amount`, `payment_method`, `payment_status`, `payment_date`, `created_at`) VALUES
(1, 2, 'TRX202506140321171282', '75000.00', 'e_wallet', 'success', '2025-06-14 03:21:17', '2025-06-14 03:21:17'),
(2, 3, 'TRX202506140336557335', '75000.00', 'credit_card', 'success', '2025-06-14 03:36:55', '2025-06-14 03:36:55'),
(3, 4, 'TRX202506140339256855', '75000.00', 'bank_transfer', 'success', '2025-06-14 03:39:25', '2025-06-14 03:39:25'),
(4, 5, 'TRX202506140341428103', '75000.00', 'virtual_account', 'success', '2025-06-14 03:41:42', '2025-06-14 03:41:42');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `phone`, `role`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@bustix.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', NULL, 'admin', '2025-06-14 03:19:49', '2025-06-14 03:19:49'),
(2, 'zidan', 'zidanrosyid22@gmail.com', '$2y$10$8LR2B3ezO0uKHO0A27RA2ujSGiUGCbMQS8A7mpb1Fx4wqvkEq7rMC', 'zidan', '08111047304', 'user', '2025-06-14 03:20:49', '2025-06-14 03:20:49');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_code` (`booking_code`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `schedule_id` (`schedule_id`);

--
-- Indexes for table `booking_history`
--
ALTER TABLE `booking_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `action_by` (`action_by`);

--
-- Indexes for table `buses`
--
ALTER TABLE `buses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bus_number` (`bus_number`);

--
-- Indexes for table `bus_routes`
--
ALTER TABLE `bus_routes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bus_schedules`
--
ALTER TABLE `bus_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bus_id` (`bus_id`),
  ADD KEY `route_id` (`route_id`);

--
-- Indexes for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_code` (`transaction_code`),
  ADD KEY `booking_id` (`booking_id`);

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
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `booking_history`
--
ALTER TABLE `booking_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `buses`
--
ALTER TABLE `buses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `bus_routes`
--
ALTER TABLE `bus_routes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `bus_schedules`
--
ALTER TABLE `bus_schedules`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`schedule_id`) REFERENCES `bus_schedules` (`id`);

--
-- Constraints for table `booking_history`
--
ALTER TABLE `booking_history`
  ADD CONSTRAINT `booking_history_ibfk_1` FOREIGN KEY (`action_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `bus_schedules`
--
ALTER TABLE `bus_schedules`
  ADD CONSTRAINT `bus_schedules_ibfk_1` FOREIGN KEY (`bus_id`) REFERENCES `buses` (`id`),
  ADD CONSTRAINT `bus_schedules_ibfk_2` FOREIGN KEY (`route_id`) REFERENCES `bus_routes` (`id`);

--
-- Constraints for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  ADD CONSTRAINT `payment_transactions_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`);

DELIMITER $$
--
-- Events
--
CREATE DEFINER=`root`@`localhost` EVENT `daily_backup_event` ON SCHEDULE EVERY 1 DAY STARTS '2025-06-14 23:59:00' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
    -- Backup bookings table
    SET @backup_query = CONCAT(
        'SELECT * FROM bookings WHERE DATE(booking_date) = CURDATE() ',
        'INTO OUTFILE "/var/backups/bustix/bookings_', 
        DATE_FORMAT(NOW(), '%Y%m%d'), 
        '.csv" FIELDS TERMINATED BY "," ENCLOSED BY "\\"" LINES TERMINATED BY "\\n"'
    );
    
    -- Log backup activity
    INSERT INTO booking_history (booking_id, action_type, new_data, action_date)
    VALUES (0, 'created', JSON_OBJECT('backup_date', NOW(), 'type', 'daily_backup'), NOW());
END$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
