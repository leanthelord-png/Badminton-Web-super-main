<?php
// ============================================
// OWNER DASHBOARD V2.0 - QUẢN LÝ SÂN CHUYÊN NGHIỆP
// ============================================

require_once 'config/database.php';
session_start();

// Kiểm tra đăng nhập và role owner
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'owner') {
    header('Location: index.php?owner_login=1');
    exit;
}

$owner_id = $_SESSION['user_id'];
$message = '';
$messageType = '';

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Helper functions
function formatCurrency($value) {
    return number_format($value, 0, ',', '.') . ' ₫';
}

function getStatusBadge($status) {
    $badges = [
        'pending' => ['bg-yellow-100 text-yellow-800', '⏳ Chờ xác nhận'],
        'confirmed' => ['bg-green-100 text-green-800', '✅ Đã xác nhận'],
        'completed' => ['bg-blue-100 text-blue-800', '✔️ Hoàn thành'],
        'cancelled' => ['bg-red-100 text-red-800', '❌ Đã hủy'],
        'paid' => ['bg-emerald-100 text-emerald-800', '💰 Đã thanh toán'],
        'active' => ['bg-green-100 text-green-800', '🟢 Hoạt động'],
        'inactive' => ['bg-gray-100 text-gray-800', '⚫ Tạm dừng']
    ];
    $badge = $badges[$status] ?? ['bg-gray-100 text-gray-800', $status];
    return "<span class='px-2 py-1 rounded-lg text-xs font-medium {$badge[0]}'>{$badge[1]}</span>";
}

// Xử lý form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = 'CSRF token không hợp lệ';
        $messageType = 'error';
    } else {
        try {
            switch ($_POST['action']) {
                case 'add_court':
                    $court_name = trim($_POST['court_name'] ?? '');
                    $court_type = trim($_POST['court_type'] ?? '');
                    $address = trim($_POST['address'] ?? '');
                    $price_per_hour = floatval($_POST['price_per_hour'] ?? 0);
                    $description = trim($_POST['description'] ?? '');
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    
                    if (empty($court_name) || $price_per_hour <= 0) {
                        throw new Exception('Vui lòng nhập đầy đủ thông tin');
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO courts (owner_id, court_name, court_type, address, price_per_hour, description, is_active, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$owner_id, $court_name, $court_type, $address, $price_per_hour, $description, $is_active]);
                    $newCourtId = $pdo->lastInsertId();
                    
                    // Xử lý upload ảnh
                    if (isset($_FILES['court_image']) && $_FILES['court_image']['error'] === UPLOAD_ERR_OK && $_FILES['court_image']['size'] > 0) {
                        $uploadDir = 'uploads/courts/';
                        if (!file_exists($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        
                        $fileExtension = strtolower(pathinfo($_FILES['court_image']['name'], PATHINFO_EXTENSION));
                        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                        
                        if (in_array($fileExtension, $allowedExtensions)) {
                            $newFileName = 'court_' . $newCourtId . '_' . time() . '.' . $fileExtension;
                            $uploadPath = $uploadDir . $newFileName;
                            if (move_uploaded_file($_FILES['court_image']['tmp_name'], $uploadPath)) {
                                $stmt = $pdo->prepare("UPDATE courts SET court_image = ? WHERE court_id = ?");
                                $stmt->execute([$newFileName, $newCourtId]);
                            }
                        }
                    }
                    
                    $message = 'Thêm sân mới thành công!';
                    $messageType = 'success';
                    break;
                    
                case 'update_court':
                    $court_id = intval($_POST['court_id'] ?? 0);
                    $court_name = trim($_POST['court_name'] ?? '');
                    $court_type = trim($_POST['court_type'] ?? '');
                    $address = trim($_POST['address'] ?? '');
                    $price_per_hour = floatval($_POST['price_per_hour'] ?? 0);
                    $description = trim($_POST['description'] ?? '');
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    
                    $stmt = $pdo->prepare("SELECT court_id, court_image FROM courts WHERE court_id = ? AND owner_id = ?");
                    $stmt->execute([$court_id, $owner_id]);
                    $court = $stmt->fetch();
                    if (!$court) {
                        throw new Exception('Bạn không có quyền sửa sân này');
                    }
                    
                    // Xử lý upload ảnh
                    $court_image = $court['court_image'];
                    if (isset($_FILES['court_image']) && $_FILES['court_image']['error'] === UPLOAD_ERR_OK && $_FILES['court_image']['size'] > 0) {
                        $uploadDir = 'uploads/courts/';
                        if (!file_exists($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        
                        $fileExtension = strtolower(pathinfo($_FILES['court_image']['name'], PATHINFO_EXTENSION));
                        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                        
                        if (in_array($fileExtension, $allowedExtensions)) {
                            if ($_FILES['court_image']['size'] > 5 * 1024 * 1024) {
                                throw new Exception('File ảnh không được vượt quá 5MB');
                            }
                            
                            $newFileName = 'court_' . $court_id . '_' . time() . '.' . $fileExtension;
                            $uploadPath = $uploadDir . $newFileName;
                            
                            if ($court_image && file_exists($uploadDir . $court_image)) {
                                unlink($uploadDir . $court_image);
                            }
                            
                            if (move_uploaded_file($_FILES['court_image']['tmp_name'], $uploadPath)) {
                                $court_image = $newFileName;
                            }
                        }
                    }
                    
                    $stmt = $pdo->prepare("UPDATE courts SET court_name = ?, court_type = ?, address = ?, price_per_hour = ?, description = ?, is_active = ?, court_image = ? WHERE court_id = ?");
                    $stmt->execute([$court_name, $court_type, $address, $price_per_hour, $description, $is_active, $court_image, $court_id]);
                    $message = 'Cập nhật sân thành công!';
                    $messageType = 'success';
                    break;
                    
                case 'delete_court_image':
                    $court_id = intval($_POST['court_id'] ?? 0);
                    
                    $stmt = $pdo->prepare("SELECT court_image FROM courts WHERE court_id = ? AND owner_id = ?");
                    $stmt->execute([$court_id, $owner_id]);
                    $court = $stmt->fetch();
                    
                    if ($court && $court['court_image']) {
                        $uploadDir = 'uploads/courts/';
                        if (file_exists($uploadDir . $court['court_image'])) {
                            unlink($uploadDir . $court['court_image']);
                        }
                        $stmt = $pdo->prepare("UPDATE courts SET court_image = NULL WHERE court_id = ?");
                        $stmt->execute([$court_id]);
                        $message = 'Đã xóa ảnh sân!';
                        $messageType = 'success';
                    }
                    break;
                    
                case 'delete_court':
                    $court_id = intval($_POST['court_id'] ?? 0);
                    
                    $stmt = $pdo->prepare("SELECT court_id FROM courts WHERE court_id = ? AND owner_id = ?");
                    $stmt->execute([$court_id, $owner_id]);
                    if (!$stmt->fetch()) {
                        throw new Exception('Bạn không có quyền xóa sân này');
                    }
                    
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE court_id = ? AND status IN ('pending', 'confirmed')");
                    $stmt->execute([$court_id]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception('Không thể xóa sân vì còn đặt sân chưa hoàn thành');
                    }
                    
                    $stmt = $pdo->prepare("UPDATE courts SET is_active = 0 WHERE court_id = ?");
                    $stmt->execute([$court_id]);
                    $message = 'Đã vô hiệu hóa sân!';
                    $messageType = 'success';
                    break;
                    
                case 'update_booking_status':
                    $booking_id = intval($_POST['booking_id'] ?? 0);
                    $status = $_POST['status'] ?? '';
                    
                    if (!in_array($status, ['confirmed', 'cancelled', 'completed', 'paid'])) {
                        throw new Exception('Trạng thái không hợp lệ');
                    }
                    
                    $stmt = $pdo->prepare("
                        SELECT b.* FROM bookings b 
                        JOIN courts c ON b.court_id = c.court_id 
                        WHERE b.booking_id = ? AND c.owner_id = ?
                    ");
                    $stmt->execute([$booking_id, $owner_id]);
                    if (!$stmt->fetch()) {
                        throw new Exception('Bạn không có quyền sửa booking này');
                    }
                    
                    $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE booking_id = ?");
                    $stmt->execute([$status, $booking_id]);
                    $message = 'Cập nhật trạng thái thành công!';
                    $messageType = 'success';
                    break;
                    
                case 'update_settings':
                    $bank_name = trim($_POST['bank_name'] ?? '');
                    $bank_account = trim($_POST['bank_account'] ?? '');
                    $bank_holder = trim($_POST['bank_holder'] ?? '');
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO owner_settings (owner_id, bank_name, bank_account, bank_holder, updated_at) 
                        VALUES (?, ?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE 
                        bank_name = VALUES(bank_name), 
                        bank_account = VALUES(bank_account), 
                        bank_holder = VALUES(bank_holder),
                        updated_at = NOW()
                    ");
                    $stmt->execute([$owner_id, $bank_name, $bank_account, $bank_holder]);
                    $message = 'Cập nhật cài đặt thành công!';
                    $messageType = 'success';
                    break;
                    
                case 'logout':
                    session_destroy();
                    header('Location: index.php');
                    exit;
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Lấy danh sách sân của owner
$myCourts = [];
$stmt = $pdo->prepare("SELECT * FROM courts WHERE owner_id = ? ORDER BY created_at DESC");
$stmt->execute([$owner_id]);
$myCourts = $stmt->fetchAll();

// Lấy cài đặt chủ sân
$ownerSettings = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM owner_settings WHERE owner_id = ?");
    $stmt->execute([$owner_id]);
    $ownerSettings = $stmt->fetch();
} catch (PDOException $e) {
    $ownerSettings = [];
}

// Thống kê
$stats = [
    'total_courts' => count($myCourts),
    'active_courts' => 0,
    'total_bookings' => 0,
    'pending_bookings' => 0,
    'confirmed_bookings' => 0,
    'completed_bookings' => 0,
    'cancelled_bookings' => 0,
    'total_revenue' => 0,
    'monthly_revenue' => 0,
    'weekly_revenue' => 0,
    'today_revenue' => 0
];

$courtRevenue = [];
$recentBookings = [];
$upcomingBookings = [];
$reviews = [];

foreach ($myCourts as $court) {
    if ($court['is_active']) $stats['active_courts']++;
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            COALESCE(SUM(CASE WHEN status IN ('confirmed', 'completed', 'paid') THEN total_price ELSE 0 END), 0) as revenue,
            COALESCE(SUM(CASE WHEN MONTH(start_time) = MONTH(CURDATE()) AND YEAR(start_time) = YEAR(CURDATE()) AND status IN ('confirmed', 'completed', 'paid') THEN total_price ELSE 0 END), 0) as monthly,
            COALESCE(SUM(CASE WHEN WEEK(start_time) = WEEK(CURDATE()) AND YEAR(start_time) = YEAR(CURDATE()) AND status IN ('confirmed', 'completed', 'paid') THEN total_price ELSE 0 END), 0) as weekly,
            COALESCE(SUM(CASE WHEN DATE(start_time) = CURDATE() AND status IN ('confirmed', 'completed', 'paid') THEN total_price ELSE 0 END), 0) as today
        FROM bookings WHERE court_id = ?
    ");
    $stmt->execute([$court['court_id']]);
    $courtStats = $stmt->fetch();
    
    $stats['total_bookings'] += $courtStats['total'];
    $stats['pending_bookings'] += $courtStats['pending'];
    $stats['confirmed_bookings'] += $courtStats['confirmed'];
    $stats['completed_bookings'] += $courtStats['completed'];
    $stats['cancelled_bookings'] += $courtStats['cancelled'];
    $stats['total_revenue'] += $courtStats['revenue'];
    $stats['monthly_revenue'] += $courtStats['monthly'];
    $stats['weekly_revenue'] += $courtStats['weekly'];
    $stats['today_revenue'] += $courtStats['today'];
    
    $courtRevenue[] = [
        'id' => $court['court_id'],
        'name' => $court['court_name'],
        'revenue' => $courtStats['revenue'],
        'bookings' => $courtStats['total'],
        'pending' => $courtStats['pending']
    ];
}

// Lấy booking gần đây
$stmt = $pdo->prepare("
    SELECT b.*, c.court_name, u.full_name, u.phone_number, u.username, u.email
    FROM bookings b
    JOIN courts c ON b.court_id = c.court_id
    JOIN users u ON b.user_id = u.user_id
    WHERE c.owner_id = ?
    ORDER BY b.created_at DESC
    LIMIT 30
");
$stmt->execute([$owner_id]);
$recentBookings = $stmt->fetchAll();

// Lấy booking sắp tới
$stmt = $pdo->prepare("
    SELECT b.*, c.court_name, u.full_name, u.phone_number
    FROM bookings b
    JOIN courts c ON b.court_id = c.court_id
    JOIN users u ON b.user_id = u.user_id
    WHERE c.owner_id = ? AND b.start_time > NOW() AND b.status IN ('confirmed', 'pending')
    ORDER BY b.start_time ASC
    LIMIT 10
");
$stmt->execute([$owner_id]);
$upcomingBookings = $stmt->fetchAll();

// Lấy doanh thu theo tháng
$monthlyRevenue = [];
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(b.start_time, '%Y-%m') as month, 
           COALESCE(SUM(b.total_price), 0) as revenue,
           COUNT(b.booking_id) as bookings
    FROM bookings b
    JOIN courts c ON b.court_id = c.court_id
    WHERE c.owner_id = ? AND b.status IN ('confirmed', 'completed', 'paid')
    GROUP BY DATE_FORMAT(b.start_time, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
");
$stmt->execute([$owner_id]);
$monthlyRevenue = $stmt->fetchAll();
$monthlyRevenue = array_reverse($monthlyRevenue);

// Lấy đánh giá
$stmt = $pdo->prepare("
    SELECT r.*, u.full_name, c.court_name
    FROM court_reviews r
    JOIN courts c ON r.court_id = c.court_id
    JOIN users u ON r.user_id = u.user_id
    WHERE c.owner_id = ?
    ORDER BY r.created_at DESC
    LIMIT 10
");
$stmt->execute([$owner_id]);
$reviews = $stmt->fetchAll();

// Page hiện tại
$page = $_GET['page'] ?? 'dashboard';
$allowedPages = ['dashboard', 'courts', 'bookings', 'revenue', 'reviews', 'settings'];
if (!in_array($page, $allowedPages)) $page = 'dashboard';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chủ sân Dashboard - BadmintonPro</title>
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .fade-in-up { animation: fadeInUp 0.5s ease-out; }
        .slide-in { animation: slideIn 0.3s ease-out; }
        
        .sidebar-link {
            transition: all 0.3s ease;
        }
        .sidebar-link.active {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            box-shadow: 0 4px 12px rgba(34,197,94,0.3);
        }
        .sidebar-link:hover:not(.active) {
            background-color: #f0fdf4;
            transform: translateX(5px);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white;
            border-radius: 20px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: fadeInUp 0.3s ease;
        }
        
        .stat-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .court-card {
            transition: all 0.3s ease;
        }
        .court-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .toast {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 1100;
            animation: slideInRight 0.3s ease-out;
        }
        
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #22c55e; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #16a34a; }
    </style>
</head>
<body class="bg-gray-50">

<!-- Navigation -->
<nav class="bg-white shadow-lg sticky top-0 z-40 border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <a href="index.php" class="flex items-center space-x-2 hover:opacity-90 transition">
                    <div class="bg-gradient-to-r from-green-500 to-emerald-600 p-2 rounded-xl">
                        <i class="fas fa-shuttlecock text-white text-xl"></i>
                    </div>
                    <span class="font-bold text-xl bg-gradient-to-r from-green-600 to-emerald-600 bg-clip-text text-transparent">BadmintonPro</span>
                </a>
            </div>
            <div class="flex items-center space-x-4">
                <div class="relative" id="userMenu">
                    <button id="userMenuBtn" class="flex items-center space-x-3 focus:outline-none bg-gray-100 hover:bg-gray-200 rounded-full px-4 py-2 transition">
                        <div class="w-8 h-8 bg-gradient-to-r from-green-500 to-emerald-600 rounded-full flex items-center justify-center">
                            <i class="fas fa-store text-white text-sm"></i>
                        </div>
                        <span class="font-medium text-gray-700"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                        <i class="fas fa-chevron-down text-gray-500 text-xs transition-transform duration-200" id="userMenuIcon"></i>
                    </button>
                    <div id="userDropdown" class="absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-xl hidden z-50 border border-gray-100 overflow-hidden">
                        <a href="index.php" class="flex items-center px-4 py-3 text-gray-700 hover:bg-green-50 hover:text-green-600 transition">
                            <i class="fas fa-home w-5 mr-3"></i>Trang chủ
                        </a>
                        <a href="?page=settings" class="flex items-center px-4 py-3 text-gray-700 hover:bg-green-50 hover:text-green-600 transition">
                            <i class="fas fa-cog w-5 mr-3"></i>Cài đặt
                        </a>
                        <hr class="my-1">
                        <form method="POST" class="block">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="logout">
                            <button type="submit" class="w-full text-left flex items-center px-4 py-3 text-red-600 hover:bg-red-50 transition">
                                <i class="fas fa-sign-out-alt w-5 mr-3"></i>Đăng xuất
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="flex">
    <!-- Sidebar -->
    <div class="bg-white w-64 shadow-lg fixed h-full overflow-y-auto border-r border-gray-100" style="top: 64px;">
        <div class="p-6">
            <div class="text-center mb-6">
                <div class="w-24 h-24 bg-gradient-to-br from-green-100 to-emerald-100 rounded-2xl flex items-center justify-center mx-auto mb-3 shadow-inner">
                    <i class="fas fa-store text-green-600 text-4xl"></i>
                </div>
                <h3 class="font-bold text-gray-800 text-lg"><?php echo htmlspecialchars($_SESSION['full_name']); ?></h3>
                <p class="text-sm text-gray-500 mt-1">
                    <i class="fas fa-map-marker-alt text-green-500 mr-1"></i>Chủ sân
                </p>
                <div class="mt-3 inline-flex items-center px-3 py-1 bg-green-100 rounded-full">
                    <i class="fas fa-shuttlecock text-green-600 text-xs mr-1"></i>
                    <span class="text-xs font-medium text-green-700"><?php echo $stats['total_courts']; ?> sân</span>
                </div>
            </div>
            
            <nav class="space-y-1">
                <a href="?page=dashboard" class="sidebar-link flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all <?php echo $page === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt w-5 mr-3"></i>
                    <span>Tổng quan</span>
                </a>
                <a href="?page=courts" class="sidebar-link flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all <?php echo $page === 'courts' ? 'active' : ''; ?>">
                    <i class="fas fa-map-marker-alt w-5 mr-3"></i>
                    <span>Quản lý sân</span>
                </a>
                <a href="?page=bookings" class="sidebar-link flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all <?php echo $page === 'bookings' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check w-5 mr-3"></i>
                    <span>Đặt sân</span>
                    <?php if ($stats['pending_bookings'] > 0): ?>
                    <span class="ml-auto bg-yellow-500 text-white text-xs px-2 py-0.5 rounded-full"><?php echo $stats['pending_bookings']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?page=revenue" class="sidebar-link flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all <?php echo $page === 'revenue' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar w-5 mr-3"></i>
                    <span>Doanh thu</span>
                </a>
                <a href="?page=reviews" class="sidebar-link flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all <?php echo $page === 'reviews' ? 'active' : ''; ?>">
                    <i class="fas fa-star w-5 mr-3"></i>
                    <span>Đánh giá</span>
                </a>
                <a href="?page=settings" class="sidebar-link flex items-center px-4 py-3 text-gray-700 rounded-xl transition-all <?php echo $page === 'settings' ? 'active' : ''; ?>">
                    <i class="fas fa-cog w-5 mr-3"></i>
                    <span>Cài đặt</span>
                </a>
            </nav>
        </div>
    </div>

    <!-- Content -->
    <div class="flex-1 p-6 overflow-y-auto" style="margin-left: 256px;">
        <!-- Thông báo -->
        <?php if ($message): ?>
        <div class="toast" id="toastMessage">
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
        <script>setTimeout(() => { let t = document.getElementById('toastMessage'); if(t) t.remove(); }, 5000);</script>
        <?php endif; ?>

        <!-- ========== DASHBOARD ========== -->
        <?php if ($page === 'dashboard'): ?>
        <div class="fade-in-up">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Xin chào, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h1>
                <p class="text-gray-500 mt-1">Đây là tổng quan hoạt động kinh doanh của bạn</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="stat-card bg-white rounded-2xl shadow-sm p-6 border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm mb-1">Tổng doanh thu</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo formatCurrency($stats['total_revenue']); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-money-bill-wave text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card bg-white rounded-2xl shadow-sm p-6 border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm mb-1">Tổng đặt sân</p>
                            <p class="text-2xl font-bold text-blue-600"><?php echo $stats['total_bookings']; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-calendar-check text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card bg-white rounded-2xl shadow-sm p-6 border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm mb-1">Sân đang hoạt động</p>
                            <p class="text-2xl font-bold text-purple-600"><?php echo $stats['active_courts']; ?>/<?php echo $stats['total_courts']; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-map-marker-alt text-purple-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card bg-white rounded-2xl shadow-sm p-6 border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm mb-1">Hôm nay</p>
                            <p class="text-2xl font-bold text-orange-600"><?php echo formatCurrency($stats['today_revenue']); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center">
                            <i class="fas fa-sun text-orange-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-100">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">
                        <i class="fas fa-chart-line text-green-600 mr-2"></i>Doanh thu 12 tháng
                    </h3>
                    <?php if (!empty($monthlyRevenue)): ?>
                    <canvas id="revenueChart" height="200"></canvas>
                    <?php else: ?>
                    <div class="text-center py-12 text-gray-400">
                        <i class="fas fa-chart-line text-5xl mb-3"></i>
                        <p>Chưa có dữ liệu doanh thu</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-100">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">
                        <i class="fas fa-chart-pie text-green-600 mr-2"></i>Doanh thu theo sân
                    </h3>
                    <div class="space-y-3 max-h-64 overflow-y-auto">
                        <?php foreach ($courtRevenue as $court): ?>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-xl">
                            <div>
                                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($court['name']); ?></p>
                                <p class="text-sm text-gray-500"><?php echo $court['bookings']; ?> lượt đặt</p>
                            </div>
                            <p class="font-bold text-green-600"><?php echo formatCurrency($court['revenue']); ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="p-4 border-b border-gray-100 bg-gradient-to-r from-blue-50 to-white">
                        <h3 class="text-lg font-bold text-gray-800">
                            <i class="fas fa-calendar-alt text-blue-600 mr-2"></i>Đặt sân sắp tới
                        </h3>
                    </div>
                    <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                        <?php if (empty($upcomingBookings)): ?>
                        <div class="p-8 text-center text-gray-400">
                            <i class="fas fa-calendar-check text-4xl mb-3"></i>
                            <p>Không có đặt sân sắp tới</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($upcomingBookings as $booking): ?>
                        <div class="p-4 hover:bg-gray-50 transition">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($booking['court_name']); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($booking['full_name']); ?></p>
                                    <p class="text-sm text-gray-500"><?php echo date('d/m/Y H:i', strtotime($booking['start_time'])); ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-green-600"><?php echo formatCurrency($booking['total_price']); ?></p>
                                    <?php echo getStatusBadge($booking['status']); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="p-4 border-b border-gray-100 bg-gradient-to-r from-yellow-50 to-white">
                        <h3 class="text-lg font-bold text-gray-800">
                            <i class="fas fa-star text-yellow-500 mr-2"></i>Đánh giá gần đây
                        </h3>
                    </div>
                    <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                        <?php if (empty($reviews)): ?>
                        <div class="p-8 text-center text-gray-400">
                            <i class="fas fa-star text-4xl mb-3"></i>
                            <p>Chưa có đánh giá nào</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($reviews as $review): ?>
                        <div class="p-4 hover:bg-gray-50 transition">
                            <div class="flex items-start space-x-3">
                                <div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user text-gray-500"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between mb-1">
                                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($review['full_name']); ?></p>
                                        <div class="flex items-center">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star text-xs <?php echo $i <= $review['rating'] ? 'text-yellow-400' : 'text-gray-300'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($review['court_name']); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($review['comment']); ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ========== QUẢN LÝ SÂN ========== -->
        <?php if ($page === 'courts'): ?>
        <div class="fade-in-up">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Quản lý sân</h1>
                    <p class="text-gray-500 mt-1">Quản lý thông tin và trạng thái các sân của bạn</p>
                </div>
                <button onclick="showAddCourtModal()" class="bg-gradient-to-r from-green-600 to-emerald-600 text-white px-6 py-3 rounded-xl hover:shadow-lg transition flex items-center">
                    <i class="fas fa-plus-circle mr-2"></i>Thêm sân mới
                </button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($myCourts as $court): ?>
                <div class="court-card bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="relative h-48 bg-gradient-to-br from-green-400 to-emerald-500">
                        <?php if (!empty($court['court_image']) && file_exists('uploads/courts/' . $court['court_image'])): ?>
                        <img src="uploads/courts/<?php echo htmlspecialchars($court['court_image']); ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center">
                            <i class="fas fa-shuttlecock text-white text-6xl opacity-50"></i>
                        </div>
                        <?php endif; ?>
                        <div class="absolute top-3 right-3">
                            <?php echo getStatusBadge($court['is_active'] ? 'active' : 'inactive'); ?>
                        </div>
                        <div class="absolute bottom-3 left-3 bg-black/50 backdrop-blur-sm rounded-lg px-3 py-1">
                            <span class="text-white font-bold"><?php echo formatCurrency($court['price_per_hour']); ?></span>
                            <span class="text-gray-300 text-xs">/giờ</span>
                        </div>
                    </div>
                    <div class="p-5">
                        <h3 class="text-xl font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($court['court_name']); ?></h3>
                        <?php if (!empty($court['address'])): ?>
                        <p class="text-sm text-gray-500 mb-3 flex items-start">
                            <i class="fas fa-map-marker-alt text-gray-400 mr-2 mt-0.5"></i>
                            <span><?php echo htmlspecialchars(substr($court['address'], 0, 60)); ?></span>
                        </p>
                        <?php endif; ?>
                        <div class="flex space-x-2">
                            <button onclick="editCourt(<?php echo $court['court_id']; ?>)" class="flex-1 bg-green-600 text-white px-4 py-2 rounded-xl hover:bg-green-700 transition flex items-center justify-center">
                                <i class="fas fa-edit mr-2"></i>Sửa
                            </button>
                            <button onclick="deleteCourt(<?php echo $court['court_id']; ?>)" class="px-4 py-2 bg-red-100 text-red-600 rounded-xl hover:bg-red-200 transition">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($myCourts)): ?>
            <div class="text-center py-16 bg-white rounded-2xl">
                <i class="fas fa-map-marker-alt text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-600 mb-2">Chưa có sân nào</h3>
                <p class="text-gray-500 mb-4">Hãy thêm sân đầu tiên của bạn</p>
                <button onclick="showAddCourtModal()" class="bg-green-600 text-white px-6 py-3 rounded-xl hover:bg-green-700 transition">
                    <i class="fas fa-plus-circle mr-2"></i>Thêm sân ngay
                </button>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ========== QUẢN LÝ ĐẶT SÂN ========== -->
        <?php if ($page === 'bookings'): ?>
        <div class="fade-in-up">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Quản lý đặt sân</h1>
                <p class="text-gray-500 mt-1">Xem và xử lý các yêu cầu đặt sân</p>
            </div>
            
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Khách hàng</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Sân</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Thời gian</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Tổng tiền</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Trạng thái</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentBookings as $booking): ?>
                            <tr class="border-b hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-800"><?php echo htmlspecialchars($booking['full_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($booking['phone_number']); ?></div>
                                </td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($booking['court_name']); ?></td>
                                <td class="px-6 py-4">
                                    <div><?php echo date('d/m/Y', strtotime($booking['start_time'])); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo date('H:i', strtotime($booking['start_time'])); ?> - <?php echo date('H:i', strtotime($booking['end_time'])); ?></div>
                                </td>
                                <td class="px-6 py-4 font-semibold text-green-600"><?php echo formatCurrency($booking['total_price']); ?></td>
                                <td class="px-6 py-4"><?php echo getStatusBadge($booking['status']); ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex space-x-2">
                                        <?php if ($booking['status'] === 'pending'): ?>
                                        <button onclick="updateBookingStatus(<?php echo $booking['booking_id']; ?>, 'confirmed')" class="text-green-600 hover:text-green-800" title="Xác nhận">
                                            <i class="fas fa-check-circle text-xl"></i>
                                        </button>
                                        <button onclick="updateBookingStatus(<?php echo $booking['booking_id']; ?>, 'cancelled')" class="text-red-600 hover:text-red-800" title="Từ chối">
                                            <i class="fas fa-times-circle text-xl"></i>
                                        </button>
                                        <?php elseif ($booking['status'] === 'confirmed'): ?>
                                        <button onclick="updateBookingStatus(<?php echo $booking['booking_id']; ?>, 'completed')" class="text-blue-600 hover:text-blue-800" title="Hoàn thành">
                                            <i class="fas fa-check-double text-xl"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ========== DOANH THU ========== -->
        <?php if ($page === 'revenue'): ?>
        <div class="fade-in-up">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Báo cáo doanh thu</h1>
                <p class="text-gray-500 mt-1">Phân tích chi tiết doanh thu từ các sân</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-gradient-to-r from-green-500 to-emerald-600 rounded-2xl p-6 text-white">
                    <p class="opacity-90 mb-2">Tổng doanh thu</p>
                    <p class="text-3xl font-bold"><?php echo formatCurrency($stats['total_revenue']); ?></p>
                </div>
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-2xl p-6 text-white">
                    <p class="opacity-90 mb-2">Doanh thu tháng này</p>
                    <p class="text-3xl font-bold"><?php echo formatCurrency($stats['monthly_revenue']); ?></p>
                </div>
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-2xl p-6 text-white">
                    <p class="opacity-90 mb-2">Doanh thu tuần này</p>
                    <p class="text-3xl font-bold"><?php echo formatCurrency($stats['weekly_revenue']); ?></p>
                </div>
            </div>
            
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-4 border-b border-gray-100">
                    <h3 class="text-lg font-bold text-gray-800">
                        <i class="fas fa-table mr-2 text-green-600"></i>Chi tiết doanh thu theo tháng
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-600">Tháng</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-600">Số lượt đặt</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-600">Doanh thu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthlyRevenue as $month): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium"><?php echo date('m/Y', strtotime($month['month'] . '-01')); ?></td>
                                <td class="px-6 py-4"><?php echo $month['bookings']; ?> lượt</td>
                                <td class="px-6 py-4 font-semibold text-green-600"><?php echo formatCurrency($month['revenue']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ========== ĐÁNH GIÁ ========== -->
        <?php if ($page === 'reviews'): ?>
        <div class="fade-in-up">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Đánh giá từ khách hàng</h1>
                <p class="text-gray-500 mt-1">Xem phản hồi và đánh giá về sân của bạn</p>
            </div>
            
            <div class="space-y-4">
                <?php foreach ($reviews as $review): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition">
                    <div class="flex items-start space-x-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-green-100 to-emerald-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-user text-green-600 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <div class="flex flex-wrap items-center justify-between mb-2">
                                <div>
                                    <h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($review['full_name']); ?></h3>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($review['court_name']); ?></p>
                                </div>
                                <div class="flex items-center">
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-yellow-400' : 'text-gray-300'; ?> text-lg"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <p class="text-gray-600 mb-3"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                            <p class="text-xs text-gray-400"><?php echo date('d/m/Y H:i', strtotime($review['created_at'])); ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($reviews)): ?>
                <div class="text-center py-16 bg-white rounded-2xl">
                    <i class="fas fa-star text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">Chưa có đánh giá nào</h3>
                    <p class="text-gray-500">Khách hàng sẽ đánh giá sau khi đặt sân</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ========== CÀI ĐẶT ========== -->
        <?php if ($page === 'settings'): ?>
        <div class="fade-in-up">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Cài đặt tài khoản</h1>
                <p class="text-gray-500 mt-1">Quản lý thông tin tài khoản và cài đặt thanh toán</p>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">
                        <i class="fas fa-university text-green-600 mr-2"></i>Thông tin thanh toán
                    </h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_settings">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tên ngân hàng</label>
                                <input type="text" name="bank_name" value="<?php echo htmlspecialchars($ownerSettings['bank_name'] ?? ''); ?>" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Số tài khoản</label>
                                <input type="text" name="bank_account" value="<?php echo htmlspecialchars($ownerSettings['bank_account'] ?? ''); ?>" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Chủ tài khoản</label>
                                <input type="text" name="bank_holder" value="<?php echo htmlspecialchars($ownerSettings['bank_holder'] ?? ''); ?>" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500">
                            </div>
                            <button type="submit" class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white py-2 rounded-xl hover:shadow-lg transition">
                                Lưu cài đặt
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">
                        <i class="fas fa-user-circle text-green-600 mr-2"></i>Thông tin cá nhân
                    </h3>
                    <div class="space-y-3">
                        <div>
                            <label class="text-sm text-gray-500">Họ và tên</label>
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-500">Tên đăng nhập</label>
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-500">Vai trò</label>
                            <p class="font-medium text-gray-800">Chủ sân</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal thêm sân -->
<div id="addCourtModal" class="modal">
    <div class="modal-content">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_court">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="modal-header p-5 border-b border-gray-100 flex justify-between items-center">
                <h3 class="text-xl font-bold">Thêm sân mới</h3>
                <button type="button" onclick="closeAddCourtModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="modal-body p-5 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tên sân *</label>
                    <input type="text" name="court_name" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Loại sân</label>
                    <select name="court_type" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500">
                        <option value="">Chọn loại sân</option>
                        <option value="Sân trong nhà">🏸 Sân trong nhà</option>
                        <option value="Sân ngoài trời">☀️ Sân ngoài trời</option>
                        <option value="Sân cao cấp">👑 Sân cao cấp</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Địa chỉ</label>
                    <input type="text" name="address" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Giá mỗi giờ (VNĐ) *</label>
                    <input type="number" name="price_per_hour" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mô tả</label>
                    <textarea name="description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ảnh sân</label>
                    <input type="file" name="court_image" accept="image/jpeg,image/png,image/gif,image/webp" class="w-full">
                    <p class="text-xs text-gray-400 mt-1">Hỗ trợ: JPG, PNG, GIF, WEBP (Tối đa 5MB)</p>
                </div>
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" value="1" checked class="mr-2">
                        <span class="text-sm text-gray-700">Đang hoạt động</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer p-5 border-t border-gray-100 flex justify-end space-x-3">
                <button type="button" onclick="closeAddCourtModal()" class="px-4 py-2 bg-gray-200 rounded-xl hover:bg-gray-300 transition">Hủy</button>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-xl hover:bg-green-700 transition">Thêm sân</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal chỉnh sửa sân -->
<div id="editCourtModal" class="modal">
    <div class="modal-content">
        <form method="POST" enctype="multipart/form-data" id="editCourtForm">
            <input type="hidden" name="action" value="update_court">
            <input type="hidden" name="court_id" id="edit_court_id">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="modal-header p-5 border-b border-gray-100 flex justify-between items-center">
                <h3 class="text-xl font-bold">Chỉnh sửa sân</h3>
                <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="modal-body p-5 space-y-4 max-h-[70vh] overflow-y-auto">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tên sân *</label>
                    <input type="text" name="court_name" id="edit_court_name" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Loại sân</label>
                    <select name="court_type" id="edit_court_type" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500">
                        <option value="">Chọn loại sân</option>
                        <option value="Sân trong nhà">🏸 Sân trong nhà</option>
                        <option value="Sân ngoài trời">☀️ Sân ngoài trời</option>
                        <option value="Sân cao cấp">👑 Sân cao cấp</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Địa chỉ</label>
                    <input type="text" name="address" id="edit_address" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Giá mỗi giờ (VNĐ) *</label>
                    <input type="number" name="price_per_hour" id="edit_price_per_hour" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mô tả</label>
                    <textarea name="description" id="edit_description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500"></textarea>
                </div>
                
                <!-- Ảnh hiện tại -->
                <div id="currentImageContainer" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ảnh hiện tại</label>
                    <div class="relative inline-block">
                        <img id="currentImage" src="" alt="Ảnh hiện tại" class="w-32 h-32 object-cover rounded-lg border">
                        <button type="button" onclick="deleteCurrentImage()" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Đổi ảnh mới</label>
                    <input type="file" name="court_image" id="court_image" accept="image/jpeg,image/png,image/gif,image/webp" class="w-full" onchange="previewNewImage(this)">
                    <div id="newImagePreview" class="mt-2 hidden">
                        <img id="newImage" src="" alt="Ảnh mới" class="w-32 h-32 object-cover rounded-lg border">
                        <p class="text-xs text-green-500 mt-1">Ảnh mới sẽ được cập nhật</p>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Hỗ trợ: JPG, PNG, GIF, WEBP (Tối đa 5MB)</p>
                </div>
                
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" id="edit_is_active" value="1" class="mr-2">
                        <span class="text-sm text-gray-700">Đang hoạt động</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer p-5 border-t border-gray-100 flex justify-end space-x-3">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-200 rounded-xl hover:bg-gray-300 transition">Hủy</button>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-xl hover:bg-green-700 transition">Lưu lại</button>
            </div>
        </form>
    </div>
</div>

<!-- Form ẩn cập nhật booking -->
<form id="updateBookingForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="update_booking_status">
    <input type="hidden" name="booking_id" id="update_booking_id">
    <input type="hidden" name="status" id="update_status">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
</form>

<script>
    // User menu
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userDropdown = document.getElementById('userDropdown');
    const userMenuIcon = document.getElementById('userMenuIcon');
    
    if (userMenuBtn && userDropdown) {
        userMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('hidden');
            if (userMenuIcon) {
                userMenuIcon.style.transform = userDropdown.classList.contains('hidden') ? 'rotate(0deg)' : 'rotate(180deg)';
            }
        });
        
        document.addEventListener('click', function(e) {
            if (!userMenuBtn.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.classList.add('hidden');
                if (userMenuIcon) userMenuIcon.style.transform = 'rotate(0deg)';
            }
        });
    }
    
    // Add court modal
    function showAddCourtModal() {
        document.getElementById('addCourtModal').classList.add('active');
    }
    
    function closeAddCourtModal() {
        document.getElementById('addCourtModal').classList.remove('active');
    }
    
    // Edit court
    function editCourt(courtId) {
        fetch(`get_court.php?id=${courtId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('edit_court_id').value = data.court.court_id;
                    document.getElementById('edit_court_name').value = data.court.court_name;
                    document.getElementById('edit_court_type').value = data.court.court_type || '';
                    document.getElementById('edit_address').value = data.court.address || '';
                    document.getElementById('edit_price_per_hour').value = data.court.price_per_hour;
                    document.getElementById('edit_description').value = data.court.description || '';
                    document.getElementById('edit_is_active').checked = data.court.is_active == 1;
                    
                    // Hiển thị ảnh hiện tại
                    const currentContainer = document.getElementById('currentImageContainer');
                    const currentImage = document.getElementById('currentImage');
                    
                    if (data.court.court_image && data.court.court_image.trim() !== '') {
                        currentImage.src = 'uploads/courts/' + data.court.court_image;
                        currentContainer.classList.remove('hidden');
                    } else {
                        currentContainer.classList.add('hidden');
                    }
                    
                    // Reset preview ảnh mới
                    document.getElementById('newImagePreview').classList.add('hidden');
                    document.getElementById('court_image').value = '';
                    
                    document.getElementById('editCourtModal').classList.add('active');
                }
            })
            .catch(error => console.error('Error:', error));
    }
    
    function closeEditModal() {
        document.getElementById('editCourtModal').classList.remove('active');
    }
    
    // Preview new image
    function previewNewImage(input) {
        const newPreview = document.getElementById('newImagePreview');
        const newImage = document.getElementById('newImage');
        
        if (input.files && input.files[0]) {
            const fileSize = input.files[0].size;
            if (fileSize > 5 * 1024 * 1024) {
                alert('File ảnh không được vượt quá 5MB!');
                input.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                newImage.src = e.target.result;
                newPreview.classList.remove('hidden');
            }
            reader.readAsDataURL(input.files[0]);
        } else {
            newPreview.classList.add('hidden');
        }
    }
    
    // Delete current image
    function deleteCurrentImage() {
        if (confirm('Bạn có chắc muốn xóa ảnh này?')) {
            const courtId = document.getElementById('edit_court_id').value;
            
            fetch('owner_dashboard.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=delete_court_image&court_id=' + courtId + '&csrf_token=<?php echo $_SESSION['csrf_token']; ?>'
            })
            .then(response => response.text())
            .then(() => {
                document.getElementById('currentImageContainer').classList.add('hidden');
                location.reload();
            });
        }
    }
    
    // Delete court
    function deleteCourt(courtId) {
        if (confirm('Bạn có chắc muốn vô hiệu hóa sân này?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_court">
                <input type="hidden" name="court_id" value="${courtId}">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    // Update booking status
    function updateBookingStatus(bookingId, status) {
        if (confirm('Xác nhận thay đổi trạng thái đặt sân?')) {
            document.getElementById('update_booking_id').value = bookingId;
            document.getElementById('update_status').value = status;
            document.getElementById('updateBookingForm').submit();
        }
    }
    
    // Close modal on outside click
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('active');
        }
    }
    
    // Chart
    <?php if ($page === 'dashboard' && !empty($monthlyRevenue)): ?>
    const ctx = document.getElementById('revenueChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: [<?php echo implode(',', array_map(function($m) { return '"' . date('m/Y', strtotime($m['month'] . '-01')) . '"'; }, $monthlyRevenue)); ?>],
            datasets: [{
                label: 'Doanh thu (VNĐ)',
                data: [<?php echo implode(',', array_column($monthlyRevenue, 'revenue')); ?>],
                borderColor: '#22c55e',
                backgroundColor: 'rgba(34,197,94,0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.raw.toLocaleString() + ' VNĐ';
                        }
                    }
                }
            },
            scales: {
                y: {
                    ticks: {
                        callback: function(value) {
                            return (value / 1000).toFixed(0) + 'K';
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
</script>

</body>
</html>