<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

class PaymentProcessor {
    private $pdo;
    private $config;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $configPath = __DIR__ . '/../config/payment_config.php';
        if (file_exists($configPath)) {
            $this->config = require_once $configPath;
        } else {
            $this->config = [];
        }
    }
    
    /**
     * Tạo giao dịch thanh toán mới
     */
    public function createTransaction($bookingId, $userId, $amount, $paymentMethod) {
        try {
            $transactionCode = $this->generateTransactionCode();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO payment_transactions 
                (booking_id, user_id, amount, payment_method, transaction_code, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$bookingId, $userId, $amount, $paymentMethod, $transactionCode]);
            
            $transactionId = $this->pdo->lastInsertId();
            
            $this->logPaymentActivity($transactionId, 'info', 'Payment transaction created', [
                'booking_id' => $bookingId,
                'amount' => $amount,
                'method' => $paymentMethod
            ]);
            
            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'transaction_code' => $transactionCode
            ];
        } catch (Exception $e) {
            $this->logPaymentActivity(null, 'error', 'Failed to create transaction: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Xử lý thanh toán qua ví nội bộ
     */
    public function processWalletPayment($userId, $transactionId, $amount) {
        try {
            $this->pdo->beginTransaction();
            
            // Check wallet balance
            $stmt = $this->pdo->prepare("SELECT balance FROM user_wallets WHERE user_id = ? FOR UPDATE");
            $stmt->execute([$userId]);
            $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$wallet || $wallet['balance'] < $amount) {
                throw new Exception('Số dư ví không đủ');
            }
            
            // Deduct balance
            $newBalance = $wallet['balance'] - $amount;
            $stmt = $this->pdo->prepare("UPDATE user_wallets SET balance = ? WHERE user_id = ?");
            $stmt->execute([$newBalance, $userId]);
            
            // Record wallet transaction
            $stmt = $this->pdo->prepare("
                INSERT INTO wallet_transactions 
                (user_id, transaction_type, amount, balance_before, balance_after, description, reference_id, reference_type) 
                VALUES (?, 'payment', ?, ?, ?, ?, ?, 'payment_transaction')
            ");
            $stmt->execute([$userId, -$amount, $wallet['balance'], $newBalance, 
                           "Thanh toán đơn hàng #{$transactionId}", $transactionId]);
            
            // Update payment transaction status
            $stmt = $this->pdo->prepare("
                UPDATE payment_transactions 
                SET status = 'success', paid_at = NOW() 
                WHERE transaction_id = ?
            ");
            $stmt->execute([$transactionId]);
            
            // Update booking payment status
            $stmt = $this->pdo->prepare("
                UPDATE bookings b 
                JOIN payment_transactions pt ON b.booking_id = pt.booking_id 
                SET b.payment_status = 'paid' 
                WHERE pt.transaction_id = ?
            ");
            $stmt->execute([$transactionId]);
            
            $this->pdo->commit();
            
            $this->logPaymentActivity($transactionId, 'info', 'Wallet payment successful');
            
            return ['success' => true, 'message' => 'Thanh toán thành công'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logPaymentActivity($transactionId, 'error', 'Wallet payment failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Xử lý thanh toán qua VNPay
     */
    public function processVNPayPayment($transactionId, $amount, $orderInfo) {
        if (!isset($this->config['vnpay'])) {
            return ['success' => false, 'error' => 'VNPay chưa được cấu hình'];
        }
        
        $vnp_TmnCode = $this->config['vnpay']['vnp_TmnCode'];
        $vnp_HashSecret = $this->config['vnpay']['vnp_HashSecret'];
        $vnp_Url = $this->config['vnpay']['vnp_Url'];
        $vnp_ReturnUrl = $this->config['vnpay']['vnp_ReturnUrl'];
        
        $vnp_TxnRef = $transactionId . '_' . time();
        $vnp_OrderInfo = $orderInfo;
        $vnp_OrderType = 'billpayment';
        $vnp_Amount = $amount * 100;
        $vnp_Locale = 'vn';
        $vnp_IpAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        $inputData = array(
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $vnp_TmnCode,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $vnp_IpAddr,
            "vnp_Locale" => $vnp_Locale,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => $vnp_OrderType,
            "vnp_ReturnUrl" => $vnp_ReturnUrl,
            "vnp_TxnRef" => $vnp_TxnRef
        );
        
        ksort($inputData);
        $hashdata = http_build_query($inputData, '', '&');
        $vnp_SecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
        
        $vnpUrl = $vnp_Url . '?' . $hashdata . '&vnp_SecureHash=' . $vnp_SecureHash;
        
        // Update transaction with VNPay reference
        $stmt = $this->pdo->prepare("
            UPDATE payment_transactions 
            SET payment_data = JSON_SET(COALESCE(payment_data, '{}'), '$.vnp_txn_ref', ?) 
            WHERE transaction_id = ?
        ");
        $stmt->execute([$vnp_TxnRef, $transactionId]);
        
        $this->logPaymentActivity($transactionId, 'info', 'VNPay payment initiated', ['vnp_url' => $vnpUrl]);
        
        return ['success' => true, 'payment_url' => $vnpUrl];
    }
    
    /**
     * Xử lý thanh toán qua MoMo
     */
    public function processMomoPayment($transactionId, $amount, $orderInfo) {
        if (!isset($this->config['momo'])) {
            return ['success' => false, 'error' => 'MoMo chưa được cấu hình'];
        }
        
        $partnerCode = $this->config['momo']['partnerCode'];
        $accessKey = $this->config['momo']['accessKey'];
        $secretKey = $this->config['momo']['secretKey'];
        $endpoint = $this->config['momo']['endpoint'];
        $returnUrl = $this->config['momo']['returnUrl'] ?? 'http://localhost/momo_return.php';
        $notifyUrl = $this->config['momo']['notifyUrl'] ?? 'http://localhost/momo_notify.php';
        
        $orderId = $transactionId . '_' . time();
        $requestId = $orderId;
        $extraData = "";
        
        $rawHash = "partnerCode=" . $partnerCode . 
                   "&accessKey=" . $accessKey . 
                   "&requestId=" . $requestId . 
                   "&amount=" . $amount . 
                   "&orderId=" . $orderId . 
                   "&orderInfo=" . $orderInfo . 
                   "&returnUrl=" . $returnUrl . 
                   "&notifyUrl=" . $notifyUrl . 
                   "&extraData=" . $extraData;
        
        $signature = hash_hmac('sha256', $rawHash, $secretKey);
        
        $data = [
            'partnerCode' => $partnerCode,
            'accessKey' => $accessKey,
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $orderId,
            'orderInfo' => $orderInfo,
            'returnUrl' => $returnUrl,
            'notifyUrl' => $notifyUrl,
            'extraData' => $extraData,
            'signature' => $signature
        ];
        
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return ['success' => false, 'error' => 'Không thể kết nối MoMo'];
        }
        
        $resultArray = json_decode($result, true);
        
        if (isset($resultArray['payUrl'])) {
            // Update transaction with MoMo reference
            $stmt = $this->pdo->prepare("
                UPDATE payment_transactions 
                SET payment_data = JSON_SET(COALESCE(payment_data, '{}'), '$.momo_order_id', ?) 
                WHERE transaction_id = ?
            ");
            $stmt->execute([$orderId, $transactionId]);
            
            $this->logPaymentActivity($transactionId, 'info', 'MoMo payment initiated');
            
            return ['success' => true, 'payment_url' => $resultArray['payUrl']];
        }
        
        $errorMsg = $resultArray['message'] ?? 'MoMo payment failed';
        return ['success' => false, 'error' => $errorMsg];
    }
    
    /**
     * Xử lý callback từ VNPay
     */
    public function handleVNPayCallback($vnp_ResponseCode, $vnp_TxnRef, $vnp_Amount, $vnp_SecureHash) {
        if (!isset($this->config['vnpay'])) {
            return ['success' => false, 'message' => 'VNPay chưa được cấu hình'];
        }
        
        $vnp_HashSecret = $this->config['vnpay']['vnp_HashSecret'];
        
        // Verify signature
        $inputData = [];
        foreach ($_GET as $key => $value) {
            if (substr($key, 0, 4) == "vnp_") {
                $inputData[$key] = $value;
            }
        }
        
        unset($inputData['vnp_SecureHash']);
        ksort($inputData);
        $hashdata = http_build_query($inputData, '', '&');
        $secureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
        
        if ($secureHash !== $vnp_SecureHash) {
            $this->logPaymentActivity(null, 'error', 'VNPay callback: Invalid signature');
            return ['success' => false, 'message' => 'Chữ ký không hợp lệ'];
        }
        
        // Extract transaction ID from TxnRef
        $transactionId = explode('_', $vnp_TxnRef)[0];
        
        if ($vnp_ResponseCode == '00') {
            // Payment successful
            $stmt = $this->pdo->prepare("
                UPDATE payment_transactions 
                SET status = 'success', 
                    paid_at = NOW(),
                    gateway_response = ?,
                    payment_data = JSON_SET(COALESCE(payment_data, '{}'), '$.vnp_response', ?)
                WHERE transaction_id = ?
            ");
            $stmt->execute([json_encode($_GET), json_encode($_GET), $transactionId]);
            
            // Update booking payment status
            $stmt = $this->pdo->prepare("
                UPDATE bookings b 
                JOIN payment_transactions pt ON b.booking_id = pt.booking_id 
                SET b.payment_status = 'paid' 
                WHERE pt.transaction_id = ?
            ");
            $stmt->execute([$transactionId]);
            
            $this->logPaymentActivity($transactionId, 'info', 'VNPay payment successful');
            
            return ['success' => true, 'message' => 'Thanh toán thành công'];
        } else {
            // Payment failed
            $stmt = $this->pdo->prepare("
                UPDATE payment_transactions 
                SET status = 'failed', 
                    gateway_response = ? 
                WHERE transaction_id = ?
            ");
            $stmt->execute([json_encode($_GET), $transactionId]);
            
            $this->logPaymentActivity($transactionId, 'error', "VNPay payment failed: Code {$vnp_ResponseCode}");
            
            return ['success' => false, 'message' => 'Thanh toán thất bại'];
        }
    }
    
    /**
     * Xử lý nạp tiền vào ví
     */
    public function rechargeWallet($userId, $amount, $paymentMethod) {
        try {
            $this->pdo->beginTransaction();
            
            // Create recharge transaction
            $transactionCode = $this->generateTransactionCode();
            $stmt = $this->pdo->prepare("
                INSERT INTO payment_transactions 
                (user_id, amount, payment_method, transaction_code, status, type, created_at) 
                VALUES (?, ?, ?, ?, 'pending', 'recharge', NOW())
            ");
            $stmt->execute([$userId, $amount, $paymentMethod, $transactionCode]);
            $transactionId = $this->pdo->lastInsertId();
            
            $this->pdo->commit();
            
            // Process based on payment method
            if ($paymentMethod === 'vnpay') {
                return $this->processVNPayPayment($transactionId, $amount, "Nạp tiền vào ví BadmintonPro");
            } elseif ($paymentMethod === 'momo') {
                return $this->processMomoPayment($transactionId, $amount, "Nạp tiền vào ví");
            } else {
                return [
                    'success' => true,
                    'transaction_id' => $transactionId,
                    'transaction_code' => $transactionCode
                ];
            }
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logPaymentActivity(null, 'error', 'Recharge failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Hoàn tiền
     */
    public function processRefund($transactionId, $reason, $adminId = null) {
        try {
            $this->pdo->beginTransaction();
            
            // Get transaction details
            $stmt = $this->pdo->prepare("
                SELECT pt.*, b.user_id as booking_user_id 
                FROM payment_transactions pt 
                LEFT JOIN bookings b ON pt.booking_id = b.booking_id 
                WHERE pt.transaction_id = ?
            ");
            $stmt->execute([$transactionId]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$transaction) {
                throw new Exception('Giao dịch không tồn tại');
            }
            
            if ($transaction['status'] !== 'success') {
                throw new Exception('Chỉ có thể hoàn tiền cho giao dịch thành công');
            }
            
            // Update transaction status
            $stmt = $this->pdo->prepare("
                UPDATE payment_transactions 
                SET status = 'refunded', 
                    refunded_at = NOW(),
                    refund_reason = ? 
                WHERE transaction_id = ?
            ");
            $stmt->execute([$reason, $transactionId]);
            
            // Refund to wallet if payment was via wallet
            if ($transaction['payment_method'] === 'wallet') {
                $userId = $transaction['user_id'];
                $stmt = $this->pdo->prepare("SELECT balance FROM user_wallets WHERE user_id = ? FOR UPDATE");
                $stmt->execute([$userId]);
                $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $newBalance = $wallet['balance'] + $transaction['amount'];
                $stmt = $this->pdo->prepare("UPDATE user_wallets SET balance = ? WHERE user_id = ?");
                $stmt->execute([$newBalance, $userId]);
                
                $stmt = $this->pdo->prepare("
                    INSERT INTO wallet_transactions 
                    (user_id, transaction_type, amount, balance_before, balance_after, description, reference_id, reference_type) 
                    VALUES (?, 'refund', ?, ?, ?, ?, ?, 'payment_transaction')
                ");
                $stmt->execute([$userId, $transaction['amount'], $wallet['balance'], $newBalance, 
                               "Hoàn tiền giao dịch #{$transactionId}", $transactionId]);
            }
            
            $this->pdo->commit();
            
            $this->logPaymentActivity($transactionId, 'info', "Refund processed: {$reason}");
            
            return ['success' => true, 'message' => 'Hoàn tiền thành công'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logPaymentActivity($transactionId, 'error', 'Refund failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Tạo mã giao dịch duy nhất
     */
    private function generateTransactionCode() {
        return 'TXN_' . date('YmdHis') . '_' . bin2hex(random_bytes(8));
    }
    
    /**
     * Ghi log hoạt động thanh toán
     */
    private function logPaymentActivity($transactionId, $level, $message, $data = null) {
        // Kiểm tra bảng payment_logs có tồn tại không
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO payment_logs 
                (transaction_id, log_level, log_message, log_data, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $transactionId,
                $level,
                $message,
                $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (Exception $e) {
            // Bỏ qua lỗi log, không ảnh hưởng chính
        }
    }
    
    /**
     * Lấy lịch sử giao dịch của người dùng
     */
    public function getUserTransactions($userId, $limit = 50, $offset = 0) {
        $stmt = $this->pdo->prepare("
            SELECT pt.*, b.court_id, c.court_name 
            FROM payment_transactions pt
            LEFT JOIN bookings b ON pt.booking_id = b.booking_id
            LEFT JOIN courts c ON b.court_id = c.court_id
            WHERE pt.user_id = ?
            ORDER BY pt.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Lấy thống kê thanh toán
     */
    public function getPaymentStats($userId = null) {
        $where = $userId ? "WHERE user_id = ?" : "";
        $params = $userId ? [$userId] : [];
        
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_transactions,
                COALESCE(SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END), 0) as total_success,
                COALESCE(SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END), 0) as total_failed,
                COALESCE(SUM(CASE WHEN payment_method = 'wallet' AND status = 'success' THEN amount ELSE 0 END), 0) as wallet_payments,
                COALESCE(SUM(CASE WHEN payment_method = 'vnpay' AND status = 'success' THEN amount ELSE 0 END), 0) as vnpay_payments
            FROM payment_transactions
            {$where}
        ");
        $stmt->execute($params);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>