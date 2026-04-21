<?php
// controllers/PaymentController.php

class PaymentController {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function topup() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php');
            exit;
        }
        
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['error'] = 'Vui lòng đăng nhập để nạp tiền';
            header('Location: index.php');
            exit;
        }
        
        $amount = floatval($_POST['amount'] ?? 0);
        $minAmount = 10000;
        $maxAmount = 10000000;
        
        if ($amount < $minAmount || $amount > $maxAmount) {
            $_SESSION['error'] = "Số tiền nạp phải từ " . number_format($minAmount) . " đến " . number_format($maxAmount) . " VNĐ";
            header('Location: index.php');
            exit;
        }
        
        $userId = $_SESSION['user_id'];
        
        try {
            // Lấy số dư hiện tại
            $stmt = $this->pdo->prepare("SELECT user_balance FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
            $before = floatval($row['user_balance'] ?? 0);
            $after = $before + $amount;
            
            $this->pdo->beginTransaction();
            
            // Cập nhật số dư
            $stmt = $this->pdo->prepare("UPDATE users SET user_balance = ? WHERE user_id = ?");
            $stmt->execute([$after, $userId]);
            
            // Ghi lịch sử
            $stmt = $this->pdo->prepare("INSERT INTO pay_history (user_id, type, amount, before_balance, after_balance, description) VALUES (?, 'topup', ?, ?, ?, 'Nạp tiền vào ví')");
            $stmt->execute([$userId, $amount, $before, $after]);
            
            $this->pdo->commit();
            
            $_SESSION['success'] = 'Nạp tiền thành công!';
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $_SESSION['error'] = 'Nạp tiền thất bại: ' . $e->getMessage();
        }
        
        header('Location: index.php');
        exit;
    }
}