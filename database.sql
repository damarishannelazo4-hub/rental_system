-- Fresh installation / destructive reset: importing deletes existing rental data.
-- Back up an existing database before importing this file.
CREATE DATABASE IF NOT EXISTS rental_system CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE rental_system;

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS rentals;
DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS items;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    first_name VARCHAR(100) NOT NULL DEFAULT '',
    middle_name VARCHAR(100) NOT NULL DEFAULT '',
    last_name VARCHAR(100) NOT NULL DEFAULT '',
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','user') NOT NULL DEFAULT 'user',
    contact_number VARCHAR(30) DEFAULT '',
    address VARCHAR(255) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(100) NOT NULL,
    category VARCHAR(60) NOT NULL,
    price_per_day DECIMAL(10,2) NOT NULL DEFAULT 0,
    image VARCHAR(100) NOT NULL DEFAULT 'tent_square.jpg',
    total_qty INT NOT NULL DEFAULT 0,
    max_days INT NOT NULL DEFAULT 0,
    penalty_per_day DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('Available','Rented','Maintenance') NOT NULL DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE reservations (
    reservation_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    rental_date DATE NOT NULL,
    return_date DATE NOT NULL,
    status ENUM('Pending','Approved','Rejected','Cancelled','Completed') NOT NULL DEFAULT 'Pending',
    quantity INT NOT NULL DEFAULT 1,
    order_ref VARCHAR(100) DEFAULT NULL,
    id_photo VARCHAR(255) DEFAULT NULL,
    payment_method VARCHAR(30) NOT NULL DEFAULT 'Cash',
    payment_status VARCHAR(30) NOT NULL DEFAULT 'Unpaid',
    payment_ref VARCHAR(100) DEFAULT NULL,
    payment_proof VARCHAR(255) DEFAULT NULL,
    combo_id INT DEFAULT NULL,
    combo_discount DECIMAL(5,2) DEFAULT NULL,
    item_color VARCHAR(100) DEFAULT NULL,
    admin_seen TINYINT(1) NOT NULL DEFAULT 0,
    user_seen TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_res_order (order_ref),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_res_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_res_item FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE RESTRICT
);

CREATE TABLE rentals (
    rental_id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT DEFAULT NULL,
    user_id INT DEFAULT NULL,
    item_id INT NOT NULL,
    rental_date DATE NOT NULL,
    return_date DATE NOT NULL,
    actual_return_date DATE DEFAULT NULL,
    status ENUM('Ongoing','Returned') NOT NULL DEFAULT 'Ongoing',
    quantity INT NOT NULL DEFAULT 1,
    walk_in_name VARCHAR(100) DEFAULT NULL,
    walk_in_contact VARCHAR(30) DEFAULT NULL,
    payment_method VARCHAR(30) NOT NULL DEFAULT 'Cash',
    payment_status VARCHAR(30) NOT NULL DEFAULT 'Unpaid',
    combo_id INT DEFAULT NULL,
    combo_discount DECIMAL(5,2) DEFAULT NULL,
    item_color VARCHAR(100) DEFAULT NULL,
    rental_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
    combo_saved DECIMAL(12,2) NOT NULL DEFAULT 0,
    late_days INT NOT NULL DEFAULT 0,
    penalty DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
    admin_seen TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_rental_reservation (reservation_id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rent_res FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id) ON DELETE CASCADE,
    CONSTRAINT fk_rent_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_rent_item FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE RESTRICT
);

INSERT INTO users (full_name, username, password, role, contact_number, address) VALUES
('System Administrator','admin','$2y$12$WA/FNHMb85Y18e4SD.1.luHAbH7p8O92b6o6SP2MnIJYE/p8a3pl','admin','09123456789','Roxas, Oriental Mindoro'),
('Juan Dela Cruz','user','$2y$12$NsuA3GZ6mWh876BzMYFEuOPzY35K0a9EFLmqSxWSFSwxGPAeR6pXS','user','09987654321','Roxas, Oriental Mindoro');

INSERT INTO items (item_name, category, price_per_day, image, status) VALUES
('Tent Square','Event Equipment',500,'tent_square.jpg','Available'),
('Half Moon Table','Tables',250,'half_moon_table.jpg','Available'),
('Long Table (Buffet)','Tables',400,'long_table_buffet.jpg','Available'),
('Monoblock Chair','Chairs',25,'monoblock_chair.jpg','Available'),
('Backdrop','Decorations',800,'backdrop.jpg','Available'),
('Cleopatra Chair','Chairs',150,'cleopatra_chair.jpg','Available'),
('Pleated Tablecloth','Linen',100,'pleated_tablecloth.jpg','Available'),
('Utensils','Catering',50,'utensils.jpg','Available');
