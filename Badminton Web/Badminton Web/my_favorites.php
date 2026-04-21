<?php
// my_favorites.php - Quản lý sân yêu thích của người dùng
require_once 'config/database.php';
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=Vui lòng đăng nhập để xem sân yêu thích');
    exit;
}

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Xử lý xóa sân khỏi yêu thích
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $message = 'Lỗi bảo mật, vui lòng thử lại';
        $messageType = 'error';
    } elseif ($_POST['action'] === 'remove_favorite') {
        $courtId = (int)$_POST['court_id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM user_favorites WHERE user_id = ? AND court_id = ?");
            $stmt->execute([$userId, $courtId]);
            
            $message = 'Đã xóa sân khỏi danh sách yêu thích';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Xóa thất bại: ' . $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($_POST['action'] === 'clear_all_favorites') {
        try {
            $stmt = $pdo->prepare("DELETE FROM user_favorites WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            $message = 'Đã xóa tất cả sân khỏi danh sách yêu thích';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Xóa thất bại: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Lấy danh sách sân yêu thích với phân trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 9;
$offset = ($page - 1) * $perPage;
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'created_at_desc';

// Đếm tổng số
$countStmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM user_favorites uf
    WHERE uf.user_id = ?
");
$countStmt->execute([$userId]);
$totalFavorites = $countStmt->fetch()['total'];
$totalPages = ceil($totalFavorites / $perPage);

// Xây dựng câu lệnh ORDER BY
$orderBy = "uf.created_at DESC";
switch ($sortBy) {
    case 'created_at_asc':
        $orderBy = "uf.created_at ASC";
        break;
    case 'price_asc':
        $orderBy = "c.price_per_hour ASC";
        break;
    case 'price_desc':
        $orderBy = "c.price_per_hour DESC";
        break;
    case 'rating_desc':
        $orderBy = "c.avg_rating DESC";
        break;
    case 'name_asc':
        $orderBy = "c.court_name ASC";
        break;
}

// Lấy danh sách favorites
$stmt = $pdo->prepare("
    SELECT 
        c.*,
        uf.created_at as favorited_at,
        COALESCE(c.avg_rating, 0) as avg_rating,
        COALESCE(c.total_reviews, 0) as total_reviews,
        (SELECT COUNT(*) FROM bookings b WHERE b.court_id = c.court_id AND b.status IN ('paid', 'confirmed')) as total_bookings
    FROM user_favorites uf
    JOIN courts c ON uf.court_id = c.court_id
    WHERE uf.user_id = ? AND c.is_active = 1
    ORDER BY $orderBy
    LIMIT ? OFFSET ?
");
$stmt->execute([$userId, $perPage, $offset]);
$favorites = $stmt->fetchAll();

// Thống kê
$stats = [
    'total' => $totalFavorites,
    'avg_price' => 0,
    'most_expensive' => 0,
    'cheapest' => 0
];

if (!empty($favorites)) {
    $prices = array_column($favorites, 'price_per_hour');
    $stats['avg_price'] = array_sum($prices) / count($prices);
    $stats['most_expensive'] = max($prices);
    $stats['cheapest'] = min($prices);
}

// Lấy gợi ý sân dựa trên sở thích
$recommendations = [];
if (count($favorites) > 0) {
    // Lấy các loại sân yêu thích
    $courtTypes = array_unique(array_column($favorites, 'court_type'));
    $typePlaceholders = implode(',', array_fill(0, count($courtTypes), '?'));
    
    // Lấy khoảng giá yêu thích
    $avgPrice = $stats['avg_price'];
    $minPrice = max(0, $avgPrice * 0.7);
    $maxPrice = $avgPrice * 1.3;
    
    $recStmt = $pdo->prepare("
        SELECT c.*, 
               COALESCE(c.avg_rating, 0) as avg_rating,
               COALESCE(c.total_reviews, 0) as total_reviews,
               (SELECT COUNT(*) FROM user_favorites uf2 WHERE uf2.court_id = c.court_id) as favorite_count
        FROM courts c
        WHERE c.is_active = 1 
        AND c.court_id NOT IN (SELECT court_id FROM user_favorites WHERE user_id = ?)
        AND c.price_per_hour BETWEEN ? AND ?
        " . (!empty($courtTypes) ? "AND c.court_type IN ($typePlaceholders)" : "") . "
        ORDER BY c.avg_rating DESC, favorite_count DESC
        LIMIT 4
    ");
    
    $params = [$userId, $minPrice, $maxPrice];
    if (!empty($courtTypes)) {
        $params = array_merge($params, $courtTypes);
    }
    $recStmt->execute($params);
    $recommendations = $recStmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sân Yêu Thích Của Tôi - BadmintonPro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .card-hover:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
        }
        
        .heart-animation {
            animation: heartBeat 0.3s ease;
        }
        
        @keyframes heartBeat {
            0% { transform: scale(1); }
            50% { transform: scale(1.3); }
            100% { transform: scale(1); }
        }
        
        .modal-enter {
            animation: modalEnter 0.3s ease-out;
        }
        
        @keyframes modalEnter {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .toast {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 1000;
            animation: slideInRight 0.3s ease-out;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .stat-card {
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-4px);
        }
        
        @media (max-width: 768px) {
            .grid-cols-1\\.5 {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .grid-cols-1\\.5 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-3">
                    <div class="bg-gradient-to-r from-green-500 to-emerald-600 p-2 rounded-xl">
                        <i class="fas fa-shuttlecock text-white text-xl"></i>
                    </div>
                    <a href="index.php" class="font-extrabold text-2xl bg-gradient-to-r from-green-600 to-emerald-600 bg-clip-text text-transparent">
                        BadmintonPro
                    </a>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="text-gray-600 hover:text-green-600 transition">
                        <i class="fas fa-home"></i>
                        <span class="hidden md:inline ml-1">Trang chủ</span>
                    </a>
                    <a href="my_bookings.php" class="text-gray-600 hover:text-green-600 transition">
                        <i class="fas fa-calendar-alt"></i>
                        <span class="hidden md:inline ml-1">Lịch đặt</span>
                    </a>
                    <div class="relative group">
                        <button class="flex items-center space-x-3 bg-gray-100 hover:bg-gray-200 rounded-full px-4 py-2 transition">
                            <div class="w-8 h-8 bg-gradient-to-r from-green-500 to-emerald-600 rounded-full flex items-center justify-center">
                                <i class="fas fa-user text-white text-sm"></i>
                            </div>
                            <span class="font-medium text-gray-700"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></span>
                            <i class="fas fa-chevron-down text-gray-500 text-xs"></i>
                        </button>
                        <div class="absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 z-50">
                            <div class="py-2">
                                <a href="my_bookings.php" class="flex items-center px-4 py-2 hover:bg-gray-50 transition">
                                    <i class="fas fa-calendar-alt w-5 text-blue-500"></i>
                                    <span class="ml-3">Lịch đặt của tôi</span>
                                </a>
                                <a href="my_favorites.php" class="flex items-center px-4 py-2 hover:bg-gray-50 transition text-red-500">
                                    <i class="fas fa-heart w-5"></i>
                                    <span class="ml-3">Sân yêu thích</span>
                                </a>
                                <hr class="my-2">
                                <form method="POST" action="index.php">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="action" value="logout">
                                    <button type="submit" class="w-full text-left flex items-center px-4 py-2 hover:bg-red-50 transition text-red-600">
                                        <i class="fas fa-sign-out-alt w-5"></i>
                                        <span class="ml-3">Đăng xuất</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Messages -->
    <?php if ($message): ?>
    <div class="toast" id="toastMessage">
        <div class="p-4 rounded-xl shadow-lg max-w-md <?php echo $messageType === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'; ?>">
            <div class="flex items-center space-x-3">
                <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> text-xl"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-auto text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
    <script>
        setTimeout(function() {
            var toast = document.getElementById('toastMessage');
            if(toast) toast.remove();
        }, 5000);
    </script>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="bg-gradient-to-r from-red-500 to-pink-500 text-white py-12">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div>
                    <h1 class="text-3xl md:text-4xl font-bold mb-2">
                        <i class="fas fa-heart mr-3"></i>Sân Yêu Thích Của Tôi
                    </h1>
                    <p class="text-red-100">Danh sách các sân cầu lông bạn đã yêu thích</p>
                </div>
                <?php if ($totalFavorites > 0): ?>
                <button onclick="showClearAllModal()" class="mt-4 md:mt-0 bg-white/20 backdrop-blur-sm hover:bg-white/30 px-6 py-3 rounded-xl font-semibold transition inline-flex items-center">
                    <i class="fas fa-trash-alt mr-2"></i>
                    Xóa tất cả
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <?php if ($totalFavorites > 0): ?>
    <div class="max-w-7xl mx-auto px-4 -mt-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl p-4 shadow-md stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Tổng số sân</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $totalFavorites; ?></p>
                    </div>
                    <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-heart text-red-500"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-md stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Giá trung bình</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['avg_price'], 0, ',', '.'); ?>₫</p>
                    </div>
                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-chart-line text-blue-500"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-md stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Rẻ nhất</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo number_format($stats['cheapest'], 0, ',', '.'); ?>₫</p>
                    </div>
                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-arrow-down text-green-500"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-md stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Đắt nhất</p>
                        <p class="text-2xl font-bold text-orange-600"><?php echo number_format($stats['most_expensive'], 0, ',', '.'); ?>₫</p>
                    </div>
                    <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-arrow-up text-orange-500"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Sort and Filter Bar -->
    <?php if ($totalFavorites > 0): ?>
    <div class="max-w-7xl mx-auto px-4 mt-8">
        <div class="bg-white rounded-xl shadow-sm p-4 flex flex-wrap justify-between items-center gap-4">
            <div class="flex items-center space-x-2">
                <i class="fas fa-sort-amount-down-alt text-gray-500"></i>
                <span class="text-gray-600">Sắp xếp:</span>
                <select id="sortSelect" onchange="updateSort()" class="px-3 py-1 border border-gray-300 rounded-lg focus:outline-none focus:border-red-500">
                    <option value="created_at_desc" <?php echo $sortBy == 'created_at_desc' ? 'selected' : ''; ?>>Mới nhất</option>
                    <option value="created_at_asc" <?php echo $sortBy == 'created_at_asc' ? 'selected' : ''; ?>>Cũ nhất</option>
                    <option value="price_asc" <?php echo $sortBy == 'price_asc' ? 'selected' : ''; ?>>Giá thấp đến cao</option>
                    <option value="price_desc" <?php echo $sortBy == 'price_desc' ? 'selected' : ''; ?>>Giá cao đến thấp</option>
                    <option value="rating_desc" <?php echo $sortBy == 'rating_desc' ? 'selected' : ''; ?>>Đánh giá cao nhất</option>
                    <option value="name_asc" <?php echo $sortBy == 'name_asc' ? 'selected' : ''; ?>>Tên A-Z</option>
                </select>
            </div>
            <div class="text-sm text-gray-500">
                Hiển thị <span class="font-semibold text-gray-700"><?php echo count($favorites); ?></span> / <span class="font-semibold text-gray-700"><?php echo $totalFavorites; ?></span> sân
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Favorites List -->
    <div class="max-w-7xl mx-auto px-4 py-8">
        <?php if (empty($favorites)): ?>
            <div class="text-center py-16 bg-white rounded-2xl shadow-sm">
                <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-heart-broken text-4xl text-gray-400"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-600 mb-2">Chưa có sân yêu thích nào</h3>
                <p class="text-gray-500 mb-6">Hãy khám phá và thêm các sân cầu lông bạn yêu thích</p>
                <a href="index.php" class="inline-flex items-center bg-gradient-to-r from-green-600 to-emerald-600 text-white px-6 py-3 rounded-xl font-semibold hover:shadow-lg transition">
                    <i class="fas fa-search mr-2"></i>
                    Khám phá sân ngay
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($favorites as $court): ?>
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden card-hover group">
                    <!-- Court Image -->
                    <div class="relative h-48 bg-gradient-to-br from-green-400 to-emerald-500 overflow-hidden">
                        <?php if (!empty($court['court_image'])): ?>
                        <img src="uploads/courts/<?php echo htmlspecialchars($court['court_image']); ?>" 
                             alt="<?php echo htmlspecialchars($court['court_name']); ?>" 
                             class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                        <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center">
                            <i class="fas fa-shuttlecock text-white text-6xl opacity-50"></i>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Rating Badge -->
                        <div class="absolute top-4 left-4 bg-yellow-400 text-gray-800 px-2 py-1 rounded-lg text-sm font-bold flex items-center">
                            <i class="fas fa-star text-yellow-600 mr-1"></i>
                            <?php echo number_format($court['avg_rating'] ?? 0, 1); ?>
                            <span class="text-xs ml-1">(<?php echo $court['total_reviews'] ?? 0; ?>)</span>
                        </div>
                        
                        <!-- Favorite Button -->
                        <button onclick="removeFavorite(<?php echo $court['court_id']; ?>, this)" 
                                class="absolute top-4 right-4 bg-white rounded-full w-10 h-10 flex items-center justify-center shadow-md hover:shadow-lg transition">
                            <i class="fas fa-heart text-red-500 text-xl"></i>
                        </button>
                        
                        <!-- Price Tag -->
                        <div class="absolute bottom-4 right-4 bg-black/70 backdrop-blur-sm rounded-lg px-3 py-1">
                            <span class="text-white font-bold"><?php echo number_format($court['price_per_hour'], 0, ',', '.'); ?>₫</span>
                            <span class="text-gray-300 text-xs">/giờ</span>
                        </div>
                        
                        <!-- Bookings Count -->
                        <div class="absolute bottom-4 left-4 bg-black/50 backdrop-blur-sm rounded-lg px-2 py-1">
                            <i class="fas fa-calendar-check text-white text-xs mr-1"></i>
                            <span class="text-white text-xs"><?php echo $court['total_bookings'] ?? 0; ?> lượt đặt</span>
                        </div>
                    </div>
                    
                    <div class="p-5">
                        <h3 class="text-xl font-bold text-gray-800 mb-2 line-clamp-1">
                            <?php echo htmlspecialchars($court['court_name']); ?>
                        </h3>
                        
                        <?php if (!empty($court['address'])): ?>
                        <p class="text-sm text-gray-500 mb-3 flex items-start">
                            <i class="fas fa-map-marker-alt text-red-500 mr-2 mt-0.5"></i>
                            <span class="line-clamp-2"><?php echo htmlspecialchars($court['address']); ?></span>
                        </p>
                        <?php endif; ?>
                        
                        <div class="flex items-center justify-between mb-3">
                            <?php if (!empty($court['court_type'])): ?>
                            <div class="flex items-center">
                                <i class="fas fa-tag text-gray-400 text-sm mr-2"></i>
                                <span class="text-xs bg-gray-100 px-2 py-1 rounded-full"><?php echo htmlspecialchars($court['court_type']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="flex items-center space-x-1 text-xs text-gray-500">
                                <i class="far fa-heart"></i>
                                <span>Đã thích <?php echo date('d/m/Y', strtotime($court['favorited_at'])); ?></span>
                            </div>
                        </div>
                        
                        <div class="flex gap-2 mt-4">
                            <a href="court.php?id=<?php echo $court['court_id']; ?>" 
                               class="flex-1 bg-gradient-to-r from-green-600 to-emerald-600 text-white px-4 py-2 rounded-xl hover:shadow-lg transition duration-300 text-center text-sm font-semibold">
                                Xem Chi Tiết
                                <i class="fas fa-arrow-right ml-1 text-xs"></i>
                            </a>
                            <button onclick="quickBook(<?php echo $court['court_id']; ?>, '<?php echo htmlspecialchars($court['court_name']); ?>')" 
                                    class="bg-blue-500 text-white px-4 py-2 rounded-xl hover:bg-blue-600 transition text-sm font-semibold">
                                <i class="fas fa-calendar-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="flex justify-center mt-8">
                <nav class="flex items-center space-x-2">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&sort=<?php echo $sortBy; ?>" 
                       class="px-4 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 transition">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i == $page): ?>
                        <span class="px-4 py-2 rounded-lg bg-gradient-to-r from-red-500 to-pink-500 text-white"><?php echo $i; ?></span>
                        <?php elseif (abs($i - $page) <= 2 || $i == 1 || $i == $totalPages): ?>
                        <a href="?page=<?php echo $i; ?>&sort=<?php echo $sortBy; ?>" 
                           class="px-4 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 transition">
                            <?php echo $i; ?>
                        </a>
                        <?php elseif (abs($i - $page) == 3): ?>
                        <span class="px-2">...</span>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&sort=<?php echo $sortBy; ?>" 
                       class="px-4 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 transition">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                </nav>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Recommendations Section -->
    <?php if (!empty($recommendations) && $totalFavorites > 0): ?>
    <div class="bg-gradient-to-r from-purple-50 to-pink-50 py-12 mt-8">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-8">
                <h2 class="text-2xl md:text-3xl font-bold text-gray-800 mb-2">
                    <i class="fas fa-lightbulb text-yellow-500 mr-2"></i>
                    Gợi ý dành riêng cho bạn
                </h2>
                <p class="text-gray-600">Dựa trên sở thích của bạn, chúng tôi gợi ý những sân này</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($recommendations as $rec): ?>
                <div class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-xl transition">
                    <div class="h-32 bg-gradient-to-r from-purple-400 to-pink-400 flex items-center justify-center">
                        <i class="fas fa-shuttlecock text-white text-4xl"></i>
                    </div>
                    <div class="p-4">
                        <h4 class="font-bold text-gray-800 mb-1"><?php echo htmlspecialchars($rec['court_name']); ?></h4>
                        <p class="text-green-600 font-bold text-lg"><?php echo number_format($rec['price_per_hour'], 0, ',', '.'); ?>₫</p>
                        <div class="flex items-center mt-2">
                            <div class="flex text-yellow-400">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= ($rec['avg_rating'] ?? 0) ? 'text-yellow-400' : 'text-gray-300'; ?> text-sm"></i>
                                <?php endfor; ?>
                            </div>
                            <span class="text-xs text-gray-500 ml-2">(<?php echo $rec['total_reviews'] ?? 0; ?>)</span>
                        </div>
                        <a href="court.php?id=<?php echo $rec['court_id']; ?>" 
                           class="block mt-3 text-center bg-gradient-to-r from-purple-500 to-pink-500 text-white py-2 rounded-lg text-sm font-semibold hover:shadow transition">
                            Xem chi tiết
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Clear All Modal -->
    <div id="clearAllModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4 modal-enter">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">Xóa tất cả sân yêu thích</h3>
                <button onclick="closeClearAllModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="mb-6">
                <p class="text-gray-600">Bạn có chắc chắn muốn xóa tất cả <span class="font-semibold"><?php echo $totalFavorites; ?></span> sân khỏi danh sách yêu thích?</p>
                <p class="text-sm text-red-500 mt-2">Hành động này không thể hoàn tác!</p>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="clear_all_favorites">
                <div class="flex gap-3">
                    <button type="button" onclick="closeClearAllModal()" 
                            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                        Quay lại
                    </button>
                    <button type="submit" 
                            class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                        Xóa tất cả
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Quick Booking Modal -->
    <div id="quickBookModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4 modal-enter">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">Đặt sân nhanh</h3>
                <button onclick="closeQuickBookModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="mb-4">
                <p class="text-gray-600">Đặt sân:</p>
                <p class="font-semibold text-gray-800 text-lg" id="quickCourtName"></p>
            </div>
            <form method="POST" action="index.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="booking">
                <input type="hidden" name="court_id" id="quickCourtId">
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 font-medium">Ngày Đặt</label>
                    <input type="date" id="quickBookingDate" name="booking_date" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 font-medium">Giờ Bắt Đầu</label>
                    <select id="quickStartTime" name="start_time" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500" required>
                        <option value="">Chọn giờ</option>
                        <option value="06:00">06:00</option>
                        <option value="07:00">07:00</option>
                        <option value="08:00">08:00</option>
                        <option value="09:00">09:00</option>
                        <option value="10:00">10:00</option>
                        <option value="11:00">11:00</option>
                        <option value="12:00">12:00</option>
                        <option value="13:00">13:00</option>
                        <option value="14:00">14:00</option>
                        <option value="15:00">15:00</option>
                        <option value="16:00">16:00</option>
                        <option value="17:00">17:00</option>
                        <option value="18:00">18:00</option>
                        <option value="19:00">19:00</option>
                        <option value="20:00">20:00</option>
                        <option value="21:00">21:00</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 font-medium">Thời Gian (giờ)</label>
                    <select name="duration" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500">
                        <option value="1">1 giờ</option>
                        <option value="1.5">1.5 giờ</option>
                        <option value="2">2 giờ</option>
                        <option value="2.5">2.5 giờ</option>
                        <option value="3">3 giờ</option>
                    </select>
                </div>
                
                <button type="submit" class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white py-2 rounded-lg font-semibold hover:shadow-lg transition">
                    Tiếp tục đặt sân
                </button>
            </form>
        </div>
    </div>

    <script>
        // Remove single favorite with animation
        async function removeFavorite(courtId, button) {
            // Add animation
            const heart = button.querySelector('i');
            heart.classList.add('heart-animation');
            setTimeout(() => heart.classList.remove('heart-animation'), 300);
            
            // Show confirm
            if (!confirm('Bạn có chắc muốn xóa sân này khỏi danh sách yêu thích?')) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'remove_favorite');
                formData.append('court_id', courtId);
                formData.append('csrf_token', '<?php echo $csrf_token; ?>');
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const text = await response.text();
                
                if (response.ok) {
                    // Remove the card with fade out animation
                    const card = button.closest('.bg-white.rounded-2xl');
                    if (card) {
                        card.style.transition = 'all 0.3s ease';
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.9)';
                        setTimeout(() => {
                            card.remove();
                            // Reload page if no favorites left
                            if (document.querySelectorAll('.bg-white.rounded-2xl').length === 0) {
                                window.location.reload();
                            }
                        }, 300);
                    } else {
                        window.location.reload();
                    }
                } else {
                    showToast('Xóa thất bại, vui lòng thử lại', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Có lỗi xảy ra', 'error');
            }
        }
        
        // Show toast notification
        function showToast(message, type = 'success') {
            var toast = document.createElement('div');
            toast.className = 'toast';
            toast.innerHTML = `
                <div class="p-4 rounded-xl shadow-lg max-w-md ${type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'}">
                    <div class="flex items-center space-x-3">
                        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} text-xl"></i>
                        <span>${message}</span>
                        <button onclick="this.parentElement.parentElement.remove()" class="ml-auto text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 5000);
        }
        
        // Clear all modal
        function showClearAllModal() {
            document.getElementById('clearAllModal').classList.remove('hidden');
        }
        
        function closeClearAllModal() {
            document.getElementById('clearAllModal').classList.add('hidden');
        }
        
        // Quick booking
        let currentQuickCourtId = null;
        
        function quickBook(courtId, courtName) {
            currentQuickCourtId = courtId;
            document.getElementById('quickCourtId').value = courtId;
            document.getElementById('quickCourtName').innerText = courtName;
            
            // Set default date to tomorrow
            var tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            var dateStr = tomorrow.toISOString().split('T')[0];
            document.getElementById('quickBookingDate').value = dateStr;
            document.getElementById('quickBookingDate').min = dateStr;
            
            document.getElementById('quickBookModal').classList.remove('hidden');
        }
        
        function closeQuickBookModal() {
            document.getElementById('quickBookModal').classList.add('hidden');
            currentQuickCourtId = null;
        }
        
        // Update sort
        function updateSort() {
            var sortSelect = document.getElementById('sortSelect');
            if (sortSelect) {
                var url = new URL(window.location.href);
                url.searchParams.set('sort', sortSelect.value);
                url.searchParams.set('page', '1');
                window.location.href = url.toString();
            }
        }
        
        // Set min date for booking
        document.getElementById('quickBookingDate')?.addEventListener('change', function() {
            var selectedDate = new Date(this.value);
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selectedDate < today) {
                alert('Không thể đặt sân trong quá khứ');
                var tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                this.value = tomorrow.toISOString().split('T')[0];
            }
        });
        
        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList && e.target.classList.contains('bg-black')) {
                e.target.classList.add('hidden');
            }
        });
        
        // Share favorites (optional feature)
        function shareFavorites() {
            if (navigator.share) {
                navigator.share({
                    title: 'Sân yêu thích của tôi',
                    text: 'Hãy xem các sân cầu lông tôi yêu thích trên BadmintonPro!',
                    url: window.location.href
                }).catch(console.error);
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(window.location.href);
                showToast('Đã sao chép link chia sẻ', 'success');
            }
        }
        
        // Print favorites list
        function printFavorites() {
            window.print();
        }
    </script>
    
    <style>
        @media print {
            nav, .bg-gradient-to-r, .stat-card, .pagination, .recommendations, .toast, button, .card-hover:hover {
                display: none !important;
            }
            body {
                background: white;
            }
            .card-hover {
                transform: none !important;
                box-shadow: none !important;
                border: 1px solid #ddd;
            }
        }
    </style>
</body>
</html>