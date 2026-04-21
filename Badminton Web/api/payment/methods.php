<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../Badminton Web/config/database.php';

try {
    $stmt = $pdo->prepare("
        SELECT method_id, method_name, method_code, icon_class, display_order
        FROM payment_methods
        WHERE is_active = 1
        ORDER BY display_order ASC
    ");
    $stmt->execute();
    $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'methods' => $methods
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Không lấy được danh sách phương thức thanh toán',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}