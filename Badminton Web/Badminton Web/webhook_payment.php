<?php
// webhook_payment.php - NHẬN CALLBACK TỪ MOMO/Ngân hàng
require_once 'config/database.php';

// Ghi log để debug
function writeLog($message) {
    $logFile = 'payment_webhook.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

writeLog("Webhook called: " . file_get_contents('php://input'));

// ========== XỬ LÝ CALLBACK TỪ MOMO ==========
function handleMomoCallback($data) {
    global $pdo;
    
    writeLog("Processing Momo callback: " . json_encode($data));
    
    $partnerCode = $data['partnerCode'] ?? '';
    $orderId = $data['orderId'] ?? '';
    $requestId = $data['requestId'] ?? '';
    $amount = $data['amount'] ?? 0;
    $resultCode = $data['resultCode'] ?? -1;
    $message = $data['message'] ?? '';
    $transId = $data['transId'] ?? '';
    
    if ($resultCode == 0) {
        // Thanh toán thành công
        try {
            $pdo->beginTransaction();
            
            // Tìm payment theo transaction_code
            $stmt = $pdo->prepare("SELECT * FROM payments WHERE transaction_code = ? AND status = 'pending'");
            $stmt->execute([$orderId]);
            $payment = $stmt->fetch();
            
            if ($payment) {
                // Cập nhật payment status
                $stmt = $pdo->prepare("UPDATE payments SET status = 'completed', updated_at = NOW() WHERE payment_id = ?");
                $stmt->execute([$payment['payment_id']]);
                
                // Cộng tiền vào ví
                $userId = $payment['user_id'];
                $amount = $payment['amount'];
                
                // Cập nhật user_wallets
                $stmt = $pdo->prepare("UPDATE user_wallets SET balance = balance + ?, total_recharge = total_recharge + ?, updated_at = NOW() WHERE user_id = ?");
                $stmt->execute([$amount, $amount, $userId]);
                
                // Cập nhật users
                $stmt = $pdo->prepare("UPDATE users SET user_balance = user_balance + ?, total_recharged = total_recharged + ? WHERE user_id = ?");
                $stmt->execute([$amount, $amount, $userId]);
                
                // Ghi vào transactions
                $stmt = $pdo->prepare("
                    INSERT INTO transactions (user_id, type, amount, status, description, created_at) 
                    VALUES (?, 'topup', ?, 'completed', 'Nạp tiền qua Momo - Mã GD: ' || ?, NOW())
                ");
                $stmt->execute([$userId, $amount, $transId]);
                
                writeLog("Topup successful for user $userId, amount $amount");
                
                $pdo->commit();
                
                // Trả về thành công cho Momo
                echo json_encode([
                    'partnerCode' => $partnerCode,
                    'orderId' => $orderId,
                    'requestId' => $requestId,
                    'resultCode' => 0,
                    'message' => 'Success'
                ]);
                return true;
            } else {
                writeLog("Payment not found for transaction_code: $orderId");
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            writeLog("Error: " . $e->getMessage());
        }
    } else {
        writeLog("Payment failed: $message (code: $resultCode)");
        
        // Cập nhật payment status thành failed
        $stmt = $pdo->prepare("UPDATE payments SET status = 'failed' WHERE transaction_code = ?");
        $stmt->execute([$orderId]);
    }
    
    // Trả về response cho Momo
    echo json_encode([
        'partnerCode' => $partnerCode,
        'orderId' => $orderId,
        'requestId' => $requestId,
        'resultCode' => 0,
        'message' => 'Received'
    ]);
    return false;
}

// ========== XỬ LÝ CALLBACK TỪ NGÂN HÀNG (VietQR) ==========
function handleBankCallback($data) {
    global $pdo;
    
    writeLog("Processing Bank callback: " . json_encode($data));
    
    // Cấu trúc callback từ ngân hàng (tùy theo ngân hàng)
    $transactionCode = $data['transaction_code'] ?? $data['orderId'] ?? '';
    $amount = $data['amount'] ?? 0;
    $status = $data['status'] ?? '';
    $bankRef = $data['reference'] ?? '';
    
    if ($status == 'success' || $status == '00') {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("SELECT * FROM payments WHERE transaction_code = ? AND status = 'pending'");
            $stmt->execute([$transactionCode]);
            $payment = $stmt->fetch();
            
            if ($payment) {
                $stmt = $pdo->prepare("UPDATE payments SET status = 'completed', updated_at = NOW() WHERE payment_id = ?");
                $stmt->execute([$payment['payment_id']]);
                
                $userId = $payment['user_id'];
                $amount = $payment['amount'];
                
                $stmt = $pdo->prepare("UPDATE user_wallets SET balance = balance + ?, total_recharge = total_recharge + ?, updated_at = NOW() WHERE user_id = ?");
                $stmt->execute([$amount, $amount, $userId]);
                
                $stmt = $pdo->prepare("UPDATE users SET user_balance = user_balance + ?, total_recharged = total_recharged + ? WHERE user_id = ?");
                $stmt->execute([$amount, $amount, $userId]);
                
                $stmt = $pdo->prepare("
                    INSERT INTO transactions (user_id, type, amount, status, description, created_at) 
                    VALUES (?, 'topup', ?, 'completed', 'Nạp tiền qua Chuyển khoản - Mã: ' || ?, NOW())
                ");
                $stmt->execute([$userId, $amount, $bankRef]);
                
                $pdo->commit();
                
                echo json_encode(['code' => '00', 'message' => 'Success']);
                return true;
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            writeLog("Error: " . $e->getMessage());
        }
    }
    
    echo json_encode(['code' => '01', 'message' => 'Failed']);
    return false;
}

// ========== NHẬN DỮ LIỆU TỪ CALLBACK ==========
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    // Nếu không phải JSON, thử parse form data
    $data = $_POST;
}

// Xác định loại callback
if (isset($data['partnerCode'])) {
    // Callback từ Momo
    handleMomoCallback($data);
} elseif (isset($data['transaction_code']) || isset($data['orderId'])) {
    // Callback từ ngân hàng
    handleBankCallback($data);
} else {
    writeLog("Unknown callback format: " . $input);
    echo json_encode(['code' => '99', 'message' => 'Unknown format']);
}
?>