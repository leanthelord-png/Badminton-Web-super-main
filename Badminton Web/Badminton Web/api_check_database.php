<?php
/**
 * API endpoint to check database status
 */

header('Content-Type: application/json');
require_once 'config/database.php';

$result = [
    'database_connected' => false,
    'tables_exist' => false,
    'columns' => []
];

try {
    // Check if database is connected
    $pdo->query("SELECT 1");
    $result['database_connected'] = true;

    // Check if tables exist
    $stmt = $pdo->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public' AND table_name = 'users'
    ");
    $result['tables_exist'] = $stmt->fetch() ? true : false;

    // Check for specific columns
    $columnList = ['user_balance', 'address', 'phone_number', 'opening_hours', 'facilities'];
    
    foreach ($columnList as $col) {
        $stmt = $pdo->query("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_name = 'users' AND column_name = '$col'
        ");
        $result['columns'][$col] = $stmt->fetch() ? true : false;
    }

    // Also check courts table columns
    $courtColumns = ['address', 'phone_number', 'opening_hours', 'facilities'];
    foreach ($courtColumns as $col) {
        $stmt = $pdo->query("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_name = 'courts' AND column_name = '$col'
        ");
        $result['columns']['courts_' . $col] = $stmt->fetch() ? true : false;
    }

} catch (Exception $e) {
    $result['database_connected'] = false;
    $result['error'] = $e->getMessage();
}

echo json_encode($result);
?>
