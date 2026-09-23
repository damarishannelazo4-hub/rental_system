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
    image VARCHAR(100) NOT NULL DEFAULT 'tent_square.svg',
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_res_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_res_item FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE RESTRICT
);

CREATE TABLE rentals (
    rental_id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    rental_date DATE NOT NULL,
    return_date DATE NOT NULL,
    actual_return_date DATE DEFAULT NULL,
    status ENUM('Ongoing','Returned') NOT NULL DEFAULT 'Ongoing',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rent_res FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id) ON DELETE CASCADE,
    CONSTRAINT fk_rent_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_rent_item FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE RESTRICT
);

INSERT INTO users (full_name, username, password, role, contact_number, address) VALUES
('System Administrator','admin','$2y$12$WA/FNHMb85Y18e4SD.1.luHAbH7p8O92b6o6SP2MnIJYE/p8a3pl','admin','09123456789','Roxas, Oriental Mindoro'),
('Juan Dela Cruz','user','$2y$12$NsuA3GZ6mWh876BzMYFEuOPzY35K0a9EFLmqSxWSFSwxGPAeR6pXS','user','09987654321','Roxas, Oriental Mindoro');

INSERT INTO items (item_name, category, price_per_day, image, status) VALUES
('Tent Square','Event Equipment',500,'tent_square.svg','Available'),
('Half Moon Table','Tables',250,'half_moon_table.svg','Available'),
('Long Table (Buffet)','Tables',400,'long_table_buffet.svg','Available'),
('Monoblock Chair','Chairs',25,'monoblock_chair.svg','Available'),
('Backdrop','Decorations',800,'backdrop.svg','Available'),
('Cleopatra Chair','Chairs',150,'cleopatra_chair.svg','Available'),
('Pleated Tablecloth','Linen',100,'pleated_tablecloth.svg','Available'),
('Utensils','Catering',50,'utensils.svg','Available');
