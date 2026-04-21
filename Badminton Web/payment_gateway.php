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
$bookingId = intval($_GET['booking_id'] ?? 0);
$amount = floatval($_GET['amount'] ?? 0);

// Get available payment methods
$stmt = $pdo->query("SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY display_order");
$paymentMethods = $stmt->fetchAll();

// Get user wallet balance
$stmt = $pdo->prepare("SELECT balance FROM user_wallets WHERE user_id = ?");
$stmt->execute([$userId]);
$wallet = $stmt->fetch();
$walletBalance = $wallet ? $wallet['balance'] : 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán - BadmintonPro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .payment-method-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .payment-method-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .payment-method-card.selected {
            border-color: #10b981;
            background-color: #f0fdf4;
        }
        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .loading.active {
            display: flex;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="loading" id="loading">
        <div class="bg-white rounded-lg p-6 flex flex-col items-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-600 mb-4"></div>
            <p class="text-gray-700">Đang xử lý thanh toán...</p>
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-4 py-12">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                <i class="fas fa-credit-card text-green-600 text-2xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800">Thanh toán đơn hàng</h1>
            <p class="text-gray-600 mt-2">Chọn phương thức thanh toán phù hợp với bạn</p>
        </div>

        <!-- Order Summary -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-receipt text-green-600 mr-2"></i>
                Thông tin đơn hàng
            </h2>
            <div class="space-y-3">
                <div class="flex justify-between py-2 border-b">
                    <span class="text-gray-600">Mã đơn hàng:</span>
                    <span class="font-semibold">#<?php echo $bookingId; ?></span>
                </div>
                <div class="flex justify-between py-2 border-b">
                    <span class="text-gray-600">Số tiền:</span>
                    <span class="text-2xl font-bold text-green-600"><?php echo number_format($amount, 0, ',', '.'); ?> ₫</span>
                </div>
                <div class="flex justify-between py-2 border-b" id="discount-row" style="display: none;">
                    <span class="text-gray-600">Giảm giá:</span>
                    <span class="text-red-600 font-semibold" id="discount-amount">0 ₫</span>
                </div>
                <div class="flex justify-between py-2 font-bold">
                    <span>Thành tiền:</span>
                    <span class="text-2xl font-bold text-green-600" id="final-amount"><?php echo number_format($amount, 0, ',', '.'); ?> ₫</span>
                </div>
            </div>
            
            <!-- Voucher Section -->
            <div class="mt-6 pt-4 border-t">
                <label class="block text-sm font-medium text-gray-700 mb-2">Mã giảm giá</label>
                <div class="flex gap-2">
                    <input type="text" id="voucher-code" placeholder="Nhập mã giảm giá" 
                           class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500">
                    <button onclick="applyVoucher()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                        Áp dụng
                    </button>
                </div>
                <div id="voucher-message" class="text-sm mt-2"></div>
            </div>
        </div>

        <!-- Payment Methods -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-credit-card text-green-600 mr-2"></i>
                Phương thức thanh toán
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($paymentMethods as $method): ?>
                <div class="payment-method-card border-2 border-gray-200 rounded-xl p-4" 
                     data-method="<?php echo $method['method_code']; ?>"
                     onclick="selectPaymentMethod('<?php echo $method['method_code']; ?>')">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="<?php echo $method['icon_class']; ?> text-2xl text-gray-600"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="font-semibold text-gray-800"><?php echo $method['method_name']; ?></h3>
                            <?php if ($method['method_code'] === 'wallet'): ?>
                            <p class="text-sm text-gray-500">Số dư: <?php echo number_format($walletBalance, 0, ',', '.'); ?> ₫</p>
                            <?php endif; ?>
                        </div>
                        <div class="radio-indicator w-5 h-5 border-2 border-gray-300 rounded-full"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Bank Transfer Info -->
        <div id="bank-transfer-info" class="bg-blue-50 rounded-2xl p-6 mb-8 hidden">
            <h3 class="font-bold text-blue-800 mb-3 flex items-center">
                <i class="fas fa-university mr-2"></i>
                Thông tin chuyển khoản
            </h3>
            <div class="space-y-2 text-sm">
                <p><strong>Ngân hàng:</strong> Vietcombank</p>
                <p><strong>Số tài khoản:</strong> 1234567890</p>
                <p><strong>Chủ tài khoản:</strong> BADMINTON PRO COMPANY</p>
                <p><strong>Chi nhánh:</strong> Hanoi Branch</p>
                <p><strong>Nội dung chuyển khoản:</strong> <span class="font-mono bg-white px-2 py-1 rounded">TXN_[MÃ ĐƠN HÀNG]</span></p>
            </div>
            <div class="mt-4 p-3 bg-yellow-100 rounded-lg">
                <p class="text-sm text-yellow-800">
                    <i class="fas fa-clock mr-2"></i>
                    Lưu ý: Sau khi chuyển khoản, vui lòng chờ 5-10 phút để hệ thống cập nhật.
                </p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-4">
            <button onclick="window.history.back()" class="flex-1 px-6 py-3 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200 transition">
                Quay lại
            </button>
            <button id="pay-btn" onclick="processPayment()" class="flex-1 px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-xl font-semibold hover:shadow-lg transition">
                Thanh toán ngay
            </button>
        </div>
    </div>

    <script>
        let selectedMethod = null;
        let originalAmount = <?php echo $amount; ?>;
        let discountAmount = 0;
        
        function selectPaymentMethod(method) {
            selectedMethod = method;
            
            // Update UI
            document.querySelectorAll('.payment-method-card').forEach(card => {
                card.classList.remove('selected');
                const radio = card.querySelector('.radio-indicator');
                radio.classList.remove('bg-green-600', 'border-green-600');
                radio.classList.add('border-gray-300');
            });
            
            const selectedCard = document.querySelector(`.payment-method-card[data-method="${method}"]`);
            selectedCard.classList.add('selected');
            const radio = selectedCard.querySelector('.radio-indicator');
            radio.classList.add('bg-green-600', 'border-green-600');
            radio.classList.remove('border-gray-300');
            
            // Show/hide bank transfer info
            const bankInfo = document.getElementById('bank-transfer-info');
            if (method === 'bank_transfer') {
                bankInfo.classList.remove('hidden');
            } else {
                bankInfo.classList.add('hidden');
            }
        }
        
        async function applyVoucher() {
            const code = document.getElementById('voucher-code').value.trim();
            if (!code) {
                showVoucherMessage('Vui lòng nhập mã giảm giá', 'error');
                return;
            }
            
            showLoading();
            
            try {
                const formData = new FormData();
                formData.append('action', 'apply_voucher');
                formData.append('voucher_code', code);
                formData.append('booking_id', <?php echo $bookingId; ?>);
                
                const response = await fetch('api_payment.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    discountAmount = data.discount;
                    const finalAmount = originalAmount - discountAmount;
                    
                    document.getElementById('discount-row').style.display = 'flex';
                    document.getElementById('discount-amount').textContent = data.discount_formatted;
                    document.getElementById('final-amount').textContent = data.final_amount_formatted;
                    
                    showVoucherMessage(data.message, 'success');
                } else {
                    showVoucherMessage(data.message, 'error');
                }
            } catch (error) {
                showVoucherMessage('Có lỗi xảy ra', 'error');
            } finally {
                hideLoading();
            }
        }
        
        function showVoucherMessage(message, type) {
            const msgDiv = document.getElementById('voucher-message');
            msgDiv.textContent = message;
            msgDiv.className = `text-sm mt-2 ${type === 'success' ? 'text-green-600' : 'text-red-600'}`;
            
            setTimeout(() => {
                msgDiv.textContent = '';
            }, 5000);
        }
        
        async function processPayment() {
            if (!selectedMethod) {
                alert('Vui lòng chọn phương thức thanh toán');
                return;
            }
            
            const finalAmount = originalAmount - discountAmount;
            
            if (selectedMethod === 'wallet') {
                const walletBalance = <?php echo $walletBalance; ?>;
                if (walletBalance < finalAmount) {
                    alert('Số dư ví không đủ. Vui lòng nạp thêm tiền hoặc chọn phương thức khác.');
                    return;
                }
            }
            
            showLoading();
            
            try {
                const formData = new FormData();
                formData.append('action', 'create_payment');
                formData.append('booking_id', <?php echo $bookingId; ?>);
                formData.append('payment_method', selectedMethod);
                formData.append('amount', finalAmount);
                
                const response = await fetch('api_payment.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    if (data.payment_url) {
                        window.location.href = data.payment_url;
                    } else {
                        alert(data.message);
                        window.location.href = 'index.php';
                    }
                } else {
                    alert(data.message);
                }
            } catch (error) {
                alert('Có lỗi xảy ra: ' + error.message);
            } finally {
                hideLoading();
            }
        }
        
        function showLoading() {
            document.getElementById('loading').classList.add('active');
        }
        
        function hideLoading() {
            document.getElementById('loading').classList.remove('active');
        }
        
        // Auto-select first payment method
        if (document.querySelector('.payment-method-card')) {
            const firstMethod = document.querySelector('.payment-method-card').dataset.method;
            selectPaymentMethod(firstMethod);
        }
    </script>
</body>
</html>