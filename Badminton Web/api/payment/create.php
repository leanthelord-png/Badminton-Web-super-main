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
$bookingId = (int)($input['booking_id'] ?? 0);
$paymentMethod = $input['payment_method'] ?? '';

if (!$bookingId || !$paymentMethod) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin']);
    exit;
}

try {
    // Kiểm tra booking
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = ? AND user_id = ? AND status = 'pending'");
    $stmt->execute([$bookingId, $userId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking không hợp lệ']);
        exit;
    }
    
    // Tạo mã giao dịch
    $transactionCode = 'TXN_' . time() . '_' . $bookingId;
    
    // Tạo transaction mới
    $stmt = $pdo->prepare("
        INSERT INTO payment_transactions (booking_id, user_id, amount, payment_method, transaction_code, status, created_at)
        VALUES (?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$bookingId, $userId, $booking['total_price'], $paymentMethod, $transactionCode]);
    
    $transactionId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'transaction_id' => $transactionId,
        'transaction_code' => $transactionCode,
        'amount' => $booking['total_price']
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>