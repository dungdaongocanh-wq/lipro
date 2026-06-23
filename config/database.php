<?php
// Cấu hình kết nối database
define('DB_HOST', 'localhost');
define('DB_USER', 'liprolog_user');
define('DB_PASS', 'dung@123A');
define('DB_NAME', 'liprolog_forwarder');

// Tạo kết nối
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Kết nối thất bại: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Bắt đầu session
session_start();

// ─────────────────────────────────────────
// HÀM XÁC THỰC & PHÂN QUYỀN
// ─────────────────────────────────────────

// Kiểm tra đăng nhập, nếu chưa → redirect login
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: /lipro/login.php");
        exit();
    }
}

// Kiểm tra quyền admin, nếu không → dừng hẳn
function checkAdmin() {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        die("Bạn không có quyền truy cập!");
    }
}

// Có phải admin không? (true/false)
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Có phải supplier (nhà cung cấp) không? (true/false)
function isSupplier() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'supplier';
}

// Có phải admin hoặc staff (user) không? (true/false)
function isAdminOrStaff() {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'user']);
}

// Lấy role hiện tại
function getCurrentRole() {
    return $_SESSION['role'] ?? '';
}

// Lấy user_id hiện tại
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Lấy tên hiện tại
function getCurrentUserName() {
    return $_SESSION['full_name'] ?? '';
}

// ─────────────────────────────────────────
// HÀM CSRF TOKEN
// ─────────────────────────────────────────

// Tạo hoặc lấy CSRF token hiện tại
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Xác thực CSRF token từ form POST
function verifyCsrfToken() {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// ─────────────────────────────────────────
// HÀM GHI NHẬT KÝ HOẠT ĐỘNG
// ─────────────────────────────────────────

function logActivity($conn, $action, $module, $description) {
    if (!isset($_SESSION['user_id'])) return;

    // Tự tạo bảng nếu chưa có
    $conn->query("CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        username VARCHAR(100),
        action VARCHAR(50),
        module VARCHAR(50),
        description TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $user_id     = intval($_SESSION['user_id']);
    $username    = $_SESSION['username'] ?? '';
    $ip_address  = $_SERVER['REMOTE_ADDR'] ?? '';

    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, username, action, module, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("isssss", $user_id, $username, $action, $module, $description, $ip_address);
        $stmt->execute();
        $stmt->close();
    }
}

// ─────────────────────────────────────────
// HÀM THÔNG BÁO
// ─────────────────────────────────────────

// Đếm số thông báo chưa đọc của user hiện tại
function getUnreadNotificationCount($conn) {
    if (!isset($_SESSION['user_id'])) return 0;

    // Tự tạo bảng nếu chưa có
    $conn->query("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(50) DEFAULT 'general',
        title VARCHAR(255) NOT NULL,
        message TEXT,
        related_id INT DEFAULT NULL,
        related_type VARCHAR(50) DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $uid  = intval($_SESSION['user_id']);
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM notifications WHERE user_id = ? AND is_read = 0");
    if (!$stmt) return 0;
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();
    if (!$res) return 0;
    return intval($res->fetch_assoc()['c'] ?? 0);
}

// Tạo thông báo mới
function createNotification($conn, $user_id, $type, $title, $message = null, $related_id = null, $related_type = null) {
    $conn->query("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(50) DEFAULT 'general',
        title VARCHAR(255) NOT NULL,
        message TEXT,
        related_id INT DEFAULT NULL,
        related_type VARCHAR(50) DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, related_id, related_type) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) return false;
    $stmt->bind_param("isssls", $user_id, $type, $title, $message, $related_id, $related_type);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// ─────────────────────────────────────────
// HÀM TẠO JOB NO
// ─────────────────────────────────────────

function generateJobNo($conn) {
    $year  = date('Y');
    $month = date('m');

    $stmt = $conn->prepare("SELECT counter FROM job_no_counter WHERE year = ? AND month = ?");
    $stmt->bind_param("ii", $year, $month);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row     = $result->fetch_assoc();
        $counter = $row['counter'] + 1;

        $stmt_update = $conn->prepare("UPDATE job_no_counter SET counter = ? WHERE year = ? AND month = ?");
        $stmt_update->bind_param("iii", $counter, $year, $month);
        $stmt_update->execute();
        $stmt_update->close();
    } else {
        $counter = 1;

        $stmt_insert = $conn->prepare("INSERT INTO job_no_counter (year, month, counter) VALUES (?, ?, ?)");
        $stmt_insert->bind_param("iii", $year, $month, $counter);
        $stmt_insert->execute();
        $stmt_insert->close();
    }

    $stmt->close();

    // Format: JOB-YYYYMM-0001
    return sprintf("JOB-%s%s-%04d", $year, $month, $counter);
}