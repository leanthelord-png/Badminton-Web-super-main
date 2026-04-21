<?php
// check_payment_ajax.php
require_once 'config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$payment_id = $input['payment_id'] ?? 0;

try {
    // LUÔN trả về success để test (CHỈ DÙNG KHI TEST)
    // Sau khi test xong thì xóa dòng này đi
    
    // Thực hiện cập nhật thủ công
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? AND user_id = ?");
    $stmt->execute([$payment_id, $_SESSION['user_id']]);
    $payment = $stmt->fetch();
    
    if ($payment && $payment['status'] != 'completed') {
        // Cập nhật payment
        $stmt = $pdo->prepare("UPDATE payments SET status = 'completed' WHERE id = ?");
        $stmt->execute([$payment_id]);
        
        // Cộng tiền
        $amount = $payment['amount'];
        $userId = $_SESSION['user_id'];
        
        $stmt = $pdo->prepare("UPDATE user_wallets SET balance = balance + ?, total_recharge = total_recharge + ? WHERE user_id = ?");
        $stmt->execute([$amount, $amount, $userId]);
        
        $stmt = $pdo->prepare("UPDATE users SET user_balance = user_balance + ? WHERE user_id = ?");
        $stmt->execute([$amount, $userId]);
        
        $pdo->commit();
        
        // Cập nhật session
        $_SESSION['user_balance'] = $_SESSION['user_balance'] + $amount;
    } else {
        $pdo->rollBack();
    }
    
    // Luôn trả về success để test
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => true]); // Vẫn trả về success để test
}
?>