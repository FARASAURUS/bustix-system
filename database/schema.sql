-- Database Schema untuk busTix
CREATE DATABASE IF NOT EXISTS bustix;
USE bustix;

-- Tabel Users
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(15),
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabel Bus Routes
CREATE TABLE bus_routes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    route_name VARCHAR(100) NOT NULL,
    origin VARCHAR(50) NOT NULL,
    destination VARCHAR(50) NOT NULL,
    distance_km INT NOT NULL,
    duration_hours DECIMAL(3,1) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Buses
CREATE TABLE buses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bus_number VARCHAR(20) UNIQUE NOT NULL,
    bus_type VARCHAR(50) NOT NULL,
    capacity INT NOT NULL,
    facilities TEXT,
    status ENUM('active', 'maintenance', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Bus Schedules
CREATE TABLE bus_schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bus_id INT NOT NULL,
    route_id INT NOT NULL,
    departure_time TIME NOT NULL,
    arrival_time TIME NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    available_seats INT NOT NULL,
    schedule_date DATE NOT NULL,
    status ENUM('active', 'cancelled', 'completed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bus_id) REFERENCES buses(id),
    FOREIGN KEY (route_id) REFERENCES bus_routes(id)
);

-- Tabel Bookings
CREATE TABLE bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    schedule_id INT NOT NULL,
    booking_code VARCHAR(20) UNIQUE NOT NULL,
    passenger_name VARCHAR(100) NOT NULL,
    passenger_phone VARCHAR(15) NOT NULL,
    seat_numbers TEXT NOT NULL,
    total_seats INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    booking_status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
    payment_method VARCHAR(50),
    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (schedule_id) REFERENCES bus_schedules(id)
);

-- Tabel Payment Transactions
CREATE TABLE payment_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    transaction_code VARCHAR(50) UNIQUE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    payment_status ENUM('pending', 'success', 'failed', 'cancelled') DEFAULT 'pending',
    payment_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id)
);

-- Tabel Booking History (untuk trigger)
CREATE TABLE booking_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    action_type ENUM('created', 'updated', 'cancelled', 'deleted') NOT NULL,
    old_data JSON,
    new_data JSON,
    action_by INT,
    action_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (action_by) REFERENCES users(id)
);

-- Insert default admin user
INSERT INTO users (username, email, password, full_name, role) VALUES 
('admin', 'admin@bustix.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');

-- Insert sample bus routes
INSERT INTO bus_routes (route_name, origin, destination, distance_km, duration_hours) VALUES
('Jakarta - Bandung', 'Jakarta', 'Bandung', 150, 3.0),
('Jakarta - Yogyakarta', 'Jakarta', 'Yogyakarta', 560, 8.5),
('Bandung - Surabaya', 'Bandung', 'Surabaya', 720, 12.0),
('Jakarta - Semarang', 'Jakarta', 'Semarang', 450, 7.0);

-- Insert sample buses
INSERT INTO buses (bus_number, bus_type, capacity, facilities) VALUES
('B001', 'Executive', 40, 'AC, Reclining Seats, WiFi, Entertainment'),
('B002', 'Economy', 50, 'AC, Standard Seats'),
('B003', 'VIP', 30, 'AC, Luxury Seats, WiFi, Meals, Entertainment'),
('B004', 'Executive', 40, 'AC, Reclining Seats, WiFi');

-- Insert sample bus schedules
INSERT INTO bus_schedules (bus_id, route_id, departure_time, arrival_time, price, available_seats, schedule_date) VALUES
(1, 1, '08:00:00', '11:00:00', 75000, 40, CURDATE() + INTERVAL 1 DAY),
(1, 1, '14:00:00', '17:00:00', 75000, 40, CURDATE() + INTERVAL 1 DAY),
(2, 2, '09:00:00', '17:30:00', 150000, 50, CURDATE() + INTERVAL 1 DAY),
(3, 3, '20:00:00', '08:00:00', 200000, 30, CURDATE() + INTERVAL 1 DAY),
(4, 4, '10:00:00', '17:00:00', 120000, 40, CURDATE() + INTERVAL 1 DAY);
