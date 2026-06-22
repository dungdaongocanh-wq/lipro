<?php
require_once '../config/database.php';
checkLogin();
checkAdmin();

$conn = getDBConnection();

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

// Bộ lọc
$filter_user   = trim($_GET['user']   ?? '');
$filter_module = trim($_GET['module'] ?? '');
$filter_date   = trim($_GET['date']   ?? '');

$where  = [];
$params = [];
$types  = '';

if ($filter_user) {
    $where[]  = 'username LIKE ?';
    $params[] = '%' . $filter_user . '%';
    $types   .= 's';
}
if ($filter_module) {
    $where[]  = 'module = ?';
    $params[] = $filter_module;
    $types   .= 's';
}
if ($filter_date) {
    $where[]  = 'DATE(created_at) = ?';
    $params[] = $filter_date;
    $types   .= 's';
}

$sql = "SELECT id, user_id, username, action, module, description, ip_address, created_at FROM activity_logs" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY created_at DESC LIMIT 200";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Lấy danh sách module cho bộ lọc
$modules_result = $conn->query("SELECT DISTINCT module FROM activity_logs ORDER BY module");
$modules = $modules_result ? $modules_result->fetch_all(MYSQLI_ASSOC) : [];

$conn->close();

$page_title = 'Nhật ký hoạt động';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nhật ký hoạt động - LIPRO LOGISTICS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/lipro/assets/css/custom.css">
</head>
<body>
    <?php
    $conn = getDBConnection();
    include '../partials/navbar.php';
    ?>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="bi bi-journal-text"></i> Nhật ký hoạt động</h4>
        </div>

        <!-- Bộ lọc -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Tìm theo người dùng</label>
                        <input type="text" name="user" class="form-control form-control-sm"
                               placeholder="Tên đăng nhập..."
                               value="<?php echo htmlspecialchars($filter_user); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Module</label>
                        <select name="module" class="form-select form-select-sm">
                            <option value="">-- Tất cả --</option>
                            <?php foreach ($modules as $m): ?>
                            <option value="<?php echo htmlspecialchars($m['module']); ?>"
                                    <?php echo $filter_module === $m['module'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m['module']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Ngày</label>
                        <input type="date" name="date" class="form-control form-control-sm"
                               value="<?php echo htmlspecialchars($filter_date); ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-search"></i> Lọc
                        </button>
                        <a href="activity.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-x-circle"></i> Xóa lọc
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bảng nhật ký -->
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Người dùng</th>
                                <th>Hành động</th>
                                <th>Module</th>
                                <th>Mô tả</th>
                                <th>IP</th>
                                <th>Thời gian</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                    Chưa có nhật ký hoạt động
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($logs as $i => $log): ?>
                            <tr>
                                <td class="text-muted small"><?php echo $i + 1; ?></td>
                                <td>
                                    <span class="fw-semibold"><?php echo htmlspecialchars($log['username']); ?></span>
                                    <?php if ($log['user_id']): ?>
                                    <small class="text-muted d-block">ID: <?php echo $log['user_id']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $action_colors = [
                                        'login'           => 'success',
                                        'logout'          => 'secondary',
                                        'create'          => 'primary',
                                        'update'          => 'warning',
                                        'delete'          => 'danger',
                                        'change_password' => 'info',
                                    ];
                                    $color = $action_colors[$log['action']] ?? 'light';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?> <?php echo in_array($color, ['warning', 'light']) ? 'text-dark' : ''; ?>">
                                        <?php echo htmlspecialchars($log['action']); ?>
                                    </span>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($log['module']); ?></span></td>
                                <td class="small"><?php echo htmlspecialchars($log['description']); ?></td>
                                <td class="small text-muted"><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                <td class="small text-muted"><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if (!empty($logs)): ?>
            <div class="card-footer text-muted small">
                Hiển thị <?php echo count($logs); ?> bản ghi gần nhất
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../partials/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
