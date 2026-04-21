<?php
require_once 'config/database.php';

$stmt = $pdo->query('SELECT court_id, court_name, court_type, price_per_hour, is_active FROM courts ORDER BY court_id');
$rows = $stmt->fetchAll();

header('Content-Type: text/plain; charset=utf-8');
if (!$rows) {
    echo "Không có dữ liệu sân.
";
    exit;
}
foreach ($rows as $row) {
    echo sprintf("[%d] %s | %s | %s | active=%s
", $row['court_id'], $row['court_name'], $row['court_type'] ?? '-', $row['price_per_hour'], $row['is_active']);
}
