<?php
require_once 'config/database.php';
require_once 'includes/PaymentProcessor.php';
session_start();

header('Content-Type: application/json');

$paymentProcessor = new PaymentProcessor($pdo);
$response = ['success' => false, 'message' => ''];

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'create_payment':
        $bookingId = $_POST['booking_id'] ?? 0;
        $paymentMethod = $_POST['payment_method'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        
        if (!$bookingId || !$paymentMethod || $amount <= 0) {
            $response['message'] = 'Thông tin thanh toán không hợp lệ';
            echo json_encode($response);
            exit;
        }
        
        // Verify booking belongs to user
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = ? AND user_id = ?");
        $stmt->execute([$bookingId, $userId]);
        $booking = $stmt->fetch();
        
        if (!$booking) {
            $response['message'] = 'Đặt sân không tồn tại';
            echo json_encode($response);
            exit;
        }
        
        // Create transaction
        $result = $paymentProcessor->createTransaction($bookingId, $userId, $amount, $paymentMethod);
        
        if ($result['success']) {
            if ($paymentMethod === 'wallet') {
                // Process wallet payment
                $paymentResult = $paymentProcessor->processWalletPayment($userId, $result['transaction_id'], $amount);
                $response = $paymentResult;
            } elseif ($paymentMethod === 'vnpay') {
                // Process VNPay payment
                $paymentResult = $paymentProcessor->processVNPayPayment(
                    $result['transaction_id'], 
                    $amount, 
                    "Thanh toán đặt sân #{$bookingId}"
                );
                $response = $paymentResult;
            } else {
                $response = ['success' => true, 'message' => 'Đã tạo yêu cầu thanh toán', 'transaction_id' => $result['transaction_id']];
            }
        } else {
            $response = $result;
        }
        
        echo json_encode($response);
        break;
        
    case 'recharge':
        $amount = floatval($_POST['amount'] ?? 0);
        $paymentMethod = $_POST['payment_method'] ?? '';
        $minAmount = 10000;
        $maxAmount = 10000000;
        
        if ($amount < $minAmount || $amount > $maxAmount) {
            $response['message'] = "Số tiền nạp phải từ " . number_format($minAmount) . " đến " . number_format($maxAmount) . " VNĐ";
            echo json_encode($response);
            exit;
        }
        
        $result = $paymentProcessor->rechargeWallet($userId, $amount, $paymentMethod);
        
        if ($result['success'] && isset($result['payment_url'])) {
            $response = [
                'success' => true,
                'payment_url' => $result['payment_url'],
                'message' => 'Chuyển hướng đến cổng thanh toán'
            ];
        } elseif ($result['success']) {
            // Direct wallet recharge (bank transfer)
            $response = [
                'success' => true,
                'message' => 'Yêu cầu nạp tiền đã được tạo. Vui lòng chuyển khoản theo thông tin bên dưới.',
                'transaction_id' => $result['transaction_id']
            ];
        } else {
            $response = $result;
        }
        
        echo json_encode($response);
        break;
        
    case 'get_transactions':
        $limit = intval($_GET['limit'] ?? 50);
        $offset = intval($_GET['offset'] ?? 0);
        
        $transactions = $paymentProcessor->getUserTransactions($userId, $limit, $offset);
        $stats = $paymentProcessor->getPaymentStats($userId);
        
        echo json_encode([
            'success' => true,
            'data' => $transactions,
            'stats' => $stats
        ]);
        break;
        
    case 'get_wallet_balance':
        $stmt = $pdo->prepare("SELECT balance, frozen_balance, total_recharge, total_spent FROM user_wallets WHERE user_id = ?");
        $stmt->execute([$userId]);
        $wallet = $stmt->fetch();
        
        if (!$wallet) {
            // Create wallet if not exists
            $stmt = $pdo->prepare("INSERT INTO user_wallets (user_id, balance) VALUES (?, 0)");
            $stmt->execute([$userId]);
            $wallet = ['balance' => 0, 'frozen_balance' => 0, 'total_recharge' => 0, 'total_spent' => 0];
        }
        
        echo json_encode([
            'success' => true,
            'balance' => number_format($wallet['balance'], 0, ',', '.'),
            'balance_raw' => $wallet['balance'],
            'frozen_balance' => $wallet['frozen_balance'],
            'total_recharge' => $wallet['total_recharge'],
            'total_spent' => $wallet['total_spent']
        ]);
        break;
        
    case 'request_refund':
        $transactionId = intval($_POST['transaction_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        
        if (!$transactionId || empty($reason)) {
            $response['message'] = 'Vui lòng cung cấp đầy đủ thông tin';
            echo json_encode($response);
            exit;
        }
        
        // Check if refund already requested
        $stmt = $pdo->prepare("SELECT * FROM refund_requests WHERE transaction_id = ? AND status = 'pending'");
        $stmt->execute([$transactionId]);
        if ($stmt->fetch()) {
            $response['message'] = 'Đã có yêu cầu hoàn tiền đang xử lý';
            echo json_encode($response);
            exit;
        }
        
        // Get transaction details
        $stmt = $pdo->prepare("SELECT amount FROM payment_transactions WHERE transaction_id = ? AND user_id = ?");
        $stmt->execute([$transactionId, $userId]);
        $transaction = $stmt->fetch();
        
        if (!$transaction) {
            $response['message'] = 'Giao dịch không tồn tại';
            echo json_encode($response);
            exit;
        }
        
        // Create refund request
        $stmt = $pdo->prepare("
            INSERT INTO refund_requests (transaction_id, user_id, reason, requested_amount, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$transactionId, $userId, $reason, $transaction['amount']]);
        
        $response = [
            'success' => true,
            'message' => 'Yêu cầu hoàn tiền đã được gửi. Chúng tôi sẽ xử lý trong vòng 24h.'
        ];
        
        echo json_encode($response);
        break;
        
    case 'apply_voucher':
        $voucherCode = trim($_POST['voucher_code'] ?? '');
        $bookingId = intval($_POST['booking_id'] ?? 0);
        
        if (empty($voucherCode) || !$bookingId) {
            $response['message'] = 'Vui lòng nhập mã giảm giá';
            echo json_encode($response);
            exit;
        }
        
        // Check voucher validity
        $stmt = $pdo->prepare("
            SELECT * FROM vouchers 
            WHERE code = ? AND is_active = 1 
            AND (valid_from IS NULL OR valid_from <= NOW()) 
            AND (valid_to IS NULL OR valid_to >= NOW())
            AND (usage_limit IS NULL OR used_count < usage_limit)
        ");
        $stmt->execute([$voucherCode]);
        $voucher = $stmt->fetch();
        
        if (!$voucher) {
            $response['message'] = 'Mã giảm giá không hợp lệ hoặc đã hết hạn';
            echo json_encode($response);
            exit;
        }
        
        // Check if user already used this voucher
        $stmt = $pdo->prepare("SELECT * FROM user_vouchers WHERE user_id = ? AND voucher_id = ? AND is_used = 1");
        $stmt->execute([$userId, $voucher['voucher_id']]);
        if ($stmt->fetch()) {
            $response['message'] = 'Bạn đã sử dụng mã giảm giá này rồi';
            echo json_encode($response);
            exit;
        }
        
        // Get booking amount
        $stmt = $pdo->prepare("SELECT total_price FROM bookings WHERE booking_id = ? AND user_id = ?");
        $stmt->execute([$bookingId, $userId]);
        $booking = $stmt->fetch();
        
        if (!$booking) {
            $response['message'] = 'Đặt sân không hợp lệ';
            echo json_encode($response);
            exit;
        }
        
        // Calculate discount
        $discount = 0;
        if ($voucher['discount_type'] === 'percent') {
            $discount = $booking['total_price'] * $voucher['discount_value'] / 100;
            if ($voucher['max_discount'] && $discount > $voucher['max_discount']) {
                $discount = $voucher['max_discount'];
            }
        } else {
            $discount = $voucher['discount_value'];
        }
        
        if ($booking['total_price'] < $voucher['min_order_value']) {
            $response['message'] = 'Đơn hàng chưa đạt giá trị tối thiểu để sử dụng mã này';
            echo json_encode($response);
            exit;
        }
        
        $finalAmount = $booking['total_price'] - $discount;
        
        // Save voucher usage
        $stmt = $pdo->prepare("
            INSERT INTO user_vouchers (user_id, voucher_id, booking_id, assigned_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $voucher['voucher_id'], $bookingId]);
        
        // Update voucher usage count
        $stmt = $pdo->prepare("UPDATE vouchers SET used_count = used_count + 1 WHERE voucher_id = ?");
        $stmt->execute([$voucher['voucher_id']]);
        
        $response = [
            'success' => true,
            'discount' => $discount,
            'discount_formatted' => number_format($discount, 0, ',', '.') . ' ₫',
            'final_amount' => $finalAmount,
            'final_amount_formatted' => number_format($finalAmount, 0, ',', '.') . ' ₫',
            'message' => 'Áp dụng mã giảm giá thành công'
        ];
        
        echo json_encode($response);
        break;
        
    default:
        $response['message'] = 'Action không hợp lệ';
        echo json_encode($response);
}
?>