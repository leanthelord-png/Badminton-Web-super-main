<?php
// reset_and_seed_courts.php - Reset và thêm dữ liệu sân
require_once 'config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Reset & Seed Courts</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
    </style>
</head>
<body>
    <h1>🔄 Đang reset và thêm dữ liệu sân...</h1>
";

try {
    // Bắt đầu transaction
    $pdo->beginTransaction();
    
    // Tắt kiểm tra khóa ngoại
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Xóa dữ liệu cũ - chỉ xóa các bảng tồn tại
    echo "<p class='info'>🗑️ Đang xóa dữ liệu cũ...</p>";
    
    // Kiểm tra bảng bookings trước khi xóa
    try {
        $pdo->exec("DELETE FROM bookings");
        echo "<p class='success'>✅ Đã xóa dữ liệu bookings</p>";
    } catch (PDOException $e) {
        echo "<p class='info'>⚠️ Bảng bookings không tồn tại, bỏ qua</p>";
    }
    
    // Kiểm tra bảng payments trước khi xóa
    try {
        $pdo->exec("DELETE FROM payments");
        echo "<p class='success'>✅ Đã xóa dữ liệu payments</p>";
    } catch (PDOException $e) {
        echo "<p class='info'>⚠️ Bảng payments không tồn tại, bỏ qua</p>";
    }
    
    // Xóa dữ liệu courts
    try {
        $pdo->exec("DELETE FROM courts");
        echo "<p class='success'>✅ Đã xóa dữ liệu courts</p>";
    } catch (PDOException $e) {
        echo "<p class='info'>⚠️ Bảng courts không tồn tại, bỏ qua</p>";
    }
    
    // Bật lại kiểm tra khóa ngoại
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // Dữ liệu sân mẫu
    $courts = [
        ['Sân Cầu Lông Hoàng Anh', 'Indoor', 150000, '123 Đường Lê Lợi, Quận 1, TP.HCM', '028-1234-5678', 'Sân cầu lông đạt chuẩn quốc tế, hệ thống ánh sáng hiện đại'],
        ['Sân Cầu Lông Tân Bình', 'Indoor', 120000, '456 Đường Trần Hưng Đạo, Quận Tân Bình, TP.HCM', '028-8765-4321', 'Sân rộng rãi, thoáng mát, phù hợp cho các giải đấu phong trào'],
        ['Sân Cầu Lông Phú Nhuận', 'Outdoor', 100000, '789 Đường Nguyễn Văn Trỗi, Quận Phú Nhuận, TP.HCM', '028-5555-1234', 'Sân ngoài trời, không khí trong lành'],
        ['Sân Cầu Lông Bình Thạnh', 'Indoor', 140000, '321 Đường Xô Viết Nghệ Tĩnh, Quận Bình Thạnh, TP.HCM', '028-9999-8888', 'Sân đạt chuẩn thi đấu, có khán đài'],
        ['Sân Cầu Lông Thủ Đức', 'Indoor', 110000, '654 Đường Võ Văn Ngân, TP. Thủ Đức, TP.HCM', '028-7777-6666', 'Sân mới xây, cơ sở vật chất hiện đại'],
        ['Sân Cầu Lông Gò Vấp', 'Indoor', 130000, '987 Đường Quang Trung, Quận Gò Vấp, TP.HCM', '028-4444-5555', 'Sân rộng, có quán nước giải khát, wifi miễn phí'],
        ['Sân Cầu Lông Quận 7', 'Indoor', 160000, '456 Đường Nguyễn Thị Thập, Quận 7, TP.HCM', '028-3333-2222', 'Sân cao cấp, khu vực sang trọng'],
        ['Sân Cầu Lông Quận 10', 'Outdoor', 90000, '123 Đường 3 Tháng 2, Quận 10, TP.HCM', '028-1111-2222', 'Sân giá rẻ, phù hợp cho sinh viên'],
        ['Sân Cầu Lông Tân Phú', 'Indoor', 125000, '789 Đường Tân Hương, Quận Tân Phú, TP.HCM', '028-8888-9999', 'Sân mới, sạch sẽ, có nhân viên hỗ trợ'],
        ['Sân Cầu Lông Bình Dương', 'Indoor', 135000, '456 Đường Đại Lộ Bình Dương, TX. Thuận An, Bình Dương', '0274-1234-5678', 'Sân đẹp, view thoáng'],
    ];
    
    $openingHours = json_encode([
        'monday' => ['open' => '06:00', 'close' => '22:00'],
        'tuesday' => ['open' => '06:00', 'close' => '22:00'],
        'wednesday' => ['open' => '06:00', 'close' => '22:00'],
        'thursday' => ['open' => '06:00', 'close' => '22:00'],
        'friday' => ['open' => '06:00', 'close' => '22:00'],
        'saturday' => ['open' => '07:00', 'close' => '21:00'],
        'sunday' => ['open' => '08:00', 'close' => '20:00']
    ], JSON_UNESCAPED_UNICODE);
    
    $facilities = json_encode(['parking', 'changing_room', 'shower', 'equipment_rental'], JSON_UNESCAPED_UNICODE);
    
    // Kiểm tra bảng courts có tồn tại không
    $tableExists = $pdo->query("SHOW TABLES LIKE 'courts'")->rowCount() > 0;
    
    if (!$tableExists) {
        echo "<p class='error'>❌ Bảng courts không tồn tại! Vui lòng chạy file setup database trước.</p>";
        $pdo->rollBack();
        exit;
    }
    
    $stmt = $pdo->prepare("INSERT INTO courts (court_name, court_type, price_per_hour, address, phone_number, description, opening_hours, facilities, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
    
    $inserted = 0;
    foreach ($courts as $court) {
        if ($stmt->execute([$court[0], $court[1], $court[2], $court[3], $court[4], $court[5], $openingHours, $facilities])) {
            $inserted++;
            echo "<p class='success'>✅ Đã thêm: {$court[0]}</p>";
        } else {
            echo "<p class='error'>❌ Lỗi thêm: {$court[0]}</p>";
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo "<hr>";
    echo "<h2 class='success'>✅ Đã thêm thành công {$inserted}/" . count($courts) . " sân!</h2>";
    
    // Hiển thị danh sách sân vừa thêm
    echo "<h3>📋 Danh sách sân hiện tại:</h3>";
    echo "<ul>";
    $stmt = $pdo->query("SELECT court_id, court_name, price_per_hour FROM courts");
    while ($row = $stmt->fetch()) {
        echo "<li>ID: {$row['court_id']} - {$row['court_name']} - " . number_format($row['price_per_hour'], 0, ',', '.') . " ₫/giờ</li>";
    }
    echo "</ul>";
    
    echo "<p><a href='index.php' style='display: inline-block; background: green; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 20px;'>🏠 Quay lại trang chủ</a></p>";
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "<p class='error'>❌ Lỗi: " . $e->getMessage() . "</p>";
}

echo "</body></html>";
?>