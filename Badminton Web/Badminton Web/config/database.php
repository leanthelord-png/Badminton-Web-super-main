<?php
// MySQL database configuration
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'badminton web1';
$user = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    $errorMessage = $e->getMessage();
    if (strpos($errorMessage, 'could not find driver') !== false) {
        die("Kết nối cơ sở dữ liệu thất bại: Trình điều khiển PDO MySQL không khả dụng. Hãy bật extension pdo_mysql trong PHP.");
    } elseif (strpos($errorMessage, 'Connection refused') !== false || strpos($errorMessage, 'No such file or directory') !== false) {
        die("Kết nối cơ sở dữ liệu thất bại: Không thể kết nối đến máy chủ MySQL. Hãy kiểm tra XAMPP/WAMP hoặc dịch vụ MySQL đang chạy.");
    } elseif (strpos($errorMessage, 'Access denied') !== false) {
        die("Kết nối cơ sở dữ liệu thất bại: Sai tên đăng nhập hoặc mật khẩu MySQL.");
    } else {
        die("Kết nối cơ sở dữ liệu thất bại: " . $errorMessage);
    }
}
?>
