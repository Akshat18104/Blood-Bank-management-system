<?php
/**
 * Unified JSON API Controller
 * Blood Bank Management System (BBMS)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

// Seed initial sample donors if table is empty for immediate clinical demo readiness
if (($dr = $conn->query("SELECT COUNT(*) c FROM donors")) && (int)$dr->fetch_assoc()['c'] === 0) {
    $conn->query("INSERT IGNORE INTO donors (donor_card_no, name, blood_group, contact) VALUES
        ('DONOR-10482', 'Aarav Sharma', 'O+', '9876543210'),
        ('DONOR-20915', 'Priya Nair', 'A+', '9823456712'),
        ('DONOR-38410', 'Rohan Verma', 'B+', '9811223344'),
        ('DONOR-49201', 'Ananya Iyer', 'O-', '9765432109'),
        ('DONOR-57390', 'Vikram Rathore', 'AB+', '9898765432')");
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $_GET['action'] ?? ($input['action'] ?? 'session');
$groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit();
}

function require_auth($roles = []) {
    if (empty($_SESSION['user_id'])) respond(['ok' => false, 'error' => 'Unauthorized', 'unauthorized' => true], 401);
    if ($roles && !in_array($_SESSION['role'], (array)$roles, true)) {
        respond(['ok' => false, 'error' => 'Access denied for role ' . ($_SESSION['role'] ?? '')], 403);
    }
}

function get_inventory($conn, $groups) {
    $map = array_fill_keys($groups, 0);
    if ($res = $conn->query("SELECT blood_group, units_available FROM inventory")) {
        while ($r = $res->fetch_assoc()) $map[$r['blood_group']] = (int)$r['units_available'];
    }
    return $map;
}

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

if ($action === 'login') {
    $u = trim($input['username'] ?? '');
    $p = $input['password'] ?? '';
    if (!$u || !$p) respond(['ok' => false, 'error' => 'Please enter both username and password.']);

    $st = $conn->prepare("SELECT user_id, username, password, role FROM users WHERE username = ? LIMIT 1");
    $st->bind_param("s", $u);
    $st->execute();
    if ($user = $st->get_result()->fetch_assoc()) {
        $valid = password_verify($p, $user['password']) || $user['password'] === $p;
        if ($valid) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $routes = ['Admin' => 'admin/dashboard.html', 'Staff' => 'staff/dashboard.html', 'Medical Staff' => 'medical_staff/dashboard.html'];
            respond(['ok' => true, 'user' => $_SESSION, 'redirect' => $routes[$user['role']] ?? 'index.html']);
        }
    }
    respond(['ok' => false, 'error' => 'Invalid username or password. Please try again.']);
}

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    respond(['ok' => true, 'redirect' => '../index.html?msg=logged_out']);
}

if ($action === 'data') {
    require_auth();
    $role = $_SESSION['role'];
    $uid = (int)$_SESSION['user_id'];
    $inv = get_inventory($conn, $groups);
    $total_stock = array_sum($inv);
    $total_donors = (int)$conn->query("SELECT COUNT(*) c FROM donors")->fetch_assoc()['c'];
    $today_donations = (int)$conn->query("SELECT COUNT(*) c FROM donors WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['c'];
    $total_users = (int)$conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'];

    // Request counts
    $counts = ['All' => 0, 'Pending' => 0, 'Accepted' => 0, 'Rejected' => 0, 'Cancelled' => 0];
    $where_req = ($role === 'Medical Staff') ? "WHERE requested_by = $uid" : "";
    if ($rc = $conn->query("SELECT status, COUNT(*) c FROM blood_requests $where_req GROUP BY status")) {
        while ($r = $rc->fetch_assoc()) {
            $counts[$r['status']] = (int)$r['c'];
            $counts['All'] += (int)$r['c'];
        }
    }

    // Requests list
    $requests = [];
    $rq_sql = "SELECT br.request_id, br.requested_by, br.blood_group, br.units_requested, br.status, br.request_date, COALESCE(u.username, CONCAT('User #', br.requested_by)) AS username
               FROM blood_requests br LEFT JOIN users u ON br.requested_by = u.user_id $where_req ORDER BY br.request_id DESC";
    if ($rr = $conn->query($rq_sql)) {
        while ($row = $rr->fetch_assoc()) $requests[] = $row;
    }

    // Donors list
    $donors = [];
    if ($dr = $conn->query("SELECT donor_id, donor_card_no, name, blood_group, contact, created_at FROM donors ORDER BY donor_id DESC")) {
        while ($row = $dr->fetch_assoc()) $donors[] = $row;
    }

    // Users list (for Admin)
    $users = [];
    if ($role === 'Admin' && ($ur = $conn->query("SELECT user_id, username, role, created_at FROM users ORDER BY user_id ASC"))) {
        while ($row = $ur->fetch_assoc()) $users[] = $row;
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

if ($action === 'add_user') {
    require_auth('Admin');
    $u = trim($input['username'] ?? '');
    $p = $input['password'] ?? '';
    $r = trim($input['role'] ?? '');
    if (strlen($u) < 3 || strlen($p) < 4 || !in_array($r, ['Admin', 'Staff', 'Medical Staff'], true)) {
        respond(['ok' => false, 'error' => 'Provide valid username (3+ chars), password (4+ chars), and role.']);
    }
    $h = password_hash($p, PASSWORD_DEFAULT);
    $st = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $st->bind_param("sss", $u, $h, $r);
    if (!$st->execute()) respond(['ok' => false, 'error' => 'Username already exists or database error.']);
    respond(['ok' => true, 'message' => "System account '$u' ($r) created successfully!"]);
}

if ($action === 'delete_user') {
    require_auth('Admin');
    $del_id = (int)($input['user_id'] ?? 0);
    if ($del_id <= 0 || $del_id === (int)$_SESSION['user_id']) {
        respond(['ok' => false, 'error' => 'Cannot delete your own active account or invalid ID.']);
    }
    $st = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $st->bind_param("i", $del_id);
    $st->execute();
    respond(['ok' => true, 'message' => "User account #$del_id removed successfully."]);
}

if ($action === 'handle_request') {
    require_auth(['Admin', 'Staff']);
    $req_id = (int)($input['request_id'] ?? 0);
    $decision = $input['decision'] ?? '';
    $st = $conn->prepare("SELECT blood_group, units_requested, status FROM blood_requests WHERE request_id = ? LIMIT 1");
    $st->bind_param("i", $req_id);
    $st->execute();
    $req = $st->get_result()->fetch_assoc();
    if (!$req || $req['status'] !== 'Pending') respond(['ok' => false, 'error' => 'Request not found or already processed.']);

    if ($decision === 'accept') {
        $bg = $req['blood_group'];
        $units = (int)$req['units_requested'];
        $inv = get_inventory($conn, $groups);
        if (($inv[$bg] ?? 0) < $units) {
            respond(['ok' => false, 'error' => "Insufficient $bg stock! Available: " . ($inv[$bg] ?? 0) . " units, Requested: $units units."]);
        }
        $conn->begin_transaction();
        $u1 = $conn->prepare("UPDATE inventory SET units_available = units_available - ? WHERE blood_group = ? AND units_available >= ?");
        $u1->bind_param("isi", $units, $bg, $units);
        $u1->execute();
        $u2 = $conn->prepare("UPDATE blood_requests SET status = 'Accepted' WHERE request_id = ?");
        $u2->bind_param("i", $req_id);
        $u2->execute();
        $conn->commit();
        respond(['ok' => true, 'message' => "Request #$req_id approved! $units unit(s) of $bg dispatched."]);
    } elseif ($decision === 'reject') {
        $u2 = $conn->prepare("UPDATE blood_requests SET status = 'Rejected' WHERE request_id = ?");
        $u2->bind_param("i", $req_id);
        $u2->execute();
        respond(['ok' => true, 'message' => "Request #$req_id has been rejected."]);
    }
    respond(['ok' => false, 'error' => 'Invalid decision action.']);
}

if ($action === 'register_donor') {
    require_auth(['Staff', 'Admin']);
    $name = trim($input['name'] ?? '');
    $bg = trim($input['blood_group'] ?? '');
    $contact = trim($input['contact'] ?? '');
    if (!$name || !in_array($bg, $groups, true) || !preg_match('/^[0-9]{10}$/', $contact)) {
        respond(['ok' => false, 'error' => 'Enter a valid name, blood group, and 10-digit contact number.']);
    }
    $card = 'DONOR-' . mt_rand(10000, 99999);
    $st = $conn->prepare("INSERT INTO donors (donor_card_no, name, blood_group, contact) VALUES (?, ?, ?, ?)");
    $st->bind_param("ssss", $card, $name, $bg, $contact);
    if (!$st->execute()) respond(['ok' => false, 'error' => 'Failed to register donor. Please try again.']);
    respond(['ok' => true, 'donor_card_no' => $card, 'message' => "Donor '$name' registered! Card No: $card"]);
}

if ($action === 'lookup_donor') {
    require_auth(['Staff', 'Admin']);
    $card = trim($input['donor_card_no'] ?? ($_GET['card'] ?? ''));
    $st = $conn->prepare("SELECT donor_id, donor_card_no, name, blood_group, contact, created_at FROM donors WHERE donor_card_no = ? LIMIT 1");
    $st->bind_param("s", $card);
    $st->execute();
    if ($d = $st->get_result()->fetch_assoc()) respond(['ok' => true, 'donor' => $d]);
    respond(['ok' => false, 'error' => "No registered donor found with Card ID '$card'."]);
}

if ($action === 'confirm_donation') {
    require_auth(['Staff', 'Admin']);
    $card = trim($input['donor_card_no'] ?? '');
    $units = max(1, min(5, (int)($input['units'] ?? 1)));
    $st = $conn->prepare("SELECT name, blood_group FROM donors WHERE donor_card_no = ? LIMIT 1");
    $st->bind_param("s", $card);
    $st->execute();
    if (!($d = $st->get_result()->fetch_assoc())) respond(['ok' => false, 'error' => 'Donor card verification failed.']);
    $bg = $d['blood_group'];
    $up = $conn->prepare("INSERT INTO inventory (blood_group, units_available) VALUES (?, ?) ON DUPLICATE KEY UPDATE units_available = units_available + ?");
    $up->bind_param("sii", $bg, $units, $units);
    $up->execute();
    respond(['ok' => true, 'blood_group' => $bg, 'message' => "Donation logged! Added +$units unit(s) of $bg from {$d['name']} ($card)."]);
}

if ($action === 'request_blood') {
    require_auth(['Medical Staff', 'Admin']);
    $bg = trim($input['blood_group'] ?? '');
    $units = (int)($input['units_requested'] ?? 0);
    $uid = (int)$_SESSION['user_id'];
    if (!in_array($bg, $groups, true) || $units < 1 || $units > 10) {
        respond(['ok' => false, 'error' => 'Select a valid blood group and between 1 to 10 units.']);
    }
    $st = $conn->prepare("INSERT INTO blood_requests (requested_by, blood_group, units_requested, status) VALUES (?, ?, ?, 'Pending')");
    $st->bind_param("isi", $uid, $bg, $units);
    $st->execute();
    respond(['ok' => true, 'request_id' => $conn->insert_id, 'message' => "Requisition #$conn->insert_id for $units unit(s) of $bg submitted for lab review!"]);
}

if ($action === 'cancel_request') {
    require_auth(['Medical Staff', 'Admin']);
    $req_id = (int)($input['request_id'] ?? 0);
    $uid = (int)$_SESSION['user_id'];
    $role = $_SESSION['role'];

    if ($role === 'Medical Staff') {
        $st = $conn->prepare("SELECT blood_group, units_requested, status FROM blood_requests WHERE request_id = ? AND requested_by = ? LIMIT 1");
        $st->bind_param("ii", $req_id, $uid);
    } else {
        $st = $conn->prepare("SELECT blood_group, units_requested, status FROM blood_requests WHERE request_id = ? LIMIT 1");
        $st->bind_param("i", $req_id);
    }
    $st->execute();
    $req = $st->get_result()->fetch_assoc();
    if (!$req) respond(['ok' => false, 'error' => 'Request not found or access denied.']);
    if ($req['status'] !== 'Pending') respond(['ok' => false, 'error' => "Cannot cancel request; current status is '{$req['status']}'."]);

    $up = $conn->prepare("UPDATE blood_requests SET status = 'Cancelled' WHERE request_id = ?");
    $up->bind_param("i", $req_id);
    $up->execute();
    respond(['ok' => true, 'message' => "Request #$req_id for {$req['units_requested']} unit(s) of {$req['blood_group']} has been cancelled."]);
}

respond(['ok' => false, 'error' => 'Unknown API action.'], 400);
