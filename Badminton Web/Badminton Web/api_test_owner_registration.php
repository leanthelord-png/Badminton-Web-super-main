<?php
/**
 * Test owner registration with detailed error reporting
 */
require_once 'config/database.php';

header('Content-Type: application/json');

$testData = [
    'fullname' => 'Test Owner ' . date('His'),
    'username' => 'owner' . time(),
    'phone' => '0912' . rand(1000000, 9999999),
    'email' => 'owner' . time() . '@test.com',
    'password' => 'Test@12345'
];

$result = [
    'success' => false,
    'data' => $testData,
    'steps' => []
];

try {
    // Step 1: Validate input
    $result['steps'][] = [
        'name' => 'Validate Input',
        'status' => 'checking',
        'details' => 'Checking required fields'
    ];
    
    if (empty($testData['fullname']) || empty($testData['username']) || empty($testData['phone']) || empty($testData['password'])) {
        throw new Exception('Tất cả các trường là bắt buộc');
    }
    $result['steps'][0]['status'] = 'passed';

    // Step 2: Check duplicates
    $result['steps'][] = [
        'name' => 'Check Duplicates',
        'status' => 'checking',
        'details' => 'Checking if username/phone already exist'
    ];
    
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? OR phone_number = ?");
    $stmt->execute([$testData['username'], $testData['phone']]);
    if ($stmt->fetch()) {
        throw new Exception('Tên đăng nhập hoặc số điện thoại đã tồn tại');
    }
    $result['steps'][1]['status'] = 'passed';

    // Step 3: Check email
    if (!empty($testData['email'])) {
        $result['steps'][] = [
            'name' => 'Check Email',
            'status' => 'checking',
            'details' => 'Checking if email already exist'
        ];
        
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$testData['email']]);
        if ($stmt->fetch()) {
            throw new Exception('Email đã tồn tại');
        }
        $result['steps'][2]['status'] = 'passed';
    }

    // Step 4: Hash password
    $result['steps'][] = [
        'name' => 'Hash Password',
        'status' => 'checking',
        'details' => 'Creating password hash'
    ];
    $hashedPassword = password_hash($testData['password'], PASSWORD_DEFAULT);
    $result['steps'][count($result['steps'])-1]['status'] = 'passed';

    // Step 5: Insert user
    $result['steps'][] = [
        'name' => 'Insert User',
        'status' => 'checking',
        'details' => 'Inserting owner user into database'
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO users (username, password_hash, full_name, phone_number, email, role, user_balance, profile_image_url) 
        VALUES (?, ?, ?, ?, NULLIF(?, ''), 'owner', 0, NULL)
    ");
    $stmt->execute([
        $testData['username'],
        $hashedPassword,
        $testData['fullname'],
        $testData['phone'],
        $testData['email']
    ]);
    $newUserId = $pdo->lastInsertId();
    $result['steps'][count($result['steps'])-1]['status'] = 'passed';
    $result['steps'][count($result['steps'])-1]['details'] = "Created user with ID: $newUserId";

    // Step 6: Verify creation
    $result['steps'][] = [
        'name' => 'Verify Creation',
        'status' => 'checking',
        'details' => 'Verifying user was created'
    ];
    
    $stmt = $pdo->prepare("SELECT user_id, username, role FROM users WHERE user_id = ?");
    $stmt->execute([$newUserId]);
    $createdUser = $stmt->fetch();
    
    if (!$createdUser) {
        throw new Exception('Không thể xác minh người dùng vừa tạo');
    }
    
    $result['steps'][count($result['steps'])-1]['status'] = 'passed';
    $result['steps'][count($result['steps'])-1]['details'] = "User verified: {$createdUser['username']} (role: {$createdUser['role']})";

    // Success!
    $result['success'] = true;
    $result['message'] = '✅ Đăng ký chủ sân thành công! Vui lòng đăng nhập.';
    $result['created_user'] = $createdUser;

} catch (Exception $e) {
    $result['success'] = false;
    $result['message'] = '❌ ' . $e->getMessage();
    if (isset($result['steps'])) {
        $currentStep = count($result['steps']) - 1;
        if ($currentStep >= 0) {
            $result['steps'][$currentStep]['status'] = 'failed';
            $result['steps'][$currentStep]['error'] = $e->getMessage();
        }
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
