<?php
require_once __DIR__ . '/config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = $_SESSION['user_id'] ?? 0;
$bookingId = (int)($_GET['booking_id'] ?? 0);

if (!$userId) {
    header('Location: index.php');
    exit;
}

if (!$bookingId) {
    die('Thiếu booking_id');
}

// ========== XỬ LÝ AJAX THANH TOÁN ==========
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $ajaxBookingId = (int)($_POST['booking_id'] ?? 0);
    
    // Xử lý thanh toán bằng ví
    if ($action === 'process_wallet_payment') {
        try {
            // Lấy thông tin booking
            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = ? AND user_id = ? AND status = 'pending'");
            $stmt->execute([$ajaxBookingId, $userId]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booking) {
                echo json_encode(['success' => false, 'message' => 'Không tìm thấy booking hoặc booking đã được xử lý']);
                exit;
            }
            
            $totalPrice = (float)$booking['total_price'];
            
            // Lấy số dư hiện tại
            $stmt = $pdo->prepare("SELECT user_balance FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $balance = (float)$stmt->fetchColumn();
            
            // Kiểm tra số dư
            if ($balance < $totalPrice) {
                echo json_encode(['success' => false, 'message' => 'Số dư không đủ để thanh toán. Vui lòng nạp thêm tiền.']);
                exit;
            }
            
            $pdo->beginTransaction();
            
            // Trừ tiền từ ví user (bảng users)
            $stmt = $pdo->prepare("UPDATE users SET user_balance = user_balance - ?, total_spent = total_spent + ? WHERE user_id = ?");
            $stmt->execute([$totalPrice, $totalPrice, $userId]);
            
            // Cập nhật booking
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed', payment_status = 'paid' WHERE booking_id = ?");
            $stmt->execute([$ajaxBookingId]);
            
            // Ghi lịch sử giao dịch vào pay_history
            $afterBalance = $balance - $totalPrice;
            $stmt = $pdo->prepare("
                INSERT INTO pay_history (user_id, type, amount, before_balance, after_balance, description, created_at) 
                VALUES (?, 'booking', ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $totalPrice, $balance, $afterBalance, 'Thanh toán đặt sân #' . $ajaxBookingId]);
            
            // Ghi vào bảng payments
            $stmt = $pdo->prepare("
                INSERT INTO payments (booking_id, user_id, amount, status, payment_method, created_at) 
                VALUES (?, ?, ?, 'completed', 'wallet', NOW())
            ");
            $stmt->execute([$ajaxBookingId, $userId, $totalPrice]);
            
            $pdo->commit();
            
            // Cập nhật session balance
            $_SESSION['user_balance'] = $afterBalance;
            
            echo json_encode(['success' => true, 'message' => 'Thanh toán thành công', 'new_balance' => $afterBalance]);
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // Xử lý xác nhận chuyển khoản
    if ($action === 'confirm_bank_transfer') {
        try {
            // Lấy thông tin booking
            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = ? AND user_id = ? AND status = 'pending'");
            $stmt->execute([$ajaxBookingId, $userId]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booking) {
                echo json_encode(['success' => false, 'message' => 'Không tìm thấy booking']);
                exit;
            }
            
            $totalPrice = (float)$booking['total_price'];
            
            $pdo->beginTransaction();
            
            // Cập nhật booking thành confirmed (chờ admin xác nhận thanh toán thực tế)
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed', payment_status = 'paid' WHERE booking_id = ?");
            $stmt->execute([$ajaxBookingId]);
            
            // Ghi vào bảng payments
            $stmt = $pdo->prepare("
                INSERT INTO payments (booking_id, user_id, amount, status, payment_method, created_at) 
                VALUES (?, ?, ?, 'pending', 'bank_transfer', NOW())
            ");
            $stmt->execute([$ajaxBookingId, $userId, $totalPrice]);
            
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => 'Đã ghi nhận yêu cầu thanh toán. Chúng tôi sẽ xác nhận trong ít phút.']);
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        exit;
    }
}

// Lấy thông tin booking
$stmt = $pdo->prepare("
    SELECT b.*, c.court_name, c.court_image, c.location, c.address, c.phone_number as court_phone
    FROM bookings b
    JOIN courts c ON b.court_id = c.court_id
    WHERE b.booking_id = ? AND b.user_id = ?
    LIMIT 1
");
$stmt->execute([$bookingId, $userId]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    die('Không tìm thấy booking');
}

// Lấy thông tin user
$stmt = $pdo->prepare("SELECT full_name, phone_number, email FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Lấy số dư hiện tại của user
$stmt = $pdo->prepare("SELECT user_balance FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$currentBalance = (float)$stmt->fetchColumn();

// Kiểm tra nếu booking đã thanh toán
$isPaid = $booking['status'] === 'confirmed' || ($booking['payment_status'] ?? '') === 'paid';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán đặt sân - BadmintonPro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            transition: all 0.3s ease;
        }
        .countdown {
            font-family: monospace;
            font-size: 1.5rem;
            font-weight: bold;
        }
        .payment-option.selected {
            border-color: #22c55e !important;
            background-color: #f0fdf4 !important;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-green-50 to-emerald-50 min-h-screen">

<!-- Navigation -->
<nav class="bg-gradient-to-r from-green-700 to-emerald-600 shadow-lg sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <a href="index.php" class="flex items-center space-x-2 hover:opacity-90 transition">
                    <i class="fas fa-table-tennis text-white text-2xl"></i>
                    <span class="font-bold text-xl text-white">BadmintonPro</span>
                </a>
            </div>
            <div class="flex items-center space-x-4">
                <a href="index.php" class="text-white hover:text-green-200 transition">
                    <i class="fas fa-home mr-1"></i> Trang chủ
                </a>
                <a href="my_bookings.php" class="text-white hover:text-green-200 transition">
                    <i class="fas fa-calendar-alt mr-1"></i> Lịch đặt của tôi
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="max-w-6xl mx-auto py-10 px-4">
    <!-- Header -->
    <div class="text-center mb-8">
        <div class="inline-block bg-green-100 rounded-full p-3 mb-4">
            <i class="fas fa-credit-card text-green-600 text-4xl"></i>
        </div>
        <h1 class="text-3xl font-bold text-gray-800">Thanh toán đặt sân</h1>
        <p class="text-gray-600 mt-2">Hoàn tất thanh toán để xác nhận đặt sân của bạn</p>
    </div>

    <!-- Nếu đã thanh toán -->
    <?php if ($isPaid): ?>
    <div class="bg-white rounded-2xl shadow-xl p-8 text-center">
        <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-check-circle text-green-600 text-4xl"></i>
        </div>
        <h2 class="text-2xl font-bold text-gray-800 mb-2">Đã thanh toán!</h2>
        <p class="text-gray-600 mb-6">Đặt sân của bạn đã được thanh toán và xác nhận thành công.</p>
        <div class="flex justify-center gap-4">
            <a href="index.php" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-xl transition">
                <i class="fas fa-home mr-2"></i>Về trang chủ
            </a>
            <a href="my_bookings.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-xl transition">
                <i class="fas fa-calendar-alt mr-2"></i>Xem lịch đặt
            </a>
        </div>
    </div>
    <?php else: ?>
    
    <div class="grid md:grid-cols-2 gap-6">
        <!-- Thông tin đặt sân -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-4">
                <h2 class="text-xl font-bold text-white">
                    <i class="fas fa-info-circle mr-2"></i>Thông tin đặt sân
                </h2>
            </div>
            
            <!-- Ảnh sân -->
            <?php 
            $courtImage = '';
            if (!empty($booking['court_image'])) {
                if (filter_var($booking['court_image'], FILTER_VALIDATE_URL)) {
                    $courtImage = $booking['court_image'];
                } elseif (file_exists('uploads/courts/' . $booking['court_image'])) {
                    $courtImage = 'uploads/courts/' . $booking['court_image'];
                } elseif (file_exists('images/' . $booking['court_image'])) {
                    $courtImage = 'images/' . $booking['court_image'];
                } else {
                    $courtImage = $booking['court_image'];
                }
            }
            if (empty($courtImage) || !file_exists(str_replace('../', '', $courtImage))) {
                $courtImage = 'https://placehold.co/800x400/22c55e/white?text=' . urlencode($booking['court_name']);
            }
            ?>
            <img src="<?php echo htmlspecialchars($courtImage); ?>" 
                 alt="<?php echo htmlspecialchars($booking['court_name']); ?>" 
                 class="w-full h-48 object-cover">
            
            <div class="p-6">
                <div class="mb-4 pb-4 border-b">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-gray-500">Mã booking:</span>
                        <span class="font-bold text-green-600 text-lg">#<?php echo $booking['booking_id']; ?></span>
                    </div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-gray-500">Sân:</span>
                        <span class="font-semibold"><?php echo htmlspecialchars($booking['court_name']); ?></span>
                    </div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-gray-500">Địa chỉ:</span>
                        <span class="text-gray-700"><?php echo htmlspecialchars($booking['location'] ?? ($booking['address'] ?? 'Chưa cập nhật')); ?></span>
                    </div>
                </div>
                
                <div class="mb-4 pb-4 border-b">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-gray-500">Ngày đặt:</span>
                        <span class="font-semibold"><?php echo date('d/m/Y', strtotime($booking['start_time'])); ?></span>
                    </div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-gray-500">Thời gian:</span>
                        <span class="font-semibold"><?php echo date('H:i', strtotime($booking['start_time'])); ?> - <?php echo date('H:i', strtotime($booking['end_time'])); ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">Thời lượng:</span>
                        <?php 
                        $start = new DateTime($booking['start_time']);
                        $end = new DateTime($booking['end_time']);
                        $hours = $start->diff($end)->h + ($start->diff($end)->i / 60);
                        ?>
                        <span class="font-semibold"><?php echo $hours; ?> giờ</span>
                    </div>
                </div>
                
                <div class="mb-4 pb-4 border-b">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-gray-500">Khách hàng:</span>
                        <span class="font-semibold"><?php echo htmlspecialchars($user['full_name'] ?? 'Khách hàng'); ?></span>
                    </div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-gray-500">Số điện thoại:</span>
                        <span class="font-semibold"><?php echo htmlspecialchars($user['phone_number'] ?? 'Chưa cập nhật'); ?></span>
                    </div>
                    <?php if (!empty($user['email'])): ?>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">Email:</span>
                        <span class="font-semibold"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="flex items-center justify-between">
                    <span class="text-gray-700 font-bold text-lg">Tổng tiền:</span>
                    <span class="text-2xl font-bold text-green-600"><?php echo number_format($booking['total_price'], 0, ',', '.'); ?>đ</span>
                </div>
            </div>
        </div>
        
        <!-- Phần thanh toán -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-4">
                <h2 class="text-xl font-bold text-white">
                    <i class="fas fa-hand-holding-usd mr-2"></i>Phương thức thanh toán
                </h2>
            </div>
            
            <div class="p-6">
                <!-- Inject data từ PHP vào JavaScript -->
                <input type="hidden" id="current-balance" value="<?php echo $currentBalance; ?>">
                <input type="hidden" id="total-price" value="<?php echo $booking['total_price']; ?>">
                <input type="hidden" id="booking-id" value="<?php echo $bookingId; ?>">
                
                <!-- Đếm ngược thời gian -->
                <div class="mb-6 p-4 bg-yellow-50 rounded-xl border border-yellow-200 text-center">
                    <p class="text-yellow-800 font-semibold mb-2">
                        <i class="fas fa-hourglass-half mr-2"></i>Thời gian còn lại để thanh toán
                    </p>
                    <div id="countdown" class="countdown text-yellow-700">15:00</div>
                    <p class="text-xs text-yellow-600 mt-2">Sau thời gian này, đặt sân sẽ bị hủy</p>
                </div>
                
                <!-- Chọn phương thức thanh toán -->
                <div class="mb-6">
                    <label class="block font-semibold text-gray-800 mb-3">Chọn phương thức thanh toán</label>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="payment-option border-2 border-gray-200 rounded-xl p-4 cursor-pointer transition hover:border-green-400" data-method="wallet">
                            <div class="flex items-center space-x-3">
                                <i class="fas fa-wallet text-2xl text-green-600"></i>
                                <div>
                                    <p class="font-semibold">Ví BadmintonPro</p>
                                    <p class="text-xs text-gray-500">Thanh toán bằng số dư ví</p>
                                </div>
                            </div>
                        </div>
                        <div class="payment-option border-2 border-gray-200 rounded-xl p-4 cursor-pointer transition hover:border-green-400" data-method="bank">
                            <div class="flex items-center space-x-3">
                                <i class="fas fa-university text-2xl text-blue-600"></i>
                                <div>
                                    <p class="font-semibold">Chuyển khoản ngân hàng</p>
                                    <p class="text-xs text-gray-500">QR Code hoặc chuyển khoản thủ công</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" id="payment-method" value="">
                </div>
                
                <!-- Nội dung thanh toán ví -->
                <div id="wallet-box" class="hidden mb-6 border rounded-xl p-5 bg-green-50">
                    <div class="flex items-start space-x-3">
                        <i class="fas fa-wallet text-green-600 text-2xl mt-1"></i>
                        <div class="flex-1">
                            <h3 class="font-bold text-gray-800 mb-2">Thanh toán bằng ví BadmintonPro</h3>
                            <div class="flex justify-between items-center mb-3">
                                <span class="text-gray-600">Số dư hiện tại:</span>
                                <span id="wallet-balance" class="font-bold text-green-700 text-xl"><?php echo number_format($currentBalance, 0, ',', '.'); ?>đ</span>
                            </div>
                            <div class="flex justify-between items-center pt-3 border-t">
                                <span class="text-gray-600">Số tiền cần thanh toán:</span>
                                <span class="font-bold text-gray-800"><?php echo number_format($booking['total_price'], 0, ',', '.'); ?>đ</span>
                            </div>
                            <div id="wallet-warning" class="hidden mt-3 p-2 bg-red-100 rounded text-red-700 text-sm">
                                <i class="fas fa-exclamation-triangle mr-1"></i> Số dư không đủ để thanh toán! Vui lòng <a href="#" onclick="showTopupModal()" class="underline">nạp thêm tiền</a>.
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Nội dung chuyển khoản -->
                <div id="bank-box" class="hidden mb-6 border rounded-xl p-5 bg-blue-50">
                    <div class="flex items-start space-x-3">
                        <i class="fas fa-university text-blue-600 text-2xl mt-1"></i>
                        <div class="flex-1">
                            <h3 class="font-bold text-gray-800 mb-3">Chuyển khoản ngân hàng</h3>
                            <div class="grid md:grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm text-gray-600 mb-1">Ngân hàng thụ hưởng:</p>
                                    <p class="font-semibold">MB Bank - Ngân hàng Quân đội</p>
                                    <p class="text-sm text-gray-600 mt-2">Số tài khoản:</p>
                                    <p class="font-semibold font-mono text-lg">1234 5678 9012 3456</p>
                                    <p class="text-sm text-gray-600 mt-2">Chủ tài khoản:</p>
                                    <p class="font-semibold">BADMINTON PRO COMPANY</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600 mb-1">Nội dung chuyển khoản:</p>
                                    <div class="bg-white rounded-lg p-2 font-mono text-sm border text-green-700 mb-3 break-all" id="transfer-content">
                                        PAY_<?php echo $bookingId; ?>_<?php echo time(); ?>
                                    </div>
                                    <button onclick="copyTransferContent()" class="text-sm bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded transition">
                                        <i class="fas fa-copy mr-1"></i> Sao chép
                                    </button>
                                </div>
                            </div>
                            <div class="mt-4 text-center">
                                <p class="text-sm text-gray-600 mb-2">Hoặc quét mã QR để thanh toán nhanh</p>
                                <img id="qr-code" src="" alt="QR thanh toán" class="w-48 mx-auto border rounded-xl shadow">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Nút thanh toán -->
                <div class="flex flex-col gap-3">
                    <button id="pay-btn" class="bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white font-bold py-4 rounded-xl transition shadow-lg transform hover:scale-[1.02]">
                        <i class="fas fa-check-circle mr-2"></i>Xác nhận thanh toán
                    </button>
                    <a href="my_bookings.php" class="text-center bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-3 rounded-xl transition">
                        <i class="fas fa-arrow-left mr-2"></i>Quay lại lịch đặt
                    </a>
                </div>
                
                <div id="payment-result" class="mt-6 text-center"></div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal nạp tiền nhanh -->
<div id="topup-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl p-8 max-w-md w-full mx-4">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold">Nạp tiền vào ví</h3>
            <button onclick="closeTopupModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form id="topup-form">
            <div class="mb-6">
                <label class="block text-gray-700 mb-2 font-medium">Số tiền (VNĐ)</label>
                <input type="number" id="topup-amount" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500" min="10000" step="10000" required>
                <div class="flex gap-2 mt-3">
                    <button type="button" onclick="setTopupAmount(50000)" class="flex-1 px-2 py-1 bg-gray-100 rounded-lg text-sm">50K</button>
                    <button type="button" onclick="setTopupAmount(100000)" class="flex-1 px-2 py-1 bg-gray-100 rounded-lg text-sm">100K</button>
                    <button type="button" onclick="setTopupAmount(200000)" class="flex-1 px-2 py-1 bg-gray-100 rounded-lg text-sm">200K</button>
                    <button type="button" onclick="setTopupAmount(500000)" class="flex-1 px-2 py-1 bg-gray-100 rounded-lg text-sm">500K</button>
                </div>
            </div>
            <button type="submit" class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white py-3 rounded-xl font-semibold">
                Nạp tiền
            </button>
        </form>
    </div>
</div>

<script>
    const bookingId = parseInt(document.getElementById('booking-id').value);
    const totalPrice = parseInt(document.getElementById('total-price').value);
    let currentBalance = parseInt(document.getElementById('current-balance').value);
    let selectedMethod = '';
    let countdownInterval = null;
    let timeLeft = 15 * 60;
    
    // Đếm ngược
    function startCountdown() {
        const countdownEl = document.getElementById('countdown');
        if (!countdownEl) return;
        
        function updateCountdown() {
            if (timeLeft <= 0) {
                clearInterval(countdownInterval);
                countdownEl.innerHTML = '00:00';
                const payBtn = document.getElementById('pay-btn');
                if (payBtn) payBtn.disabled = true;
                document.getElementById('payment-result').innerHTML = '<p class="text-red-600 font-semibold">Đã hết thời gian thanh toán. Vui lòng đặt lại sân.</p>';
                return;
            }
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            countdownEl.innerHTML = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            timeLeft--;
        }
        updateCountdown();
        countdownInterval = setInterval(updateCountdown, 1000);
    }
    
    // Chọn phương thức thanh toán
    document.querySelectorAll('.payment-option').forEach(option => {
        option.addEventListener('click', function() {
            document.querySelectorAll('.payment-option').forEach(opt => {
                opt.classList.remove('selected', 'border-green-500', 'bg-green-50');
                opt.classList.add('border-gray-200');
            });
            this.classList.add('selected', 'border-green-500', 'bg-green-50');
            
            selectedMethod = this.dataset.method;
            document.getElementById('payment-method').value = selectedMethod;
            
            document.getElementById('wallet-box').classList.add('hidden');
            document.getElementById('bank-box').classList.add('hidden');
            
            if (selectedMethod === 'wallet') {
                document.getElementById('wallet-box').classList.remove('hidden');
                checkWalletBalance();
            } else if (selectedMethod === 'bank') {
                document.getElementById('bank-box').classList.remove('hidden');
                updateQRCode();
            }
        });
    });
    
    function checkWalletBalance() {
        const warning = document.getElementById('wallet-warning');
        const payBtn = document.getElementById('pay-btn');
        if (currentBalance < totalPrice) {
            if (warning) warning.classList.remove('hidden');
            if (payBtn) payBtn.disabled = true;
        } else {
            if (warning) warning.classList.add('hidden');
            if (payBtn) payBtn.disabled = false;
        }
    }
    
    function updateQRCode() {
        const content = document.getElementById('transfer-content').innerText;
        const qrUrl = `https://img.vietqr.io/image/MB-123456789-compact2.png?amount=${totalPrice}&addInfo=${encodeURIComponent(content)}&accountName=BADMINTON%20PRO`;
        document.getElementById('qr-code').src = qrUrl;
    }
    
    function copyTransferContent() {
        const content = document.getElementById('transfer-content').innerText;
        navigator.clipboard.writeText(content).then(() => showToast('Đã sao chép!', 'success'));
    }
    
    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = `fixed bottom-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 ${type === 'success' ? 'bg-green-600' : 'bg-red-600'} text-white`;
        toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} mr-2"></i>${message}`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
    
    // Xử lý thanh toán
    document.getElementById('pay-btn').addEventListener('click', async function() {
        if (!selectedMethod) {
            showToast('Vui lòng chọn phương thức thanh toán!', 'error');
            return;
        }
        
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Đang xử lý...';
        
        const formData = new FormData();
        formData.append('booking_id', bookingId);
        
        if (selectedMethod === 'wallet') {
            if (currentBalance < totalPrice) {
                showToast('Số dư không đủ!', 'error');
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Xác nhận thanh toán';
                return;
            }
            formData.append('action', 'process_wallet_payment');
        } else {
            const confirmed = confirm('Xác nhận bạn đã chuyển khoản thành công?');
            if (!confirmed) {
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Xác nhận thanh toán';
                return;
            }
            formData.append('action', 'confirm_bank_transfer');
        }
        
        try {
            const res = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            const data = await res.json();
            
            if (data.success) {
                showToast(data.message, 'success');
                document.getElementById('payment-result').innerHTML = `<p class="text-green-600 font-semibold">✓ ${data.message} Đang chuyển hướng...</p>`;
                setTimeout(() => window.location.href = 'my_bookings.php', 2000);
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            showToast(error.message, 'error');
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Xác nhận thanh toán';
        }
    });
    
    // Modal nạp tiền
    function showTopupModal() {
        document.getElementById('topup-modal').classList.remove('hidden');
    }
    function closeTopupModal() {
        document.getElementById('topup-modal').classList.add('hidden');
    }
    function setTopupAmount(amount) {
        document.getElementById('topup-amount').value = amount;
    }
    document.getElementById('topup-form')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const amount = parseInt(document.getElementById('topup-amount').value);
        if (amount < 10000) {
            showToast('Số tiền tối thiểu 10,000đ', 'error');
            return;
        }
        const formData = new FormData();
        formData.append('action', 'topup');
        formData.append('amount', amount);
        formData.append('csrf_token', '<?php echo $_SESSION['csrf_token'] ?? ''; ?>');
        
        const res = await fetch('index.php', { method: 'POST', body: formData });
        const text = await res.text();
        if (text.includes('thành công')) {
            showToast('Nạp tiền thành công!', 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast('Nạp tiền thất bại', 'error');
        }
        closeTopupModal();
    });
    
    // Khởi tạo
    startCountdown();
    checkWalletBalance();
</script>

</body>
</html>