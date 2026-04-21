<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Xử lý preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Chỉ nhận POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'response' => 'Method not allowed']);
    exit();
}

// ========== SỬA ĐƯỜNG DẪN REQUIRE ==========
// Đi từ api/chatbot/ lên 2 cấp để tới thư mục gốc
require_once __DIR__ . '/../../Badminton Web/config/database.php';

// Khởi động session để lấy user_id nếu cần
session_start();

// Lấy input
$input = json_decode(file_get_contents('php://input'), true);

// Ưu tiên lấy user_id từ session nếu có, sau đó mới dùng từ request
$userId = $input['user_id'] ?? ($_SESSION['user_id'] ?? 0);
$message = trim($input['message'] ?? '');

// Kiểm tra đăng nhập
if (!$userId || !$message) {
    echo json_encode(['success' => false, 'response' => 'Thiếu thông tin hoặc chưa đăng nhập']);
    exit();
}

// ========== HÀM XỬ LÝ INTENT ==========
function detectIntent($message) {
    $patterns = [
        'greeting' => '/^(chào|hi|hello|helo|chào bạn|chào bot|chào cậu|hey)$/i',
        'find_court' => '/(tìm|kiếm|còn|trống).*(sân|sân cầu)|(sân|sân cầu).*(trống|còn)/i',
        'check_booking' => '/(kiểm tra|xem|lịch sử|tra cứu|xem lại).*(lịch|đặt|sân|đơn)/i',
        'price' => '/(giá|bao nhiêu|tiền|chi phí|giá sân|giá cả)/i',
        'guide' => '/(hướng dẫn|cách|chỉ|bước|làm sao|hướng dẫn đặt)/i',
        'suggestion' => '/(gợi ý|đề xuất|recommend|sân nào|chọn sân|sân phù hợp)/i',
        'help' => '/(help|menu|chức năng|có thể làm gì|\?|giúp với)/i'
    ];
    
    $lowerMsg = mb_strtolower($message, 'UTF-8');
    foreach ($patterns as $intent => $pattern) {
        if (preg_match($pattern, $lowerMsg)) {
            return $intent;
        }
    }
    return 'fallback';
}

function extractTime($message) {
    if (preg_match('/(\d{1,2})\s*(h|giờ)/i', $message, $match)) {
        $hour = intval($match[1]);
        return sprintf('%02d:00', $hour);
    }
    if (preg_match('/(\d{1,2}):(\d{2})/', $message, $match)) {
        return sprintf('%02d:%02d', $match[1], $match[2]);
    }
    return null;
}

function extractDate($message) {
    if (strpos($message, 'mai') !== false || strpos($message, 'ngày mai') !== false) {
        return date('Y-m-d', strtotime('+1 day'));
    }
    if (strpos($message, 'hôm nay') !== false || strpos($message, 'nay') !== false) {
        return date('Y-m-d');
    }
    return date('Y-m-d');
}

// ========== XỬ LÝ INTENT ==========
$intent = detectIntent($message);
$response = '';

try {
    global $pdo;
    
    // Kiểm tra kết nối database
    if (!$pdo) {
        throw new Exception('Không thể kết nối database');
    }
    
    switch ($intent) {
        case 'greeting':
            $response = "🏸 **Chào bạn!** Mình là trợ lý AI của BadmintonPro.\n\nMình có thể giúp bạn:\n• 🔍 Tìm sân trống (vd: \"tìm sân 19h tối mai\")\n• 📋 Xem lịch đặt của bạn\n• 💰 Xem giá các sân\n• 🏸 Gợi ý sân theo thói quen\n\nBạn cần mình giúp gì ạ?";
            break;
            
        case 'find_court':
            $time = extractTime($message) ?? '19:00';
            $date = extractDate($message);
            
            $startDatetime = $date . ' ' . $time;
            $endDatetime = date('Y-m-d H:i:s', strtotime($startDatetime . ' +1 hour'));
            
            $stmt = $pdo->prepare("
                SELECT c.* FROM courts c
                WHERE c.is_active = 1
                AND NOT EXISTS (
                    SELECT 1 FROM bookings b
                    WHERE b.court_id = c.court_id
                    AND b.status IN ('pending', 'confirmed')
                    AND b.start_time < ?
                    AND b.end_time > ?
                )
                LIMIT 5
            ");
            $stmt->execute([$endDatetime, $startDatetime]);
            $courts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($courts) === 0) {
                $response = "😢 Rất tiếc, không có sân trống vào khung giờ bạn muốn.\n\nBạn muốn xem khung giờ khác không?";
            } else {
                $response = "🏸 **Sân còn trống lúc " . date('H:i', strtotime($time)) . " ngày " . date('d/m/Y', strtotime($date)) . ":**\n\n";
                foreach ($courts as $idx => $court) {
                    $response .= ($idx + 1) . ". **" . htmlspecialchars($court['court_name']) . "**\n";
                    $response .= "   💰 " . number_format($court['price_per_hour'], 0, ',', '.') . " ₫/giờ\n\n";
                }
                $response .= "👉 Vào mục Đặt Sân để đặt nhé!";
            }
            break;
            
        case 'check_booking':
            $stmt = $pdo->prepare("
                SELECT b.*, c.court_name 
                FROM bookings b
                JOIN courts c ON b.court_id = c.court_id
                WHERE b.user_id = ?
                ORDER BY b.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$userId]);
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($bookings) === 0) {
                $response = "📋 Bạn chưa có lịch đặt sân nào.\n\n👉 Hãy đặt sân ngay để trải nghiệm nhé!";
            } else {
                $response = "📋 **5 Lịch đặt gần đây của bạn:**\n\n";
                foreach ($bookings as $b) {
                    $status = $b['status'] === 'confirmed' ? '✅ Đã xác nhận' : ($b['status'] === 'pending' ? '⏳ Chờ xác nhận' : '❌ Đã hủy');
                    $response .= "🏸 **" . htmlspecialchars($b['court_name']) . "**\n";
                    $response .= "   📅 " . date('d/m/Y H:i', strtotime($b['start_time'])) . " - " . date('H:i', strtotime($b['end_time'])) . "\n";
                    $response .= "   💰 " . number_format($b['total_price'], 0, ',', '.') . " ₫ | $status\n\n";
                }
            }
            break;
            
        case 'price':
            $stmt = $pdo->query("SELECT court_name, price_per_hour FROM courts WHERE is_active = 1 LIMIT 5");
            $courts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response = "💰 **Bảng giá các sân:**\n\n";
            foreach ($courts as $court) {
                $response .= "• " . htmlspecialchars($court['court_name']) . ": **" . number_format($court['price_per_hour'], 0, ',', '.') . " ₫/giờ**\n";
            }
            break;
            
        case 'suggestion':
            $stmt = $pdo->prepare("
                SELECT c.court_id, c.court_name, c.price_per_hour, COUNT(b.booking_id) as so_lan
                FROM bookings b
                JOIN courts c ON b.court_id = c.court_id
                WHERE b.user_id = ? AND b.status = 'confirmed'
                GROUP BY c.court_id
                ORDER BY so_lan DESC
                LIMIT 3
            ");
            $stmt->execute([$userId]);
            $suggestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($suggestions) === 0) {
                $response = "🏸 Bạn chưa đặt sân lần nào.\n\n👉 Hãy thử tìm sân trống và đặt ngay hôm nay nhé!";
            } else {
                $response = "📊 **Gợi ý sân dựa trên thói quen của bạn:**\n\n";
                foreach ($suggestions as $s) {
                    $response .= "🏸 **" . htmlspecialchars($s['court_name']) . "**\n";
                    $response .= "   💰 " . number_format($s['price_per_hour'], 0, ',', '.') . " ₫/giờ\n";
                    $response .= "   📊 Đã đặt " . $s['so_lan'] . " lần\n\n";
                }
            }
            break;
            
        case 'guide':
        case 'help':
            $response = "📖 **Hướng dẫn sử dụng BadmintonPro**\n\n**1. Đặt sân:**\n• Đăng nhập tài khoản\n• Chọn sân → Chọn ngày giờ → Xác nhận\n\n**2. Xem lịch đặt:**\n• Vào mục Lịch sử trong trang chủ\n\n**3. Nạp tiền:**\n• Click vào tên người dùng → Nạp tiền\n\n💡 Bạn cần hướng dẫn chi tiết phần nào không?";
            break;
            
        default:
            $response = "🤔 Mình chưa hiểu ý bạn lắm.\n\nBạn có thể thử hỏi:\n• \"Tìm sân trống 19h tối mai\"\n• \"Xem lịch đặt của tôi\"\n• \"Giá sân bao nhiêu\"\n• \"Gợi ý sân\"\n\nHoặc gõ **help** để xem hướng dẫn!";
            break;
    }
    
    // Lưu log chat (tạo bảng chat_logs nếu chưa có)
    try {
        // Kiểm tra bảng có tồn tại không
        $stmt = $pdo->query("SHOW TABLES LIKE 'chat_logs'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("INSERT INTO chat_logs (user_id, message, intent, response, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$userId, $message, $intent, $response]);
        }
    } catch (Exception $e) {
        // Silent fail - không ảnh hưởng chat
    }
    
    echo json_encode(['success' => true, 'response' => $response]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'response' => 'Lỗi server: ' . $e->getMessage()]);
}
?>