<?php
require_once 'config/database.php';

header('Content-Type: text/html; charset=utf-8');

function runSqlFile(PDO $pdo, string $filePath): void {
    if (!file_exists($filePath)) {
        throw new Exception('Không tìm thấy file schema: ' . $filePath);
    }
    $sql = file_get_contents($filePath);
    $pdo->exec($sql);
}

function seedData(PDO $pdo): void {
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $staffPassword = password_hash('staff123', PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        "INSERT INTO users (username, password_hash, full_name, phone_number, email, role)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), role = VALUES(role)"
    );

    $stmt->execute(['admin', $adminPassword, 'Quản trị viên', '0900000001', 'admin@example.com', 'admin']);
    $stmt->execute(['staff01', $staffPassword, 'Nhân viên 1', '0900000002', 'staff01@example.com', 'staff']);

    $courtStmt = $pdo->prepare(
        "INSERT INTO courts (court_name, court_type, price_per_hour, description, is_active)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE price_per_hour = VALUES(price_per_hour), description = VALUES(description), is_active = VALUES(is_active)"
    );

    $courtStmt->execute(['Sân A', 'indoor', 120000, 'Sân tiêu chuẩn trong nhà', 1]);
    $courtStmt->execute(['Sân B', 'indoor', 140000, 'Sân tiêu chuẩn trong nhà', 1]);

    $slotStmt = $pdo->prepare(
        "INSERT INTO pricing_slots (slot_name, start_hour, end_hour, day_type, multiplier, is_peak, description, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE multiplier = VALUES(multiplier), is_peak = VALUES(is_peak), description = VALUES(description), is_active = VALUES(is_active)"
    );

    $slotStmt->execute(['Sáng', 6, 12, 'weekday', 1.00, 0, 'Khung giờ buổi sáng', 1]);
    $slotStmt->execute(['Tối cao điểm', 18, 22, 'weekday', 1.25, 1, 'Khung giờ cao điểm buổi tối', 1]);
}

try {
    runSqlFile($pdo, __DIR__ . '/mysql_schema.sql');
    seedData($pdo);
    echo '<h2>✅ Thiết lập MySQL thành công</h2>';
    echo '<p>Đã tạo schema MySQL và dữ liệu mẫu cơ bản.</p>';
    echo '<p>Tài khoản mẫu: <strong>admin / admin123</strong></p>';
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h2>❌ Thiết lập thất bại</h2>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
}
