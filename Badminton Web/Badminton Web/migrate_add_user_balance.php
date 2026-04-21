<?php
/**
 * Migration script to add missing user_balance column
 * Run this script once to add the missing column to the users table
 */

require_once 'config/database.php';

echo "<h1>Database Migration: Add user_balance column</h1>";
echo "<pre>";

try {
    // Check if column already exists
    $stmt = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'users' AND column_name = 'user_balance'
    ");
    $columnExists = $stmt->fetch();
    
    if ($columnExists) {
        echo "✓ Column 'user_balance' already exists in users table\n";
    } else {
        // Add the column if it doesn't exist
        $pdo->exec("ALTER TABLE users ADD COLUMN user_balance DECIMAL(10,2) DEFAULT 0");
        echo "✓ Successfully added column 'user_balance' to users table\n";
    }
    
    // Also check and add missing columns from other tables if needed
    $stmt = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'courts' AND column_name = 'address'
    ");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE courts ADD COLUMN address VARCHAR(255)");
        echo "✓ Successfully added column 'address' to courts table\n";
    } else {
        echo "✓ Column 'address' already exists in courts table\n";
    }
    
    $stmt = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'courts' AND column_name = 'phone_number'
    ");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE courts ADD COLUMN phone_number VARCHAR(20)");
        echo "✓ Successfully added column 'phone_number' to courts table\n";
    } else {
        echo "✓ Column 'phone_number' already exists in courts table\n";
    }
    
    $stmt = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'courts' AND column_name = 'opening_hours'
    ");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE courts ADD COLUMN opening_hours TEXT");
        echo "✓ Successfully added column 'opening_hours' to courts table\n";
    } else {
        echo "✓ Column 'opening_hours' already exists in courts table\n";
    }
    
    $stmt = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'courts' AND column_name = 'facilities'
    ");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE courts ADD COLUMN facilities TEXT");
        echo "✓ Successfully added column 'facilities' to courts table\n";
    } else {
        echo "✓ Column 'facilities' already exists in courts table\n";
    }
    
    echo "\n✓ Migration completed successfully!\n";
    echo "</pre>";
    echo "<p style='color: green; font-weight: bold;'>Database has been updated. You can now use the application.</p>";
    
} catch (PDOException $e) {
    echo "</pre>";
    echo "<p style='color: red; font-weight: bold;'>Error during migration: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
