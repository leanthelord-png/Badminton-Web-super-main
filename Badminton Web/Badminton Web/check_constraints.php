<?php
require_once 'config/database.php';

echo "Check constraints on users table:\n";
$result = $pdo->query("
    SELECT constraint_name, constraint_type
    FROM information_schema.table_constraints 
    WHERE table_name = 'users'
");

foreach ($result as $row) {
    echo " - {$row['constraint_name']} ({$row['constraint_type']})\n";
}
?>
