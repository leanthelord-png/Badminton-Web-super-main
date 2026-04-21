<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../Badminton Web/config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = $_SESSION['user_id'] ?? ($_GET['user_id'] ?? 0);
$transactionId = (int)($_GET['transaction_id'] ?? 0);

if (!$userId || !$transactionId) {
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu dữ liệu'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT pt.*, b.start_time, b.end_time, b.total_price, c.court_name
        FROM payment_transactions pt
        JOIN bookings b ON pt.booking_id = b.booking_id
        JOIN courts c ON b.court_id = c.court_id
        WHERE pt.transaction_id = ? AND pt.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$transactionId, $userId]);
    $detail = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$detail) {
        throw new Exception('Không tìm thấy giao dịch');
    }

    echo json_encode([
        'success' => true,
        'data' => $detail
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi lấy chi tiết giao dịch',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}