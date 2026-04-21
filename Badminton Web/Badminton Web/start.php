<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Badminton Web - Navigation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-50 to-purple-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4">
        <div class="bg-white rounded-lg shadow-2xl max-w-2xl w-full p-8">
            <div class="text-center mb-8">
                <i class="fas fa-table-tennis text-blue-600 text-5xl mb-4"></i>
                <h1 class="text-4xl font-bold text-gray-800 mb-2">🏓 Badminton Web</h1>
                <p class="text-gray-600 text-lg">Hệ Thống Đặt Sân Cầu Lông</p>
            </div>

            <div class="bg-blue-50 border-l-4 border-blue-600 p-4 mb-8 rounded">
                <p class="text-blue-800">
                    <i class="fas fa-info-circle mr-2"></i>
                    <strong>Chọn trang bạn muốn truy cập:</strong>
                </p>
            </div>

            <div class="grid grid-cols-1 gap-4 mb-8">
                <!-- MAIN: Complete Setup (Featured) -->
                <div class="bg-gradient-to-r from-orange-100 to-red-100 border-2 border-orange-500 p-6 rounded-lg shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-2xl font-bold text-gray-800 mb-2">⚡ ONE-CLICK SETUP ⚡</h3>
                            <p class="text-gray-700">Migration + Test Data + Verify - TẤT CẢ TRONG MỘT NÚT BẤM</p>
                        </div>
                        <a href="setup_complete.php" class="px-8 py-3 bg-red-600 hover:bg-red-700 text-white font-bold text-lg rounded-lg transition">
                            BẮT ĐẦU →
                        </a>
                    </div>
                </div>

                <!-- Main Application -->
                <a href="index.php" class="block p-6 border-2 border-gray-200 rounded-lg hover:border-blue-500 hover:shadow-lg transition duration-300 group">
                    <div class="flex items-center">
                        <i class="fas fa-home text-blue-600 text-3xl mr-4 group-hover:scale-110 transition"></i>
                        <div>
                            <h3 class="text-xl font-bold text-gray-800">Trang Chính</h3>
                            <p class="text-gray-600 text-sm">Đặt sân cầu lông, đăng nhập, đăng ký</p>
                        </div>
                    </div>
                </a>

                <!-- Setup Dashboard -->
                <a href="setup.php" class="block p-6 border-2 border-gray-200 rounded-lg hover:border-green-500 hover:shadow-lg transition duration-300 group">
                    <div class="flex items-center">
                        <i class="fas fa-wrench text-green-600 text-3xl mr-4 group-hover:scale-110 transition"></i>
                        <div>
                            <h3 class="text-xl font-bold text-gray-800">Setup Database</h3>
                            <p class="text-gray-600 text-sm">Kiểm tra & sửa lỗi cơ sở dữ liệu</p>
                        </div>
                    </div>
                </a>

                <!-- Court Details Page -->
                <a href="court.php?id=1" class="block p-6 border-2 border-gray-200 rounded-lg hover:border-purple-500 hover:shadow-lg transition duration-300 group">
                    <div class="flex items-center">
                        <i class="fas fa-calendar-check text-purple-600 text-3xl mr-4 group-hover:scale-110 transition"></i>
                        <div>
                            <h3 class="text-xl font-bold text-gray-800">Chi Tiết Sân</h3>
                            <p class="text-gray-600 text-sm">Xem thông tin & đặt sân cụ thể</p>
                        </div>
                    </div>
                </a>

                <!-- Admin Frontend -->
                <a href="admin_frontend/index.php" class="block p-6 border-2 border-gray-200 rounded-lg hover:border-orange-500 hover:shadow-lg transition duration-300 group">
                    <div class="flex items-center">
                        <i class="fas fa-lock text-orange-600 text-3xl mr-4 group-hover:scale-110 transition"></i>
                        <div>
                            <h3 class="text-xl font-bold text-gray-800">Admin Panel</h3>
                            <p class="text-gray-600 text-sm">Quản lý hệ thống (chỉ admin)</p>
                        </div>
                    </div>
                </a>

                <!-- Test Connection -->
                <a href="test_connection.php" class="block p-6 border-2 border-gray-200 rounded-lg hover:border-red-500 hover:shadow-lg transition duration-300 group">
                    <div class="flex items-center">
                        <i class="fas fa-plug text-red-600 text-3xl mr-4 group-hover:scale-110 transition"></i>
                        <div>
                            <h3 class="text-xl font-bold text-gray-800">Test Database</h3>
                            <p class="text-gray-600 text-sm">Kiểm tra kết nối cơ sở dữ liệu</p>
                        </div>
                    </div>
                </a>

                <!-- MySQL Config -->
                <a href="mysql_config.php" class="block p-6 border-2 border-gray-200 rounded-lg hover:border-indigo-500 hover:shadow-lg transition duration-300 group">
                    <div class="flex items-center">
                        <i class="fas fa-database text-indigo-600 text-3xl mr-4 group-hover:scale-110 transition"></i>
                        <div>
                            <h3 class="text-xl font-bold text-gray-800">MySQL Config</h3>
                            <p class="text-gray-600 text-sm">Cấu hình & kiểm tra MySQL</p>
                        </div>
                    </div>
                </a>

                <!-- Complete Setup UI -->
                <a href="db_setup_ui.php" class="block p-6 border-2 border-gray-200 rounded-lg hover:border-pink-500 hover:shadow-lg transition duration-300 group">
                    <div class="flex items-center">
                        <i class="fas fa-sliders-h text-pink-600 text-3xl mr-4 group-hover:scale-110 transition"></i>
                        <div>
                            <h3 class="text-xl font-bold text-gray-800">⚡ Complete Setup</h3>
                            <p class="text-gray-600 text-sm">Migration + Seed Data + Verify (Tất cả trong một trang)</p>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Info Section -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
                <h3 class="text-lg font-bold text-yellow-800 mb-3">
                    <i class="fas fa-lightbulb mr-2"></i> Khắc Phục Nhanh
                </h3>
                <div class="text-yellow-900 space-y-2 text-sm">
                    <p><strong>🎯 ⭐ BẮT ĐẦU TỪ ĐÂY:</strong> → Xem <strong><a href="quick_start_ui.php" style="color: #dc2626;">QUICK START</a></strong> hoặc <strong><a href="SETUP_CHECKLIST.md" style="color: #dc2626;">SETUP CHECKLIST</a></strong></p>
                    <p><strong>① Lỗi Database?</strong> → Nhấp "Setup Database" ở trên</p>
                    <p><strong>② Không đăng nhập được?</strong> → Chạy Setup rồi thử lại</p>
                    <p><strong>③ Cần kiểm tra kết nối?</strong> → Nhấp "Test Database"</p>
                    <p><strong>④ Muốn reset toàn bộ?</strong> → Vào Setup & chọn "Reset Database"</p>
                    <p><strong>⑤ Dùng MySQL?</strong> → Xem <strong><a href="mysql_config.php" style="color: #dc2626;">MYSQL CONFIG</a></strong> hoặc <strong>MYSQL_MIGRATION_NOTE.txt</strong></p>
                    <p><strong>⑥ Lỗi owner_id?</strong> → Xem <strong>FIX_OWNER_ID.md</strong></p>
                    <p><strong>⑦ Tóm tắt all fixes?</strong> → Xem <strong>QUICK_FIX_SUMMARY.md</strong></p>
                </div>
            </div>

            <!-- Credentials -->
            <div class="bg-green-50 border border-green-200 rounded-lg p-6 mt-6">
                <h3 class="text-lg font-bold text-green-800 mb-3">
                    <i class="fas fa-key mr-2"></i> Tài Khoản Mặc Định
                </h3>
                <div class="text-green-900 space-y-1 text-sm font-mono">
                    <p><strong>Username:</strong> admin</p>
                    <p><strong>Password:</strong> admin123</p>
                    <p class="text-xs mt-2 italic">(Đổi mật khẩu sau khi đăng nhập)</p>
                </div>
            </div>

            <!-- Debug Info -->
            <div class="bg-gray-100 rounded-lg p-4 mt-6 text-xs text-gray-700">
                <p><strong>📁 Thư mục:</strong> <?php echo __DIR__; ?></p>
                <p><strong>🖥️  Server:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></p>
                <p><strong>🐘 PHP:</strong> <?php echo phpversion(); ?></p>
            </div>
        </div>
    </div>
</body>
</html>
