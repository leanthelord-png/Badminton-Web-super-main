<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$transactionId = (int)($input['transaction_id'] ?? 0);

if (!$transactionId) {
    echo json_encode(['success' => false, 'message' => 'Thiếu transaction_id']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Lấy thông tin transaction
    $stmt = $pdo->prepare("
        SELECT pt.*, b.total_price 
        FROM payment_transactions pt
        JOIN bookings b ON pt.booking_id = b.booking_id
        WHERE pt.transaction_id = ? AND pt.user_id = ? AND pt.status = 'pending'
    ");
    $stmt->execute([$transactionId, $userId]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        throw new Exception('Giao dịch không hợp lệ');
    }
    
    // Cập nhật transaction thành công (chờ admin xác nhận thực tế)
    $stmt = $pdo->prepare("UPDATE payment_transactions SET status = 'processing' WHERE transaction_id = ?");
    $stmt->execute([$transactionId]);
    
    // Cập nhật booking thành confirmed
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed', payment_status = 'paid' WHERE booking_id = ?");
    $stmt->execute([$transaction['booking_id']]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Xác nhận thành công, chúng tôi sẽ kiểm tra và xử lý']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>