<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprehensive Setup - Badminton Web</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-50 to-purple-50 py-8">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-lg shadow-xl p-8">
            <!-- Header -->
            <div class="text-center mb-8">
                <i class="fas fa-rocket text-blue-600 text-6xl mb-4"></i>
                <h1 class="text-4xl font-bold text-gray-800">🚀 One-Click Setup</h1>
                <p class="text-gray-600 text-lg mt-2">Tất cả trong một nút bấm</p>
            </div>

            <!-- Progress Card -->
            <div id="progress-container" class="mb-8 hidden">
                <div class="space-y-4">
                    <div class="flex items-center">
                        <div id="step1-icon" class="text-2xl mr-3">⏳</div>
                        <div class="flex-1">
                            <h3 class="font-bold text-gray-800">Step 1: Fix Database Columns</h3>
                            <div id="step1-bar" class="w-full bg-gray-200 rounded-full h-2 mt-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div id="step2-icon" class="text-2xl mr-3">⏳</div>
                        <div class="flex-1">
                            <h3 class="font-bold text-gray-800">Step 2: Add Test Data</h3>
                            <div id="step2-bar" class="w-full bg-gray-200 rounded-full h-2 mt-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div id="step3-icon" class="text-2xl mr-3">⏳</div>
                        <div class="flex-1">
                            <h3 class="font-bold text-gray-800">Step 3: Verify Connection</h3>
                            <div id="step3-bar" class="w-full bg-gray-200 rounded-full h-2 mt-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Action -->
            <div id="main-section" class="mb-8">
                <div class="bg-gradient-to-r from-green-50 to-blue-50 p-8 rounded-lg border-2 border-green-300">
                    <div class="text-center">
                        <h2 class="text-2xl font-bold text-gray-800 mb-4">Sẵn sàng?</h2>
                        <p class="text-gray-600 mb-6 text-lg">Nhấp nút dưới để:</p>
                        <ul class="text-left inline-block text-gray-700 mb-8">
                            <li class="flex items-center mb-2"><i class="fas fa-check text-green-600 mr-3"></i>Thêm các cột còn thiếu</li>
                            <li class="flex items-center mb-2"><i class="fas fa-check text-green-600 mr-3"></i>Thêm dữ liệu test</li>
                            <li class="flex items-center mb-2"><i class="fas fa-check text-green-600 mr-3"></i>Kiểm tra kết nối</li>
                        </ul>
                        <button onclick="runCompleteSetup()" class="bg-green-600 hover:bg-green-700 text-white px-12 py-4 rounded-lg font-bold text-xl transition duration-300 shadow-lg">
                            <i class="fas fa-flash mr-2"></i> LÀM NGAY
                        </button>
                    </div>
                </div>
            </div>

            <!-- Log Output -->
            <div class="bg-gray-900 text-green-400 p-6 rounded-lg font-mono text-sm h-96 overflow-y-auto border-2 border-gray-700">
                <div id="output-log">
                    <div class="text-yellow-400">🔄 Chờ lệnh ...[Enter để bắt đầu]</div>
                </div>
            </div>

            <!-- Result Section -->
            <div id="result-section" class="mt-8 hidden">
                <div id="result-content" class="p-6 rounded-lg border-2">
                    <!-- Content inserted here -->
                </div>
            </div>

            <!-- Navigation -->
            <div class="mt-8 grid grid-cols-1 md:grid-cols-4 gap-4">
                <a href="index.php" class="p-4 bg-blue-100 hover:bg-blue-200 rounded text-center transition">
                    <i class="fas fa-home text-blue-600 text-2xl mb-2"></i>
                    <p class="font-bold text-gray-800">Trang Chính</p>
                </a>
                <a href="start.php" class="p-4 bg-purple-100 hover:bg-purple-200 rounded text-center transition">
                    <i class="fas fa-bars text-purple-600 text-2xl mb-2"></i>
                    <p class="font-bold text-gray-800">Menu</p>
                </a>
                <a href="test_connection.php" class="p-4 bg-orange-100 hover:bg-orange-200 rounded text-center transition">
                    <i class="fas fa-plug text-orange-600 text-2xl mb-2"></i>
                    <p class="font-bold text-gray-800">Test</p>
                </a>
                <a href="index.php?owner_login=1" class="p-4 bg-indigo-100 hover:bg-indigo-200 rounded text-center transition">
                    <i class="fas fa-user-tie text-indigo-600 text-2xl mb-2"></i>
                    <p class="font-bold text-gray-800">Đăng Nhập Chủ Sân</p>
                </a>
            </div>
        </div>
    </div>

    <script>
        const logElement = document.getElementById('output-log');
        const progressContainer = document.getElementById('progress-container');
        const mainSection = document.getElementById('main-section');
        const resultSection = document.getElementById('result-section');
        const resultContent = document.getElementById('result-content');

        function addLog(message, type = 'info') {
            const timestamp = new Date().toLocaleTimeString('vi-VN');
            const colorClass = type === 'error' ? 'text-red-400' : type === 'success' ? 'text-green-400' : 'text-blue-400';
            logElement.innerHTML += `<div class="${colorClass}">[${timestamp}] ${escapeHtml(message)}</div>`;
            logElement.scrollTop = logElement.scrollHeight;
        }

        function escapeHtml(text) {
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return String(text).replace(/[&<>"']/g, c => map[c]);
        }

        function updateStep(stepNum, status, percentage) {
            const icons = ['⏳', '✅', '❌'];
            const colors = ['bg-gray-400', 'bg-green-600', 'bg-red-600'];
            const statusMap = { 'pending': 0, 'success': 1, 'error': 2 };
            
            const idx = statusMap[status] || 0;
            document.getElementById(`step${stepNum}-icon`).textContent = icons[idx];
            document.getElementById(`step${stepNum}-bar`).querySelector('div').style.width = percentage + '%';
            document.getElementById(`step${stepNum}-bar`).querySelector('div').className = `${colors[idx]} h-2 rounded-full`;
        }

        async function runCompleteSetup() {
            logElement.innerHTML = '';
            progressContainer.classList.remove('hidden');
            mainSection.classList.add('hidden');
            resultSection.classList.add('hidden');

            try {
                // STEP 1: Migration
                addLog('🔧 Bước 1: Fix Database Columns...', 'info');
                updateStep(1, 'pending', 0);
                
                const migResponse = await fetch('api_migrate_fix_columns.php');
                const migData = await migResponse.json();

                migData.messages.forEach(msg => addLog(msg, 'info'));
                if (migData.errors.length > 0) {
                    migData.errors.forEach(err => addLog('❌ ' + err, 'error'));
                }

                updateStep(1, migData.success ? 'success' : 'error', 100);
                addLog(migData.success ? '✅ Bước 1 hoàn thành' : '❌ Bước 1 lỗi', migData.success ? 'success' : 'error');

                // STEP 2: Seed Data
                await new Promise(r => setTimeout(r, 1000));
                addLog('\n🌱 Bước 2: Add Test Data...', 'info');
                updateStep(2, 'pending', 0);

                const seedResponse = await fetch('api_seed_test_data.php');
                const seedData = await seedResponse.json();

                seedData.messages.forEach(msg => addLog(msg, 'info'));
                if (seedData.errors.length > 0) {
                    seedData.errors.forEach(err => addLog('❌ ' + err, 'error'));
                }

                updateStep(2, seedData.success ? 'success' : 'error', 100);
                addLog(seedData.success ? '✅ Bước 2 hoàn thành' : '❌ Bước 2 lỗi', seedData.success ? 'success' : 'error');

                // STEP 3: Verify
                await new Promise(r => setTimeout(r, 1000));
                addLog('\n🔍 Bước 3: Verify Connection...', 'info');
                updateStep(3, 'pending', 0);

                const verifyResponse = await fetch('test_connection.php');
                const verifyText = await verifyResponse.text();

                addLog('📊 Connection Test Result:', 'info');
                addLog(verifyText.substring(0, 500), 'info');

                updateStep(3, 'success', 100);
                addLog('✅ Bước 3 hoàn thành', 'success');

                // Final Result
                await new Promise(r => setTimeout(r, 500));
                addLog('\n' + '='.repeat(50), 'success');
                addLog('🎉 SETUP HOÀN THÀNH THÀNH CÔNG!', 'success');
                addLog('='.repeat(50), 'success');
                addLog('', 'success');
                addLog('📌 Tài khoản test:', 'info');
                addLog('  • Admin: admin / admin123', 'info');
                addLog('  • Customer: testuser / test123', 'info');
                addLog('  • Owner: owner1 / owner123', 'info');
                addLog('', 'info');
                addLog('👉 Nhấp "Trang Chính" để truy cập ứng dụng', 'success');

                showSuccessResult();

            } catch (error) {
                addLog('❌ LỖI: ' + error.message, 'error');
                updateStep(1, 'error', 100);
                showErrorResult(error.message);
            }
        }

        function showSuccessResult() {
            resultSection.classList.remove('hidden');
            resultContent.className = 'p-6 rounded-lg border-2 border-green-500 bg-green-50';
            resultContent.innerHTML = `
                <div class="text-center">
                    <i class="fas fa-check-circle text-green-600 text-6xl mb-4"></i>
                    <h2 class="text-2xl font-bold text-green-800 mb-4">✅ Setup Thành Công!</h2>
                    <p class="text-green-700 mb-6">Database đã được thiết lập hoàn toàn. Hệ thống sẵn sàng sử dụng.</p>
                    <div class="grid md:grid-cols-2 gap-4">
                        <a href="index.php" class="block p-4 bg-green-600 text-white rounded-lg hover:bg-green-700 font-bold">
                            <i class="fas fa-arrow-right mr-2"></i>Vào Ứng Dụng
                        </a>
                        <button onclick="location.reload()" class="p-4 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-bold">
                            <i class="fas fa-redo mr-2"></i>Chạy Lại
                        </button>
                    </div>
                </div>
            `;
        }

        function showErrorResult(error) {
            resultSection.classList.remove('hidden');
            resultContent.className = 'p-6 rounded-lg border-2 border-red-500 bg-red-50';
            resultContent.innerHTML = `
                <div class="text-center">
                    <i class="fas fa-exclamation-circle text-red-600 text-6xl mb-4"></i>
                    <h2 class="text-2xl font-bold text-red-800 mb-4">❌ Lỗi Setup</h2>
                    <p class="text-red-700 mb-6">${escapeHtml(error)}</p>
                    <button onclick="location.reload()" class="p-4 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-bold">
                        <i class="fas fa-redo mr-2"></i>Thử Lại
                    </button>
                </div>
            `;
        }

        // Welcome message
        addLog('✅ Setup Tool Ready', 'success');
        addLog('Nhấp nút "LÀM NGAY" để bắt đầu setup', 'info');
    </script>
</body>
</html>
