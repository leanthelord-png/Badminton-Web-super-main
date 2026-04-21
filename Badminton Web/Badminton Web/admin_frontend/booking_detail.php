<?php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$booking_id = intval($_GET['id'] ?? 0);

try {
    $stmt = $pdo->prepare("
        SELECT b.*, c.court_name, u.full_name, u.username, u.phone_number
        FROM bookings b
        JOIN courts c ON b.court_id = c.court_id
        JOIN users u ON b.user_id = u.user_id
        WHERE b.booking_id = ?
    ");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($booking) {
        echo json_encode([
            'success' => true,
            'booking' => [
                'booking_id' => $booking['booking_id'],
                'court_name' => $booking['court_name'],
                'full_name' => $booking['full_name'],
                'username' => $booking['username'],
                'phone_number' => $booking['phone_number'],
                'start_time' => date('d/m/Y H:i', strtotime($booking['start_time'])),
                'end_time' => date('d/m/Y H:i', strtotime($booking['end_time'])),
                'total_price' => $booking['total_price'],
                'status' => $booking['status'],
                'created_at' => date('d/m/Y H:i', strtotime($booking['created_at'])),
                'is_flagged' => $booking['is_flagged'],
                'flag_reason' => $booking['flag_reason'],
                'admin_note' => $booking['admin_note']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy booking']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>