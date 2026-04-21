<?php
require_once 'config/database.php';
session_start();

header('Content-Type: application/json');

$court_id = isset($_GET['court_id']) ? (int)$_GET['court_id'] : 0;
$date = $_GET['date'] ?? date('Y-m-d');

if (!$court_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid court ID']);
    exit;
}

try {
    // Get booked slots for the date
    $stmt = $pdo->prepare("
        SELECT start_time, end_time FROM bookings 
        WHERE court_id = ? AND DATE(start_time) = ? AND status != 'cancelled'
        ORDER BY start_time
    ");
    $stmt->execute([$court_id, $date]);
    $bookedSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $bookedTimes = [];
    foreach ($bookedSlots as $slot) {
        $startHour = date('H:i', strtotime($slot['start_time']));
        $bookedTimes[$startHour] = true;
    }
    
    // Generate available slots from 6:00 to 22:00
    $slots = [];
    for ($hour = 6; $hour < 22; $hour++) {
        $start = sprintf("%02d:00", $hour);
        $end = sprintf("%02d:00", $hour + 1);
        $slots[] = [
            'start' => $start,
            'end' => $end,
            'booked' => isset($bookedTimes[$start])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'slots' => $slots,
        'date' => $date
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>