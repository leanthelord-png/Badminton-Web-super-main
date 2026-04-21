<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MySQL Configuration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-12 px-4">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg shadow-lg p-8 mb-8 text-white">
                <div class="flex items-center mb-4">
                    <i class="fas fa-database text-5xl mr-4"></i>
                    <div>
                        <h1 class="text-4xl font-bold">MySQL Configuration</h1>
                        <p class="text-blue-100">Cấu hình kết nối MySQL cho Badminton Web</p>
                    </div>
                </div>
            </div>

            <!-- Current Configuration -->
            <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-check-circle text-green-600 mr-2"></i> Cấu Hình Hiện Tại
                </h2>
                
                <div class="bg-gray-50 rounded-lg p-6 font-mono text-sm space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Host:</span>
                        <span class="font-bold text-gray-800"><?php echo 'localhost' ?></span>
                    </div>
                    <div class="flex justify-between items-center border-t pt-3">
                        <span class="text-gray-600">Port:</span>
                        <span class="font-bold text-gray-800"><?php echo '3306' ?></span>
                    </div>
                    <div class="flex justify-between items-center border-t pt-3">
                        <span class="text-gray-600">Database:</span>
                        <span class="font-bold text-gray-800"><?php echo 'badminton_web' ?></span>
                    </div>
                    <div class="flex justify-between items-center border-t pt-3">
                        <span class="text-gray-600">Username:</span>
                        <span class="font-bold text-gray-800"><?php echo 'root' ?></span>
                    </div>
                    <div class="flex justify-between items-center border-t pt-3">
                        <span class="text-gray-600">Password:</span>
                        <span class="font-bold text-gray-800">•••••••••</span>
                    </div>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-6">
                    <p class="text-blue-900">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong>Lưu ý:</strong> Thay đổi trong file <code class="bg-white px-2 py-1 rounded">config/database.php</code>
                    </p>
                </div>
            </div>

            <!-- Quick Test -->
            <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-plug text-orange-600 mr-2"></i> Kiểm Tra Kết Nối
                </h2>

                <button onclick="testConnection()" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300 flex items-center justify-center">
                    <i class="fas fa-sync mr-2"></i> Test Kết Nối MySQL
                </button>

                <div id="test-result" class="hidden mt-6 p-4 rounded-lg">
                    <pre id="result-text" class="text-sm font-mono whitespace-pre-wrap"></pre>
                </div>
            </div>

            <!-- Configuration Guide -->
            <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-cog text-gray-600 mr-2"></i> Hướng Dẫn Cấu Hình
                </h2>

                <div class="space-y-6">
                    <!-- MySQL Running Check -->
                    <div class="border-l-4 border-blue-500 pl-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-2">
                            1️⃣ Kiểm Tra MySQL Đang Chạy
                        </h3>
                        <p class="text-gray-600 mb-3">Chạy lệnh trong Command Prompt/Terminal:</p>
                        <div class="bg-gray-900 text-green-400 p-4 rounded-lg font-mono text-sm overflow-x-auto">
                            <code>mysql -h localhost -u root -P 3306 badminton_web</code>
                        </div>
                        <p class="text-gray-600 mt-3 text-sm">Nhập password MySQL của bạn nếu có</p>
                    </div>

                    <!-- MySQL Remote Server -->
                    <div class="border-l-4 border-purple-500 pl-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-2">
                            2️⃣ MySQL Trên Server Khác
                        </h3>
                        <p class="text-gray-600 mb-3">Nếu MySQL chạy trên máy khác, sửa file <code class="bg-gray-100 px-2 py-1 rounded">config/database.php</code>:</p>
                        <div class="bg-gray-100 p-4 rounded-lg font-mono text-sm">
                            <code>
&lt;?php<br>
$host = '<span class="text-red-600">192.168.1.100</span>';  // ← IP server MySQL<br>
$port = '3306';<br>
$dbname = 'badminton_web';<br>
$user = 'root';<br>
$password = '';<br>
                            </code>
                        </div>
                    </div>

                    <!-- MySQL Port -->
                    <div class="border-l-4 border-green-500 pl-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-2">
                            3️⃣ MySQL Port Khác (Không 3306)
                        </h3>
                        <p class="text-gray-600 mb-3">Nếu port không phải 3306, thay đổi trong <code class="bg-gray-100 px-2 py-1 rounded">config/database.php</code>:</p>
                        <div class="bg-gray-100 p-4 rounded-lg font-mono text-sm">
                            <code>
$port = '<span class="text-red-600">3307</span>';  // ← Thay port khác
                            </code>
                        </div>
                    </div>

                    <!-- Custom Credentials -->
                    <div class="border-l-4 border-orange-500 pl-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-2">
                            4️⃣ Username/Password Khác
                        </h3>
                        <p class="text-gray-600 mb-3">Nếu dùng user MySQL khác, cập nhật trong <code class="bg-gray-100 px-2 py-1 rounded">config/database.php</code>:</p>
                        <div class="bg-gray-100 p-4 rounded-lg font-mono text-sm">
                            <code>
$user = '<span class="text-red-600">your_username</span>';<br>
$password = '<span class="text-red-600">your_password</span>';<br>
                            </code>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MySQL Commands -->
            <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-terminal text-gray-600 mr-2"></i> Các Lệnh MySQL Hữu Ích
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-bold text-gray-800 mb-2">Kết Nối Database</h4>
                        <code class="bg-gray-900 text-green-400 p-3 rounded block font-mono text-xs overflow-x-auto">mysql -u root -p badminton_web</code>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-bold text-gray-800 mb-2">Danh Sách Database</h4>
                        <code class="bg-gray-900 text-green-400 p-3 rounded block font-mono text-xs overflow-x-auto">SHOW DATABASES;</code>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-bold text-gray-800 mb-2">Danh Sách Users</h4>
                        <code class="bg-gray-900 text-green-400 p-3 rounded block font-mono text-xs overflow-x-auto">SELECT User, Host FROM mysql.user;</code>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-bold text-gray-800 mb-2">Danh Sách Tables</h4>
                        <code class="bg-gray-900 text-green-400 p-3 rounded block font-mono text-xs overflow-x-auto">SHOW TABLES;</code>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-bold text-gray-800 mb-2">Đổi Password</h4>
                        <code class="bg-gray-900 text-green-400 p-3 rounded block font-mono text-xs overflow-x-auto">ALTER USER 'root'@'localhost' IDENTIFIED BY 'new_pass';</code>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-bold text-gray-800 mb-2">Thoát MySQL</h4>
                        <code class="bg-gray-900 text-green-400 p-3 rounded block font-mono text-xs overflow-x-auto">exit</code>
                    </div>
                </div>
            </div>

            <!-- Next Steps -->
            <div class="bg-gradient-to-r from-green-50 to-blue-50 border border-green-200 rounded-lg shadow-lg p-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-arrow-right text-green-600 mr-2"></i> Bước Tiếp Theo
                </h2>

                <ol class="space-y-3 text-lg">
                    <li class="flex items-start">
                        <span class="bg-green-600 text-white rounded-full w-8 h-8 flex items-center justify-center mr-3 flex-shrink-0">1</span>
                        <span class="text-gray-700">✅ Kiểm tra cấu hình <code class="bg-white px-2 py-1 rounded">config/database.php</code></span>
                    </li>
                    <li class="flex items-start">
                        <span class="bg-green-600 text-white rounded-full w-8 h-8 flex items-center justify-center mr-3 flex-shrink-0">2</span>
                        <span class="text-gray-700">✅ Nhấp button "Test Kết Nối MySQL" ở trên</span>
                    </li>
                    <li class="flex items-start">
                        <span class="bg-green-600 text-white rounded-full w-8 h-8 flex items-center justify-center mr-3 flex-shrink-0">3</span>
                        <span class="text-gray-700">✅ Chạy <a href="setup.php" class="text-blue-600 hover:underline">Setup Database</a> để tạo tables</span>
                    </li>
                    <li class="flex items-start">
                        <span class="bg-green-600 text-white rounded-full w-8 h-8 flex items-center justify-center mr-3 flex-shrink-0">4</span>
                        <span class="text-gray-700">✅ Quay lại <a href="index.php" class="text-blue-600 hover:underline">Trang Chính</a> và đăng nhập</span>
                    </li>
                </ol>
            </div>

            <!-- Back Button -->
            <div class="text-center mt-8">
                <a href="start.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 font-semibold text-lg">
                    <i class="fas fa-arrow-left mr-2"></i> Quay Lại Trang Navigation
                </a>
            </div>
        </div>
    </div>

    <script>
        async function testConnection() {
            const btn = event.target;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Đang kiểm tra...';

            try {
                const response = await fetch('test_connection.php');
                const text = await response.text();
                
                document.getElementById('test-result').classList.remove('hidden');
                document.getElementById('result-text').textContent = text;

                if (text.includes('✓') && text.includes('thành công')) {
                    document.getElementById('test-result').className = 'hidden mt-6 p-4 rounded-lg bg-green-100 border border-green-300';
                } else if (text.includes('❌') || text.includes('thất bại')) {
                    document.getElementById('test-result').className = 'hidden mt-6 p-4 rounded-lg bg-red-100 border border-red-300';
                    document.getElementById('result-text').className = 'text-sm font-mono whitespace-pre-wrap text-red-800';
                }
                
                document.getElementById('test-result').classList.remove('hidden');
            } catch (error) {
                document.getElementById('test-result').classList.remove('hidden');
                document.getElementById('test-result').className = 'hidden mt-6 p-4 rounded-lg bg-red-100 border border-red-300';
                document.getElementById('result-text').textContent = 'Lỗi: ' + error.message;
                document.getElementById('result-text').className = 'text-sm font-mono whitespace-pre-wrap text-red-800';
                document.getElementById('test-result').classList.remove('hidden');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sync mr-2"></i> Test Kết Nối MySQL';
            }
        }
    </script>
</body>
</html>
