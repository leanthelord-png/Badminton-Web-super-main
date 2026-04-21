<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Start - Badminton Web</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-50 to-purple-50">
    <div class="max-w-5xl mx-auto py-8 px-4">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
            <div class="flex items-center mb-4">
                <i class="fas fa-rocket text-red-600 text-4xl mr-4"></i>
                <h1 class="text-4xl font-bold text-gray-800">Quick Start Guide</h1>
            </div>
            <p class="text-gray-600 text-lg">Bắt đầu nhanh với Badminton Web</p>
        </div>

        <!-- Main Action -->
        <div class="bg-gradient-to-r from-orange-100 to-red-100 border-2 border-orange-500 rounded-lg p-8 mb-8 shadow-lg">
            <h2 class="text-3xl font-bold text-gray-800 mb-4">⚡ FASTEST WAY (Khuyên dùng)</h2>
            <div class="bg-white p-6 rounded-lg mb-4">
                <h3 class="text-xl font-bold text-gray-800 mb-4">3 bước đơn giản</h3>
                <div class="space-y-4">
                    <div class="flex items-start">
                        <div class="bg-orange-500 text-white rounded-full w-10 h-10 flex items-center justify-center font-bold mr-4 flex-shrink-0">1</div>
                        <div>
                            <p class="font-bold text-gray-800">Mở One-Click Setup</p>
                            <a href="setup_complete.php" class="text-blue-600 hover:underline font-mono text-sm">setup_complete.php</a>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="bg-orange-500 text-white rounded-full w-10 h-10 flex items-center justify-center font-bold mr-4 flex-shrink-0">2</div>
                        <div>
                            <p class="font-bold text-gray-800">Nhấp "LÀM NGAY"</p>
                            <p class="text-gray-600">Chờ 10-20 giây, hệ thống sẽ:</p>
                            <ul class="text-sm text-gray-600 ml-4 mt-2">
                                <li>✅ Thêm cột thiếu</li>
                                <li>✅ Thêm dữ liệu test</li>
                                <li>✅ Kiểm tra kết nối</li>
                            </ul>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="bg-orange-500 text-white rounded-full w-10 h-10 flex items-center justify-center font-bold mr-4 flex-shrink-0">3</div>
                        <div>
                            <p class="font-bold text-gray-800">Truy cập ứng dụng</p>
                            <a href="index.php" class="text-blue-600 hover:underline font-mono text-sm">index.php</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="text-center">
                <a href="setup_complete.php" class="inline-block bg-red-600 hover:bg-red-700 text-white px-10 py-4 rounded-lg font-bold text-xl">
                    <i class="fas fa-flash mr-2"></i> LÀM NGAY
                </a>
            </div>
        </div>

        <!-- Test Accounts -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">📝 TEST ACCOUNTS</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-200">
                            <th class="text-left p-3 font-bold">Role</th>
                            <th class="text-left p-3 font-bold">Username</th>
                            <th class="text-left p-3 font-bold">Password</th>
                            <th class="text-left p-3 font-bold">Purpose</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr class="hover:bg-gray-50">
                            <td class="p-3"><span class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm font-bold">Admin</span></td>
                            <td class="p-3 font-mono">admin</td>
                            <td class="p-3 font-mono">admin123</td>
                            <td class="p-3 text-gray-600">Quản lý hệ thống</td>
                        </tr>
                        <tr class="hover:bg-gray-50">
                            <td class="p-3"><span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-bold">Customer</span></td>
                            <td class="p-3 font-mono">testuser</td>
                            <td class="p-3 font-mono">test123</td>
                            <td class="p-3 text-gray-600">Đặt sân, nạp tiền</td>
                        </tr>
                        <tr class="hover:bg-gray-50">
                            <td class="p-3"><span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-bold">Owner</span></td>
                            <td class="p-3 font-mono">owner1</td>
                            <td class="p-3 font-mono">owner123</td>
                            <td class="p-3 text-gray-600">Quản lý sân</td>
                        </tr>
                        <tr class="hover:bg-gray-50">
                            <td class="p-3"><span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-bold">Owner</span></td>
                            <td class="p-3 font-mono">owner2</td>
                            <td class="p-3 font-mono">owner123</td>
                            <td class="p-3 text-gray-600">Quản lý sân</td>
                        </tr>
                        <tr class="hover:bg-gray-50">
                            <td class="p-3"><span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-sm font-bold">Staff</span></td>
                            <td class="p-3 font-mono">staff</td>
                            <td class="p-3 font-mono">staff123</td>
                            <td class="p-3 text-gray-600">Hỗ trợ khách hàng</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Alternative Methods -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">🔧 STEP-BY-STEP (Nếu cần kiểm soát)</h2>
            <div class="grid md:grid-cols-2 gap-6">
                <div class="border-2 border-blue-300 rounded-lg p-4 hover:shadow-lg transition">
                    <h3 class="font-bold text-blue-800 mb-3">Option A: Manual UI</h3>
                    <p class="text-gray-600 mb-3">Chạy từng bước riêng, xem chi tiết log</p>
                    <a href="db_setup_ui.php" class="block p-3 bg-blue-600 text-white text-center rounded font-bold hover:bg-blue-700">
                        <i class="fas fa-tools mr-2"></i> Setup UI
                    </a>
                </div>
                <div class="border-2 border-purple-300 rounded-lg p-4 hover:shadow-lg transition">
                    <h3 class="font-bold text-purple-800 mb-3">Option B: Manual API Calls</h3>
                    <p class="text-gray-600 mb-3">Gọi từng API riêng lẻ</p>
                    <div class="space-y-2 text-sm">
                        <a href="api_migrate_fix_columns.php" class="text-blue-600 hover:underline block">→ Fix Columns</a>
                        <a href="api_seed_test_data.php" class="text-blue-600 hover:underline block">→ Seed Test Data</a>
                        <a href="test_connection.php" class="text-blue-600 hover:underline block">→ Test Connection</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Troubleshooting -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">🐛 TROUBLESHOOTING</h2>
            <div class="space-y-4">
                <details class="border border-gray-300 rounded-lg p-4 cursor-pointer hover:bg-gray-50">
                    <summary class="font-bold text-gray-800">❌ Lỗi kết nối MySQL</summary>
                    <div class="mt-3 text-gray-600">
                        <p><strong>Giải pháp:</strong></p>
                        <ol class="list-decimal list-inside space-y-2 mt-2">
                            <li>Kiểm tra MySQL đang chạy</li>
                            <li>Vào <a href="mysql_config.php" class="text-blue-600 hover:underline">mysql_config.php</a> để kiểm tra connection string</li>
                            <li>Đảm bảo cổng 3306 mở</li>
                        </ol>
                    </div>
                </details>
                <details class="border border-gray-300 rounded-lg p-4 cursor-pointer hover:bg-gray-50">
                    <summary class="font-bold text-gray-800">❌ Cột không tồn tại</summary>
                    <div class="mt-3 text-gray-600">
                        <p><strong>Giải pháp:</strong></p>
                        <ol class="list-decimal list-inside space-y-2 mt-2">
                            <li>Chạy lại <a href="setup_complete.php" class="text-blue-600 hover:underline">setup_complete.php</a></li>
                            <li>Nếu vẫn lỗi, vào db_setup_ui.php → "Reset Database"</li>
                        </ol>
                    </div>
                </details>
                <details class="border border-gray-300 rounded-lg p-4 cursor-pointer hover:bg-gray-50">
                    <summary class="font-bold text-gray-800">❌ Không đăng nhập được</summary>
                    <div class="mt-3 text-gray-600">
                        <p><strong>Giải pháp:</strong></p>
                        <ol class="list-decimal list-inside space-y-2 mt-2">
                            <li>Chạy <a href="api_seed_test_data.php" class="text-blue-600 hover:underline">api_seed_test_data.php</a> để thêm tài khoản test</li>
                            <li>Dùng testuser / test123 để test</li>
                        </ol>
                    </div>
                </details>
            </div>
        </div>

        <!-- Verification -->
        <div class="bg-green-50 border-2 border-green-500 rounded-lg p-8 mb-8">
            <h2 class="text-2xl font-bold text-green-800 mb-4">✅ VERIFICATION CHECKLIST</h2>
            <div class="space-y-2 text-gray-700">
                <p class="flex items-center"><input type="checkbox" class="mr-3"> Setup page load không lỗi</p>
                <p class="flex items-center"><input type="checkbox" class="mr-3"> Homepage hiển thị 4 sân</p>
                <p class="flex items-center"><input type="checkbox" class="mr-3"> Có thể login bằng testuser/test123</p>
                <p class="flex items-center"><input type="checkbox" class="mr-3"> Có thể xem chi tiết sân</p>
                <p class="flex items-center"><input type="checkbox" class="mr-3"> Có thể đặt sân (nếu đã login)</p>
                <p class="flex items-center"><input type="checkbox" class="mr-3"> Admin account hoạt động</p>
                <p class="flex items-center"><input type="checkbox" class="mr-3"> Owner account hoạt động</p>
            </div>
        </div>

        <!-- Navigation -->
        <div class="grid grid-cols-4 gap-4 mb-8">
            <a href="start.php" class="p-4 bg-purple-100 hover:bg-purple-200 rounded text-center transition">
                <i class="fas fa-home text-purple-600 text-2xl mb-2"></i>
                <p class="font-bold text-sm">Back</p>
            </a>
            <a href="index.php" class="p-4 bg-blue-100 hover:bg-blue-200 rounded text-center transition">
                <i class="fas fa-table-tennis text-blue-600 text-2xl mb-2"></i>
                <p class="font-bold text-sm">App</p>
            </a>
            <a href="setup_complete.php" class="p-4 bg-red-100 hover:bg-red-200 rounded text-center transition">
                <i class="fas fa-flash text-red-600 text-2xl mb-2"></i>
                <p class="font-bold text-sm">Setup</p>
            </a>
            <a href="test_connection.php" class="p-4 bg-green-100 hover:bg-green-200 rounded text-center transition">
                <i class="fas fa-plug text-green-600 text-2xl mb-2"></i>
                <p class="font-bold text-sm">Test</p>
            </a>
        </div>

        <!-- Footer -->
        <div class="text-center text-gray-600 text-sm">
            <p>Version 1.0 | Last Updated 2024</p>
            <p>Status: <span class="text-green-600 font-bold">✅ Ready to Use</span></p>
        </div>
    </div>
</body>
</html>
