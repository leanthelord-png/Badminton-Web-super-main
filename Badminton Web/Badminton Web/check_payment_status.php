<?php
// check_payment.php - Kiểm tra thanh toán thủ công
require_once 'config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// SỬA: Dùng 'id' thay vì 'payment_id'
$payment_id = $_GET['payment_id'] ?? 0;

try {
    // SỬA: Câu lệnh SQL dùng 'id'
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? AND user_id = ?");
    $stmt->execute([$payment_id, $_SESSION['user_id']]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        header('Location: index.php');
        exit;
    }
    
    // Nếu đã completed, cập nhật session và chuyển về
    if ($payment['status'] == 'completed') {
        $stmt = $pdo->prepare("SELECT user_balance FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $_SESSION['user_balance'] = $stmt->fetchColumn();
        
        header('Location: index.php?topup_success=1');
        exit;
    }
    
    // Xác nhận thủ công
    if (isset($_POST['confirm'])) {
        $pdo->beginTransaction();
        
        // Cập nhật payment
        $stmt = $pdo->prepare("UPDATE payments SET status = 'completed' WHERE id = ?");
        $stmt->execute([$payment_id]);
        
        // Cộng tiền
        $amount = $payment['amount'];
        $userId = $_SESSION['user_id'];
        
        $stmt = $pdo->prepare("UPDATE user_wallets SET balance = balance + ?, total_recharge = total_recharge + ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->execute([$amount, $amount, $userId]);
        
        $stmt = $pdo->prepare("UPDATE users SET user_balance = user_balance + ?, total_recharged = total_recharged + ? WHERE user_id = ?");
        $stmt->execute([$amount, $amount, $userId]);
        
        $stmt = $pdo->prepare("
            INSERT INTO transactions (user_id, type, amount, status, description, created_at) 
            VALUES (?, 'topup', ?, 'completed', 'Nạp tiền thủ công', NOW())
        ");
        $stmt->execute([$userId, $amount]);
        
        $pdo->commit();
        
        $_SESSION['user_balance'] += $amount;
        
        header('Location: index.php?topup_success=1');
        exit;
    }
    
} catch (Exception $e) {
    $message = "Lỗi: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Kiểm tra thanh toán</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white rounded-2xl p-8 max-w-md w-full">
            <h1 class="text-2xl font-bold mb-4">Kiểm tra thanh toán</h1>
            
            <div class="mb-6">
                <p><strong>Mã giao dịch:</strong> <?php echo $payment['transaction_code'] ?? $payment['transaction_id'] ?? 'N/A'; ?></p>
                <p><strong>Số tiền:</strong> <?php echo number_format($payment['amount'], 0, ',', '.'); ?> ₫</p>
                <p><strong>Phương thức:</strong> <?php echo $payment['payment_method']; ?></p>
                <p><strong>Trạng thái:</strong> 
                    <span class="inline-block px-2 py-1 rounded-full text-xs 
                        <?php echo $payment['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'; ?>">
                        <?php echo $payment['status'] == 'pending' ? 'Chờ thanh toán' : 'Đã thanh toán'; ?>
                    </span>
                </p>
            </div>
            
            <?php if ($payment['status'] == 'pending'): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <p class="text-sm text-yellow-800">
                        <i class="fas fa-clock mr-2"></i>
                        Hệ thống đang chờ xác nhận thanh toán từ ngân hàng.
                        Vui lòng chờ trong giây lát hoặc bấm "Xác nhận thủ công" nếu bạn đã chuyển khoản.
                    </p>
                </div>
                
                <form method="POST">
                    <button type="submit" name="confirm" 
                            class="w-full bg-green-600 text-white py-3 rounded-xl font-semibold hover:bg-green-700 transition"
                            onclick="return confirm('Bạn đã chuyển khoản thành công?')">
                        <i class="fas fa-check-circle mr-2"></i>
                        Tôi đã thanh toán (Xác nhận thủ công)
                    </button>
                </form>
            <?php else: ?>
                <a href="index.php" class="block w-full bg-green-600 text-white py-3 rounded-xl font-semibold text-center">
                    Quay về trang chủ
                </a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>