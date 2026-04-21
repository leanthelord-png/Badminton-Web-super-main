<?php
require_once 'config/database.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $password = password_hash('123456', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, phone_number, email, role) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), role = VALUES(role)");
    $stmt->execute(['customer01', $password, 'Khách hàng 01', '0911000001', 'customer01@example.com', 'customer']);
    $stmt->execute(['owner01', $password, 'Chủ sân 01', '0911000002', 'owner01@example.com', 'owner']);

    $pdo->exec("INSERT INTO owners (user_id, court_count, revenue)
                SELECT user_id, 1, 0 FROM users WHERE username = 'owner01'
                ON DUPLICATE KEY UPDATE court_count = VALUES(court_count), revenue = VALUES(revenue)");

    $courtId = $pdo->query("SELECT court_id FROM courts ORDER BY court_id LIMIT 1")->fetchColumn();
    $customerId = $pdo->query("SELECT user_id FROM users WHERE username = 'customer01' LIMIT 1")->fetchColumn();

    if ($courtId && $customerId) {
        $bookingStmt = $pdo->prepare("INSERT INTO bookings (user_id, court_id, start_time, end_time, total_price, status) VALUES (?, ?, ?, ?, ?, ?)");
        $bookingStmt->execute([$customerId, $courtId, date('Y-m-d 18:00:00'), date('Y-m-d 20:00:00'), 240000, 'confirmed']);
    }

    echo json_encode(['success' => true, 'message' => 'Đã seed dữ liệu mẫu MySQL'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
