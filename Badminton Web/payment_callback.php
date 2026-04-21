<?php
require_once 'config/database.php';
require_once 'includes/PaymentProcessor.php';

$paymentProcessor = new PaymentProcessor($pdo);
$method = $_GET['method'] ?? '';

if ($method === 'vnpay') {
    $result = $paymentProcessor->handleVNPayCallback(
        $_GET['vnp_ResponseCode'] ?? '',
        $_GET['vnp_TxnRef'] ?? '',
        $_GET['vnp_Amount'] ?? 0,
        $_GET['vnp_SecureHash'] ?? ''
    );
    
    if ($result['success']) {
        // Redirect to success page
        header('Location: payment_success.php?transaction=' . explode('_', $_GET['vnp_TxnRef'])[0]);
    } else {
        // Redirect to failure page
        header('Location: payment_failed.php?message=' . urlencode($result['message']));
    }
} elseif ($method === 'momo') {
    // Handle Momo callback
    $data = json_decode(file_get_contents('php://input'), true);
    // Process Momo callback...
} else {
    echo "Invalid callback method";
}
?>