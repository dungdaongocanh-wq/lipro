<?php
/**
 * Script nâng cấp mật khẩu MD5 → bcrypt (chạy 1 lần)
 * Đặt lại tất cả mật khẩu về Admin@123 và yêu cầu đổi lại
 *
 * CÁCH DÙNG: Truy cập URL này một lần trên trình duyệt khi đã đăng nhập admin
 * Sau khi chạy xong, hãy XÓA hoặc đổi tên file này để bảo mật!
 */

// Bảo vệ script: chỉ chạy từ CLI hoặc khi có key đúng
$secret_key = 'lipro_migrate_2024';
if (PHP_SAPI !== 'cli' && ($_GET['key'] ?? '') !== $secret_key) {
    die("Truy cập bị từ chối. Thêm ?key=$secret_key vào URL để chạy.");
}

require_once __DIR__ . '/config/database.php';

$conn = getDBConnection();

// Thêm cột password_version nếu chưa có
$conn->query("ALTER TABLE accounts ADD COLUMN IF NOT EXISTS password_version VARCHAR(10) DEFAULT 'md5'");

// Lấy tất cả tài khoản chưa được nâng cấp
$stmt = $conn->prepare("SELECT id, username FROM accounts WHERE password_version = 'md5' OR password_version IS NULL");
$stmt->execute();
$accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$default_pass = 'Admin@123';
$hashed       = password_hash($default_pass, PASSWORD_DEFAULT);

$updated = 0;
$upd = $conn->prepare("UPDATE accounts SET password=?, password_version='bcrypt' WHERE id=?");

foreach ($accounts as $acc) {
    $upd->bind_param("si", $hashed, $acc['id']);
    $upd->execute();
    $updated++;
    echo "✅ Đã nâng cấp: " . htmlspecialchars($acc['username']) . "<br>\n";
}
$upd->close();
$conn->close();

echo "<br><strong>Hoàn tất! Đã nâng cấp $updated tài khoản.</strong><br>\n";
echo "<strong>Mật khẩu mặc định: <code>$default_pass</code></strong><br>\n";
echo "<br><span style='color:red;'>⚠️ Hãy thông báo cho tất cả người dùng đổi mật khẩu ngay!</span><br>\n";
echo "<br><span style='color:red;'>⚠️ Hãy XÓA file này sau khi chạy xong!</span>\n";
