<?php
require_once 'config/database.php';

function runSqlFile(PDO $pdo, string $filePath): void {
    if (!file_exists($filePath)) {
        throw new Exception('Không tìm thấy file SQL: ' . $filePath);
    }
    $pdo->exec(file_get_contents($filePath));
}

header('Content-Type: application/json; charset=utf-8');
try {
    runSqlFile($pdo, __DIR__ . '/mysql_schema.sql');
    echo json_encode(['success' => true, 'message' => 'Đã chạy migration MySQL thành công'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
