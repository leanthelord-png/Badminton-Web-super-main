<?php
require_once __DIR__ . '/../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

$userId = $_SESSION['user_id'];
$bookingId = (int)($_POST['booking_id'] ?? 0);
$paymentMethod = $_POST['payment_method'] ?? 'wallet';

if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Thiếu booking_id']);
    exit;
}

try {
    // Lấy thông tin booking
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = ? AND user_id = ? AND status = 'pending'");
    $stmt->execute([$bookingId, $userId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy booking hoặc booking đã được xử lý']);
        exit;
    }
    
    $totalPrice = (float)$booking['total_price'];
    
    // Kiểm tra số dư
    $stmt = $pdo->prepare("SELECT user_balance FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $balance = (float)$stmt->fetchColumn();
    
    if ($balance < $totalPrice) {
        echo json_encode(['success' => false, 'message' => 'Số dư không đủ để thanh toán']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Trừ tiền từ ví user
    $stmt = $pdo->prepare("UPDATE users SET user_balance = user_balance - ?, total_spent = total_spent + ? WHERE user_id = ?");
    $stmt->execute([$totalPrice, $totalPrice, $userId]);
    
    // Cập nhật booking
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed', payment_status = 'paid' WHERE booking_id = ?");
    $stmt->execute([$bookingId]);
    
    // Ghi lịch sử giao dịch
    $stmt = $pdo->prepare("
        INSERT INTO transactions (user_id, type, amount, status, description, created_at) 
        VALUES (?, 'payment', ?, 'completed', ?, NOW())
    ");
    $stmt->execute([$userId, $totalPrice, 'Thanh toán đặt sân #' . $bookingId]);
    
    // Ghi vào pay_history
    $stmt = $pdo->prepare("
        INSERT INTO pay_history (user_id, type, amount, before_balance, after_balance, description, created_at) 
        VALUES (?, 'booking', ?, ?, ?, ?, NOW())
    ");
    $afterBalance = $balance - $totalPrice;
    $stmt->execute([$userId, $totalPrice, $balance, $afterBalance, 'Thanh toán đặt sân #' . $bookingId]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Thanh toán thành công']);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>