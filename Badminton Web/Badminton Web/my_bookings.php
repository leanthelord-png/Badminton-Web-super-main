<?php
// my_bookings.php - Quản lý lịch đặt sân của người dùng
require_once 'config/database.php';
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=Vui lòng đăng nhập để xem lịch đặt');
    exit;
}

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Xử lý hủy đặt sân
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = 'Lỗi bảo mật, vui lòng thử lại';
        $messageType = 'error';
    } elseif ($_POST['action'] === 'cancel_booking') {
        $bookingId = (int)$_POST['booking_id'];
        
        try {
            // Kiểm tra booking có thuộc về user không và có thể hủy không
            $stmt = $pdo->prepare("
                SELECT b.*, c.court_name 
                FROM bookings b
                JOIN courts c ON b.court_id = c.court_id
                WHERE b.booking_id = ? AND b.user_id = ? AND b.status IN ('pending', 'confirmed')
            ");
            $stmt->execute([$bookingId, $userId]);
            $booking = $stmt->fetch();
            
            if (!$booking) {
                $message = 'Không tìm thấy đặt sân hoặc không thể hủy';
                $messageType = 'error';
            } else {
                // Kiểm tra thời gian hủy (phải hủy trước 2 giờ)
                $startTime = strtotime($booking['start_time']);
                $currentTime = time();
                $hoursDiff = ($startTime - $currentTime) / 3600;
                
                if ($hoursDiff < 2) {
                    $message = 'Chỉ có thể hủy đặt sân trước 2 giờ so với giờ bắt đầu';
                    $messageType = 'error';
                } else {
                    $pdo->beginTransaction();
                    
                    // Cập nhật trạng thái booking
                    $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE booking_id = ?");
                    $stmt->execute([$bookingId]);
                    
                    // Hoàn tiền nếu đã thanh toán
                    if ($booking['status'] === 'paid') {
                        // Cập nhật số dư user
                        $stmt = $pdo->prepare("UPDATE users SET user_balance = user_balance + ? WHERE user_id = ?");
                        $stmt->execute([$booking['total_price'], $userId]);
                        
                        // Cập nhật wallet
                        $stmt = $pdo->prepare("
                            UPDATE user_wallets 
                            SET balance = balance + ?, updated_at = NOW() 
                            WHERE user_id = ?
                        ");
                        $stmt->execute([$booking['total_price'], $userId]);
                        
                        // Ghi nhận giao dịch hoàn tiền
                        $stmt = $pdo->prepare("
                            INSERT INTO transactions (user_id, type, amount, status, description, created_at) 
                            VALUES (?, 'refund', ?, 'completed', ?, NOW())
                        ");
                        $stmt->execute([$userId, $booking['total_price'], 'Hoàn tiền hủy đặt sân: ' . $booking['court_name']]);
                        
                        // Cập nhật payment status
                        $stmt = $pdo->prepare("UPDATE payments SET status = 'refunded' WHERE booking_id = ?");
                        $stmt->execute([$bookingId]);
                    }
                    
                    $pdo->commit();
                    $message = 'Hủy đặt sân thành công!';
                    $messageType = 'success';
                }
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Hủy đặt sân thất bại: ' . $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($_POST['action'] === 'submit_review') {
        $bookingId = (int)$_POST['booking_id'];
        $rating = (int)$_POST['rating'];
        $comment = trim($_POST['comment'] ?? '');
        
        if ($rating < 1 || $rating > 5) {
            $message = 'Vui lòng chọn số sao đánh giá từ 1-5';
            $messageType = 'error';
        } else {
            try {
                // Lấy court_id từ booking
                $stmt = $pdo->prepare("SELECT court_id FROM bookings WHERE booking_id = ? AND user_id = ?");
                $stmt->execute([$bookingId, $userId]);
                $booking = $stmt->fetch();
                
                if (!$booking) {
                    $message = 'Không tìm thấy đặt sân';
                    $messageType = 'error';
                } else {
                    $courtId = $booking['court_id'];
                    
                    // Kiểm tra đã đánh giá chưa
                    $stmt = $pdo->prepare("SELECT id FROM court_reviews WHERE booking_id = ?");
                    $stmt->execute([$bookingId]);
                    $existing = $stmt->fetch();
                    
                    if ($existing) {
                        // Cập nhật đánh giá cũ
                        $stmt = $pdo->prepare("
                            UPDATE court_reviews 
                            SET rating = ?, comment = ?, updated_at = NOW() 
                            WHERE booking_id = ?
                        ");
                        $stmt->execute([$rating, $comment, $bookingId]);
                    } else {
                        // Thêm đánh giá mới
                        $stmt = $pdo->prepare("
                            INSERT INTO court_reviews (court_id, user_id, booking_id, rating, comment, created_at) 
                            VALUES (?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([$courtId, $userId, $bookingId, $rating, $comment]);
                    }
                    
                    // Cập nhật rating trung bình của sân
                    $stmt = $pdo->prepare("
                        UPDATE courts SET 
                            avg_rating = (SELECT AVG(rating) FROM court_reviews WHERE court_id = ?),
                            total_reviews = (SELECT COUNT(*) FROM court_reviews WHERE court_id = ?)
                        WHERE court_id = ?
                    ");
                    $stmt->execute([$courtId, $courtId, $courtId]);
                    
                    $message = 'Cảm ơn bạn đã đánh giá!';
                    $messageType = 'success';
                }
            } catch (Exception $e) {
                $message = 'Đánh giá thất bại: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Lấy danh sách đặt sân với bộ lọc
$statusFilter = $_GET['status'] ?? 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Xây dựng câu lệnh truy vấn
$whereCondition = "b.user_id = ?";
$params = [$userId];

if ($statusFilter !== 'all') {
    $whereCondition .= " AND b.status = ?";
    $params[] = $statusFilter;
}

// Đếm tổng số
$countStmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM bookings b 
    WHERE $whereCondition
");
$countStmt->execute($params);
$totalBookings = $countStmt->fetch()['total'];
$totalPages = ceil($totalBookings / $perPage);

// Lấy danh sách booking
$params[] = $perPage;
$params[] = $offset;
$stmt = $pdo->prepare("
    SELECT 
        b.*,
        c.court_name,
        c.address,
        c.price_per_hour,
        c.court_image,
        c.court_type,
        (SELECT COUNT(*) FROM court_reviews cr WHERE cr.booking_id = b.booking_id) as has_reviewed
    FROM bookings b
    JOIN courts c ON b.court_id = c.court_id
    WHERE $whereCondition
    ORDER BY 
        CASE 
            WHEN b.status = 'pending' THEN 1
            WHEN b.status = 'confirmed' THEN 2
            WHEN b.status = 'paid' THEN 3
            ELSE 4
        END,
        b.start_time DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Thống kê theo trạng thái
$stats = [
    'pending' => 0,
    'confirmed' => 0,
    'paid' => 0,
    'cancelled' => 0,
    'completed' => 0
];

$stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM bookings WHERE user_id = ? GROUP BY status");
$stmt->execute([$userId]);
foreach ($stmt->fetchAll() as $stat) {
    $stats[$stat['status']] = $stat['count'];
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch Đặt Sân Của Tôi - BadmintonPro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        .status-badge {
            @apply px-3 py-1 rounded-full text-xs font-semibold;
        }
        .status-pending { @apply bg-yellow-100 text-yellow-800; }
        .status-confirmed { @apply bg-blue-100 text-blue-800; }
        .status-paid { @apply bg-green-100 text-green-800; }
        .status-cancelled { @apply bg-red-100 text-red-800; }
        .status-completed { @apply bg-gray-100 text-gray-800; }
        
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .modal-enter {
            animation: modalEnter 0.3s ease-out;
        }
        
        @keyframes modalEnter {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .star-rating {
            display: inline-flex;
            gap: 4px;
            cursor: pointer;
        }
        .star-rating i {
            font-size: 24px;
            color: #d1d5db;
            transition: color 0.2s;
        }
        .star-rating i.active {
            color: #fbbf24;
        }
        .star-rating i:hover,
        .star-rating i:hover ~ i {
            color: #fbbf24;
        }
        
        @media (max-width: 768px) {
            .booking-card {
                flex-direction: column;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-3">
                    <div class="bg-gradient-to-r from-green-500 to-emerald-600 p-2 rounded-xl">
                        <i class="fas fa-shuttlecock text-white text-xl"></i>
                    </div>
                    <a href="index.php" class="font-extrabold text-2xl bg-gradient-to-r from-green-600 to-emerald-600 bg-clip-text text-transparent">
                        BadmintonPro
                    </a>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="text-gray-600 hover:text-green-600 transition">
                        <i class="fas fa-home"></i>
                        <span class="hidden md:inline ml-1">Trang chủ</span>
                    </a>
                    <a href="my_favorites.php" class="text-gray-600 hover:text-red-500 transition">
                        <i class="fas fa-heart"></i>
                        <span class="hidden md:inline ml-1">Yêu thích</span>
                    </a>
                    <div class="relative group">
                        <button class="flex items-center space-x-3 bg-gray-100 hover:bg-gray-200 rounded-full px-4 py-2 transition">
                            <div class="w-8 h-8 bg-gradient-to-r from-green-500 to-emerald-600 rounded-full flex items-center justify-center">
                                <i class="fas fa-user text-white text-sm"></i>
                            </div>
                            <span class="font-medium text-gray-700"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></span>
                            <i class="fas fa-chevron-down text-gray-500 text-xs"></i>
                        </button>
                        <div class="absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 z-50">
                            <div class="py-2">
                                <a href="my_bookings.php" class="flex items-center px-4 py-2 hover:bg-gray-50 transition text-green-600">
                                    <i class="fas fa-calendar-alt w-5"></i>
                                    <span class="ml-3">Lịch đặt của tôi</span>
                                </a>
                                <a href="my_favorites.php" class="flex items-center px-4 py-2 hover:bg-gray-50 transition">
                                    <i class="fas fa-heart w-5 text-red-500"></i>
                                    <span class="ml-3">Sân yêu thích</span>
                                </a>
                                <hr class="my-2">
                                <form method="POST" action="index.php">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="action" value="logout">
                                    <button type="submit" class="w-full text-left flex items-center px-4 py-2 hover:bg-red-50 transition text-red-600">
                                        <i class="fas fa-sign-out-alt w-5"></i>
                                        <span class="ml-3">Đăng xuất</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Messages -->
    <?php if ($message): ?>
    <div class="fixed top-20 right-4 z-50 animate-slide-down" id="toastMessage">
        <div class="p-4 rounded-xl shadow-lg max-w-md <?php echo $messageType === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'; ?>">
            <div class="flex items-center space-x-3">
                <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> text-xl"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-auto text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
    <script>
        setTimeout(function() {
            var toast = document.getElementById('toastMessage');
            if(toast) toast.remove();
        }, 5000);
    </script>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="bg-gradient-to-r from-green-600 to-emerald-600 text-white py-12">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div>
                    <h1 class="text-3xl md:text-4xl font-bold mb-2">Lịch Đặt Sân Của Tôi</h1>
                    <p class="text-green-100">Quản lý tất cả đặt sân của bạn tại đây</p>
                </div>
                <a href="index.php#booking-section" class="mt-4 md:mt-0 bg-white text-green-600 px-6 py-3 rounded-xl font-semibold hover:shadow-lg transition inline-flex items-center">
                    <i class="fas fa-plus-circle mr-2"></i>
                    Đặt sân mới
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="max-w-7xl mx-auto px-4 -mt-6">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div class="bg-white rounded-xl p-4 shadow-md text-center">
                <div class="text-yellow-600 text-2xl mb-1">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800"><?php echo $stats['pending']; ?></div>
                <div class="text-sm text-gray-500">Chờ xác nhận</div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-md text-center">
                <div class="text-blue-600 text-2xl mb-1">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800"><?php echo $stats['confirmed']; ?></div>
                <div class="text-sm text-gray-500">Đã xác nhận</div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-md text-center">
                <div class="text-green-600 text-2xl mb-1">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800"><?php echo $stats['paid']; ?></div>
                <div class="text-sm text-gray-500">Đã thanh toán</div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-md text-center">
                <div class="text-gray-600 text-2xl mb-1">
                    <i class="fas fa-check-double"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800"><?php echo $stats['completed'] ?? 0; ?></div>
                <div class="text-sm text-gray-500">Đã hoàn thành</div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-md text-center">
                <div class="text-red-600 text-2xl mb-1">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="text-2xl font-bold text-gray-800"><?php echo $stats['cancelled']; ?></div>
                <div class="text-sm text-gray-500">Đã hủy</div>
            </div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="max-w-7xl mx-auto px-4 mt-8">
        <div class="flex flex-wrap gap-2 border-b border-gray-200">
            <a href="?status=all" 
               class="px-4 py-2 font-medium <?php echo $statusFilter === 'all' ? 'text-green-600 border-b-2 border-green-600' : 'text-gray-500 hover:text-gray-700'; ?>">
                Tất cả (<?php echo $totalBookings; ?>)
            </a>
            <a href="?status=pending" 
               class="px-4 py-2 font-medium <?php echo $statusFilter === 'pending' ? 'text-green-600 border-b-2 border-green-600' : 'text-gray-500 hover:text-gray-700'; ?>">
                Chờ xác nhận (<?php echo $stats['pending']; ?>)
            </a>
            <a href="?status=confirmed" 
               class="px-4 py-2 font-medium <?php echo $statusFilter === 'confirmed' ? 'text-green-600 border-b-2 border-green-600' : 'text-gray-500 hover:text-gray-700'; ?>">
                Đã xác nhận (<?php echo $stats['confirmed']; ?>)
            </a>
            <a href="?status=paid" 
               class="px-4 py-2 font-medium <?php echo $statusFilter === 'paid' ? 'text-green-600 border-b-2 border-green-600' : 'text-gray-500 hover:text-gray-700'; ?>">
                Đã thanh toán (<?php echo $stats['paid']; ?>)
            </a>
            <a href="?status=cancelled" 
               class="px-4 py-2 font-medium <?php echo $statusFilter === 'cancelled' ? 'text-green-600 border-b-2 border-green-600' : 'text-gray-500 hover:text-gray-700'; ?>">
                Đã hủy (<?php echo $stats['cancelled']; ?>)
            </a>
        </div>
    </div>

    <!-- Bookings List -->
    <div class="max-w-7xl mx-auto px-4 py-8">
        <?php if (empty($bookings)): ?>
            <div class="text-center py-16 bg-white rounded-2xl shadow-sm">
                <i class="fas fa-calendar-alt text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-600 mb-2">Chưa có đặt sân nào</h3>
                <p class="text-gray-500 mb-6">Bạn chưa có đặt sân nào trong mục này</p>
                <a href="index.php#booking-section" class="inline-flex items-center bg-gradient-to-r from-green-600 to-emerald-600 text-white px-6 py-3 rounded-xl font-semibold hover:shadow-lg transition">
                    <i class="fas fa-calendar-plus mr-2"></i>
                    Đặt sân ngay
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($bookings as $booking): ?>
                <div class="bg-white rounded-2xl shadow-md overflow-hidden card-hover">
                    <div class="flex flex-col md:flex-row">
                        <!-- Court Image -->
                        <div class="md:w-48 h-48 bg-gradient-to-br from-green-400 to-emerald-500 flex items-center justify-center">
                            <?php if (!empty($booking['court_image'])): ?>
                            <img src="uploads/courts/<?php echo htmlspecialchars($booking['court_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($booking['court_name']); ?>" 
                                 class="w-full h-full object-cover">
                            <?php else: ?>
                            <i class="fas fa-shuttlecock text-white text-5xl opacity-50"></i>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Booking Info -->
                        <div class="flex-1 p-6">
                            <div class="flex flex-wrap justify-between items-start gap-4">
                                <div>
                                    <h3 class="text-xl font-bold text-gray-800 mb-2">
                                        <?php echo htmlspecialchars($booking['court_name']); ?>
                                    </h3>
                                    <div class="space-y-1 text-sm text-gray-600">
                                        <p>
                                            <i class="fas fa-calendar-day text-green-600 w-5"></i>
                                            Ngày: <?php echo date('d/m/Y', strtotime($booking['start_time'])); ?>
                                        </p>
                                        <p>
                                            <i class="fas fa-clock text-green-600 w-5"></i>
                                            Thời gian: <?php echo date('H:i', strtotime($booking['start_time'])); ?> - <?php echo date('H:i', strtotime($booking['end_time'])); ?>
                                        </p>
                                        <p>
                                            <i class="fas fa-map-marker-alt text-green-600 w-5"></i>
                                            <?php echo htmlspecialchars($booking['address'] ?: 'Đang cập nhật'); ?>
                                        </p>
                                        <p>
                                            <i class="fas fa-tag text-green-600 w-5"></i>
                                            Loại sân: <?php echo htmlspecialchars($booking['court_type'] ?: 'Sân cầu lông'); ?>
                                        </p>
                                        <p class="text-lg font-bold text-green-600 mt-2">
                                            <i class="fas fa-money-bill-wave"></i>
                                            <?php echo number_format($booking['total_price'], 0, ',', '.'); ?> ₫
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="text-right">
                                    <span class="status-badge status-<?php echo $booking['status']; ?>">
                                        <?php 
                                        $statusText = [
                                            'pending' => '⏳ Chờ xác nhận',
                                            'confirmed' => '✓ Đã xác nhận',
                                            'paid' => '💰 Đã thanh toán',
                                            'cancelled' => '✗ Đã hủy',
                                            'completed' => '✓ Hoàn thành'
                                        ];
                                        echo $statusText[$booking['status']] ?? $booking['status'];
                                        ?>
                                    </span>
                                    
                                    <?php if ($booking['booking_code']): ?>
                                    <p class="text-xs text-gray-400 mt-2">
                                        Mã đặt: <?php echo $booking['booking_code']; ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="flex flex-wrap gap-3 mt-6 pt-4 border-t border-gray-100">
                                <?php if ($booking['status'] === 'pending'): ?>
                                    <a href="payment.php?booking_id=<?php echo $booking['booking_id']; ?>" 
                                       class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition text-sm font-medium">
                                        <i class="fas fa-credit-card mr-1"></i> Thanh toán ngay
                                    </a>
                                    <button onclick="showCancelModal(<?php echo $booking['booking_id']; ?>, '<?php echo htmlspecialchars($booking['court_name']); ?>', '<?php echo $booking['start_time']; ?>')" 
                                            class="border border-red-500 text-red-500 px-4 py-2 rounded-lg hover:bg-red-50 transition text-sm font-medium">
                                        <i class="fas fa-times-circle mr-1"></i> Hủy đặt
                                    </button>
                                    
                                <?php elseif ($booking['status'] === 'confirmed'): ?>
                                    <?php 
                                    $startTime = strtotime($booking['start_time']);
                                    $canCancel = ($startTime - time()) > 7200; // Còn hơn 2 giờ
                                    ?>
                                    <?php if ($canCancel): ?>
                                    <button onclick="showCancelModal(<?php echo $booking['booking_id']; ?>, '<?php echo htmlspecialchars($booking['court_name']); ?>', '<?php echo $booking['start_time']; ?>')" 
                                            class="border border-red-500 text-red-500 px-4 py-2 rounded-lg hover:bg-red-50 transition text-sm font-medium">
                                        <i class="fas fa-times-circle mr-1"></i> Hủy đặt
                                    </button>
                                    <?php else: ?>
                                    <span class="text-gray-400 text-sm px-4 py-2">
                                        <i class="fas fa-info-circle mr-1"></i> Không thể hủy (còn dưới 2 giờ)
                                    </span>
                                    <?php endif; ?>
                                    
                                <?php elseif ($booking['status'] === 'paid'): ?>
                                    <?php 
                                    $startTime = strtotime($booking['start_time']);
                                    $endTime = strtotime($booking['end_time']);
                                    $now = time();
                                    
                                    if ($now > $endTime && !$booking['has_reviewed']):
                                    ?>
                                    <button onclick="showReviewModal(<?php echo $booking['booking_id']; ?>, '<?php echo htmlspecialchars($booking['court_name']); ?>')" 
                                            class="bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600 transition text-sm font-medium">
                                        <i class="fas fa-star mr-1"></i> Đánh giá sân
                                    </button>
                                    <?php elseif ($booking['has_reviewed']): ?>
                                    <span class="text-green-600 text-sm px-4 py-2">
                                        <i class="fas fa-check-circle mr-1"></i> Đã đánh giá
                                    </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($now < $startTime): ?>
                                    <a href="court.php?id=<?php echo $booking['court_id']; ?>" 
                                       class="text-blue-600 px-4 py-2 rounded-lg hover:bg-blue-50 transition text-sm font-medium">
                                        <i class="fas fa-info-circle mr-1"></i> Xem chi tiết sân
                                    </a>
                                    <?php endif; ?>
                                    
                                <?php endif; ?>
                                
                                <a href="court.php?id=<?php echo $booking['court_id']; ?>" 
                                   class="text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-100 transition text-sm font-medium">
                                    <i class="fas fa-shuttlecock mr-1"></i> Xem sân
                                </a>
                                
                                <!-- Receipt/Invoice -->
                                <button onclick="showInvoice(<?php echo $booking['booking_id']; ?>)" 
                                        class="text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-100 transition text-sm font-medium">
                                    <i class="fas fa-file-invoice mr-1"></i> Hóa đơn
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="flex justify-center mt-8">
                <nav class="flex items-center space-x-2">
                    <?php if ($page > 1): ?>
                    <a href="?status=<?php echo $statusFilter; ?>&page=<?php echo $page - 1; ?>" 
                       class="px-4 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 transition">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i == $page): ?>
                        <span class="px-4 py-2 rounded-lg bg-gradient-to-r from-green-600 to-emerald-600 text-white"><?php echo $i; ?></span>
                        <?php elseif (abs($i - $page) <= 2 || $i == 1 || $i == $totalPages): ?>
                        <a href="?status=<?php echo $statusFilter; ?>&page=<?php echo $i; ?>" 
                           class="px-4 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 transition">
                            <?php echo $i; ?>
                        </a>
                        <?php elseif (abs($i - $page) == 3): ?>
                        <span class="px-2">...</span>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="?status=<?php echo $statusFilter; ?>&page=<?php echo $page + 1; ?>" 
                       class="px-4 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 transition">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                </nav>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Cancel Booking Modal -->
    <div id="cancelModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4 modal-enter">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">Xác nhận hủy đặt sân</h3>
                <button onclick="closeCancelModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="mb-6">
                <p class="text-gray-600 mb-2">Bạn có chắc chắn muốn hủy đặt sân:</p>
                <p class="font-semibold text-gray-800" id="cancelCourtName"></p>
                <p class="text-sm text-gray-500 mt-2" id="cancelTimeInfo"></p>
                <div class="mt-4 p-3 bg-yellow-50 rounded-lg">
                    <p class="text-sm text-yellow-800">
                        <i class="fas fa-info-circle mr-1"></i>
                        Lưu ý: Chỉ có thể hủy trước 2 giờ so với giờ bắt đầu.
                    </p>
                </div>
            </div>
            <form method="POST" id="cancelForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="cancel_booking">
                <input type="hidden" name="booking_id" id="cancelBookingId">
                <div class="flex gap-3">
                    <button type="button" onclick="closeCancelModal()" 
                            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                        Quay lại
                    </button>
                    <button type="submit" 
                            class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                        Xác nhận hủy
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Review Modal -->
    <div id="reviewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4 modal-enter">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">Đánh giá sân</h3>
                <button onclick="closeReviewModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="mb-4">
                <p class="text-gray-600">Đánh giá của bạn về sân:</p>
                <p class="font-semibold text-gray-800" id="reviewCourtName"></p>
            </div>
            <form method="POST" id="reviewForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="submit_review">
                <input type="hidden" name="booking_id" id="reviewBookingId">
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 font-medium">Số sao đánh giá</label>
                    <div class="star-rating" id="starRating">
                        <i class="fas fa-star" data-rating="1"></i>
                        <i class="fas fa-star" data-rating="2"></i>
                        <i class="fas fa-star" data-rating="3"></i>
                        <i class="fas fa-star" data-rating="4"></i>
                        <i class="fas fa-star" data-rating="5"></i>
                    </div>
                    <input type="hidden" name="rating" id="ratingValue" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 font-medium">Nhận xét của bạn</label>
                    <textarea name="comment" rows="3" 
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500"
                              placeholder="Chia sẻ trải nghiệm của bạn về sân này..."></textarea>
                </div>
                
                <div class="flex gap-3">
                    <button type="button" onclick="closeReviewModal()" 
                            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                        Để sau
                    </button>
                    <button type="submit" 
                            class="flex-1 px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition">
                        Gửi đánh giá
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Cancel Modal functions
        let currentCancelBookingId = null;
        
        function showCancelModal(bookingId, courtName, startTime) {
            currentCancelBookingId = bookingId;
            document.getElementById('cancelCourtName').innerText = courtName;
            document.getElementById('cancelTimeInfo').innerHTML = `<i class="fas fa-clock mr-1"></i> Thời gian: ${new Date(startTime).toLocaleString('vi-VN')}`;
            document.getElementById('cancelBookingId').value = bookingId;
            document.getElementById('cancelModal').classList.remove('hidden');
        }
        
        function closeCancelModal() {
            document.getElementById('cancelModal').classList.add('hidden');
            currentCancelBookingId = null;
        }
        
        // Review Modal functions
        let currentReviewBookingId = null;
        
        function showReviewModal(bookingId, courtName) {
            currentReviewBookingId = bookingId;
            document.getElementById('reviewCourtName').innerText = courtName;
            document.getElementById('reviewBookingId').value = bookingId;
            document.getElementById('ratingValue').value = '';
            document.getElementById('reviewModal').classList.remove('hidden');
            
            // Reset star rating
            const stars = document.querySelectorAll('#starRating i');
            stars.forEach(star => star.classList.remove('active'));
        }
        
        function closeReviewModal() {
            document.getElementById('reviewModal').classList.add('hidden');
            currentReviewBookingId = null;
        }
        
        // Star rating functionality
        document.querySelectorAll('#starRating i').forEach(star => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.dataset.rating);
                document.getElementById('ratingValue').value = rating;
                
                // Update stars display
                const stars = document.querySelectorAll('#starRating i');
                stars.forEach((s, index) => {
                    if (index < rating) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });
            });
            
            star.addEventListener('mouseenter', function() {
                const rating = parseInt(this.dataset.rating);
                const stars = document.querySelectorAll('#starRating i');
                stars.forEach((s, index) => {
                    if (index < rating) {
                        s.style.color = '#fbbf24';
                    } else {
                        s.style.color = '#d1d5db';
                    }
                });
            });
        });
        
        document.getElementById('starRating')?.addEventListener('mouseleave', function() {
            const currentRating = parseInt(document.getElementById('ratingValue').value) || 0;
            const stars = document.querySelectorAll('#starRating i');
            stars.forEach((s, index) => {
                if (index < currentRating) {
                    s.style.color = '#fbbf24';
                } else {
                    s.style.color = '#d1d5db';
                }
            });
        });
        
        // Validate review form before submit
        document.getElementById('reviewForm')?.addEventListener('submit', function(e) {
            const rating = document.getElementById('ratingValue').value;
            if (!rating || rating < 1 || rating > 5) {
                e.preventDefault();
                alert('Vui lòng chọn số sao đánh giá');
                return false;
            }
            return true;
        });
        
        // Invoice function
        function showInvoice(bookingId) {
            window.open('invoice.php?id=' + bookingId, '_blank', 'width=800,height=600');
        }
        
        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList && e.target.classList.contains('bg-black')) {
                e.target.classList.add('hidden');
            }
        });
    </script>
</body>
</html>