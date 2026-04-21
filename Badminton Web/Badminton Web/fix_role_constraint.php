<?php
/**
 * Fix users table role constraint to include 'owner'
 */
require_once 'config/database.php';

echo "<h2>🔧 Fixing users table role constraint</h2>";
echo "<pre>";

try {
    // Get CHECK constraint name specifically
    $stmt = $pdo->query("
        SELECT constraint_name 
        FROM information_schema.table_constraints 
        WHERE table_name = 'users' AND constraint_type = 'CHECK'
    ");
    $constraints = $stmt->fetchAll();
    
    $dropped = false;
    if (count($constraints) > 0) {
        foreach ($constraints as $constraint) {
            $constraintName = $constraint['constraint_name'];
            if (strpos($constraintName, 'role') !== false) {
                echo "Found role constraint: $constraintName\n";
                
                // Drop the old constraint
                $pdo->exec("ALTER TABLE users DROP CONSTRAINT IF EXISTS $constraintName");
                echo "✓ Dropped old role constraint\n";
                $dropped = true;
            }
        }
    }
    
    if (!$dropped) {
        echo "Note: No role constraint found, adding new one\n";
    }
    
    // Add new constraint with 'owner' included
    $pdo->exec("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('customer', 'staff', 'admin', 'owner'))");
    echo "✓ Added new role constraint with 'owner' included\n";
    
    echo "\n✅ users table role constraint fixed!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
