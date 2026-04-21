<?php
// Include database configuration
require_once 'config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get court ID from URL
$courtId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$court = null;
$message = '';
$messageType = '';

if ($courtId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM courts WHERE court_id = ? AND is_active = 1");
        $stmt->execute([$courtId]);
        $court = $stmt->fetch();
    } catch (Exception $e) {
        // Handle error silently
    }
}

if (!$court) {
    header('Location: index.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'register':
                $fullname = trim($_POST['full_name'] ?? '');
                $username = trim($_POST['username'] ?? '');
                $phone = trim($_POST['phone_number'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';

                if (empty($fullname) || empty($username) || empty($phone) || empty($password)) {
                    $message = 'Tất cả các trường là bắt buộc';
                    $messageType = 'error';
                } else {
                    // Check if username or phone already exists
                    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? OR phone_number = ?");
                    $stmt->execute([$username, $phone]);
                    if ($stmt->fetch()) {
                        $message = 'Tên đăng nhập hoặc số điện thoại đã tồn tại';
                        $messageType = 'error';
                    } else {
                        // Hash password and insert user
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, phone_number, email, role, wallet_balance) VALUES (?, ?, ?, ?, ?, 'customer', 0)");
                        if ($stmt->execute([$username, $hashedPassword, $fullname, $phone, $email])) {
                            $message = 'Đăng ký thành công! Vui lòng đăng nhập.';
                            $messageType = 'success';
                        } else {
                            $message = 'Đăng ký thất bại';
                            $messageType = 'error';
                        }
                    }
                }
                break;

            case 'login':
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';

                if (empty($username) || empty($password)) {
                    $message = 'Tên đăng nhập và mật khẩu là bắt buộc';
                    $messageType = 'error';
                } else {
                    $stmt = $pdo->prepare("SELECT user_id, username, full_name, password_hash, role FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    $user = $stmt->fetch();

                    if ($user && password_verify($password, $user['password_hash'])) {
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['user_role'] = $user['role'];
                        header('Location: ' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING']);
                        exit;
                    } else {
                        $message = 'Tên đăng nhập hoặc mật khẩu không đúng';
                        $messageType = 'error';
                    }
                }
                break;

            case 'booking':
                if (!isset($_SESSION['user_id'])) {
                    $message = 'Vui lòng đăng nhập để đặt sân';
                    $messageType = 'error';
                } else {
                    $court_id = (int)($_POST['court_id'] ?? 0);
                    $start_time = $_POST['start_time'] ?? '';
                    $end_time = $_POST['end_time'] ?? '';

                    if (empty($court_id) || empty($start_time) || empty($end_time)) {
                        $message = 'Tất cả các trường đặt sân là bắt buộc';
                        $messageType = 'error';
                    } else {
                        // Check if start time is in the past
                        $now = new DateTime();
                        $start = new DateTime($start_time);
                        if ($start < $now) {
                            $message = 'Không thể đặt sân trong quá khứ';
                            $messageType = 'error';
                        } else {
                            // Check for booking conflicts
                            $stmt = $pdo->prepare("
                                SELECT COUNT(*) as conflicts FROM bookings
                                WHERE court_id = ? AND status != 'cancelled' AND (
                                    (start_time <= ? AND end_time > ?) OR
                                    (start_time < ? AND end_time >= ?) OR
                                    (start_time >= ? AND end_time <= ?)
                                )
                            ");
                            $stmt->execute([$court_id, $start_time, $start_time, $end_time, $end_time, $start_time, $end_time]);
                            $conflict = $stmt->fetch();

                            if ($conflict['conflicts'] > 0) {
                                $message = 'Khung giờ này đã được đặt. Vui lòng chọn khung giờ khác.';
                                $messageType = 'error';
                            } else {
                                // Calculate total price
                                $stmt = $pdo->prepare("SELECT price_per_hour FROM courts WHERE court_id = ?");
                                $stmt->execute([$court_id]);
                                $courtData = $stmt->fetch();

                                if ($courtData) {
                                    $start = new DateTime($start_time);
                                    $end = new DateTime($end_time);
                                    $diff = $start->diff($end);
                                    $hours = $diff->h + ($diff->i / 60);
                                    $total_price = $courtData['price_per_hour'] * $hours;

                                    $stmt = $pdo->prepare("INSERT INTO bookings (user_id, court_id, start_time, end_time, total_price, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
                                    if ($stmt->execute([$_SESSION['user_id'], $court_id, $start_time, $end_time, $total_price])) {
                                        $message = 'Đặt sân thành công! Vui lòng chờ xác nhận.';
                                        $messageType = 'success';
                                    } else {
                                        $message = 'Đặt sân thất bại';
                                        $messageType = 'error';
                                    }
                                }
                            }
                        }
                    }
                }
                break;

            case 'logout':
                session_destroy();
                header('Location: ' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING']);
                exit;
        }
    }
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? '';
$userRole = $_SESSION['user_role'] ?? '';

// Get booking history with pagination
$bookings = [];
$totalBookings = 0;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($currentPage - 1) * $perPage;

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM bookings WHERE court_id = ?");
    $stmt->execute([$courtId]);
    $totalBookings = $stmt->fetch()['total'];

    $stmt = $pdo->prepare("
        SELECT b.*, u.full_name, u.phone_number, u.username
        FROM bookings b
        JOIN users u ON b.user_id = u.user_id
        WHERE b.court_id = ?
        ORDER BY b.start_time DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $courtId, PDO::PARAM_INT);
    $stmt->bindValue(2, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $bookings = $stmt->fetchAll();
} catch (Exception $e) {
    // Handle error silently
}

$totalPages = ceil($totalBookings / $perPage);

// Get available time slots for today
$availableSlots = [];
$today = date('Y-m-d');
try {
    $stmt = $pdo->prepare("
        SELECT start_time, end_time FROM bookings 
        WHERE court_id = ? AND DATE(start_time) = ? AND status != 'cancelled'
        ORDER BY start_time
    ");
    $stmt->execute([$courtId, $today]);
    $bookedSlots = $stmt->fetchAll();
    
    // Generate available slots from 6:00 to 22:00
    $startHour = 6;
    $endHour = 22;
    for ($hour = $startHour; $hour < $endHour; $hour++) {
        $slotStart = sprintf("%02d:00", $hour);
        $slotEnd = sprintf("%02d:00", $hour + 1);
        $isBooked = false;
        foreach ($bookedSlots as $booked) {
            $bookedStart = date('H:i', strtotime($booked['start_time']));
            if ($bookedStart == $slotStart) {
                $isBooked = true;
                break;
            }
        }
        if (!$isBooked) {
            $availableSlots[] = ['start' => $slotStart, 'end' => $slotEnd];
        }
    }
} catch (Exception $e) {}

// Parse JSON data
$openingHours = json_decode($court['opening_hours'] ?? '{}', true);
$facilities = json_decode($court['facilities'] ?? '[]', true);
$reviews = json_decode($court['reviews'] ?? '[]', true);

// Calculate average rating
$avgRating = 0;
if (!empty($reviews)) {
    $totalRating = array_sum(array_column($reviews, 'rating'));
    $avgRating = round($totalRating / count($reviews), 1);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($court['court_name']); ?> - Đặt Sân Cầu Lông</title>
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { transition: all 0.3s ease; }
        .hover-scale:hover { transform: scale(1.02); }
        .image-fade-in { animation: fadeIn 0.5s ease-in; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .animate-fade-in { animation: fadeIn 0.3s ease-out; }
        .time-slot { cursor: pointer; transition: all 0.2s; }
        .time-slot:hover { transform: translateY(-2px); }
        .time-slot.selected { background: #22c55e !important; color: white !important; border-color: #22c55e !important; }
        .time-slot.booked { opacity: 0.5; cursor: not-allowed; }
    </style>
</head>
<body class="bg-gradient-to-br from-green-50 to-emerald-50">

<!-- Navigation - Phiên bản sửa lỗi menu dropdown -->
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
                <?php if ($isLoggedIn): ?>
                <!-- User Menu với JavaScript -->
                <div class="relative" id="userMenu">
                    <button id="userMenuBtn" class="text-white flex items-center space-x-2 focus:outline-none">
                        <i class="fas fa-user-circle text-2xl"></i>
                        <span><?php echo htmlspecialchars($userName); ?></span>
                        <i class="fas fa-chevron-down text-sm transition-transform duration-200" id="userMenuIcon"></i>
                    </button>
                    <div id="userDropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl hidden z-50">
                        <a href="my_bookings.php" class="block px-4 py-2 text-gray-700 hover:bg-green-50 hover:text-green-600 rounded-t-lg">
                            <i class="fas fa-calendar-alt mr-2"></i>Lịch đặt của tôi
                        </a>
                        <a href="wallet.php" class="block px-4 py-2 text-gray-700 hover:bg-green-50 hover:text-green-600">
                            <i class="fas fa-wallet mr-2"></i>Ví của tôi
                        </a>
                        <?php if ($userRole === 'owner'): ?>
                        <a href="admin_frontend/index.php" class="block px-4 py-2 text-gray-700 hover:bg-green-50 hover:text-green-600">
                            <i class="fas fa-chart-line mr-2"></i>Quản lý sân
                        </a>
                        <?php endif; ?>
                        <form method="POST" class="block">
                            <input type="hidden" name="action" value="logout">
                            <button type="submit" class="w-full text-left px-4 py-2 text-gray-700 hover:bg-red-50 hover:text-red-600 rounded-b-lg">
                                <i class="fas fa-sign-out-alt mr-2"></i>Đăng xuất
                            </button>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <button onclick="showModal('login')" class="bg-white text-green-600 px-5 py-2 rounded-lg hover:bg-green-50 transition duration-300 font-semibold shadow-md">
                    <i class="fas fa-sign-in-alt mr-2"></i>Đăng nhập
                </button>
                <button onclick="showModal('register')" class="bg-green-500 text-white px-5 py-2 rounded-lg hover:bg-green-400 transition duration-300 font-semibold shadow-md">
                    <i class="fas fa-user-plus mr-2"></i>Đăng ký
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- Hiển thị thông báo -->
<?php if ($message): ?>
<div class="max-w-7xl mx-auto px-4 mt-6">
    <div class="p-4 rounded-lg shadow-md <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800 border-l-4 border-green-500' : 'bg-red-100 text-red-800 border-l-4 border-red-500'; ?>">
        <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
        <?php echo htmlspecialchars($message); ?>
    </div>
</div>
<?php endif; ?>

<!-- Chi tiết sân -->
<section class="max-w-7xl mx-auto px-4 py-8">
    <div class="bg-white rounded-2xl shadow-2xl overflow-hidden hover-scale transition-all duration-500">
        <div class="md:flex">
            <!-- Ảnh sân -->
            <div class="md:w-1/2 bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                <?php 
                $courtImage = '';
                if (!empty($court['court_image'])) {
                    if (filter_var($court['court_image'], FILTER_VALIDATE_URL)) {
                        $courtImage = $court['court_image'];
                    } elseif (file_exists($court['court_image'])) {
                        $courtImage = $court['court_image'];
                    } elseif (file_exists('uploads/courts/' . $court['court_image'])) {
                        $courtImage = 'uploads/courts/' . $court['court_image'];
                    } elseif (file_exists('images/' . $court['court_image'])) {
                        $courtImage = 'images/' . $court['court_image'];
                    } else {
                        $courtImage = $court['court_image'];
                    }
                }
                
                if (empty($courtImage) || !file_exists(str_replace('../', '', $courtImage))) {
                    $courtImage = 'https://placehold.co/800x600/22c55e/white?text=' . urlencode($court['court_name']);
                }
                ?>
                <img src="<?php echo htmlspecialchars($courtImage); ?>" 
                     alt="<?php echo htmlspecialchars($court['court_name']); ?>" 
                     class="w-full h-96 object-cover image-fade-in"
                     onerror="this.src='https://placehold.co/800x600/22c55e/white?text=Badminton+Court'">
            </div>
            
            <!-- Thông tin sân -->
            <div class="md:w-1/2 p-8">
                <div class="mb-4 flex items-center justify-between">
                    <span class="inline-block bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm font-semibold">
                        <i class="fas fa-map-marker-alt mr-1"></i> <?php echo htmlspecialchars($court['location'] ?? 'TP. Hồ Chí Minh'); ?>
                    </span>
                    <?php if ($avgRating > 0): ?>
                    <div class="flex items-center">
                        <div class="flex text-yellow-400">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?php echo $i <= $avgRating ? 'text-yellow-400' : 'text-gray-300'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="ml-2 text-sm text-gray-600">(<?php echo count($reviews); ?> đánh giá)</span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <h1 class="text-4xl font-bold text-gray-800 mb-3"><?php echo htmlspecialchars($court['court_name']); ?></h1>
                <p class="text-green-600 font-medium mb-4">
                    <i class="fas fa-tag mr-1"></i> <?php echo htmlspecialchars($court['court_type'] ?? 'Sân cầu lông'); ?>
                </p>
                
                <div class="mb-5">
                    <span class="text-4xl font-bold text-green-600"><?php echo number_format($court['price_per_hour']); ?>đ</span>
                    <span class="text-gray-500 ml-2">/giờ</span>
                </div>
                
                <p class="text-gray-600 mb-6 leading-relaxed"><?php echo nl2br(htmlspecialchars($court['description'] ?? 'Sân cầu lông chất lượng cao với đầy đủ tiện nghi')); ?></p>
                
                <!-- Tiện ích -->
                <?php if (!empty($facilities)): ?>
                <div class="mb-5">
                    <h3 class="font-bold text-gray-800 mb-3 text-lg">
                        <i class="fas fa-wifi text-green-600 mr-2"></i>Tiện ích:
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($facilities as $facility): ?>
                        <span class="bg-green-50 text-green-700 px-3 py-1.5 rounded-full text-sm font-medium">
                            <i class="fas fa-check-circle text-xs mr-1"></i> <?php echo htmlspecialchars($facility); ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Giờ mở cửa -->
                <?php if (!empty($openingHours)): ?>
                <div class="mb-6">
                    <h3 class="font-bold text-gray-800 mb-3 text-lg">
                        <i class="far fa-clock text-green-600 mr-2"></i>Giờ mở cửa:
                    </h3>
                    <div class="grid grid-cols-2 gap-3 text-sm bg-gray-50 p-4 rounded-lg">
                        <?php foreach ($openingHours as $day => $hours): ?>
                        <div class="flex justify-between">
                            <span class="font-semibold text-gray-700"><?php echo ucfirst($day); ?>:</span>
                            <span class="text-gray-600"><?php echo $hours['open'] ?? '--'; ?> - <?php echo $hours['close'] ?? '--'; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Form đặt sân với chọn giờ nhanh -->
<?php if ($isLoggedIn): ?>
<section class="max-w-7xl mx-auto px-4 py-8">
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-4">
            <h2 class="text-2xl font-bold text-white">
                <i class="fas fa-calendar-check mr-2"></i>Đặt sân ngay
            </h2>
            <p class="text-green-100 mt-1">Chọn khung giờ bạn muốn đặt sân</p>
        </div>
        
        <div class="p-6">
            <form method="POST" id="bookingForm">
                <input type="hidden" name="action" value="booking">
                <input type="hidden" name="court_id" value="<?php echo $courtId; ?>">
                <input type="hidden" name="start_time" id="start_time">
                <input type="hidden" name="end_time" id="end_time">
                
                <div class="mb-6">
                    <label class="block text-gray-700 font-semibold mb-3">
                        <i class="fas fa-calendar-day text-green-600 mr-2"></i>Chọn ngày
                    </label>
                    <input type="date" id="booking_date" class="w-full md:w-64 border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none transition">
                </div>
                
                <div class="mb-6">
                    <label class="block text-gray-700 font-semibold mb-3">
                        <i class="fas fa-clock text-green-600 mr-2"></i>Chọn khung giờ
                    </label>
                    <div id="timeSlots" class="grid grid-cols-4 md:grid-cols-8 gap-3">
                        <!-- Time slots sẽ được load bằng JS -->
                        <div class="col-span-4 text-center py-8 text-gray-500">
                            <i class="fas fa-spinner fa-spin mr-2"></i>Đang tải khung giờ...
                        </div>
                    </div>
                </div>
                
                <div id="bookingSummary" class="hidden mt-6 p-4 bg-green-50 rounded-lg border border-green-200">
                    <h3 class="font-bold text-green-800 mb-2">Thông tin đặt sân</h3>
                    <p><strong>Sân:</strong> <?php echo htmlspecialchars($court['court_name']); ?></p>
                    <p><strong>Thời gian:</strong> <span id="summaryTime"></span></p>
                    <p><strong>Số giờ:</strong> <span id="summaryHours"></span> giờ</p>
                    <p><strong>Tổng tiền:</strong> <span id="summaryPrice" class="text-xl font-bold text-green-600"></span></p>
                </div>
                
                <button type="submit" id="submitBtn" disabled class="mt-6 w-full bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white font-bold py-3.5 rounded-lg transition duration-300 shadow-lg transform hover:scale-[1.02] disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-check-circle mr-2"></i>Xác nhận đặt sân
                </button>
            </form>
        </div>
    </div>
</section>
<?php else: ?>
<section class="max-w-7xl mx-auto px-4 py-8">
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-4">
            <h2 class="text-2xl font-bold text-white">
                <i class="fas fa-calendar-check mr-2"></i>Đặt sân ngay
            </h2>
        </div>
        <div class="p-8 text-center">
            <i class="fas fa-lock text-yellow-600 text-5xl mb-4"></i>
            <p class="text-gray-700 text-lg mb-4">Vui lòng đăng nhập để đặt sân</p>
            <button onclick="showModal('login')" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition">
                <i class="fas fa-sign-in-alt mr-2"></i>Đăng nhập ngay
            </button>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Lịch sử đặt sân -->
<section class="max-w-7xl mx-auto px-4 py-8">
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-4">
            <h2 class="text-2xl font-bold text-white">
                <i class="fas fa-history mr-2"></i>Lịch sử đặt sân
            </h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr class="border-b-2 border-green-200">
                        <th class="py-4 px-6 text-left text-gray-700 font-semibold">Khách hàng</th>
                        <th class="py-4 px-6 text-left text-gray-700 font-semibold">Thời gian</th>
                        <th class="py-4 px-6 text-left text-gray-700 font-semibold">Tổng tiền</th>
                        <th class="py-4 px-6 text-left text-gray-700 font-semibold">Trạng thái</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($bookings)): ?>
                    <tr>
                        <td colspan="4" class="py-12 text-center text-gray-500">
                            <i class="fas fa-calendar-times text-4xl mb-3 opacity-50"></i>
                            <p>Chưa có đặt sân nào</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($bookings as $booking): ?>
                    <tr class="hover:bg-green-50 transition">
                        <td class="py-3 px-6">
                            <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($booking['full_name']); ?></div>
                            <div class="text-sm text-gray-500"><i class="fas fa-phone-alt mr-1"></i><?php echo htmlspecialchars($booking['phone_number']); ?></div>
                        </td>
                        <td class="py-3 px-6">
                            <div><?php echo date('d/m/Y', strtotime($booking['start_time'])); ?></div>
                            <div class="text-sm text-gray-500"><?php echo date('H:i', strtotime($booking['start_time'])); ?> - <?php echo date('H:i', strtotime($booking['end_time'])); ?></div>
                        </td>
                        <td class="py-3 px-6 font-semibold text-green-600"><?php echo number_format($booking['total_price']); ?>đ</td>
                        <td class="py-3 px-6">
                            <?php
                            $statusClass = [
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                'confirmed' => 'bg-green-100 text-green-800',
                                'cancelled' => 'bg-red-100 text-red-800',
                                'completed' => 'bg-blue-100 text-blue-800'
                            ];
                            $statusIcon = [
                                'pending' => 'fa-clock',
                                'confirmed' => 'fa-check-circle',
                                'cancelled' => 'fa-times-circle',
                                'completed' => 'fa-star'
                            ];
                            $statusText = [
                                'pending' => 'Chờ xác nhận',
                                'confirmed' => 'Đã xác nhận',
                                'cancelled' => 'Đã hủy',
                                'completed' => 'Hoàn thành'
                            ];
                            ?>
                            <span class="px-3 py-1.5 rounded-full text-sm font-medium inline-flex items-center gap-1 <?php echo $statusClass[$booking['status']] ?? 'bg-gray-100'; ?>">
                                <i class="fas <?php echo $statusIcon[$booking['status']] ?? 'fa-info-circle'; ?>"></i>
                                <?php echo $statusText[$booking['status']] ?? $booking['status']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Phân trang -->
        <?php if ($totalPages > 1): ?>
        <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between bg-gray-50">
            <div class="text-sm text-gray-600">
                <i class="fas fa-chart-line mr-1"></i> Hiển thị <?php echo ($currentPage-1)*$perPage+1; ?> - <?php echo min($currentPage*$perPage, $totalBookings); ?> của <?php echo $totalBookings; ?>
            </div>
            <div class="flex space-x-2">
                <?php if ($currentPage > 1): ?>
                <a href="?id=<?php echo $courtId; ?>&page=<?php echo $currentPage-1; ?>" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-green-50 hover:border-green-400 transition">
                    <i class="fas fa-chevron-left mr-1"></i> Trước
                </a>
                <?php endif; ?>
                <span class="px-4 py-2 bg-green-600 text-white rounded-lg shadow"><?php echo $currentPage; ?></span>
                <?php if ($currentPage < $totalPages): ?>
                <a href="?id=<?php echo $courtId; ?>&page=<?php echo $currentPage+1; ?>" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-green-50 hover:border-green-400 transition">
                    Sau <i class="fas fa-chevron-right ml-1"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Modal đăng nhập / đăng ký -->
<div id="login-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl max-w-md w-full p-8 animate-fade-in shadow-2xl">
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center space-x-2">
                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-sign-in-alt text-green-600 text-xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800">Đăng nhập</h3>
            </div>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="login">
            <div class="mb-4">
                <label class="block text-gray-700 mb-2 font-semibold">Tên đăng nhập</label>
                <div class="relative">
                    <i class="fas fa-user absolute left-3 top-3.5 text-gray-400"></i>
                    <input type="text" name="username" required class="w-full border-2 border-gray-200 rounded-lg pl-10 pr-4 py-2.5 focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none transition">
                </div>
            </div>
            <div class="mb-6">
                <label class="block text-gray-700 mb-2 font-semibold">Mật khẩu</label>
                <div class="relative">
                    <i class="fas fa-lock absolute left-3 top-3.5 text-gray-400"></i>
                    <input type="password" name="password" required class="w-full border-2 border-gray-200 rounded-lg pl-10 pr-4 py-2.5 focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none transition">
                </div>
            </div>
            <button type="submit" class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white py-3 rounded-lg hover:from-green-700 hover:to-emerald-700 transition font-semibold shadow-md">
                <i class="fas fa-arrow-right mr-2"></i>Đăng nhập
            </button>
        </form>
        <div class="text-center mt-5 pt-4 border-t">
            <button onclick="showRegister()" class="text-green-600 hover:text-green-700 font-semibold">
                Chưa có tài khoản? Đăng ký ngay <i class="fas fa-arrow-right ml-1"></i>
            </button>
        </div>
    </div>
</div>

<div id="register-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl max-w-md w-full p-8 animate-fade-in shadow-2xl">
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center space-x-2">
                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-user-plus text-green-600 text-xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800">Đăng ký</h3>
            </div>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="register">
            <div class="mb-3">
                <label class="block text-gray-700 mb-1 font-semibold">Họ tên</label>
                <input type="text" name="full_name" required class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none transition">
            </div>
            <div class="mb-3">
                <label class="block text-gray-700 mb-1 font-semibold">Tên đăng nhập</label>
                <input type="text" name="username" required class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none transition">
            </div>
            <div class="mb-3">
                <label class="block text-gray-700 mb-1 font-semibold">Số điện thoại</label>
                <input type="tel" name="phone_number" required class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none transition">
            </div>
            <div class="mb-3">
                <label class="block text-gray-700 mb-1 font-semibold">Email</label>
                <input type="email" name="email" class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none transition">
            </div>
            <div class="mb-5">
                <label class="block text-gray-700 mb-1 font-semibold">Mật khẩu</label>
                <input type="password" name="password" required class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none transition">
            </div>
            <button type="submit" class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white py-3 rounded-lg hover:from-green-700 hover:to-emerald-700 transition font-semibold shadow-md">
                <i class="fas fa-user-plus mr-2"></i>Đăng ký
            </button>
        </form>
        <div class="text-center mt-5 pt-4 border-t">
            <button onclick="showLogin()" class="text-green-600 hover:text-green-700 font-semibold">
                Đã có tài khoản? Đăng nhập <i class="fas fa-arrow-right ml-1"></i>
            </button>
        </div>
    </div>
</div>

<script>
    const courtId = <?php echo $courtId; ?>;
    const pricePerHour = <?php echo $court['price_per_hour']; ?>;
    
    function showModal(type) {
        if (type === 'login') {
            document.getElementById('login-modal').classList.remove('hidden');
            document.getElementById('login-modal').classList.add('flex');
            document.getElementById('register-modal').classList.add('hidden');
        } else {
            document.getElementById('register-modal').classList.remove('hidden');
            document.getElementById('register-modal').classList.add('flex');
            document.getElementById('login-modal').classList.add('hidden');
        }
        document.body.style.overflow = 'hidden';
    }
    
    function closeModal() {
        document.getElementById('login-modal').classList.add('hidden');
        document.getElementById('register-modal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
    
    function showLogin() { showModal('login'); }
    function showRegister() { showModal('register'); }
    
    window.onclick = function(event) {
        const loginModal = document.getElementById('login-modal');
        const registerModal = document.getElementById('register-modal');
        if (event.target === loginModal) closeModal();
        if (event.target === registerModal) closeModal();
    }
    
    // Load time slots based on selected date
    const bookingDate = document.getElementById('booking_date');
    const timeSlotsDiv = document.getElementById('timeSlots');
    const startTimeInput = document.getElementById('start_time');
    const endTimeInput = document.getElementById('end_time');
    const submitBtn = document.getElementById('submitBtn');
    const bookingSummary = document.getElementById('bookingSummary');
    
    let selectedSlot = null;
    
    // Set min date to today
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    bookingDate.min = today.toISOString().split('T')[0];
    bookingDate.value = today.toISOString().split('T')[0];
    
    function loadTimeSlots() {
        const date = bookingDate.value;
        if (!date) return;
        
        timeSlotsDiv.innerHTML = '<div class="col-span-4 text-center py-8 text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Đang tải khung giờ...</div>';
        
        fetch(`get_available_slots.php?court_id=${courtId}&date=${date}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.slots.length === 0) {
                        timeSlotsDiv.innerHTML = '<div class="col-span-4 text-center py-8 text-gray-500"><i class="fas fa-info-circle mr-2"></i>Không có khung giờ trống trong ngày này</div>';
                    } else {
                        timeSlotsDiv.innerHTML = '';
                        data.slots.forEach(slot => {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = `time-slot px-4 py-3 rounded-lg border-2 font-medium transition-all ${slot.booked ? 'booked bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed' : 'bg-white text-gray-700 border-gray-200 hover:border-green-400 hover:bg-green-50'}`;
                            btn.innerHTML = `${slot.start}<br><span class="text-xs">${slot.end}</span>`;
                            btn.onclick = () => {
                                if (slot.booked) return;
                                if (selectedSlot) {
                                    const prevBtn = document.querySelector(`.time-slot[data-slot="${selectedSlot}"]`);
                                    if (prevBtn) prevBtn.classList.remove('selected');
                                }
                                btn.classList.add('selected');
                                selectedSlot = slot.start;
                                startTimeInput.value = `${date} ${slot.start}:00`;
                                endTimeInput.value = `${date} ${slot.end}:00`;
                                updateSummary(slot);
                                submitBtn.disabled = false;
                            };
                            btn.setAttribute('data-slot', slot.start);
                            timeSlotsDiv.appendChild(btn);
                        });
                    }
                } else {
                    timeSlotsDiv.innerHTML = '<div class="col-span-4 text-center py-8 text-red-500"><i class="fas fa-exclamation-triangle mr-2"></i>Lỗi tải dữ liệu</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                timeSlotsDiv.innerHTML = '<div class="col-span-4 text-center py-8 text-red-500"><i class="fas fa-exclamation-triangle mr-2"></i>Lỗi kết nối</div>';
            });
    }
    
    function updateSummary(slot) {
        bookingSummary.classList.remove('hidden');
        const start = slot.start;
        const end = slot.end;
        const hours = 1; // Mỗi slot là 1 giờ
        
        document.getElementById('summaryTime').innerHTML = `${start} - ${end}`;
        document.getElementById('summaryHours').innerHTML = hours;
        document.getElementById('summaryPrice').innerHTML = new Intl.NumberFormat('vi-VN').format(pricePerHour * hours) + 'đ';
    }
    
    bookingDate.addEventListener('change', () => {
        selectedSlot = null;
        submitBtn.disabled = true;
        bookingSummary.classList.add('hidden');
        loadTimeSlots();
    });
    
    // Initial load
    loadTimeSlots();
    
    // Form validation
    document.getElementById('bookingForm').addEventListener('submit', function(e) {
        if (!selectedSlot) {
            e.preventDefault();
            alert('Vui lòng chọn khung giờ đặt sân');
            return false;
        }
    });
</script>
<script>
    // ========== USER MENU DROPDOWN ==========
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userDropdown = document.getElementById('userDropdown');
    const userMenuIcon = document.getElementById('userMenuIcon');
    
    if (userMenuBtn && userDropdown) {
        // Toggle menu khi click vào button
        userMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isHidden = userDropdown.classList.contains('hidden');
            
            // Đóng tất cả menu khác
            document.querySelectorAll('.user-dropdown').forEach(dropdown => {
                if (dropdown !== userDropdown) {
                    dropdown.classList.add('hidden');
                }
            });
            
            if (isHidden) {
                userDropdown.classList.remove('hidden');
                userMenuIcon.style.transform = 'rotate(180deg)';
            } else {
                userDropdown.classList.add('hidden');
                userMenuIcon.style.transform = 'rotate(0deg)';
            }
        });
        
        // Giữ menu mở khi hover vào dropdown
        let hoverTimeout;
        
        userMenuBtn.addEventListener('mouseenter', function() {
            clearTimeout(hoverTimeout);
            userDropdown.classList.remove('hidden');
            userMenuIcon.style.transform = 'rotate(180deg)';
        });
        
        userDropdown.addEventListener('mouseenter', function() {
            clearTimeout(hoverTimeout);
            userDropdown.classList.remove('hidden');
        });
        
        userDropdown.addEventListener('mouseleave', function() {
            hoverTimeout = setTimeout(() => {
                userDropdown.classList.add('hidden');
                userMenuIcon.style.transform = 'rotate(0deg)';
            }, 200);
        });
        
        userMenuBtn.addEventListener('mouseleave', function() {
            hoverTimeout = setTimeout(() => {
                if (!userDropdown.matches(':hover')) {
                    userDropdown.classList.add('hidden');
                    userMenuIcon.style.transform = 'rotate(0deg)';
                }
            }, 200);
        });
        
        // Đóng menu khi click ra ngoài
        document.addEventListener('click', function(e) {
            if (!userMenuBtn.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.classList.add('hidden');
                userMenuIcon.style.transform = 'rotate(0deg)';
            }
        });
    }
    
    // Các hàm modal vẫn giữ nguyên
    function showModal(type) {
        if (type === 'login') {
            document.getElementById('login-modal').classList.remove('hidden');
            document.getElementById('login-modal').classList.add('flex');
            document.getElementById('register-modal').classList.add('hidden');
        } else {
            document.getElementById('register-modal').classList.remove('hidden');
            document.getElementById('register-modal').classList.add('flex');
            document.getElementById('login-modal').classList.add('hidden');
        }
        document.body.style.overflow = 'hidden';
    }
    
    function closeModal() {
        document.getElementById('login-modal').classList.add('hidden');
        document.getElementById('register-modal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
    
    function showLogin() { showModal('login'); }
    function showRegister() { showModal('register'); }
    
    window.onclick = function(event) {
        const loginModal = document.getElementById('login-modal');
        const registerModal = document.getElementById('register-modal');
        if (event.target === loginModal) closeModal();
        if (event.target === registerModal) closeModal();
    }
</script>

</body>
</html>