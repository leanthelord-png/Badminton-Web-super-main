<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup & Migration Database</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4">
        <div class="bg-white rounded-lg shadow-lg max-w-2xl w-full p-8">
            <div class="text-center mb-8">
                <i class="fas fa-database text-blue-600 text-5xl mb-4"></i>
                <h1 class="text-4xl font-bold text-gray-800 mb-2">🔧 Database Setup</h1>
                <p class="text-gray-600">Kiểm tra và khôi phục cơ sở dữ liệu</p>
            </div>

            <div class="space-y-6">
                <!-- Status Check Section -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                    <h2 class="text-xl font-bold text-blue-800 mb-4">
                        <i class="fas fa-heartbeat mr-2"></i> Kiểm Tra Trạng Thái
                    </h2>
                    <div id="status-container" class="space-y-3">
                        <p class="text-gray-600">Đang kiểm tra...</p>
                    </div>
                </div>

                <!-- Actions Section -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <button onclick="runMigration()" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300 flex items-center justify-center">
                        <i class="fas fa-sync mr-2"></i> Chạy Migration
                    </button>
                    <button onclick="resetDatabase()" class="bg-orange-600 hover:bg-orange-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300 flex items-center justify-center">
                        <i class="fas fa-refresh mr-2"></i> Reset Database
                    </button>
                </div>

                <!-- Result Section -->
                <div id="result-container" class="hidden bg-gray-50 border border-gray-200 rounded-lg p-6">
                    <pre id="result-text" class="text-sm text-gray-700 whitespace-pre-wrap font-mono overflow-auto max-h-96"></pre>
                </div>

                <!-- Help Section -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
                    <h3 class="text-lg font-bold text-yellow-800 mb-3">
                        <i class="fas fa-exclamation-circle mr-2"></i> Lưu Ý
                    </h3>
                    <ul class="text-yellow-900 space-y-2 text-sm">
                        <li>✓ Migration sẽ tự động thêm các cột bị thiếu</li>
                        <li>✓ Không ảnh hưởng đến dữ liệu hiện có</li>
                        <li>✓ Hãy chạy Migration trước khi sử dụng ứng dụng</li>
                        <li>⚠ Reset Database sẽ xóa TẤT CẢ dữ liệu</li>
                    </ul>
                </div>
            </div>

            <div class="mt-8 text-center">
                <a href="index.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 font-semibold">
                    <i class="fas fa-arrow-left mr-2"></i> Quay lại Trang Chính
                </a>
            </div>
        </div>
    </div>

    <script>
        // Check database status on page load
        document.addEventListener('DOMContentLoaded', checkStatus);

        async function checkStatus() {
            try {
                const response = await fetch('api_check_database.php');
                const data = await response.json();
                displayStatusResults(data);
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('status-container').innerHTML = 
                    '<p class="text-red-600">❌ Lỗi: Không thể kết nối đến máy chủ</p>';
            }
        }

        function displayStatusResults(data) {
            const container = document.getElementById('status-container');
            let html = '';
            
            if (data.database_connected) {
                html += '<p class="text-green-600"><i class="fas fa-check-circle mr-2"></i> ✓ Database kết nối thành công</p>';
            } else {
                html += '<p class="text-red-600"><i class="fas fa-times-circle mr-2"></i> ❌ Database không kết nối</p>';
            }

            if (data.tables_exist) {
                html += '<p class="text-green-600"><i class="fas fa-check-circle mr-2"></i> ✓ Bảng dữ liệu tồn tại</p>';
            } else {
                html += '<p class="text-red-600"><i class="fas fa-times-circle mr-2"></i> ❌ Bảng dữ liệu bị thiếu</p>';
            }

            if (data.columns) {
                for (const [col, exists] of Object.entries(data.columns)) {
                    if (exists) {
                        html += `<p class="text-green-600"><i class="fas fa-check-circle mr-2"></i> ✓ Cột: <strong>${col}</strong></p>`;
                    } else {
                        html += `<p class="text-red-600"><i class="fas fa-times-circle mr-2"></i> ❌ Cột thiếu: <strong>${col}</strong></p>`;
                    }
                }
            }

            container.innerHTML = html;
        }

        async function runMigration() {
            const btn = event.target;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Đang xử lý...';

            try {
                const response = await fetch('api_run_migration.php');
                const text = await response.text();
                
                document.getElementById('result-container').classList.remove('hidden');
                document.getElementById('result-text').textContent = text;

                // Recheck status after migration
                setTimeout(checkStatus, 2000);
            } catch (error) {
                document.getElementById('result-text').textContent = 'Lỗi: ' + error.message;
                document.getElementById('result-container').classList.remove('hidden');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sync mr-2"></i> Chạy Migration';
            }
        }

        async function resetDatabase() {
            if (!confirm('⚠️  Bạn có chắc chắn muốn reset database? TẤT CẢ dữ liệu sẽ bị xóa!')) {
                return;
            }

            const btn = event.target;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Đang xử lý...';

            try {
                const response = await fetch('api_reset_database.php');
                const text = await response.text();
                
                document.getElementById('result-container').classList.remove('hidden');
                document.getElementById('result-text').textContent = text;

                // Recheck status after reset
                setTimeout(checkStatus, 2000);
            } catch (error) {
                document.getElementById('result-text').textContent = 'Lỗi: ' + error.message;
                document.getElementById('result-container').classList.remove('hidden');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-refresh mr-2"></i> Reset Database';
            }
        }
    </script>
</body>
</html>
