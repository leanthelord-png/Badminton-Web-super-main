<?php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$court_id = intval($_GET['id'] ?? 0);

if ($court_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid court ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM courts WHERE court_id = ?");
    $stmt->execute([$court_id]);
    $court = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($court) {
        echo json_encode(['success' => true, 'court' => $court]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Court not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>