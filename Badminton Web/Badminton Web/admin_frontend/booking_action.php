<?php
require_once '../config/database.php';
session_start();

if (!isset($_SESSION['admin_user_id'])) {
    http_response_code(401);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    // Nếu không phải JSON, xử lý form
    $action = $_POST['action'] ?? '';
    $booking_id = intval($_POST['booking_id'] ?? 0);
    $csrf_token = $_POST['csrf_token'] ?? '';
} else {
    $action = $input['action'] ?? '';
    $booking_id = intval($input['booking_id'] ?? 0);
    $csrf_token = $input['csrf_token'] ?? '';
}

// CSRF check
if ($csrf_token !== $_SESSION['csrf_token']) {
    http_response_code(403);
    exit;
}

try {
    switch ($action) {
        case 'confirm_booking':
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE booking_id = ?");
            $stmt->execute([$booking_id]);
            break;
            
        case 'flag_booking':
            $reason = $input['reason'] ?? $_POST['reason'] ?? '';
            $stmt = $pdo->prepare("UPDATE bookings SET is_flagged = 1, flag_reason = ? WHERE booking_id = ?");
            $stmt->execute([$reason, $booking_id]);
            break;
    }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>