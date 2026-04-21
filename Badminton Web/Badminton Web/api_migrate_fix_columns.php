<?php
require_once 'config/database.php';
header('Content-Type: application/json; charset=utf-8');

function addColumnIfMissing(PDO $pdo, string $table, string $column, string $definition): void {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

try {
    addColumnIfMissing($pdo, 'users', 'user_balance', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
    addColumnIfMissing($pdo, 'users', 'profile_image_url', 'TEXT NULL');
    addColumnIfMissing($pdo, 'courts', 'address', 'TEXT NULL');
    addColumnIfMissing($pdo, 'courts', 'opening_hours', 'JSON NULL');
    addColumnIfMissing($pdo, 'courts', 'phone_number', 'VARCHAR(20) NULL');
    addColumnIfMissing($pdo, 'courts', 'facilities', 'JSON NULL');
    addColumnIfMissing($pdo, 'courts', 'court_image', 'TEXT NULL');
    addColumnIfMissing($pdo, 'courts', 'owner_id', 'INT NULL');
    addColumnIfMissing($pdo, 'bookings', 'time_slot_id', 'INT NULL');

    echo json_encode(['success' => true, 'message' => 'Đã bổ sung các cột cần thiết cho MySQL'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
