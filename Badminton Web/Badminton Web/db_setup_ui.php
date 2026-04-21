<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Badminton Web</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-50 to-purple-50 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-xl p-8">
            <div class="text-center mb-8">
                <i class="fas fa-database text-blue-600 text-5xl mb-4"></i>
                <h1 class="text-4xl font-bold text-gray-800">🗄️ Database Setup</h1>
                <p class="text-gray-600 text-lg mt-2">Badminton Court Booking System</p>
            </div>

            <!-- Status Card -->
            <div class="bg-blue-50 border-l-4 border-blue-600 p-4 mb-8 rounded">
                <h2 class="text-lg font-bold text-blue-900 mb-4">📋 Công Việc Cần Làm</h2>
                <div class="space-y-3">
                    <!-- Migration -->
                    <div class="bg-white p-4 rounded border-l-4 border-orange-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-bold text-gray-800">① Fix Columns</h3>
                                <p class="text-sm text-gray-600">Thêm hoặc sửa các cột còn thiếu trong database</p>
                            </div>
                            <button onclick="runMigration()" class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2 rounded font-bold">
                                <i class="fas fa-tools mr-2"></i> Chạy
                            </button>
                        </div>
                        <div id="migration-status" class="mt-3 hidden"></div>
                    </div>

                    <!-- Seed Data -->
                    <div class="bg-white p-4 rounded border-l-4 border-green-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-bold text-gray-800">② Thêm Test Data</h3>
                                <p class="text-sm text-gray-600">Thêm dữ liệu test để kiểm tra hệ thống</p>
                            </div>
                            <button onclick="runSeedData()" class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded font-bold">
                                <i class="fas fa-plus-circle mr-2"></i> Chạy
                            </button>
                        </div>
                        <div id="seed-status" class="mt-3 hidden"></div>
                    </div>

                    <!-- Verify -->
                    <div class="bg-white p-4 rounded border-l-4 border-purple-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-bold text-gray-800">③ Kiểm Tra Kết Nối</h3>
                                <p class="text-sm text-gray-600">Kiểm tra database đã được setup đúng</p>
                            </div>
                            <button onclick="verifyConnection()" class="bg-purple-500 hover:bg-purple-600 text-white px-6 py-2 rounded font-bold">
                                <i class="fas fa-check-circle mr-2"></i> Kiểm Tra
                            </button>
                        </div>
                        <div id="verify-status" class="mt-3 hidden"></div>
                    </div>

                    <!-- Reset -->
                    <div class="bg-white p-4 rounded border-l-4 border-red-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-bold text-gray-800">④ Reset Database (Nếu cần)</h3>
                                <p class="text-sm text-gray-600">Xóa toàn bộ dữ liệu và reset database</p>
                            </div>
                            <button onclick="resetDatabase()" class="bg-red-500 hover:bg-red-600 text-white px-6 py-2 rounded font-bold">
                                <i class="fas fa-redo mr-2"></i> Reset
                            </button>
                        </div>
                        <div id="reset-status" class="mt-3 hidden"></div>
                    </div>
                </div>
            </div>

            <!-- Output Log -->
            <div class="bg-gray-900 text-green-400 p-4 rounded font-mono text-sm h-96 overflow-y-auto">
                <div id="output-log">
                    <div class="text-yellow-400">⏳ Chờ lệnh...</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="mt-8 grid grid-cols-2 gap-4">
                <a href="index.php" class="block p-4 bg-blue-100 hover:bg-blue-200 rounded text-center">
                    <i class="fas fa-home text-blue-600 text-2xl mb-2"></i>
                    <p class="font-bold text-gray-800">Trang Chính</p>
                </a>
                <a href="start.php" class="block p-4 bg-purple-100 hover:bg-purple-200 rounded text-center">
                    <i class="fas fa-bars text-purple-600 text-2xl mb-2"></i>
                    <p class="font-bold text-gray-800">Settings</p>
                </a>
            </div>

            <!-- Login Info -->
            <div class="mt-8 p-4 bg-green-50 border border-green-300 rounded">
                <h3 class="font-bold text-green-800 mb-2">📝 Tài Khoản Test</h3>
                <div class="text-sm text-green-900 space-y-1 font-mono">
                    <p><strong>Admin:</strong> admin / admin123</p>
                    <p><strong>Customer:</strong> testuser / test123</p>
                    <p><strong>Owner:</strong> owner1 / owner123</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const logElement = document.getElementById('output-log');

        function addLog(message, type = 'info') {
            const timestamp = new Date().toLocaleTimeString('vi-VN');
            const color = type === 'error' ? 'text-red-400' : type === 'success' ? 'text-green-400' : 'text-blue-400';
            logElement.innerHTML += `<div class="${color}">[${timestamp}] ${escapeHtml(message)}</div>`;
            logElement.scrollTop = logElement.scrollHeight;
        }

        function escapeHtml(text) {
            const map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
            return text.replace(/[&<>"']/g, char => map[char]);
        }

        async function runMigration() {
            logElement.innerHTML = '';
            addLog('🔧 Đang chạy migration...', 'info');
            showStatus('migration', 'Đang chạy...', 'blue');

            try {
                const response = await fetch('api_migrate_fix_columns.php');
                const data = await response.json();

                data.messages.forEach(msg => {
                    addLog(msg, 'info');
                });

                if (data.errors.length > 0) {
                    data.errors.forEach(err => {
                        addLog('❌ ' + err, 'error');
                    });
                }

                showStatus('migration', data.success ? '✅ Hoàn thành' : '❌ Lỗi', data.success ? 'green' : 'red');
            } catch (error) {
                addLog('❌ Lỗi: ' + error.message, 'error');
                showStatus('migration', '❌ Lỗi', 'red');
            }
        }

        async function runSeedData() {
            logElement.innerHTML = '';
            addLog('🌱 Đang thêm test data...', 'info');
            showStatus('seed', 'Đang chạy...', 'blue');

            try {
                const response = await fetch('api_seed_test_data.php');
                const data = await response.json();

                data.messages.forEach(msg => {
                    addLog(msg, 'info');
                });

                if (data.errors.length > 0) {
                    data.errors.forEach(err => {
                        addLog('❌ ' + err, 'error');
                    });
                }

                showStatus('seed', data.success ? '✅ Hoàn thành' : '❌ Lỗi', data.success ? 'green' : 'red');
            } catch (error) {
                addLog('❌ Lỗi: ' + error.message, 'error');
                showStatus('seed', '❌ Lỗi', 'red');
            }
        }

        async function verifyConnection() {
            logElement.innerHTML = '';
            addLog('🔍 Đang kiểm tra kết nối...', 'info');
            showStatus('verify', 'Đang kiểm tra...', 'blue');

            try {
                const response = await fetch('test_connection.php');
                const data = await response.text();

                addLog(data, 'info');
                showStatus('verify', '✅ Hoàn thành', 'green');
            } catch (error) {
                addLog('❌ Lỗi: ' + error.message, 'error');
                showStatus('verify', '❌ Lỗi', 'red');
            }
        }

        async function resetDatabase() {
            if (!confirm('⚠️ Bạn chắc chắn muốn xóa toàn bộ dữ liệu? Hành động này không thể hoàn tác!')) {
                return;
            }

            logElement.innerHTML = '';
            addLog('⚠️ Đang reset database...', 'error');
            showStatus('reset', 'Đang chạy...', 'blue');

            try {
                const response = await fetch('setup_database.php');
                const data = await response.text();

                // Extract text content from HTML
                const div = document.createElement('div');
                div.innerHTML = data;
                const text = div.innerText || div.textContent;

                addLog(text, 'info');
                showStatus('reset', '✅ Hoàn thành', 'green');
            } catch (error) {
                addLog('❌ Lỗi: ' + error.message, 'error');
                showStatus('reset', '❌ Lỗi', 'red');
            }
        }

        function showStatus(id, message, color) {
            const statusEl = document.getElementById(id + '-status');
            statusEl.className = `mt-3 p-2 rounded font-bold text-${color}-700 bg-${color}-100`;
            statusEl.textContent = message;
            statusEl.classList.remove('hidden');
        }

        // Run all in sequence
        async function runAllSetup() {
            await runMigration();
            await new Promise(r => setTimeout(r, 1000));
            await runSeedData();
            await new Promise(r => setTimeout(r, 1000));
            await verifyConnection();
        }

        // Show welcome message
        addLog('✅ Database Setup Tool Ready', 'success');
        addLog('Nhấp vào các nút để chạy các công việc', 'info');
    </script>
</body>
</html>
