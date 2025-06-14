-- Task Scheduler untuk backup otomatis
-- Note: Ini memerlukan konfigurasi di level sistem MySQL/MariaDB

-- Event untuk backup otomatis setiap hari jam 23:59
DELIMITER //
CREATE EVENT IF NOT EXISTS daily_backup_event
ON SCHEDULE EVERY 1 DAY
STARTS CONCAT(CURDATE(), ' 23:59:00')
DO
BEGIN
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
END //
DELIMITER ;

-- Enable event scheduler
SET GLOBAL event_scheduler = ON;
