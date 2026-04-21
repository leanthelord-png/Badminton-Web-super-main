<?php
require_once 'config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'owner') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$court_id = $_GET['id'] ?? 0;
$owner_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM courts WHERE court_id = ? AND owner_id = ?");
$stmt->execute([$court_id, $owner_id]);
$court = $stmt->fetch(PDO::FETCH_ASSOC);

if ($court) {
    echo json_encode(['success' => true, 'court' => $court]);
} else {
    echo json_encode(['success' => false, 'message' => 'Court not found']);
}
?>