CREATE DATABASE IF NOT EXISTS bbms_db;
USE bbms_db;

-- 1. Users Table (Handles logins for Admin, Lab Staff, and Medical Staff)
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- Will store securely hashed passwords
    role ENUM('Admin', 'Staff', 'Medical Staff') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Donors Table (Captures donor details and unique ID)
CREATE TABLE donors (
    donor_id INT AUTO_INCREMENT PRIMARY KEY,
    donor_card_no VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    blood_group VARCHAR(5) NOT NULL,
    contact VARCHAR(15) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Inventory Table (Tracks real-time aggregate blood stock)
CREATE TABLE inventory (
    blood_group VARCHAR(5) PRIMARY KEY,
    units_available INT DEFAULT 0
);

-- Insert the standard blood groups with a default stock of 0
INSERT INTO inventory (blood_group, units_available) VALUES
('A+', 0), ('A-', 0), ('B+', 0), ('B-', 0),
('AB+', 0), ('AB-', 0), ('O+', 0), ('O-', 0);

-- 4. Blood Requests Table (Manages internal demands from the wards)
CREATE TABLE blood_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    requested_by INT,
    blood_group VARCHAR(5) NOT NULL,
    units_requested INT NOT NULL,
    status ENUM('Pending', 'Accepted', 'Rejected') DEFAULT 'Pending',
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requested_by) REFERENCES users(user_id) ON DELETE CASCADE
);


--   admin    / admin123    (Admin)
--   labstaff / staff123    (Staff)
--   doctor   / doctor123   (Medical Staff)
