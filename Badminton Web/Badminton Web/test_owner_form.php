<?php
/**
 * Test owner registration via POST like the form submits
 */

require_once 'config/database.php';

echo "<h2>🧪 Test Owner Registration (Simulating Form POST)</h2>";
echo "<pre>";

// Simulate form submission
$_POST['action'] = 'register_owner';
$_POST['full_name'] = 'Nguyễn Chủ Sân ' . date('His');
$_POST['username'] = 'owner' . time();
$_POST['phone_number'] = '0912' . rand(1000000, 9999999);
$_POST['email'] = 'owner' . time() . '@test.com';
$_POST['password'] = 'Password@123';

$message = '';
$messageType = '';

// Copy registration logic from index.php
$fullname = trim($_POST['full_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$phone = trim($_POST['phone_number'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

echo "📋 Dữ liệu đăng ký:\n";
echo "  Họ tên: $fullname\n";
echo "  Tên đăng nhập: $username\n";
echo "  Số điện thoại: $phone\n";
echo "  Email: $email\n";
echo "  Mật khẩu: (hidden)\n\n";

if (empty($fullname) || empty($username) || empty($phone) || empty($password)) {
    $message = 'Tất cả các trường là bắt buộc';
    $messageType = 'error';
} else if (strlen($password) < 6) {
    $message = 'Mật khẩu phải có ít nhất 6 ký tự';
    $messageType = 'error';
} else {
    // Check if username or phone already exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? OR phone_number = ?");
    $stmt->execute([$username, $phone]);
    if ($stmt->fetch()) {
        $message = 'Tên đăng nhập hoặc số điện thoại đã tồn tại';
        $messageType = 'error';
    } else {
        // Check if email exists (if provided)
        if (!empty($email)) {
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $message = 'Email đã tồn tại';
                $messageType = 'error';
            }
        }
        
        if (empty($message)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            try {
                // Insert with email (can be NULL)
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, password_hash, full_name, phone_number, email, role, user_balance, profile_image_url) 
                    VALUES (?, ?, ?, ?, NULLIF(?, ''), 'owner', 0, NULL)
                ");
                $stmt->execute([$username, $hashedPassword, $fullname, $phone, $email]);
                $message = 'Đăng ký chủ sân thành công! Vui lòng đăng nhập.';
                $messageType = 'success';
            } catch (PDOException $e) {
                // More detailed error handling
                $errorMsg = $e->getMessage();
                if (strpos($errorMsg, 'duplicate key') !== false || strpos($errorMsg, 'UNIQUE') !== false) {
                    $message = 'Tên đăng nhập, số điện thoại hoặc email đã tồn tại';
                } else if (strpos($errorMsg, 'role') !== false) {
                    $message = 'Loại tài khoản không hợp lệ';
                } else {
                    $message = 'Đăng ký thất bại: ' . $errorMsg;
                }
                $messageType = 'error';
            }
        }
    }
}

// Show result
if ($messageType === 'success') {
    echo "✅ KẾT QUẢ: " . $message . "\n";
    echo "\n🔐 Thông tin đăng nhập:\n";
    echo "  Username: $username\n";
    echo "  Password: (như vừa nhập)\n";
} else {
    echo "❌ KẾT QUẢ: " . $message . "\n";
}

echo "</pre>";
?>
