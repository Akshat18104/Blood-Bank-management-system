<?php
/**
 * Unified JSON API Controller
 * Blood Bank Management System (BBMS)
 * 
 * This file handles all backend API requests sent from the frontend interfaces.
 * It manages authentication sessions, returns dashboard statistics and records,
 * and handles database updates for users, donors, donations, and blood requests.
 */

// Step 1: Start session to maintain user login state across requests
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// All responses from this API controller are returned as JSON
header('Content-Type: application/json; charset=utf-8');

// Include our database connection and auto-initialization script
require_once __DIR__ . '/../config/db.php';

// Seed sample donors if the table is currently empty
// This ensures donor lists and donation forms have sample data ready for testing immediately
if (($dr = $conn->query("SELECT COUNT(*) c FROM donors")) && (int)$dr->fetch_assoc()['c'] === 0) {
    $conn->query("INSERT IGNORE INTO donors (donor_card_no, name, blood_group, contact) VALUES
        ('DONOR-10482', 'Aarav Sharma', 'O+', '9876543210'),
        ('DONOR-20915', 'Priya Nair', 'A+', '9823456712'),
        ('DONOR-38410', 'Rohan Verma', 'B+', '9811223344'),
        ('DONOR-49201', 'Ananya Iyer', 'O-', '9765432109'),
        ('DONOR-57390', 'Vikram Rathore', 'AB+', '9898765432')");
}

// Parse input payload: supports both JSON bodies (from fetch) and regular POST submissions
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $_GET['action'] ?? ($input['action'] ?? 'session');

// Supported blood group types in the system
$groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

/**
 * Helper: Output JSON response and terminate script
 */
function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit();
}

/**
 * Helper: Verify that the user is logged in and belongs to an authorized role
 */
function require_auth($roles = []) {
    // If user is not logged in, return an HTTP 401 Unauthorized response
    if (empty($_SESSION['user_id'])) {
        respond(['ok' => false, 'error' => 'Unauthorized', 'unauthorized' => true], 401);
    }
    // If specific roles are required, ensure current user's role matches
    if ($roles && !in_array($_SESSION['role'], (array)$roles, true)) {
        respond(['ok' => false, 'error' => 'Access denied for role ' . ($_SESSION['role'] ?? '')], 403);
    }
}

/**
 * Helper: Retrieve available stock units for all 8 blood groups as an associative array
 */
function get_inventory($conn, $groups) {
    $map = array_fill_keys($groups, 0); // start with 0 for all groups
    if ($res = $conn->query("SELECT blood_group, units_available FROM inventory")) {
        while ($r = $res->fetch_assoc()) {
            $map[$r['blood_group']] = (int)$r['units_available'];
        }
    }
    return $map;
}

// =========================================================================
// API Action: session
// Checks if the user is currently signed in and returns their profile info
// =========================================================================
if ($action === 'session') {
    respond([
        'ok' => true,
        'logged_in' => !empty($_SESSION['user_id']),
        'user' => !empty($_SESSION['user_id']) ? [
            'user_id' => (int)$_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role']
        ] : null
    ]);
}

// =========================================================================
// API Action: login
// Authenticates credentials against the users table and starts a session
// =========================================================================
if ($action === 'login') {
    $u = trim($input['username'] ?? '');
    $p = $input['password'] ?? '';
    if (!$u || !$p) {
        respond(['ok' => false, 'error' => 'Please enter both username and password.']);
    }

    // Use a prepared statement to prevent SQL injection vulnerabilities
    $st = $conn->prepare("SELECT user_id, username, password, role FROM users WHERE username = ? LIMIT 1");
    $st->bind_param("s", $u);
    $st->execute();

    if ($user = $st->get_result()->fetch_assoc()) {
        // Validate password against bcrypt hash or fallback to direct comparison
        $valid = password_verify($p, $user['password']) || $user['password'] === $p;
        if ($valid) {
            session_regenerate_id(true); // secure session ID against fixation attacks
            $_SESSION['user_id'] = (int)$user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Map user role to the corresponding landing dashboard
            $routes = [
                'Admin' => 'admin/dashboard.html',
                'Staff' => 'staff/dashboard.html',
                'Medical Staff' => 'medical_staff/dashboard.html'
            ];
            respond(['ok' => true, 'user' => $_SESSION, 'redirect' => $routes[$user['role']] ?? 'index.html']);
        }
    }
    respond(['ok' => false, 'error' => 'Invalid username or password. Please try again.']);
}

// =========================================================================
// API Action: logout
// Clears session variables and destroys the session
// =========================================================================
if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    respond(['ok' => true, 'redirect' => '../index.html?msg=logged_out']);
}

// =========================================================================
// API Action: data
// Fetches all dashboard information: summary counters, inventory grid,
// blood request logs, registered donors, and system user accounts
// =========================================================================
if ($action === 'data') {
    require_auth(); // must be logged in to view dashboard data
    $role = $_SESSION['role'];
    $uid = (int)$_SESSION['user_id'];

    // 1. Fetch current blood inventory and compute total units across all types
    $inv = get_inventory($conn, $groups);
    $total_stock = array_sum($inv);

    // 2. Fetch database counters
    $total_donors = (int)$conn->query("SELECT COUNT(*) c FROM donors")->fetch_assoc()['c'];
    $today_donations = (int)$conn->query("SELECT COUNT(*) c FROM donors WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['c'];
    $total_users = (int)$conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'];

    // 3. Calculate status counts (Medical Staff only see their own requests; Admin & Staff see all)
    $counts = ['All' => 0, 'Pending' => 0, 'Accepted' => 0, 'Rejected' => 0];
    $where_req = ($role === 'Medical Staff') ? "WHERE requested_by = $uid" : "";
    if ($rc = $conn->query("SELECT status, COUNT(*) c FROM blood_requests $where_req GROUP BY status")) {
        while ($r = $rc->fetch_assoc()) {
            $counts[$r['status']] = (int)$r['c'];
            $counts['All'] += (int)$r['c'];
        }
    }

    // 4. Fetch blood request records with username of the requester
    $requests = [];
    $rq_sql = "SELECT br.request_id, br.requested_by, br.blood_group, br.units_requested, br.status, br.request_date, COALESCE(u.username, CONCAT('User #', br.requested_by)) AS username
               FROM blood_requests br LEFT JOIN users u ON br.requested_by = u.user_id $where_req ORDER BY br.request_id DESC";
    if ($rr = $conn->query($rq_sql)) {
        while ($row = $rr->fetch_assoc()) {
            $requests[] = $row;
        }
    }

    // 5. Fetch registered donor list (newest donors first)
    $donors = [];
    if ($dr = $conn->query("SELECT donor_id, donor_card_no, name, blood_group, contact, created_at FROM donors ORDER BY donor_id DESC")) {
        while ($row = $dr->fetch_assoc()) {
            $donors[] = $row;
        }
    }

    // 6. Fetch user accounts (Admin only)
    $users = [];
    if ($role === 'Admin' && ($ur = $conn->query("SELECT user_id, username, role, created_at FROM users ORDER BY user_id ASC"))) {
        while ($row = $ur->fetch_assoc()) {
            $users[] = $row;
        }
    }

    respond([
        'ok' => true,
        'user' => ['user_id' => $uid, 'username' => $_SESSION['username'], 'role' => $role],
        'stats' => [
            'total_donors' => $total_donors,
            'total_stock' => $total_stock,
            'today_donations' => $today_donations,
            'total_users' => $total_users,
            'counts' => $counts
        ],
        'inventory' => $inv,
        'requests' => $requests,
        'donors' => $donors,
        'users' => $users
    ]);
}

// =========================================================================
// API Action: add_user
// Admin creates a new user account (Admin, Staff, or Medical Staff)
// =========================================================================
if ($action === 'add_user') {
    require_auth('Admin'); // only administrators can create users
    $u = trim($input['username'] ?? '');
    $p = $input['password'] ?? '';
    $r = trim($input['role'] ?? '');

    // Validate inputs
    if (strlen($u) < 3 || strlen($p) < 4 || !in_array($r, ['Admin', 'Staff', 'Medical Staff'], true)) {
        respond(['ok' => false, 'error' => 'Provide valid username (3+ chars), password (4+ chars), and role.']);
    }

    // Securely hash the password before saving
    $h = password_hash($p, PASSWORD_DEFAULT);
    $st = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $st->bind_param("sss", $u, $h, $r);

    if (!$st->execute()) {
        respond(['ok' => false, 'error' => 'Username already exists or database error.']);
    }
    respond(['ok' => true, 'message' => "System account '$u' ($r) created successfully!"]);
}

// =========================================================================
// API Action: delete_user
// Admin deletes an existing user account
// =========================================================================
if ($action === 'delete_user') {
    require_auth('Admin'); // only administrators can delete users
    $del_id = (int)($input['user_id'] ?? 0);

    // Prevent deleting own currently active session account
    if ($del_id <= 0 || $del_id === (int)$_SESSION['user_id']) {
        respond(['ok' => false, 'error' => 'Cannot delete your own active account or invalid ID.']);
    }

    $st = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $st->bind_param("i", $del_id);
    $st->execute();

    respond(['ok' => true, 'message' => "User account #$del_id removed successfully."]);
}

// =========================================================================
// API Action: handle_request
// Staff or Admin approves or rejects a pending blood request
// When approving, it checks for sufficient stock and deducts the units
// =========================================================================
if ($action === 'handle_request') {
    require_auth(['Admin', 'Staff']);
    $req_id = (int)($input['request_id'] ?? 0);
    $decision = $input['decision'] ?? '';

    // Check that the request exists and is currently in Pending status
    $st = $conn->prepare("SELECT blood_group, units_requested, status FROM blood_requests WHERE request_id = ? LIMIT 1");
    $st->bind_param("i", $req_id);
    $st->execute();
    $req = $st->get_result()->fetch_assoc();

    if (!$req || $req['status'] !== 'Pending') {
        respond(['ok' => false, 'error' => 'Request not found or already processed.']);
    }

    // If approving: check stock, deduct from inventory, and mark Accepted
    if ($decision === 'accept') {
        $bg = $req['blood_group'];
        $units = (int)$req['units_requested'];
        $inv = get_inventory($conn, $groups);

        // Verify sufficient inventory stock is available
        if (($inv[$bg] ?? 0) < $units) {
            respond(['ok' => false, 'error' => "Insufficient $bg stock! Available: " . ($inv[$bg] ?? 0) . " units, Requested: $units units."]);
        }

        // Perform inventory deduction and status update atomically in a database transaction
        $conn->begin_transaction();
        $u1 = $conn->prepare("UPDATE inventory SET units_available = units_available - ? WHERE blood_group = ? AND units_available >= ?");
        $u1->bind_param("isi", $units, $bg, $units);
        $u1->execute();

        $u2 = $conn->prepare("UPDATE blood_requests SET status = 'Accepted' WHERE request_id = ?");
        $u2->bind_param("i", $req_id);
        $u2->execute();
        $conn->commit();

        respond(['ok' => true, 'message' => "Request #$req_id approved! $units unit(s) of $bg dispatched."]);

    // If rejecting: simply update the status to Rejected
    } elseif ($decision === 'reject') {
        $u2 = $conn->prepare("UPDATE blood_requests SET status = 'Rejected' WHERE request_id = ?");
        $u2->bind_param("i", $req_id);
        $u2->execute();
        respond(['ok' => true, 'message' => "Request #$req_id has been rejected."]);
    }

    respond(['ok' => false, 'error' => 'Invalid decision action.']);
}

// =========================================================================
// API Action: register_donor
// Registers a new blood donor with contact info and generates a donor card ID
// =========================================================================
if ($action === 'register_donor') {
    require_auth(['Staff', 'Admin']);
    $name = trim($input['name'] ?? '');
    $bg = trim($input['blood_group'] ?? '');
    $contact = trim($input['contact'] ?? '');

    // Validate inputs
    if (!$name || !in_array($bg, $groups, true) || !preg_match('/^[0-9]{10}$/', $contact)) {
        respond(['ok' => false, 'error' => 'Enter a valid name, blood group, and 10-digit contact number.']);
    }

    // Generate a unique 5-digit donor card number (e.g. DONOR-48192)
    $card = 'DONOR-' . mt_rand(10000, 99999);
    $st = $conn->prepare("INSERT INTO donors (donor_card_no, name, blood_group, contact) VALUES (?, ?, ?, ?)");
    $st->bind_param("ssss", $card, $name, $bg, $contact);

    if (!$st->execute()) {
        respond(['ok' => false, 'error' => 'Failed to register donor. Please try again.']);
    }
    respond(['ok' => true, 'donor_card_no' => $card, 'message' => "Donor '$name' registered! Card No: $card"]);
}

// =========================================================================
// API Action: lookup_donor
// Looks up an existing donor by their card number (used when logging donations)
// =========================================================================
if ($action === 'lookup_donor') {
    require_auth(['Staff', 'Admin']);
    $card = trim($input['donor_card_no'] ?? ($_GET['card'] ?? ''));

    $st = $conn->prepare("SELECT donor_id, donor_card_no, name, blood_group, contact, created_at FROM donors WHERE donor_card_no = ? LIMIT 1");
    $st->bind_param("s", $card);
    $st->execute();

    if ($d = $st->get_result()->fetch_assoc()) {
        respond(['ok' => true, 'donor' => $d]);
    }
    respond(['ok' => false, 'error' => "No registered donor found with Card ID '$card'."]);
}

// =========================================================================
// API Action: confirm_donation
// Records a blood donation for a registered donor and adds units to inventory
// =========================================================================
if ($action === 'confirm_donation') {
    require_auth(['Staff', 'Admin']);
    $card = trim($input['donor_card_no'] ?? '');
    $units = max(1, min(5, (int)($input['units'] ?? 1)));

    // Verify donor card exists
    $st = $conn->prepare("SELECT name, blood_group FROM donors WHERE donor_card_no = ? LIMIT 1");
    $st->bind_param("s", $card);
    $st->execute();

    if (!($d = $st->get_result()->fetch_assoc())) {
        respond(['ok' => false, 'error' => 'Donor card verification failed.']);
    }

    // Add donated units to current inventory stock
    $bg = $d['blood_group'];
    $up = $conn->prepare("INSERT INTO inventory (blood_group, units_available) VALUES (?, ?) ON DUPLICATE KEY UPDATE units_available = units_available + ?");
    $up->bind_param("sii", $bg, $units, $units);
    $up->execute();

    respond(['ok' => true, 'blood_group' => $bg, 'message' => "Donation logged! Added +$units unit(s) of $bg from {$d['name']} ($card)."]);
}

// =========================================================================
// API Action: request_blood
// Doctors / Medical Staff submit a requisition for blood units
// =========================================================================
if ($action === 'request_blood') {
    require_auth(['Medical Staff', 'Admin']);
    $bg = trim($input['blood_group'] ?? '');
    $units = (int)($input['units_requested'] ?? 0);
    $uid = (int)$_SESSION['user_id'];

    if (!in_array($bg, $groups, true) || $units < 1 || $units > 10) {
        respond(['ok' => false, 'error' => 'Select a valid blood group and between 1 to 10 units.']);
    }

    // Insert new requisition with 'Pending' status so staff can review it
    $st = $conn->prepare("INSERT INTO blood_requests (requested_by, blood_group, units_requested, status) VALUES (?, ?, ?, 'Pending')");
    $st->bind_param("isi", $uid, $bg, $units);
    $st->execute();

    respond(['ok' => true, 'request_id' => $conn->insert_id, 'message' => "Requisition #$conn->insert_id for $units unit(s) of $bg submitted for lab review!"]);
}

// Default response if an unhandled action is requested
respond(['ok' => false, 'error' => 'Unknown API action.'], 400);
