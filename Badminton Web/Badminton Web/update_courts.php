<?php
require_once 'config/database.php';

header('Content-Type: text/plain; charset=utf-8');

function addColumnIfMissing(PDO $pdo, string $table, string $column, string $definition): void {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

addColumnIfMissing($pdo, 'courts', 'address', 'TEXT NULL');
addColumnIfMissing($pdo, 'courts', 'opening_hours', 'JSON NULL');
addColumnIfMissing($pdo, 'courts', 'phone_number', 'VARCHAR(20) NULL');
addColumnIfMissing($pdo, 'courts', 'facilities', 'JSON NULL');
addColumnIfMissing($pdo, 'courts', 'court_image', 'TEXT NULL');

echo "Đã đồng bộ cột cho bảng courts.
";

$pdo->exec('DELETE FROM courts');

$courts = [
    ['Sân cầu lông Hoàng Anh', 'Indoor', 150000, '123 Đường Lê Lợi, Quận 1, TP.HCM', '028-1234-5678', ['parking','changing_room','shower','equipment_rental'], 'court1.jpg'],
    ['Sân cầu lông Tân Bình', 'Indoor', 120000, '456 Đường Trần Hưng Đạo, Quận Tân Bình, TP.HCM', '028-8765-4321', ['parking','changing_room','cafe'], 'court2.jpg'],
    ['Sân cầu lông Phú Nhuận', 'Outdoor', 100000, '789 Đường Nguyễn Văn Trỗi, Quận Phú Nhuận, TP.HCM', '028-5555-1234', ['parking','lighting','equipment_rental'], 'court3.jpg'],
    ['Sân cầu lông Bình Thạnh', 'Indoor', 140000, '321 Đường Xô Viết Nghệ Tĩnh, Quận Bình Thạnh, TP.HCM', '028-9999-8888', ['parking','changing_room','shower','equipment_rental','coaching'], 'court4.jpg'],
    ['Sân cầu lông Thủ Đức', 'Indoor', 110000, '654 Đường Võ Văn Ngân, TP. Thủ Đức, TP.HCM', '028-7777-6666', ['parking','changing_room','cafe'], 'court5.jpg']
];

$openingHours = [
    'monday' => ['open' => '06:00', 'close' => '22:00'],
    'tuesday' => ['open' => '06:00', 'close' => '22:00'],
    'wednesday' => ['open' => '06:00', 'close' => '22:00'],
    'thursday' => ['open' => '06:00', 'close' => '22:00'],
    'friday' => ['open' => '06:00', 'close' => '22:00'],
    'saturday' => ['open' => '07:00', 'close' => '21:00'],
    'sunday' => ['open' => '08:00', 'close' => '20:00']
];

$stmt = $pdo->prepare("INSERT INTO courts (court_name, court_type, price_per_hour, address, phone_number, facilities, court_image, opening_hours, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
foreach ($courts as $court) {
    $stmt->execute([
        $court[0],
        $court[1],
        $court[2],
        $court[3],
        $court[4],
        json_encode($court[5], JSON_UNESCAPED_UNICODE),
        $court[6],
        json_encode($openingHours, JSON_UNESCAPED_UNICODE)
    ]);
}

echo "Đã thêm dữ liệu mẫu cho courts bằng MySQL.
";
