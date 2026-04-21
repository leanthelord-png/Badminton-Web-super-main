<?php
// ============================================
// ADMIN FRONTEND - QUẢN LÝ SÂN CẦU LÔNG
// ============================================

require_once '../config/database.php';

session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_user_id'])) {
    header('Location: login.php');
    exit;
}

// Xử lý logout
if (isset($_POST['action']) && $_POST['action'] === 'logout') {
    session_destroy();
    header('Location: login.php');
    exit;
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ========== HELPER FUNCTIONS ==========
function formatAmountK($value) {
    return number_format($value / 1000, 0, ',', '.') . 'K';
}

function formatCurrency($value) {
    return number_format($value, 0, ',', '.') . ' VNĐ';
}

function getStatusBadge($status) {
    $badges = [
        'pending' => 'bg-yellow-100 text-yellow-800',
        'confirmed' => 'bg-blue-100 text-blue-800',
        'completed' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-800',
        'active' => 'bg-green-100 text-green-800',
        'inactive' => 'bg-gray-100 text-gray-800',
        'resolved' => 'bg-green-100 text-green-800',
        'open' => 'bg-yellow-100 text-yellow-800',
        'investigating' => 'bg-purple-100 text-purple-800',
        'rejected' => 'bg-gray-100 text-gray-800'
    ];
    $class = $badges[$status] ?? 'bg-gray-100 text-gray-800';
    return "<span class='px-2 py-1 rounded text-xs {$class}'>" . ucfirst($status) . "</span>";
}

// ========== XỬ LÝ FORM POST ==========
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = 'CSRF token không hợp lệ';
        $messageType = 'error';
    } else {
        try {
            switch ($_POST['action']) {
                case 'add_court':
                    $court_name = trim($_POST['court_name'] ?? '');
                    $court_type = trim($_POST['court_type'] ?? '');
                    $price_per_hour = floatval($_POST['price_per_hour'] ?? 0);
                    $description = trim($_POST['description'] ?? '');
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    $owner_id = intval($_POST['owner_id'] ?? 0);

                    if (empty($court_name) || $price_per_hour <= 0) {
                        throw new Exception('Tên sân và giá là bắt buộc');
                    }

                    $stmt = $pdo->prepare("INSERT INTO courts (court_name, court_type, price_per_hour, description, is_active, owner_id) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$court_name, $court_type, $price_per_hour, $description, $is_active, $owner_id ?: null]);
                    $message = 'Thêm sân thành công!';
                    $messageType = 'success';
                    break;

                case 'update_court':
                    $court_id = intval($_POST['court_id'] ?? 0);
                    $court_name = trim($_POST['court_name'] ?? '');
                    $court_type = trim($_POST['court_type'] ?? '');
                    $price_per_hour = floatval($_POST['price_per_hour'] ?? 0);
                    $description = trim($_POST['description'] ?? '');
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    $owner_id = intval($_POST['owner_id'] ?? 0);

                    if (!$court_id || empty($court_name) || $price_per_hour <= 0) {
                        throw new Exception('Dữ liệu không hợp lệ');
                    }

                    $stmt = $pdo->prepare("UPDATE courts SET court_name = ?, court_type = ?, price_per_hour = ?, description = ?, is_active = ?, owner_id = ? WHERE court_id = ?");
                    $stmt->execute([$court_name, $court_type, $price_per_hour, $description, $is_active, $owner_id ?: null, $court_id]);
                    $message = 'Cập nhật sân thành công!';
                    $messageType = 'success';
                    break;

                case 'delete_court':
                    $court_id = intval($_POST['court_id'] ?? 0);
                    
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE court_id = ?");
                    $stmt->execute([$court_id]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception('Không thể xóa sân đã có lịch đặt');
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM courts WHERE court_id = ?");
                    $stmt->execute([$court_id]);
                    $message = 'Xóa sân thành công!';
                    $messageType = 'success';
                    break;

                case 'upload_court_image':
    $court_id = intval($_POST['court_id'] ?? 0);
    
    if (isset($_FILES['court_image']) && $_FILES['court_image']['error'] === UPLOAD_ERR_OK) {
        // SỬA ĐƯỜNG DẪN - dùng đường dẫn tuyệt đối hoặc tương đối đúng
        $uploadDir = __DIR__ . '/uploads/courts/';  // Dùng __DIR__ để có đường dẫn tuyệt đối
        
        // Hoặc nếu uploads ở thư mục gốc:
        // $uploadDir = '../uploads/courts/';
        
        // Tạo thư mục nếu chưa tồn tại
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['court_image']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception('Chỉ chấp nhận file ảnh (JPG, PNG, GIF, WEBP)');
        }
        
        // Tạo tên file duy nhất
        $newFileName = 'court_' . $court_id . '_' . time() . '.' . $fileExtension;
        $uploadPath = $uploadDir . $newFileName;
        
        // Xóa ảnh cũ nếu có
        $stmt = $pdo->prepare("SELECT court_image FROM courts WHERE court_id = ?");
        $stmt->execute([$court_id]);
        $oldImage = $stmt->fetchColumn();
        if ($oldImage && file_exists($uploadDir . $oldImage)) {
            unlink($uploadDir . $oldImage);
        }
        
        // Upload ảnh mới
        if (move_uploaded_file($_FILES['court_image']['tmp_name'], $uploadPath)) {
            // LƯU Ý: Chỉ lưu tên file, không lưu đường dẫn
            $stmt = $pdo->prepare("UPDATE courts SET court_image = ? WHERE court_id = ?");
            $stmt->execute([$newFileName, $court_id]);
            $message = 'Cập nhật ảnh sân thành công!';
            $messageType = 'success';
        } else {
            throw new Exception('Không thể upload ảnh');
        }
    } else {
        throw new Exception('Vui lòng chọn file ảnh');
    }
    break;

                case 'resolve_ticket':
                    $ticketId = intval($_POST['ticket_id'] ?? 0);
                    $stmt = $pdo->prepare("UPDATE support_tickets SET status = 'resolved', resolved_at = NOW() WHERE ticket_id = ?");
                    $stmt->execute([$ticketId]);
                    $message = 'Ticket đã được giải quyết';
                    $messageType = 'success';
                    break;

                case 'cancel_booking':
                    $booking_id = intval($_POST['booking_id'] ?? 0);
                    $reason = trim($_POST['cancel_reason'] ?? 'Admin hủy đặt sân');
                    
                    $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled', cancellation_reason = ?, cancelled_by = ? WHERE booking_id = ?");
                    $stmt->execute([$reason, $_SESSION['admin_user_id'], $booking_id]);
                    
                    // Hoàn tiền nếu đã thanh toán
                    $stmt = $pdo->prepare("SELECT user_id, total_price FROM bookings WHERE booking_id = ?");
                    $stmt->execute([$booking_id]);
                    $booking = $stmt->fetch();
                    if ($booking) {
                        $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE user_id = ?");
                        $stmt->execute([$booking['total_price'], $booking['user_id']]);
                    }
                    
                    $message = 'Đã hủy đặt sân và hoàn tiền thành công';
                    $messageType = 'success';
                    break;

                case 'flag_booking':
                    $booking_id = intval($_POST['booking_id'] ?? 0);
                    $reason = trim($_POST['flag_reason'] ?? '');
                    $admin_note = trim($_POST['admin_note'] ?? '');
                    
                    $stmt = $pdo->prepare("UPDATE bookings SET is_flagged = 1, flag_reason = ?, admin_note = ? WHERE booking_id = ?");
                    $stmt->execute([$reason, $admin_note, $booking_id]);
                    $message = 'Đã đánh dấu booking bất thường';
                    $messageType = 'success';
                    break;

                case 'resolve_dispute':
                    $dispute_id = intval($_POST['dispute_id'] ?? 0);
                    $resolution = trim($_POST['resolution'] ?? '');
                    $action = $_POST['resolution_action'] ?? 'resolve';
                    
                    $status = $action === 'reject' ? 'rejected' : 'resolved';
                    
                    $stmt = $pdo->prepare("UPDATE disputes SET status = ?, resolution = ?, resolved_by = ?, resolved_at = NOW() WHERE dispute_id = ?");
                    $stmt->execute([$status, $resolution, $_SESSION['admin_user_id'], $dispute_id]);
                    
                    // Nếu chọn hoàn tiền
                    if ($action === 'refund') {
                        $stmt = $pdo->prepare("
                            SELECT b.user_id, b.total_price 
                            FROM disputes d 
                            JOIN bookings b ON d.booking_id = b.booking_id 
                            WHERE d.dispute_id = ?
                        ");
                        $stmt->execute([$dispute_id]);
                        $dispute_data = $stmt->fetch();
                        if ($dispute_data) {
                            $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE user_id = ?");
                            $stmt->execute([$dispute_data['total_price'], $dispute_data['user_id']]);
                            $resolution .= " (Đã hoàn tiền " . formatCurrency($dispute_data['total_price']) . ")";
                            $stmt = $pdo->prepare("UPDATE disputes SET resolution = ? WHERE dispute_id = ?");
                            $stmt->execute([$resolution, $dispute_id]);
                        }
                    }
                    
                    $message = 'Đã xử lý khiếu nại';
                    $messageType = 'success';
                    break;

                case 'cancel_recurring':
                    $recurring_id = intval($_POST['recurring_id'] ?? 0);
                    $stmt = $pdo->prepare("UPDATE recurring_bookings SET status = 'cancelled' WHERE id = ?");
                    $stmt->execute([$recurring_id]);
                    
                    // Hủy các booking tương lai
                    $stmt = $pdo->prepare("
                        UPDATE bookings SET status = 'cancelled', cancellation_reason = 'Hủy đặt lặp' 
                        WHERE recurring_parent_id = ? AND start_time > NOW()
                    ");
                    $stmt->execute([$recurring_id]);
                    
                    $message = 'Đã hủy đặt lặp thành công';
                    $messageType = 'success';
                    break;
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
}

// ========== LẤY DỮ LIỆU THEO PAGE ==========
$page = $_GET['page'] ?? 'dashboard';
$allowed_pages = ['dashboard', 'courts', 'users', 'owners', 'transactions', 'support', 'reports', 'calendar', 'disputes', 'recurring', 'dispute_detail'];
if (!in_array($page, $allowed_pages)) $page = 'dashboard';

$stats = [];
$bookings = [];
$courts = [];
$customers = [];
$owners = [];
$transactions = [];
$supportTickets = [];
$monthly_revenue = [];
$court_revenue = [];
$court_list = [];
$calendar_bookings = [];
$hourly_stats = [];
$disputes = [];
$recurring_bookings = [];

try {
    switch ($page) {
        case 'dashboard':
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM courts WHERE is_active = 1");
            $stats['total_courts'] = $stmt->fetch()['total'] ?? 0;
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM bookings WHERE DATE(start_time) = CURDATE()");
            $stats['today_bookings'] = $stmt->fetch()['total'] ?? 0;
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'customer'");
            $stats['total_users'] = $stmt->fetch()['total'] ?? 0;
            $stmt = $pdo->query("SELECT COALESCE(SUM(total_price), 0) as total FROM bookings WHERE DATE(start_time) = CURDATE() AND status = 'completed'");
            $stats['today_revenue'] = $stmt->fetch()['total'] ?? 0;
            $stmt = $pdo->query("SELECT b.*, c.court_name, u.full_name, u.username FROM bookings b JOIN courts c ON b.court_id = c.court_id JOIN users u ON b.user_id = u.user_id ORDER BY b.created_at DESC LIMIT 10");
            $bookings = $stmt->fetchAll();
            break;

        case 'courts':
            $stmt = $pdo->query("SELECT c.*, u.full_name as owner_name, u.phone_number as owner_phone FROM courts c LEFT JOIN users u ON c.owner_id = u.user_id ORDER BY c.court_name");
            $courts = $stmt->fetchAll();
            break;

        case 'users':
            $stmt = $pdo->query("SELECT * FROM users WHERE role = 'customer' ORDER BY created_at DESC");
            $customers = $stmt->fetchAll();
            break;

        case 'owners':
            $stmt = $pdo->query("SELECT * FROM users WHERE role = 'owner' ORDER BY created_at DESC");
            $owners = $stmt->fetchAll();
            break;

        case 'transactions':
            $stmt = $pdo->query("SELECT h.*, u.username, u.full_name FROM pay_history h JOIN users u ON h.user_id = u.user_id ORDER BY h.created_at DESC LIMIT 50");
            $transactions = $stmt->fetchAll();
            break;

        case 'support':
            $stmt = $pdo->query("SELECT t.*, u.username as customer_name, o.username as owner_name FROM support_tickets t LEFT JOIN users u ON t.user_id = u.user_id LEFT JOIN users o ON t.owner_id = o.user_id ORDER BY t.created_at DESC LIMIT 30");
            $supportTickets = $stmt->fetchAll();
            break;

        case 'reports':
            $stmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COALESCE(SUM(total_price), 0) as revenue FROM bookings WHERE status = 'completed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month DESC");
            $monthly_revenue = $stmt->fetchAll();
            $stmt = $pdo->query("SELECT c.court_name, COALESCE(SUM(b.total_price), 0) as revenue, COUNT(b.booking_id) as booking_count FROM courts c LEFT JOIN bookings b ON c.court_id = b.court_id AND b.status = 'completed' GROUP BY c.court_id, c.court_name ORDER BY revenue DESC");
            $court_revenue = $stmt->fetchAll();
            $stmt = $pdo->query("SELECT COALESCE(SUM(total_price), 0) as total FROM bookings WHERE status = 'completed'");
            $stats['total_revenue'] = $stmt->fetch()['total'] ?? 0;
            $stmt = $pdo->query("SELECT COALESCE(SUM(total_price), 0) as total FROM bookings WHERE status = 'completed' AND DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')");
            $stats['monthly_revenue'] = $stmt->fetch()['total'] ?? 0;
            break;

        case 'calendar':
            $stmt = $pdo->query("SELECT court_id, court_name FROM courts WHERE is_active = 1 ORDER BY court_name");
            $court_list = $stmt->fetchAll();
            
            $start_date = $_GET['start'] ?? date('Y-m-d');
            $end_date = date('Y-m-d', strtotime($start_date . ' +6 days'));
            $selected_court = $_GET['court_id'] ?? 0;
            
            if ($selected_court) {
                $stmt = $pdo->prepare("
                    SELECT b.*, u.full_name, u.phone_number, u.username, c.court_name
                    FROM bookings b
                    JOIN users u ON b.user_id = u.user_id
                    JOIN courts c ON b.court_id = c.court_id
                    WHERE b.court_id = ? 
                    AND DATE(b.start_time) BETWEEN ? AND ?
                    AND b.status != 'cancelled'
                    ORDER BY b.start_time
                ");
                $stmt->execute([$selected_court, $start_date, $end_date]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT b.*, u.full_name, u.phone_number, u.username, c.court_name
                    FROM bookings b
                    JOIN users u ON b.user_id = u.user_id
                    JOIN courts c ON b.court_id = c.court_id
                    WHERE DATE(b.start_time) BETWEEN ? AND ?
                    AND b.status != 'cancelled'
                    ORDER BY b.start_time
                ");
                $stmt->execute([$start_date, $end_date]);
            }
            $calendar_bookings = $stmt->fetchAll();
            
            $hourly_stats = [];
            foreach ($calendar_bookings as $booking) {
                $hour = date('H', strtotime($booking['start_time']));
                if (!isset($hourly_stats[$hour])) $hourly_stats[$hour] = 0;
                $hourly_stats[$hour]++;
            }
            break;

        case 'disputes':
            $stmt = $pdo->query("
                SELECT d.*, 
                       u1.full_name as reporter_name, u1.username as reporter_username,
                       u2.full_name as reported_name, u2.username as reported_username,
                       b.start_time, b.total_price, c.court_name
                FROM disputes d
                JOIN users u1 ON d.reporter_id = u1.user_id
                JOIN users u2 ON d.reported_id = u2.user_id
                JOIN bookings b ON d.booking_id = b.booking_id
                JOIN courts c ON b.court_id = c.court_id
                ORDER BY d.created_at DESC
            ");
            $disputes = $stmt->fetchAll();
            break;

        case 'dispute_detail':
            $dispute_id = intval($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT d.*, 
                       u1.full_name as reporter_name, u1.username as reporter_username, u1.phone_number as reporter_phone, u1.email as reporter_email,
                       u2.full_name as reported_name, u2.username as reported_username, u2.phone_number as reported_phone,
                       b.*, c.court_name, c.price_per_hour,
                       admin.full_name as resolver_name
                FROM disputes d
                JOIN users u1 ON d.reporter_id = u1.user_id
                JOIN users u2 ON d.reported_id = u2.user_id
                JOIN bookings b ON d.booking_id = b.booking_id
                JOIN courts c ON b.court_id = c.court_id
                LEFT JOIN users admin ON d.resolved_by = admin.user_id
                WHERE d.dispute_id = ?
            ");
            $stmt->execute([$dispute_id]);
            $dispute_detail = $stmt->fetch();
            if (!$dispute_detail) {
                header('Location: ?page=disputes');
                exit;
            }
            break;

        case 'recurring':
            $stmt = $pdo->prepare("
                SELECT r.*, u.full_name, u.username, u.phone_number, c.court_name, c.price_per_hour
                FROM recurring_bookings r
                JOIN users u ON r.user_id = u.user_id
                JOIN courts c ON r.court_id = c.court_id
                WHERE r.status = 'active'
                ORDER BY r.created_at DESC
            ");
            $stmt->execute();
            $recurring_bookings = $stmt->fetchAll();
            break;
    }
} catch (Exception $e) {
    error_log("Database Error: " . $e->getMessage());
}

// Lấy danh sách owner cho dropdown
$owner_list = [];
try {
    $stmt = $pdo->query("SELECT user_id, full_name, username FROM users WHERE role = 'owner' ORDER BY full_name");
    $owner_list = $stmt->fetchAll();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="vi" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Quản lý Sân Cầu Lông</title>
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .fade-in { animation: fadeIn 0.3s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .sidebar-link.active { background-color: #1e40af; color: white; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; border-radius: 12px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto; animation: slideIn 0.3s ease; }
        @keyframes slideIn { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-header { padding: 1rem 1.5rem; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 1.5rem; }
        .modal-footer { padding: 1rem 1.5rem; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 0.75rem; }
        .close-modal { cursor: pointer; font-size: 1.5rem; color: #6b7280; transition: color 0.2s; }
        .close-modal:hover { color: #ef4444; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #374151; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; transition: all 0.2s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        .image-preview { margin-top: 0.5rem; display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .image-preview img { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 1px solid #e5e7eb; }
        .table-hover tbody tr:hover { background-color: #f3f4f6; }
        .calendar-hour:hover { background-color: #f9fafb; cursor: pointer; }
    </style>
</head>
<body class="bg-gray-100 h-full">
    <div class="h-screen flex">
        <!-- Sidebar -->
        <div class="bg-gray-900 text-white w-64 flex flex-col shadow-lg z-10">
            <div class="p-6 border-b border-gray-800 flex items-center space-x-3">
                <div class="bg-blue-600 p-2 rounded-lg"><i class="fas fa-table-tennis-paddle-ball text-2xl"></i></div>
                <div><h1 class="text-xl font-bold">Badminton Admin</h1><p class="text-xs text-gray-400">Quản lý sân cầu lông</p></div>
            </div>
            <nav class="flex-1 px-4 py-6 overflow-y-auto">
                <ul class="space-y-2">
                    <li><a href="?page=dashboard" class="sidebar-link flex items-center px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white rounded-lg transition-all <?php echo $page === 'dashboard' ? 'active bg-gray-800' : ''; ?>"><i class="fas fa-tachometer-alt w-5 mr-3"></i>Dashboard</a></li>
                    <li><a href="?page=courts" class="sidebar-link flex items-center px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white rounded-lg transition-all <?php echo $page === 'courts' ? 'active bg-gray-800' : ''; ?>"><i class="fas fa-map-marker-alt w-5 mr-3"></i>Quản lý sân</a></li>
                    <li><a href="?page=calendar" class="sidebar-link flex items-center px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white rounded-lg transition-all <?php echo $page === 'calendar' ? 'active bg-gray-800' : ''; ?>"><i class="fas fa-calendar-alt w-5 mr-3"></i>Lịch đặt sân</a></li>
                    <li><a href="?page=users" class="sidebar-link flex items-center px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white rounded-lg transition-all <?php echo $page === 'users' ? 'active bg-gray-800' : ''; ?>"><i class="fas fa-users w-5 mr-3"></i>Khách hàng</a></li>
                    <li><a href="?page=owners" class="sidebar-link flex items-center px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white rounded-lg transition-all <?php echo $page === 'owners' ? 'active bg-gray-800' : ''; ?>"><i class="fas fa-user-tie w-5 mr-3"></i>Chủ sân</a></li>
                    <li><a href="?page=recurring" class="sidebar-link flex items-center px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white rounded-lg transition-all <?php echo $page === 'recurring' ? 'active bg-gray-800' : ''; ?>"><i class="fas fa-sync-alt w-5 mr-3"></i>Đặt lặp lại</a></li>
                    <li><a href="?page=transactions" class="sidebar-link flex items-center px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white rounded-lg transition-all <?php echo $page === 'transactions' ? 'active bg-gray-800' : ''; ?>"><i class="fas fa-wallet w-5 mr-3"></i>Giao dịch</a></li>
                    <li><a href="?page=disputes" class="sidebar-link flex items-center px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white rounded-lg transition-all <?php echo $page === 'disputes' ? 'active bg-gray-800' : ''; ?>"><i class="fas fa-gavel w-5 mr-3"></i>Khiếu nại</a></li>
                    <li><a href="?page=support" class="sidebar-link flex items-center px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white rounded-lg transition-all <?php echo $page === 'support' ? 'active bg-gray-800' : ''; ?>"><i class="fas fa-headset w-5 mr-3"></i>Support</a></li>
                    <li><a href="?page=reports" class="sidebar-link flex items-center px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white rounded-lg transition-all <?php echo $page === 'reports' ? 'active bg-gray-800' : ''; ?>"><i class="fas fa-chart-bar w-5 mr-3"></i>Doanh thu</a></li>
                </ul>
            </nav>
            <div class="p-4 border-t border-gray-800">
                <form method="POST">
                    <input type="hidden" name="action" value="logout">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <button type="submit" class="flex items-center w-full px-4 py-3 text-gray-300 hover:bg-red-600 hover:text-white rounded-lg transition-all"><i class="fas fa-sign-out-alt w-5 mr-3"></i>Đăng xuất</button>
                </form>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="bg-white shadow-sm px-6 py-4">
                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-bold text-gray-800">
                        <?php 
                        $titles = [
                            'dashboard'=>'Dashboard',
                            'courts'=>'Quản lý sân',
                            'calendar'=>'Lịch đặt sân',
                            'users'=>'Dữ liệu khách hàng',
                            'owners'=>'Dữ liệu chủ sân',
                            'recurring'=>'Đặt lặp lại',
                            'transactions'=>'Giao dịch',
                            'disputes'=>'Khiếu nại',
                            'dispute_detail'=>'Chi tiết khiếu nại',
                            'support'=>'Hỗ trợ',
                            'reports'=>'Báo cáo doanh thu'
                        ]; 
                        echo $titles[$page]; 
                        ?>
                    </h1>
                    <div class="text-sm text-gray-600"><i class="fas fa-user-circle mr-1"></i><?php echo htmlspecialchars($_SESSION['admin_full_name'] ?? $_SESSION['admin_username']); ?></div>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-6">
                <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg fade-in <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800 border-l-4 border-green-500' : 'bg-red-100 text-red-800 border-l-4 border-red-500'; ?>">
                    <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-2"></i><?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <!-- DASHBOARD -->
                <?php if ($page === 'dashboard'): ?>
                <div class="fade-in">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow"><div class="flex items-center"><div class="bg-blue-500 p-3 rounded-lg"><i class="fas fa-map-marker-alt text-white text-xl"></i></div><div class="ml-4"><p class="text-gray-600">Tổng sân</p><p class="text-2xl font-bold"><?php echo $stats['total_courts'] ?? 0; ?></p></div></div></div>
                        <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow"><div class="flex items-center"><div class="bg-green-500 p-3 rounded-lg"><i class="fas fa-calendar-check text-white text-xl"></i></div><div class="ml-4"><p class="text-gray-600">Đặt sân hôm nay</p><p class="text-2xl font-bold"><?php echo $stats['today_bookings'] ?? 0; ?></p></div></div></div>
                        <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow"><div class="flex items-center"><div class="bg-purple-500 p-3 rounded-lg"><i class="fas fa-users text-white text-xl"></i></div><div class="ml-4"><p class="text-gray-600">Tổng người dùng</p><p class="text-2xl font-bold"><?php echo $stats['total_users'] ?? 0; ?></p></div></div></div>
                        <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow"><div class="flex items-center"><div class="bg-yellow-500 p-3 rounded-lg"><i class="fas fa-money-bill-wave text-white text-xl"></i></div><div class="ml-4"><p class="text-gray-600">Doanh thu hôm nay</p><p class="text-2xl font-bold"><?php echo formatAmountK($stats['today_revenue'] ?? 0); ?></p></div></div></div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6"><h3 class="text-lg font-bold mb-4"><i class="fas fa-history mr-2"></i>Đặt sân gần đây</h3><div class="overflow-x-auto"><table class="w-full"><thead><tr class="bg-gray-50 border-b"><th class="px-4 py-3 text-left">Người dùng</th><th class="px-4 py-3 text-left">Sân</th><th class="px-4 py-3 text-left">Thời gian</th><th class="px-4 py-3 text-left">Trạng thái</th></tr></thead><tbody><?php foreach ($bookings as $booking): ?><tr class="border-b hover:bg-gray-50"><td class="px-4 py-3"><?php echo htmlspecialchars($booking['full_name'] ?: $booking['username']); ?></td><td class="px-4 py-3"><?php echo htmlspecialchars($booking['court_name']); ?></td><td class="px-4 py-3"><?php echo date('d/m/Y H:i', strtotime($booking['start_time'])); ?></td><td class="px-4 py-3"><?php echo getStatusBadge($booking['status']); ?></td></tr><?php endforeach; ?></tbody>}</table></div></div>
                </div>

                <!-- QUẢN LÝ SÂN -->
                <?php elseif ($page === 'courts'): ?>
                <div class="fade-in">
                    <div class="flex justify-between items-center mb-6"><h2 class="text-2xl font-bold"><i class="fas fa-plus-circle mr-2 text-blue-600"></i>Quản lý sân</h2><button onclick="showAddCourtModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors"><i class="fas fa-plus mr-2"></i>Thêm sân mới</button></div>
                    <div class="bg-white rounded-lg shadow overflow-hidden"><div class="overflow-x-auto"><table class="w-full"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left">Tên sân</th><th class="px-6 py-3 text-left">Chủ sân</th><th class="px-6 py-3 text-left">Ảnh</th><th class="px-6 py-3 text-left">Loại</th><th class="px-6 py-3 text-left">Giá/giờ</th><th class="px-6 py-3 text-left">Trạng thái</th><th class="px-6 py-3 text-left">Thao tác</th></tr></thead><tbody><?php foreach ($courts as $court): ?><tr class="border-b hover:bg-gray-50"><td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($court['court_name']); ?></td><td class="px-6 py-4"><div class="text-sm"><div class="font-semibold"><?php echo htmlspecialchars($court['owner_name'] ?: 'Hệ thống'); ?></div><div class="text-gray-500 text-xs"><?php echo htmlspecialchars($court['owner_phone'] ?: ''); ?></div></div></td><td class="px-6 py-4"><?php if ($court['court_image']): ?><img src="../uploads/courts/<?php echo htmlspecialchars($court['court_image']); ?>" class="w-12 h-12 object-cover rounded"><?php else: ?><span class="text-gray-400 text-sm">Chưa có</span><?php endif; ?></td><td class="px-6 py-4"><?php echo htmlspecialchars($court['court_type'] ?: 'N/A'); ?></td><td class="px-6 py-4 font-semibold text-green-600"><?php echo formatCurrency($court['price_per_hour']); ?></td><td class="px-6 py-4"><?php echo getStatusBadge($court['is_active'] ? 'active' : 'inactive'); ?></td><td class="px-6 py-4"><button onclick="editCourt(<?php echo $court['court_id']; ?>)" class="text-blue-600 hover:text-blue-800 mr-2" title="Sửa"><i class="fas fa-edit"></i></button><button onclick="uploadCourtImage(<?php echo $court['court_id']; ?>)" class="text-green-600 hover:text-green-800 mr-2" title="Upload ảnh"><i class="fas fa-image"></i></button><button onclick="deleteCourt(<?php echo $court['court_id']; ?>, '<?php echo addslashes(htmlspecialchars($court['court_name'])); ?>')" class="text-red-600 hover:text-red-800" title="Xóa"><i class="fas fa-trash"></i></button></tr><?php endforeach; ?></tbody>}</table></div></div>
                </div>

                <!-- LỊCH ĐẶT SÂN -->
                <?php elseif ($page === 'calendar'): ?>
                <div class="fade-in">
                    <div class="bg-white rounded-lg shadow p-6 mb-6">
                        <h2 class="text-2xl font-bold mb-4"><i class="fas fa-calendar-alt mr-2 text-blue-600"></i>Lịch đặt sân</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                            <div>
                                <label class="block text-sm font-medium mb-2">Chọn sân</label>
                                <select id="courtFilter" class="w-full border rounded-lg px-3 py-2" onchange="filterCourt()">
                                    <option value="0">Tất cả sân</option>
                                    <?php foreach ($court_list as $court): ?>
                                    <option value="<?php echo $court['court_id']; ?>" <?php echo ($_GET['court_id'] ?? 0) == $court['court_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($court['court_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-2">Tuần bắt đầu</label>
                                <input type="date" id="weekStart" value="<?php echo $_GET['start'] ?? date('Y-m-d'); ?>" class="w-full border rounded-lg px-3 py-2" onchange="changeWeek()">
                            </div>
                            <div class="flex items-end gap-2">
                                <button onclick="previousWeek()" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">
                                    <i class="fas fa-chevron-left"></i> Tuần trước
                                </button>
                                <button onclick="nextWeek()" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">
                                    Tuần sau <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="border p-3 w-20">Giờ</th>
                                        <?php
                                        $dates = [];
                                        $start_date = $_GET['start'] ?? date('Y-m-d');
                                        for ($i = 0; $i < 7; $i++) {
                                            $date = date('Y-m-d', strtotime($start_date . " +$i days"));
                                            $dates[] = $date;
                                            echo "<th class='border p-3'>" . date('d/m/Y<br>(D)', strtotime($date)) . "</th>";
                                        }
                                        ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php for ($hour = 6; $hour <= 22; $hour++): ?>
                                    <tr class="calendar-hour">
                                        <td class="border p-3 font-semibold bg-gray-50 text-center"><?php echo sprintf('%02d:00', $hour); ?></td>
                                        <?php foreach ($dates as $date): ?>
                                        <td class="border p-2 align-top min-h-[100px]">
                                            <?php
                                            $bookings_in_hour = array_filter($calendar_bookings, function($b) use ($date, $hour) {
                                                $booking_date = date('Y-m-d', strtotime($b['start_time']));
                                                $booking_hour = date('H', strtotime($b['start_time']));
                                                return $booking_date == $date && $booking_hour == $hour;
                                            });
                                            
                                            foreach ($bookings_in_hour as $booking):
                                                $badge_color = [
                                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                                    'confirmed' => 'bg-blue-100 text-blue-800',
                                                    'completed' => 'bg-green-100 text-green-800'
                                                ][$booking['status']] ?? 'bg-gray-100';
                                                
                                                $flag_icon = $booking['is_flagged'] ? '<i class="fas fa-exclamation-triangle text-red-500 ml-1" title="Bất thường: ' . htmlspecialchars($booking['flag_reason']) . '"></i>' : '';
                                            ?>
                                                <div class="text-sm mb-2 p-2 rounded <?php echo $badge_color; ?> hover:shadow-md transition-shadow">
                                                    <div class="font-semibold flex items-center justify-between">
                                                        <span><?php echo htmlspecialchars($booking['court_name']); ?></span>
                                                        <?php echo $flag_icon; ?>
                                                    </div>
                                                    <div class="text-xs mt-1">
                                                        <strong><?php echo htmlspecialchars($booking['full_name'] ?: $booking['username']); ?></strong><br>
                                                        <?php echo date('H:i', strtotime($booking['start_time'])); ?> - <?php echo date('H:i', strtotime($booking['end_time'])); ?>
                                                    </div>
                                                    <div class="mt-2 flex gap-1">
                                                        <button onclick="viewBookingDetail(<?php echo $booking['booking_id']; ?>)" class="text-xs text-blue-600 hover:text-blue-800">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <?php if ($booking['status'] == 'pending'): ?>
                                                        <button onclick="confirmBooking(<?php echo $booking['booking_id']; ?>)" class="text-xs text-green-600 hover:text-green-800">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                        <button onclick="cancelBooking(<?php echo $booking['booking_id']; ?>)" class="text-xs text-red-600 hover:text-red-800">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="bg-blue-50 rounded-lg p-4">
                                <div class="text-sm text-gray-600">Tổng số booking tuần</div>
                                <div class="text-2xl font-bold text-blue-600"><?php echo count($calendar_bookings); ?></div>
                            </div>
                            <div class="bg-green-50 rounded-lg p-4">
                                <div class="text-sm text-gray-600">Giờ cao điểm nhất</div>
                                <div class="text-2xl font-bold text-green-600">
                                    <?php 
                                    $max_hour = array_keys($hourly_stats, max($hourly_stats))[0] ?? 'N/A';
                                    echo $max_hour != 'N/A' ? $max_hour . ':00' : 'N/A';
                                    ?>
                                </div>
                            </div>
                            <div class="bg-yellow-50 rounded-lg p-4">
                                <div class="text-sm text-gray-600">Booking chờ xác nhận</div>
                                <div class="text-2xl font-bold text-yellow-600">
                                    <?php echo count(array_filter($calendar_bookings, fn($b) => $b['status'] == 'pending')); ?>
                                </div>
                            </div>
                            <div class="bg-red-50 rounded-lg p-4">
                                <div class="text-sm text-gray-600">Booking bất thường</div>
                                <div class="text-2xl font-bold text-red-600">
                                    <?php echo count(array_filter($calendar_bookings, fn($b) => $b['is_flagged'] == 1)); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KHÁCH HÀNG -->
                <?php elseif ($page === 'users'): ?>
                <div class="fade-in"><h2 class="text-2xl font-bold mb-6"><i class="fas fa-users mr-2 text-blue-600"></i>Dữ liệu khách hàng</h2><div class="bg-white rounded-lg shadow overflow-hidden"><div class="overflow-x-auto"><table class="w-full"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left">Họ tên</th><th class="px-6 py-3 text-left">Username</th><th class="px-6 py-3 text-left">SĐT</th><th class="px-6 py-3 text-left">Email</th><th class="px-6 py-3 text-left">Ngày đăng ký</th><th class="px-6 py-3 text-left">Số đặt sân</th></tr></thead><tbody><?php foreach ($customers as $customer): ?><?php $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ?"); $stmt->execute([$customer['user_id']]); $booking_count = $stmt->fetchColumn(); ?><tr class="border-b hover:bg-gray-50"><td class="px-6 py-4"><?php echo htmlspecialchars($customer['full_name'] ?: 'N/A'); ?></td><td class="px-6 py-4"><?php echo htmlspecialchars($customer['username']); ?></td><td class="px-6 py-4"><?php echo htmlspecialchars($customer['phone_number'] ?: 'N/A'); ?></td><td class="px-6 py-4"><?php echo htmlspecialchars($customer['email'] ?: 'N/A'); ?></td><td class="px-6 py-4"><?php echo date('d/m/Y', strtotime($customer['created_at'])); ?></td><td class="px-6 py-4"><span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-sm"><?php echo $booking_count; ?> lần</span></td></tr><?php endforeach; ?></tbody></table></div></div></div>

                <!-- CHỦ SÂN -->
                <?php elseif ($page === 'owners'): ?>
                <div class="fade-in"><h2 class="text-2xl font-bold mb-6"><i class="fas fa-user-tie mr-2 text-blue-600"></i>Dữ liệu chủ sân</h2><div class="bg-white rounded-lg shadow overflow-hidden"><div class="overflow-x-auto"><table class="w-full"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left">Họ tên</th><th class="px-6 py-3 text-left">Username</th><th class="px-6 py-3 text-left">SĐT</th><th class="px-6 py-3 text-left">Email</th><th class="px-6 py-3 text-left">Số sân</th><th class="px-6 py-3 text-left">Đặt sân</th><th class="px-6 py-3 text-left">Doanh thu</th><th class="px-6 py-3 text-left">Ngày đăng ký</th></tr></thead><tbody><?php foreach ($owners as $owner): ?><?php $stmt = $pdo->prepare("SELECT COUNT(*) FROM courts WHERE owner_id = ?"); $stmt->execute([$owner['user_id']]); $court_count = $stmt->fetchColumn(); $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings b JOIN courts c ON b.court_id = c.court_id WHERE c.owner_id = ?"); $stmt->execute([$owner['user_id']]); $booking_count = $stmt->fetchColumn(); $stmt = $pdo->prepare("SELECT COALESCE(SUM(b.total_price), 0) FROM bookings b JOIN courts c ON b.court_id = c.court_id WHERE c.owner_id = ? AND b.status IN ('confirmed','completed')"); $stmt->execute([$owner['user_id']]); $total_revenue = $stmt->fetchColumn(); ?><tr class="border-b hover:bg-gray-50"><td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($owner['full_name']); ?></td><td class="px-6 py-4"><?php echo htmlspecialchars($owner['username']); ?></td><td class="px-6 py-4"><?php echo htmlspecialchars($owner['phone_number']); ?></td><td class="px-6 py-4 text-sm"><?php echo htmlspecialchars($owner['email']); ?></td><td class="px-6 py-4"><span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-sm"><?php echo $court_count; ?> sân</span></td><td class="px-6 py-4"><?php echo $booking_count; ?> lần</td><td class="px-6 py-4 font-semibold text-green-600"><?php echo formatAmountK($total_revenue); ?></td><td class="px-6 py-4 text-sm text-gray-500"><?php echo date('d/m/Y', strtotime($owner['created_at'])); ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>

                <!-- ĐẶT LẶP LẠI -->
                <?php elseif ($page === 'recurring'): ?>
                <div class="fade-in">
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-2xl font-bold mb-4"><i class="fas fa-sync-alt mr-2 text-blue-600"></i>Quản lý đặt lặp lại</h2>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Khách hàng</th>
                                        <th class="px-4 py-3 text-left">Sân</th>
                                        <th class="px-4 py-3 text-left">Giờ đặt</th>
                                        <th class="px-4 py-3 text-left">Loại lặp</th>
                                        <th class="px-4 py-3 text-left">Thời gian</th>
                                        <th class="px-4 py-3 text-left">Trạng thái</th>
                                        <th class="px-4 py-3 text-left">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recurring_bookings as $recurring): ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            <div class="font-medium"><?php echo htmlspecialchars($recurring['full_name']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($recurring['phone_number']); ?></div>
                                        </td>
                                        <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($recurring['court_name']); ?></td>
                                        <td class="px-4 py-3"><?php echo date('H:i', strtotime($recurring['start_time'])); ?> - <?php echo date('H:i', strtotime($recurring['end_time'])); ?></td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-1 rounded text-xs <?php echo $recurring['recurring_type'] == 'weekly' ? 'bg-purple-100 text-purple-800' : 'bg-indigo-100 text-indigo-800'; ?>">
                                                <?php echo $recurring['recurring_type'] == 'weekly' ? 'Hàng tuần' : 'Hàng tháng'; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            <?php echo date('d/m/Y', strtotime($recurring['start_date'])); ?><br>
                                            <span class="text-gray-500">→ <?php echo $recurring['end_date'] ? date('d/m/Y', strtotime($recurring['end_date'])) : 'Không giới hạn'; ?></span>
                                        </td>
                                        <td class="px-4 py-3"><?php echo getStatusBadge($recurring['status']); ?></td>
                                        <td class="px-4 py-3">
                                            <button onclick="cancelRecurring(<?php echo $recurring['id']; ?>)" class="text-red-600 hover:text-red-800">
                                                <i class="fas fa-ban"></i> Hủy
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($recurring_bookings)): ?>
                                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">Chưa có đặt lặp nào</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- GIAO DỊCH -->
                <?php elseif ($page === 'transactions'): ?>
                <div class="fade-in"><h2 class="text-2xl font-bold mb-6"><i class="fas fa-wallet mr-2 text-blue-600"></i>Giao dịch nạp/rút</h2><div class="bg-white rounded-lg shadow overflow-hidden"><div class="overflow-x-auto"><table class="w-full"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left">Thời gian</th><th class="px-6 py-3 text-left">Người dùng</th><th class="px-6 py-3 text-left">Loại</th><th class="px-6 py-3 text-left">Số tiền</th><th class="px-6 py-3 text-left">Mô tả</th></tr></thead><tbody><?php foreach ($transactions as $tx): ?><tr class="border-b hover:bg-gray-50"><td class="px-6 py-4"><?php echo date('d/m/Y H:i', strtotime($tx['created_at'])); ?></td><td class="px-6 py-4"><?php echo htmlspecialchars($tx['username']); ?></td><td class="px-6 py-4"><span class="px-2 py-1 rounded text-xs <?php echo $tx['type'] === 'deposit' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>"><?php echo $tx['type'] === 'deposit' ? 'Nạp tiền' : 'Rút tiền'; ?></span></td><td class="px-6 py-4 font-semibold"><?php echo formatCurrency($tx['amount']); ?></td><td class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($tx['description']); ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>

                <!-- KHIẾU NẠI -->
                <?php elseif ($page === 'disputes'): ?>
                <div class="fade-in">
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-2xl font-bold mb-4"><i class="fas fa-gavel mr-2 text-blue-600"></i>Quản lý khiếu nại</h2>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left">ID</th>
                                        <th class="px-4 py-3 text-left">Người khiếu nại</th>
                                        <th class="px-4 py-3 text-left">Người bị khiếu nại</th>
                                        <th class="px-4 py-3 text-left">Sân</th>
                                        <th class="px-4 py-3 text-left">Lý do</th>
                                        <th class="px-4 py-3 text-left">Trạng thái</th>
                                        <th class="px-4 py-3 text-left">Ngày tạo</th>
                                        <th class="px-4 py-3 text-left">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($disputes as $dispute): ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="px-4 py-3">#<?php echo $dispute['dispute_id']; ?></td>
                                        <td class="px-4 py-3">
                                            <div class="font-medium"><?php echo htmlspecialchars($dispute['reporter_name']); ?></div>
                                            <div class="text-xs text-gray-500">@<?php echo htmlspecialchars($dispute['reporter_username']); ?></div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="font-medium"><?php echo htmlspecialchars($dispute['reported_name']); ?></div>
                                            <div class="text-xs text-gray-500">@<?php echo htmlspecialchars($dispute['reported_username']); ?></div>
                                        </td>
                                        <td class="px-4 py-3"><?php echo htmlspecialchars($dispute['court_name']); ?></td>
                                        <td class="px-4 py-3">
                                            <div class="text-sm font-medium"><?php echo htmlspecialchars($dispute['reason']); ?></div>
                                            <div class="text-xs text-gray-500 truncate max-w-xs"><?php echo htmlspecialchars(substr($dispute['description'], 0, 50)); ?></div>
                                        </td>
                                        <td class="px-4 py-3"><?php echo getStatusBadge($dispute['status']); ?></td>
                                        <td class="px-4 py-3"><?php echo date('d/m/Y', strtotime($dispute['created_at'])); ?></td>
                                        <td class="px-4 py-3">
                                            <a href="?page=dispute_detail&id=<?php echo $dispute['dispute_id']; ?>" class="text-blue-600 hover:text-blue-800">
                                                <i class="fas fa-eye"></i> Xử lý
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- CHI TIẾT KHIẾU NẠI -->
                <?php elseif ($page === 'dispute_detail' && isset($dispute_detail)): ?>
                <div class="fade-in">
                    <div class="mb-4">
                        <a href="?page=disputes" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-arrow-left"></i> Quay lại danh sách khiếu nại
                        </a>
                    </div>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Thông tin khiếu nại -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-lg font-bold mb-4 border-b pb-2">Thông tin khiếu nại</h3>
                            <div class="space-y-3">
                                <div><strong>Mã khiếu nại:</strong> #<?php echo $dispute_detail['dispute_id']; ?></div>
                                <div><strong>Trạng thái:</strong> <?php echo getStatusBadge($dispute_detail['status']); ?></div>
                                <div><strong>Ngày tạo:</strong> <?php echo date('d/m/Y H:i', strtotime($dispute_detail['created_at'])); ?></div>
                                <div><strong>Lý do:</strong> <?php echo htmlspecialchars($dispute_detail['reason']); ?></div>
                                <div><strong>Mô tả chi tiết:</strong></div>
                                <div class="bg-gray-50 p-3 rounded"><?php echo nl2br(htmlspecialchars($dispute_detail['description'])); ?></div>
                                <?php if ($dispute_detail['evidence']): ?>
                                <div><strong>Bằng chứng:</strong> <a href="<?php echo htmlspecialchars($dispute_detail['evidence']); ?>" target="_blank" class="text-blue-600">Xem bằng chứng</a></div>
                                <?php endif; ?>
                                <?php if ($dispute_detail['resolution']): ?>
                                <div><strong>Giải pháp:</strong></div>
                                <div class="bg-green-50 p-3 rounded"><?php echo nl2br(htmlspecialchars($dispute_detail['resolution'])); ?></div>
                                <div><strong>Người xử lý:</strong> <?php echo htmlspecialchars($dispute_detail['resolver_name'] ?? 'N/A'); ?></div>
                                <div><strong>Ngày xử lý:</strong> <?php echo date('d/m/Y H:i', strtotime($dispute_detail['resolved_at'])); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Thông tin booking -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-lg font-bold mb-4 border-b pb-2">Thông tin đặt sân</h3>
                            <div class="space-y-3">
                                <div><strong>Sân:</strong> <?php echo htmlspecialchars($dispute_detail['court_name']); ?></div>
                                <div><strong>Thời gian:</strong> <?php echo date('d/m/Y H:i', strtotime($dispute_detail['start_time'])); ?> - <?php echo date('H:i', strtotime($dispute_detail['end_time'])); ?></div>
                                <div><strong>Tổng tiền:</strong> <span class="font-bold text-green-600"><?php echo formatCurrency($dispute_detail['total_price']); ?></span></div>
                                <div><strong>Trạng thái booking:</strong> <?php echo getStatusBadge($dispute_detail['status']); ?></div>
                            </div>
                        </div>
                        
                        <!-- Thông tin người khiếu nại -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-lg font-bold mb-4 border-b pb-2">Người khiếu nại</h3>
                            <div class="space-y-2">
                                <div><strong>Họ tên:</strong> <?php echo htmlspecialchars($dispute_detail['reporter_name']); ?></div>
                                <div><strong>Username:</strong> @<?php echo htmlspecialchars($dispute_detail['reporter_username']); ?></div>
                                <div><strong>SĐT:</strong> <?php echo htmlspecialchars($dispute_detail['reporter_phone']); ?></div>
                                <div><strong>Email:</strong> <?php echo htmlspecialchars($dispute_detail['reporter_email']); ?></div>
                            </div>
                        </div>
                        
                        <!-- Thông tin người bị khiếu nại -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-lg font-bold mb-4 border-b pb-2">Người bị khiếu nại</h3>
                            <div class="space-y-2">
                                <div><strong>Họ tên:</strong> <?php echo htmlspecialchars($dispute_detail['reported_name']); ?></div>
                                <div><strong>Username:</strong> @<?php echo htmlspecialchars($dispute_detail['reported_username']); ?></div>
                                <div><strong>SĐT:</strong> <?php echo htmlspecialchars($dispute_detail['reported_phone']); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form xử lý khiếu nại -->
                    <?php if ($dispute_detail['status'] == 'pending' || $dispute_detail['status'] == 'investigating'): ?>
                    <div class="bg-white rounded-lg shadow p-6 mt-6">
                        <h3 class="text-lg font-bold mb-4 border-b pb-2">Xử lý khiếu nại</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="resolve_dispute">
                            <input type="hidden" name="dispute_id" value="<?php echo $dispute_detail['dispute_id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div class="form-group">
                                <label>Quyết định xử lý</label>
                                <select name="resolution_action" class="w-full border rounded-lg px-3 py-2" required>
                                    <option value="resolve">Đồng ý với người khiếu nại</option>
                                    <option value="reject">Bác bỏ khiếu nại</option>
                                    <option value="refund">Đồng ý và hoàn tiền</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Nội dung giải quyết</label>
                                <textarea name="resolution" rows="4" class="w-full border rounded-lg px-3 py-2" required placeholder="Nhập quyết định và lý do..."></textarea>
                            </div>
                            
                            <div class="flex gap-2">
                                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                                    <i class="fas fa-check"></i> Xác nhận xử lý
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- SUPPORT -->
                <?php elseif ($page === 'support'): ?>
                <div class="fade-in"><h2 class="text-2xl font-bold mb-6"><i class="fas fa-headset mr-2 text-blue-600"></i>Hỗ trợ khách hàng</h2><div class="bg-white rounded-lg shadow overflow-hidden"><div class="overflow-x-auto"><table class="w-full"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left">ID</th><th class="px-6 py-3 text-left">Khách hàng</th><th class="px-6 py-3 text-left">Chủ sân</th><th class="px-6 py-3 text-left">Tiêu đề</th><th class="px-6 py-3 text-left">Trạng thái</th><th class="px-6 py-3 text-left">Hành động</th></tr></thead><tbody><?php foreach ($supportTickets as $ticket): ?><tr class="border-b hover:bg-gray-50"><td class="px-6 py-4 font-mono">#<?php echo $ticket['ticket_id']; ?></td><td class="px-6 py-4"><?php echo htmlspecialchars($ticket['customer_name'] ?: '-'); ?></td><td class="px-6 py-4"><?php echo htmlspecialchars($ticket['owner_name'] ?: '-'); ?></td><td class="px-6 py-4"><?php echo htmlspecialchars($ticket['subject']); ?></td><td class="px-6 py-4"><?php echo getStatusBadge($ticket['status']); ?></td><td class="px-6 py-4"><?php if ($ticket['status'] !== 'resolved'): ?><form method="POST" class="inline"><input type="hidden" name="action" value="resolve_ticket"><input type="hidden" name="ticket_id" value="<?php echo $ticket['ticket_id']; ?>"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><button type="submit" class="px-3 py-1 rounded-lg bg-green-600 text-white hover:bg-green-700 text-xs transition-colors"><i class="fas fa-check mr-1"></i>Giải quyết</button></form><?php else: ?><span class="text-gray-400 text-sm">Đã xử lý</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>

                <!-- BÁO CÁO DOANH THU -->
                <?php elseif ($page === 'reports'): ?>
                <div class="fade-in"><h2 class="text-2xl font-bold mb-6"><i class="fas fa-chart-bar mr-2 text-blue-600"></i>Báo cáo doanh thu</h2><div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8"><div class="bg-white rounded-lg shadow p-6"><div class="flex items-center"><div class="bg-green-500 p-3 rounded-lg"><i class="fas fa-money-bill-wave text-white text-xl"></i></div><div class="ml-4"><p class="text-gray-600">Tổng doanh thu</p><p class="text-2xl font-bold"><?php echo formatAmountK($stats['total_revenue'] ?? 0); ?></p></div></div></div><div class="bg-white rounded-lg shadow p-6"><div class="flex items-center"><div class="bg-blue-500 p-3 rounded-lg"><i class="fas fa-calendar-month text-white text-xl"></i></div><div class="ml-4"><p class="text-gray-600">Doanh thu tháng này</p><p class="text-2xl font-bold"><?php echo formatAmountK($stats['monthly_revenue'] ?? 0); ?></p></div></div></div><div class="bg-white rounded-lg shadow p-6"><div class="flex items-center"><div class="bg-purple-500 p-3 rounded-lg"><i class="fas fa-chart-line text-white text-xl"></i></div><div class="ml-4"><p class="text-gray-600">Số sân đang hoạt động</p><p class="text-2xl font-bold"><?php echo count($courts); ?></p></div></div></div></div><div class="grid grid-cols-1 lg:grid-cols-2 gap-6"><div class="bg-white rounded-lg shadow p-6"><h3 class="text-lg font-semibold mb-4"><i class="fas fa-chart-pie mr-2"></i>Doanh thu theo sân</h3><div class="space-y-4"><?php foreach ($court_revenue as $court): ?><div class="flex justify-between items-center p-2 hover:bg-gray-50 rounded"><div><p class="font-medium"><?php echo htmlspecialchars($court['court_name']); ?></p><p class="text-sm text-gray-600"><?php echo $court['booking_count']; ?> đặt sân</p></div><p class="font-bold text-green-600"><?php echo formatAmountK($court['revenue']); ?></p></div><?php endforeach; ?></div></div><div class="bg-white rounded-lg shadow p-6"><h3 class="text-lg font-semibold mb-4"><i class="fas fa-chart-line mr-2"></i>Doanh thu theo tháng</h3><canvas id="revenueChart" height="300"></canvas></div></div></div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- MODAL THÊM/SỬA SÂN -->
    <div id="courtModal" class="modal">
        <div class="modal-content">
            <form id="courtForm" method="POST">
                <input type="hidden" name="action" id="formAction" value="add_court">
                <input type="hidden" name="court_id" id="court_id" value="0">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="modal-header"><h3 class="text-lg font-semibold" id="modalTitle">Thêm sân mới</h3><span class="close-modal" onclick="closeModal()">&times;</span></div>
                <div class="modal-body">
                    <div class="form-group"><label>Tên sân <span class="text-red-500">*</span></label><input type="text" name="court_name" id="court_name" required placeholder="VD: Sân cầu lông A"></div>
                    <div class="form-group"><label>Loại sân</label><select name="court_type" id="court_type"><option value="">Chọn loại sân</option><option value="Sân trong nhà">Sân trong nhà</option><option value="Sân ngoài trời">Sân ngoài trời</option><option value="Sân cao cấp">Sân cao cấp</option><option value="Sân thường">Sân thường</option></select></div>
                    <div class="form-group"><label>Giá mỗi giờ (VNĐ) <span class="text-red-500">*</span></label><input type="number" name="price_per_hour" id="price_per_hour" required placeholder="VD: 140000"></div>
                    <div class="form-group"><label>Chủ sân</label><select name="owner_id" id="owner_id"><option value="">Hệ thống (Không có chủ)</option><?php foreach ($owner_list as $owner): ?><option value="<?php echo $owner['user_id']; ?>"><?php echo htmlspecialchars($owner['full_name'] ?: $owner['username']); ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Mô tả</label><textarea name="description" id="description" rows="3" placeholder="Mô tả về sân..."></textarea></div>
                    <div class="form-group"><label class="flex items-center"><input type="checkbox" name="is_active" id="is_active" value="1" checked class="mr-2"><span>Hoạt động</span></label></div>
                </div>
                <div class="modal-footer"><button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-300 rounded-lg hover:bg-gray-400">Hủy</button><button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Lưu lại</button></div>
            </form>
        </div>
    </div>

    <!-- MODAL UPLOAD ẢNH -->
    <div id="uploadImageModal" class="modal">
        <div class="modal-content">
            <form id="uploadImageForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_court_image">
                <input type="hidden" name="court_id" id="upload_court_id" value="0">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="modal-header"><h3 class="text-lg font-semibold">Upload ảnh sân</h3><span class="close-modal" onclick="closeUploadModal()">&times;</span></div>
                <div class="modal-body">
                    <div class="form-group"><label>Chọn ảnh (JPEG, PNG, GIF, WebP - tối đa 5MB)</label><input type="file" name="court_image" id="court_image" accept="image/*" required onchange="previewImage(this)"><div id="imagePreview" class="image-preview"></div></div>
                    <div class="text-sm text-gray-500 mt-2"><i class="fas fa-info-circle"></i> Nên dùng ảnh kích thước 800x600px để hiển thị tốt nhất</div>
                </div>
                <div class="modal-footer"><button type="button" onclick="closeUploadModal()" class="px-4 py-2 bg-gray-300 rounded-lg hover:bg-gray-400">Hủy</button><button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">Upload</button></div>
            </form>
        </div>
    </div>

    <!-- MODAL CHI TIẾT BOOKING -->
    <div id="bookingDetailModal" class="modal">
        <div class="modal-content max-w-2xl">
            <div class="modal-header">
                <h3 class="text-lg font-semibold">Chi tiết đặt sân</h3>
                <span class="close-modal" onclick="closeBookingModal()">&times;</span>
            </div>
            <div class="modal-body" id="bookingDetailContent">
                <!-- Nội dung sẽ load bằng AJAX -->
            </div>
        </div>
    </div>

    <!-- MODAL XÁC NHẬN XÓA -->
    <div id="deleteConfirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3 class="text-lg font-semibold text-red-600">Xác nhận xóa</h3><span class="close-modal" onclick="closeDeleteModal()">&times;</span></div>
            <div class="modal-body"><p>Bạn có chắc chắn muốn xóa sân <strong id="deleteCourtName"></strong>?</p><p class="text-sm text-red-500 mt-2">⚠️ Hành động này không thể hoàn tác nếu sân chưa có lịch đặt.</p></div>
            <div class="modal-footer"><button type="button" onclick="closeDeleteModal()" class="px-4 py-2 bg-gray-300 rounded-lg hover:bg-gray-400">Hủy</button><form id="deleteForm" method="POST" class="inline"><input type="hidden" name="action" value="delete_court"><input type="hidden" name="court_id" id="delete_court_id" value="0"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Xóa</button></form></div>
        </div>
    </div>

    <!-- JAVASCRIPT -->
    <script>
        // Calendar functions
        function filterCourt() {
            const courtId = document.getElementById('courtFilter').value;
            const weekStart = document.getElementById('weekStart').value;
            window.location.href = `?page=calendar&court_id=${courtId}&start=${weekStart}`;
        }

        function changeWeek() {
            const weekStart = document.getElementById('weekStart').value;
            const courtId = document.getElementById('courtFilter').value;
            window.location.href = `?page=calendar&court_id=${courtId}&start=${weekStart}`;
        }

        function previousWeek() {
            const currentStart = document.getElementById('weekStart').value;
            const prevStart = new Date(currentStart);
            prevStart.setDate(prevStart.getDate() - 7);
            document.getElementById('weekStart').value = prevStart.toISOString().split('T')[0];
            changeWeek();
        }

        function nextWeek() {
            const currentStart = document.getElementById('weekStart').value;
            const nextStart = new Date(currentStart);
            nextStart.setDate(nextStart.getDate() + 7);
            document.getElementById('weekStart').value = nextStart.toISOString().split('T')[0];
            changeWeek();
        }

        // Booking functions
        function viewBookingDetail(bookingId) {
            fetch(`booking_detail.php?id=${bookingId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('bookingDetailContent').innerHTML = `
                            <div class="space-y-3">
                                <div class="grid grid-cols-2 gap-4">
                                    <div><strong>Sân:</strong> ${data.booking.court_name}</div>
                                    <div><strong>Người đặt:</strong> ${data.booking.full_name || data.booking.username}</div>
                                    <div><strong>Thời gian:</strong> ${data.booking.start_time} - ${data.booking.end_time}</div>
                                    <div><strong>Tổng tiền:</strong> ${new Intl.NumberFormat('vi-VN').format(data.booking.total_price)} VNĐ</div>
                                    <div><strong>Trạng thái:</strong> ${data.booking.status}</div>
                                    <div><strong>Ngày đặt:</strong> ${data.booking.created_at}</div>
                                </div>
                                ${data.booking.is_flagged ? `
                                <div class="bg-red-50 p-3 rounded border border-red-200">
                                    <strong class="text-red-600"><i class="fas fa-exclamation-triangle"></i> Booking bất thường</strong>
                                    <p class="text-sm mt-1">Lý do: ${data.booking.flag_reason}</p>
                                    ${data.booking.admin_note ? `<p class="text-sm mt-1">Ghi chú admin: ${data.booking.admin_note}</p>` : ''}
                                </div>
                                ` : ''}
                                <div class="flex gap-2 mt-4">
                                    <button onclick="showFlagBookingForm(${data.booking.booking_id})" class="px-3 py-1 bg-yellow-500 text-white rounded hover:bg-yellow-600">
                                        <i class="fas fa-flag"></i> Đánh dấu bất thường
                                    </button>
                                    <button onclick="cancelBooking(${data.booking.booking_id})" class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600">
                                        <i class="fas fa-times"></i> Hủy booking
                                    </button>
                                </div>
                            </div>
                        `;
                        document.getElementById('bookingDetailModal').classList.add('active');
                    }
                });
        }

        function confirmBooking(bookingId) {
            if (confirm('Xác nhận đặt sân này?')) {
                fetch('booking_action.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=confirm_booking&booking_id=${bookingId}&csrf_token=${document.querySelector('meta[name="csrf-token"]').content}`
                }).then(() => location.reload());
            }
        }

        function cancelBooking(bookingId) {
            const reason = prompt('Nhập lý do hủy đặt sân:');
            if (reason) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="cancel_booking">
                    <input type="hidden" name="booking_id" value="${bookingId}">
                    <input type="hidden" name="cancel_reason" value="${reason}">
                    <input type="hidden" name="csrf_token" value="${document.querySelector('meta[name="csrf-token"]').content}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function showFlagBookingForm(bookingId) {
            const reason = prompt('Nhập lý do đánh dấu booking này là bất thường:');
            if (reason) {
                const adminNote = prompt('Ghi chú thêm (không bắt buộc):');
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="flag_booking">
                    <input type="hidden" name="booking_id" value="${bookingId}">
                    <input type="hidden" name="flag_reason" value="${reason}">
                    <input type="hidden" name="admin_note" value="${adminNote || ''}">
                    <input type="hidden" name="csrf_token" value="${document.querySelector('meta[name="csrf-token"]').content}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function cancelRecurring(recurringId) {
            if (confirm('Hủy đặt lặp này sẽ hủy tất cả các booking trong tương lai. Tiếp tục?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="cancel_recurring">
                    <input type="hidden" name="recurring_id" value="${recurringId}">
                    <input type="hidden" name="csrf_token" value="${document.querySelector('meta[name="csrf-token"]').content}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Court management functions
        function showAddCourtModal() {
            document.getElementById('modalTitle').innerText = 'Thêm sân mới';
            document.getElementById('formAction').value = 'add_court';
            document.getElementById('court_id').value = '0';
            document.getElementById('courtForm').reset();
            document.getElementById('is_active').checked = true;
            document.getElementById('courtModal').classList.add('active');
        }
        
        function editCourt(courtId) {
            fetch(`get_court.php?id=${courtId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('modalTitle').innerText = 'Chỉnh sửa sân';
                        document.getElementById('formAction').value = 'update_court';
                        document.getElementById('court_id').value = data.court.court_id;
                        document.getElementById('court_name').value = data.court.court_name;
                        document.getElementById('court_type').value = data.court.court_type || '';
                        document.getElementById('price_per_hour').value = data.court.price_per_hour;
                        document.getElementById('owner_id').value = data.court.owner_id || '';
                        document.getElementById('description').value = data.court.description || '';
                        document.getElementById('is_active').checked = data.court.is_active == 1;
                        document.getElementById('courtModal').classList.add('active');
                    } else { alert('Không thể tải thông tin sân'); }
                })
                .catch(error => { console.error('Error:', error); alert('Có lỗi xảy ra'); });
        }
        
        function uploadCourtImage(courtId) {
            document.getElementById('upload_court_id').value = courtId;
            document.getElementById('uploadImageForm').reset();
            document.getElementById('imagePreview').innerHTML = '';
            document.getElementById('uploadImageModal').classList.add('active');
        }
        
        function deleteCourt(courtId, courtName) {
            document.getElementById('delete_court_id').value = courtId;
            document.getElementById('deleteCourtName').innerText = courtName;
            document.getElementById('deleteConfirmModal').classList.add('active');
        }
        
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) { const img = document.createElement('img'); img.src = e.target.result; preview.appendChild(img); }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function closeModal() { document.getElementById('courtModal').classList.remove('active'); }
        function closeUploadModal() { document.getElementById('uploadImageModal').classList.remove('active'); }
        function closeDeleteModal() { document.getElementById('deleteConfirmModal').classList.remove('active'); }
        function closeBookingModal() { document.getElementById('bookingDetailModal').classList.remove('active'); }
        
        window.onclick = function(event) { if (event.target.classList.contains('modal')) event.target.classList.remove('active'); }
        
        document.getElementById('courtForm').addEventListener('submit', function(e) {
            if (!document.getElementById('court_name').value.trim()) { e.preventDefault(); alert('Vui lòng nhập tên sân'); return false; }
            if (document.getElementById('price_per_hour').value <= 0) { e.preventDefault(); alert('Vui lòng nhập giá hợp lệ'); return false; }
        });
        
        document.getElementById('uploadImageForm').addEventListener('submit', function(e) {
            const file = document.getElementById('court_image').files[0];
            if (!file) { e.preventDefault(); alert('Vui lòng chọn ảnh'); return false; }
            if (file.size > 5*1024*1024) { e.preventDefault(); alert('File quá lớn (tối đa 5MB)'); return false; }
        });
        
        <?php if ($page === 'reports' && !empty($monthly_revenue)): ?>
        const ctx = document.getElementById('revenueChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: { labels: [<?php echo implode(',', array_map(function($m) { return '"' . date('m/Y', strtotime($m['month'] . '-01')) . '"'; }, array_reverse($monthly_revenue))); ?>], datasets: [{ label: 'Doanh thu (VNĐ)', data: [<?php echo implode(',', array_map(function($m) { return $m['revenue']; }, array_reverse($monthly_revenue))); ?>], borderColor: 'rgb(59, 130, 246)', backgroundColor: 'rgba(59, 130, 246, 0.1)', tension: 0.4, fill: true }] },
            options: { responsive: true, maintainAspectRatio: true, plugins: { tooltip: { callbacks: { label: function(context) { return context.raw.toLocaleString() + ' VNĐ'; } } } }, scales: { y: { ticks: { callback: function(value) { return (value / 1000).toFixed(0) + 'K'; } } } } }
        });
        <?php endif; ?>
    </script>
</body>
</html>