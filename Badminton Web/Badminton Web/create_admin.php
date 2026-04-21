<?php
// create_admin.php

$host = 'localhost';
$dbname = 'badminton web1'; // đổi lại nếu DB của bạn tên khác
$username = 'root';
$password = '';

$newAdminUsername = 'admin';
$newAdminPassword = 'Admin@123';
$newAdminFullName = 'Administrator';
$newAdminPhone = '0999999999';
$newAdminEmail = 'admin@example.com';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );

    // kiểm tra username/email/phone đã tồn tại chưa
    $check = $pdo->prepare("
        SELECT user_id, username, email, phone_number
        FROM users
        WHERE username = :username
           OR email = :email
           OR phone_number = :phone
        LIMIT 1
    ");
    $check->execute([
        ':username' => $newAdminUsername,
        ':email' => $newAdminEmail,
        ':phone' => $newAdminPhone
    ]);

    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo "Tài khoản đã tồn tại hoặc bị trùng username/email/phone.<br>";
        echo "Username: " . htmlspecialchars($existing['username'] ?? '') . "<br>";
        echo "Email: " . htmlspecialchars($existing['email'] ?? '') . "<br>";
        echo "Phone: " . htmlspecialchars($existing['phone_number'] ?? '') . "<br>";
        exit;
    }

    $hashedPassword = password_hash($newAdminPassword, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO users (
            username,
            password_hash,
            full_name,
            phone_number,
            email,
            role,
            created_at,
            user_balance
        ) VALUES (
            :username,
            :password_hash,
            :full_name,
            :phone_number,
            :email,
            'admin',
            NOW(),
            0.00
        )
    ");

    $stmt->execute([
        ':username' => $newAdminUsername,
        ':password_hash' => $hashedPassword,
        ':full_name' => $newAdminFullName,
        ':phone_number' => $newAdminPhone,
        ':email' => $newAdminEmail
    ]);

    echo "Tạo admin thành công!<br>";
    echo "Username: " . htmlspecialchars($newAdminUsername) . "<br>";
    echo "Password: " . htmlspecialchars($newAdminPassword) . "<br>";
} catch (PDOException $e) {
    echo "Lỗi: " . $e->getMessage();
}
?>