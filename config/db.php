<?php
/**
 * Database Configuration & Auto-Initializer
 * Blood Bank Management System (BBMS)
 */
$conn = @new mysqli('localhost', 'root', '');
if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['ok' => false, 'error' => 'Database connection failed: ' . $conn->connect_error]));
}

$dbname = 'bbms_db';
$conn->query("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db($dbname);
$conn->set_charset('utf8mb4');

// Auto-create schema tables
$conn->query("CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Staff', 'Medical Staff') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS donors (
    donor_id INT AUTO_INCREMENT PRIMARY KEY,
    donor_card_no VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    blood_group VARCHAR(5) NOT NULL,
    contact VARCHAR(15) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS inventory (
    blood_group VARCHAR(5) PRIMARY KEY,
    units_available INT DEFAULT 0
)");

$conn->query("CREATE TABLE IF NOT EXISTS blood_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    requested_by INT,
    blood_group VARCHAR(5) NOT NULL,
    units_requested INT NOT NULL,
    status ENUM('Pending', 'Accepted', 'Rejected', 'Cancelled') DEFAULT 'Pending',
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requested_by) REFERENCES users(user_id) ON DELETE CASCADE
)");
@$conn->query("ALTER TABLE blood_requests MODIFY COLUMN status ENUM('Pending', 'Accepted', 'Rejected', 'Cancelled') DEFAULT 'Pending'");

// Seed default blood groups if empty
if (($r = $conn->query("SELECT COUNT(*) c FROM inventory")) && (int)$r->fetch_assoc()['c'] === 0) {
    $conn->query("INSERT IGNORE INTO inventory (blood_group, units_available) VALUES
        ('A+', 14), ('A-', 4), ('B+', 18), ('B-', 3),
        ('AB+', 8), ('AB-', 2), ('O+', 22), ('O-', 6)");
}

// Seed or repair default role accounts
foreach ([['admin', 'admin123', 'Admin'], ['labstaff', 'staff123', 'Staff'], ['doctor', 'doctor123', 'Medical Staff']] as $u) {
    $st = $conn->prepare("SELECT user_id, password FROM users WHERE username = ? LIMIT 1");
    $st->bind_param("s", $u[0]);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) {
        if ($row['password'] === $u[1] || strlen($row['password']) < 40) {
            $h = password_hash($u[1], PASSWORD_DEFAULT);
            $up = $conn->prepare("UPDATE users SET password = ?, role = ? WHERE user_id = ?");
            $up->bind_param("ssi", $h, $u[2], $row['user_id']);
            $up->execute();
        }
    } else {
        $h = password_hash($u[1], PASSWORD_DEFAULT);
        $in = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $in->bind_param("sss", $u[0], $h, $u[2]);
        $in->execute();
    }
}
