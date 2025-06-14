USE bustix;

-- Drop existing triggers if they exist
DROP TRIGGER IF EXISTS booking_history_trigger;
DROP TRIGGER IF EXISTS booking_delete_trigger;
DROP TRIGGER IF EXISTS payment_update_trigger;

-- Trigger: Log booking status changes
DELIMITER //
CREATE TRIGGER booking_history_trigger
AFTER UPDATE ON bookings
FOR EACH ROW
BEGIN
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
END //

-- Trigger: Log booking creation
CREATE TRIGGER booking_create_trigger
AFTER INSERT ON bookings
FOR EACH ROW
BEGIN
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
END //

-- Trigger: Log booking deletion and restore seats
CREATE TRIGGER booking_delete_trigger
BEFORE DELETE ON bookings
FOR EACH ROW
BEGIN
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
END //

-- Trigger: Update schedule seats when booking is cancelled
CREATE TRIGGER booking_cancel_trigger
AFTER UPDATE ON bookings
FOR EACH ROW
BEGIN
    IF OLD.booking_status != 'cancelled' AND NEW.booking_status = 'cancelled' THEN
        -- Return seats to schedule
        UPDATE bus_schedules 
        SET available_seats = available_seats + NEW.total_seats 
        WHERE id = NEW.schedule_id;
    END IF;
END //

DELIMITER ;
