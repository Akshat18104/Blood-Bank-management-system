<?php
/**
 * Database Configuration & Auto-Setup
 * Blood Bank Management System (BBMS)
 * 
 * This script connects to the MySQL server, creates the 'bbms_db' database and
 * all required tables if they do not exist, and populates initial sample data.
 */

// Step 1: Connect to the MySQL server (default XAMPP credentials: user 'root', no password)
$conn = @new mysqli('localhost', 'root', '');
if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['ok' => false, 'error' => 'Database connection failed: ' . $conn->connect_error]));
}

// Step 2: Create our database if it doesn't exist yet, then select it
$dbname = 'bbms_db';
$conn->query("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db($dbname);
$conn->set_charset('utf8mb4');

// Step 3: Create the Users table
// Stores login credentials and system roles: Admin, Staff, or Medical Staff (Doctor)
$conn->query("CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Staff', 'Medical Staff') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Step 4: Create the Donors table
// Stores donor details like blood group, contact number, and unique donor card ID
$conn->query("CREATE TABLE IF NOT EXISTS donors (
    donor_id INT AUTO_INCREMENT PRIMARY KEY,
    donor_card_no VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    blood_group VARCHAR(5) NOT NULL,
    contact VARCHAR(15) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Step 5: Create the Inventory table
// Keeps track of the currently available blood units for each of the 8 blood groups
$conn->query("CREATE TABLE IF NOT EXISTS inventory (
    blood_group VARCHAR(5) PRIMARY KEY,
    units_available INT DEFAULT 0
)");

// Step 6: Create the Blood Requests table
// Logs requisitions raised by medical staff with their status (Pending, Accepted, Rejected)
$conn->query("CREATE TABLE IF NOT EXISTS blood_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    requested_by INT,
    blood_group VARCHAR(5) NOT NULL,
    units_requested INT NOT NULL,
    status ENUM('Pending', 'Accepted', 'Rejected') DEFAULT 'Pending',
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requested_by) REFERENCES users(user_id) ON DELETE CASCADE
)");
@$conn->query("ALTER TABLE blood_requests MODIFY COLUMN status ENUM('Pending', 'Accepted', 'Rejected') DEFAULT 'Pending'");

// Step 7: Seed initial inventory stock if the inventory table is empty
// This ensures the dashboard always has realistic stock numbers ready on first run
if (($r = $conn->query("SELECT COUNT(*) c FROM inventory")) && (int)$r->fetch_assoc()['c'] === 0) {
    $conn->query("INSERT IGNORE INTO inventory (blood_group, units_available) VALUES
        ('A+', 14), ('A-', 4), ('B+', 18), ('B-', 3),
        ('AB+', 8), ('AB-', 2), ('O+', 22), ('O-', 6)");
}

// Step 8: Create default login accounts for each role
// - Admin:         admin / admin123
// - Lab Staff:     labstaff / staff123
// - Medical Staff: doctor / doctor123
// If the account already exists, we ensure its password hash is up to date.
foreach ([['admin', 'admin123', 'Admin'], ['labstaff', 'staff123', 'Staff'], ['doctor', 'doctor123', 'Medical Staff']] as $u) {
    $st = $conn->prepare("SELECT user_id, password FROM users WHERE username = ? LIMIT 1");
    $st->bind_param("s", $u[0]);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) {
        // If password is plain text or needs re-hashing, update it securely with bcrypt
        if ($row['password'] === $u[1] || strlen($row['password']) < 40) {
            $h = password_hash($u[1], PASSWORD_DEFAULT);
            $up = $conn->prepare("UPDATE users SET password = ?, role = ? WHERE user_id = ?");
            $up->bind_param("ssi", $h, $u[2], $row['user_id']);
            $up->execute();
        }
    } else {
        // Account doesn't exist yet, insert new user with hashed password
        $h = password_hash($u[1], PASSWORD_DEFAULT);
        $in = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $in->bind_param("sss", $u[0], $h, $u[2]);
        $in->execute();
    }
}
