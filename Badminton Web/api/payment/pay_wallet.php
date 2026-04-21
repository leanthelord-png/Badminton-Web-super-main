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
    
    $totalPrice = (float)$transaction['total_price'];
    
    // Lấy số dư hiện tại
    $stmt = $pdo->prepare("SELECT user_balance FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $balance = (float)$stmt->fetchColumn();
    
    if ($balance < $totalPrice) {
        throw new Exception('Số dư không đủ');
    }
    
    // Trừ tiền
    $stmt = $pdo->prepare("UPDATE users SET user_balance = user_balance - ?, total_spent = total_spent + ? WHERE user_id = ?");
    $stmt->execute([$totalPrice, $totalPrice, $userId]);
    
    // Cập nhật transaction
    $stmt = $pdo->prepare("UPDATE payment_transactions SET status = 'success', paid_at = NOW() WHERE transaction_id = ?");
    $stmt->execute([$transactionId]);
    
    // Cập nhật booking
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed', payment_status = 'paid' WHERE booking_id = ?");
    $stmt->execute([$transaction['booking_id']]);
    
    // Ghi lịch sử
    $stmt = $pdo->prepare("
        INSERT INTO pay_history (user_id, type, amount, before_balance, after_balance, description, created_at)
        VALUES (?, 'booking', ?, ?, ?, ?, NOW())
    ");
    $afterBalance = $balance - $totalPrice;
    $stmt->execute([$userId, $totalPrice, $balance, $afterBalance, 'Thanh toán đặt sân qua ví']);
    
    $pdo->commit();
    
    // Cập nhật session balance
    $_SESSION['user_balance'] = $afterBalance;
    
    echo json_encode(['success' => true, 'message' => 'Thanh toán thành công', 'new_balance' => $afterBalance]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>