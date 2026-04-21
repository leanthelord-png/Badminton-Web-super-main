<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = $_SESSION['user_id'] ?? 0;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

try {
    // Lấy từ bảng users (đơn giản và chính xác)
    $stmt = $pdo->prepare("SELECT user_balance as balance, total_recharged, total_spent FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy người dùng']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'wallet' => [
            'balance' => (float)$user['balance'],
            'total_recharged' => (float)($user['total_recharged'] ?? 0),
            'total_spent' => (float)($user['total_spent'] ?? 0)
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>