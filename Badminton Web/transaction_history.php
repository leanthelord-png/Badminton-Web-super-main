<?php
require_once 'config/database.php';
require_once 'includes/PaymentProcessor.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$paymentProcessor = new PaymentProcessor($pdo);
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$transactions = $paymentProcessor->getUserTransactions($userId, $limit, $offset);
$stats = $paymentProcessor->getPaymentStats($userId);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch sử giao dịch - BadmintonPro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="max-w-6xl mx-auto px-4 py-12">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Lịch sử giao dịch</h1>
                <p class="text-gray-600 mt-1">Quản lý tất cả giao dịch thanh toán của bạn</p>
            </div>
            <a href="index.php" class="flex items-center text-green-600 hover:text-green-700">
                <i class="fas fa-arrow-left mr-2"></i>
                Quay lại
            </a>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Tổng giao dịch</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['total_transactions'] ?? 0); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-exchange-alt text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Giao dịch thành công</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo number_format($stats['total_success'] ?? 0); ?> ₫</p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Thanh toán qua ví</p>
                        <p class="text-2xl font-bold text-purple-600"><?php echo number_format($stats['wallet_payments'] ?? 0); ?> ₫</p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-wallet text-purple-600 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Thanh toán VNPay</p>
                        <p class="text-2xl font-bold text-orange-600"><?php echo number_format($stats['vnpay_payments'] ?? 0); ?> ₫</p>
                    </div>
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <i class="fab fa-paypal text-orange-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Mã GD</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Số tiền</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Phương thức</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Trạng thái</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Ngày tạo</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($transactions as $txn): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <span class="font-mono text-sm"><?php echo $txn['transaction_code']; ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-semibold text-green-600"><?php echo number_format($txn['amount'], 0, ',', '.'); ?> ₫</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 rounded-full text-xs font-medium 
                                    <?php echo $txn['payment_method'] === 'wallet' ? 'bg-green-100 text-green-700' : 
                                          ($txn['payment_method'] === 'vnpay' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700'); ?>">
                                    <?php echo $txn['payment_method'] === 'wallet' ? 'Ví nội bộ' : 
                                          ($txn['payment_method'] === 'vnpay' ? 'VNPay' : 'Chuyển khoản'); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 rounded-full text-xs font-medium
                                    <?php echo $txn['status'] === 'success' ? 'bg-green-100 text-green-700' : 
                                          ($txn['status'] === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'); ?>">
                                    <?php echo $txn['status'] === 'success' ? 'Thành công' : 
                                          ($txn['status'] === 'pending' ? 'Đang xử lý' : 'Thất bại'); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?php echo date('d/m/Y H:i', strtotime($txn['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($txn['status'] === 'success' && $txn['payment_method'] !== 'wallet'): ?>
                                <button onclick="requestRefund(<?php echo $txn['transaction_id']; ?>)" 
                                        class="text-red-600 hover:text-red-700 text-sm">
                                    <i class="fas fa-undo-alt mr-1"></i>
                                    Hoàn tiền
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-3 block"></i>
                                Chưa có giao dịch nào
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Refund Modal -->
    <div id="refund-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Yêu cầu hoàn tiền</h3>
                <button onclick="closeRefundModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="refund-form">
                <input type="hidden" id="refund-transaction-id">
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Lý do hoàn tiền</label>
                    <textarea id="refund-reason" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500" required></textarea>
                </div>
                <button type="submit" class="w-full bg-red-600 text-white py-2 rounded-lg hover:bg-red-700 transition">
                    Gửi yêu cầu
                </button>
            </form>
        </div>
    </div>

    <script>
        async function requestRefund(transactionId) {
            document.getElementById('refund-transaction-id').value = transactionId;
            document.getElementById('refund-modal').classList.remove('hidden');
        }
        
        function closeRefundModal() {
            document.getElementById('refund-modal').classList.add('hidden');
        }
        
        document.getElementById('refund-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const transactionId = document.getElementById('refund-transaction-id').value;
            const reason = document.getElementById('refund-reason').value;
            
            const formData = new FormData();
            formData.append('action', 'request_refund');
            formData.append('transaction_id', transactionId);
            formData.append('reason', reason);
            
            try {
                const response = await fetch('api_payment.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert(data.message);
                    closeRefundModal();
                    location.reload();
                } else {
                    alert(data.message);
                }
            } catch (error) {
                alert('Có lỗi xảy ra: ' + error.message);
            }
        });
    </script>
</body>
</html>