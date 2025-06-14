USE bustix;

-- Stored Procedure: buatBooking (Updated with better error handling)
DELIMITER //
DROP PROCEDURE IF EXISTS buatBooking //
CREATE PROCEDURE buatBooking(
    IN p_user_id INT,
    IN p_schedule_id INT,
    IN p_passenger_name VARCHAR(100),
    IN p_passenger_phone VARCHAR(15),
    IN p_seat_numbers TEXT,
    IN p_total_seats INT,
    OUT p_booking_code VARCHAR(20),
    OUT p_result VARCHAR(100)
)
BEGIN
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
END //
DELIMITER ;

-- Function: cekKetersediaan
DELIMITER //
CREATE FUNCTION cekKetersediaan(p_schedule_id INT, p_required_seats INT)
RETURNS VARCHAR(50)
READS SQL DATA
DETERMINISTIC
BEGIN
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
END //
DELIMITER ;

-- Function: generateBookingCode
DELIMITER //
CREATE FUNCTION generateBookingCode()
RETURNS VARCHAR(20)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_code VARCHAR(20);
    DECLARE v_exists INT DEFAULT 1;
    
    WHILE v_exists > 0 DO
        SET v_code = CONCAT('BT', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(FLOOR(RAND() * 10000), 4, '0'));
        SELECT COUNT(*) INTO v_exists FROM bookings WHERE booking_code = v_code;
    END WHILE;
    
    RETURN v_code;
END //
DELIMITER ;

-- Stored Procedure: batalkanBooking
DELIMITER //
CREATE PROCEDURE batalkanBooking(
    IN p_booking_id INT,
    IN p_user_id INT,
    OUT p_result VARCHAR(100)
)
BEGIN
    DECLARE v_booking_status VARCHAR(20);
    DECLARE v_schedule_id INT;
    DECLARE v_total_seats INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_result = 'ERROR: Transaction failed';
    END;

    START TRANSACTION;
    
    -- Get booking details
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
        -- Update booking status
        UPDATE bookings 
        SET booking_status = 'cancelled', payment_status = 'refunded'
        WHERE id = p_booking_id;
        
        SET p_result = 'SUCCESS: Booking cancelled successfully';
        COMMIT;
    END IF;
END //
DELIMITER ;
