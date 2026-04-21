<?php
// topup_instruction.php
require_once 'config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$payment_id = $_GET['payment_id'] ?? 0;
$amount = $_GET['amount'] ?? 0;
$method = $_GET['method'] ?? 'bank_transfer';

$stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? AND user_id = ?");
$stmt->execute([$payment_id, $_SESSION['user_id']]);
$payment = $stmt->fetch();

if (!$payment) {
    die('Không tìm thấy thông tin thanh toán');
}

// Cấu hình ngân hàng (nên đặt trong file config)
$bankInfo = [
    'bank_name' => 'Vietcombank',
    'account_number' => '1234567890',
    'account_name' => 'BADMINTON PRO COMPANY LIMITED',
    'branch' => 'Chi nhánh Hà Nội',
    'bin' => '970436', // Mã BIN của Vietcombank
    'hotline' => '1900 1234'
];

$transactionCode = $payment['transaction_code'] ?? $payment['transaction_id'] ?? 'NAP' . date('Ymd') . rand(1000, 9999);
$expiryTime = date('H:i', strtotime('+30 minutes'));

// Tạo QR code với định dạng đẹp hơn
$qrData = sprintf(
    "https://img.vietqr.io/image/%s-%s-compact2.png?amount=%s&addInfo=%s&accountName=%s",
    $bankInfo['bank_name'],
    $bankInfo['account_number'],
    $amount,
    urlencode($transactionCode),
    urlencode($bankInfo['account_name'])
);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán nạp tiền - BadmintonPro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .countdown-timer {
            font-family: monospace;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .copy-btn.copied {
            background-color: #10b981;
            color: white;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .animate-pulse-slow {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-4xl w-full overflow-hidden">
            <!-- Header với gradient -->
            <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-8 py-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="bg-white/20 backdrop-blur-sm p-3 rounded-xl">
                            <i class="fas fa-wallet text-white text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-white">Nạp tiền vào ví</h1>
                            <p class="text-green-100 text-sm mt-1">Mã giao dịch: <?php echo $transactionCode; ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-white/80 text-sm">Số tiền cần thanh toán</div>
                        <div class="text-white text-3xl font-bold"><?php echo number_format($amount, 0, ',', '.'); ?> ₫</div>
                    </div>
                </div>
            </div>

            <!-- Countdown Timer -->
            <div class="bg-orange-50 border-b border-orange-100 px-8 py-3 flex items-center justify-between">
                <div class="flex items-center space-x-2 text-orange-700">
                    <i class="fas fa-hourglass-half"></i>
                    <span class="text-sm font-medium">Thời gian còn lại để thanh toán:</span>
                </div>
                <div class="countdown-timer text-orange-700" id="countdown">30:00</div>
            </div>

            <div class="p-8">
                <div class="grid md:grid-cols-2 gap-8">
                    <!-- Cột trái: QR Code -->
                    <div class="bg-gradient-to-br from-gray-50 to-white rounded-2xl p-6 border border-gray-100 shadow-sm">
                        <div class="text-center">
                            <div class="inline-flex items-center justify-center w-12 h-12 bg-green-100 rounded-full mb-4">
                                <i class="fas fa-qrcode text-green-600 text-xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800 mb-2">Quét mã QR để thanh toán</h3>
                            <p class="text-sm text-gray-500 mb-4">Sử dụng ứng dụng ngân hàng hoặc ví điện tử</p>
                            
                            <div class="bg-white p-4 rounded-2xl shadow-lg inline-block">
                                <img src="<?php echo $qrData; ?>" alt="QR Code thanh toán" class="w-72 h-72 rounded-xl" id="qrCode">
                            </div>
                            
                            <button onclick="downloadQR()" class="mt-4 text-sm text-green-600 hover:text-green-700 font-medium">
                                <i class="fas fa-download mr-1"></i>Tải mã QR
                            </button>
                        </div>
                        
                        <div class="mt-6 pt-6 border-t border-gray-100">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-500">Phương thức thanh toán:</span>
                                <div class="flex items-center space-x-2">
                                    <img src="https://cdn.jsdelivr.net/gh/logo-cdn/vietcombank@master/logo.png" class="h-6" alt="Vietcombank" onerror="this.style.display='none'">
                                    <img src="https://cdn.jsdelivr.net/gh/logo-cdn/momo@master/logo.png" class="h-6" alt="Momo" onerror="this.style.display='none'">
                                    <span class="text-gray-600">VietQR</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cột phải: Thông tin chuyển khoản -->
                    <div>
                        <!-- Thông tin ngân hàng -->
                        <div class="mb-6">
                            <h3 class="font-semibold text-gray-800 mb-3 flex items-center">
                                <i class="fas fa-university text-green-600 mr-2"></i>
                                Thông tin chuyển khoản
                            </h3>
                            <div class="space-y-3 bg-gray-50 rounded-2xl p-5">
                                <div class="info-row flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="text-gray-500">Ngân hàng thụ hưởng:</span>
                                    <span class="font-semibold text-gray-800"><?php echo $bankInfo['bank_name']; ?></span>
                                </div>
                                <div class="info-row flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="text-gray-500">Số tài khoản:</span>
                                    <div class="flex items-center space-x-2">
                                        <span class="font-mono font-semibold text-gray-800"><?php echo $bankInfo['account_number']; ?></span>
                                        <button onclick="copyToClipboard('<?php echo $bankInfo['account_number']; ?>', this)" class="text-gray-400 hover:text-green-600 transition">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="info-row flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="text-gray-500">Chủ tài khoản:</span>
                                    <span class="font-semibold text-gray-800"><?php echo $bankInfo['account_name']; ?></span>
                                </div>
                                <div class="info-row flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="text-gray-500">Chi nhánh:</span>
                                    <span class="text-gray-800"><?php echo $bankInfo['branch']; ?></span>
                                </div>
                                <div class="info-row flex justify-between items-center py-2">
                                    <span class="text-gray-500">Nội dung chuyển khoản:</span>
                                    <div class="flex items-center space-x-2">
                                        <code class="bg-gray-200 px-2 py-1 rounded text-sm font-mono"><?php echo $transactionCode; ?></code>
                                        <button onclick="copyToClipboard('<?php echo $transactionCode; ?>', this)" class="text-gray-400 hover:text-green-600 transition">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Hướng dẫn chi tiết -->
                        <div class="mb-6">
                            <h3 class="font-semibold text-gray-800 mb-3 flex items-center">
                                <i class="fas fa-list-check text-green-600 mr-2"></i>
                                Các bước thực hiện
                            </h3>
                            <div class="space-y-3">
                                <div class="flex items-start space-x-3 p-3 bg-blue-50 rounded-xl">
                                    <div class="w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">1</div>
                                    <div class="text-sm text-blue-800">Mở ứng dụng ngân hàng hoặc ví điện tử (Momo, ZaloPay, VietQR)</div>
                                </div>
                                <div class="flex items-start space-x-3 p-3 bg-blue-50 rounded-xl">
                                    <div class="w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">2</div>
                                    <div class="text-sm text-blue-800">Chọn "Chuyển khoản" và quét mã QR bên cạnh hoặc nhập thông tin thủ công</div>
                                </div>
                                <div class="flex items-start space-x-3 p-3 bg-blue-50 rounded-xl">
                                    <div class="w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">3</div>
                                    <div class="text-sm text-blue-800">Nhập chính xác nội dung chuyển khoản: <strong><?php echo $transactionCode; ?></strong></div>
                                </div>
                                <div class="flex items-start space-x-3 p-3 bg-blue-50 rounded-xl">
                                    <div class="w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">4</div>
                                    <div class="text-sm text-blue-800">Xác nhận chuyển khoản và bấm nút "Đã thanh toán" bên dưới</div>
                                </div>
                            </div>
                        </div>

                        <!-- Lưu ý quan trọng -->
                        <div class="bg-yellow-50 border border-yellow-200 rounded-2xl p-4 mb-6">
                            <div class="flex items-start">
                                <i class="fas fa-exclamation-triangle text-yellow-600 mt-0.5 mr-3 text-lg"></i>
                                <div class="text-sm text-yellow-800">
                                    <p class="font-semibold mb-2">📌 Lưu ý quan trọng:</p>
                                    <ul class="space-y-1 list-disc list-inside">
                                        <li>Nội dung chuyển khoản phải <strong>CHÍNH XÁC</strong> để hệ thống tự động cập nhật</li>
                                        <li>Sau khi chuyển khoản, hệ thống sẽ tự động cập nhật trong vòng 5-10 phút</li>
                                        <li>Nếu quá 30 phút chưa nhận được tiền, vui lòng liên hệ hotline <strong><?php echo $bankInfo['hotline']; ?></strong></li>
                                        <li>Giữ lại ảnh chụp màn hình giao dịch để đối chiếu khi cần</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Nút hành động -->
                        <div class="flex gap-3">
                            <button onclick="checkPaymentStatus()" class="flex-1 bg-gradient-to-r from-green-600 to-emerald-600 text-white py-3 rounded-xl font-semibold hover:shadow-lg transition duration-300 transform hover:scale-[1.02]">
                                <i class="fas fa-check-circle mr-2"></i>
                                Tôi đã thanh toán
                            </button>
                            <a href="index.php" class="flex-1 bg-gray-100 text-gray-700 py-3 rounded-xl font-semibold hover:bg-gray-200 transition text-center">
                                <i class="fas fa-times-circle mr-2"></i>
                                Hủy bỏ
                            </a>
                        </div>
                        
                        <p class="text-center text-xs text-gray-400 mt-4">
                            <i class="fas fa-lock mr-1"></i>
                            Giao dịch được bảo mật bởi SSL 256-bit
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Countdown timer (30 phút)
        let timeLeft = 30 * 60; // 30 phút = 1800 giây
        
        function updateCountdown() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            const countdownEl = document.getElementById('countdown');
            if (countdownEl) {
                countdownEl.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            }
            
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                alert('Thời gian thanh toán đã hết. Vui lòng tạo lại giao dịch mới.');
                window.location.href = 'index.php';
            }
            timeLeft--;
        }
        
        const timerInterval = setInterval(updateCountdown, 1000);
        
        // Copy to clipboard function
        function copyToClipboard(text, button) {
            navigator.clipboard.writeText(text).then(() => {
                const originalHTML = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check"></i>';
                button.classList.add('copied');
                setTimeout(() => {
                    button.innerHTML = originalHTML;
                    button.classList.remove('copied');
                }, 2000);
            });
        }
        
        // Download QR Code
        function downloadQR() {
            const qrImg = document.getElementById('qrCode');
            const link = document.createElement('a');
            link.download = 'qr_thanh_toan.png';
            link.href = qrImg.src;
            link.click();
        }
        
        // Check payment status with AJAX
        async function checkPaymentStatus() {
            const paymentId = <?php echo $payment_id; ?>;
            
            // Hiển thị loading
            const btn = event.currentTarget;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Đang kiểm tra...';
            btn.disabled = true;
            
            try {
                const response = await fetch('check_payment_ajax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ payment_id: paymentId })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    window.location.href = 'index.php?topup_success=1';
                } else {
                    alert('Hệ thống chưa nhận được thanh toán.\nVui lòng kiểm tra lại hoặc liên hệ hotline hỗ trợ.');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Có lỗi xảy ra. Vui lòng thử lại sau.');
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }
        
        // Auto refresh check mỗi 10 giây
        let autoCheckInterval;
        function startAutoCheck() {
            autoCheckInterval = setInterval(async () => {
                try {
                    const response = await fetch('check_payment_ajax.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ payment_id: <?php echo $payment_id; ?> })
                    });
                    const data = await response.json();
                    if (data.success) {
                        clearInterval(autoCheckInterval);
                        clearInterval(timerInterval);
                        window.location.href = 'index.php?topup_success=1';
                    }
                } catch (error) {
                    console.error('Auto check error:', error);
                }
            }, 10000); // Kiểm tra mỗi 10 giây
        }
        
        // Bắt đầu auto check
        startAutoCheck();
    </script>
</body>
</html>