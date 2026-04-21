<?php
require_once 'config/database.php';

echo "Users table columns:\n";
$result = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'users' ORDER BY ordinal_position");
foreach ($result as $col) {
    echo " - {$col['column_name']}: {$col['data_type']}\n";
}
?>
