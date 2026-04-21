// Backup Management
let autoBackupInterval = null;

async function loadBackupPage() {
    pageContent.innerHTML = `
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between">
                <div>
                    <h3 class="text-2xl font-bold text-gray-800">Sao lưu & Khôi phục</h3>
                    <p class="text-gray-600">Quản lý dữ liệu hệ thống và sao lưu định kỳ</p>
                </div>
                <div class="flex space-x-3 mt-4 md:mt-0">
                    <button onclick="createDatabaseBackup()" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 flex items-center">
                        <i class="fas fa-database mr-2"></i> Backup Database
                    </button>
                    <button onclick="createManualBackup()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center">
                        <i class="fas fa-save mr-2"></i> Tạo sao lưu
                    </button>
                </div>
            </div>
            
            <!-- Backup Status -->
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-xl font-bold mb-2">Trạng thái sao lưu</h4>
                        <p class="text-blue-100">Đảm bảo dữ liệu luôn được bảo vệ</p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-3 rounded-lg">
                        <i class="fas fa-shield-alt text-3xl"></i>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    <div class="bg-white bg-opacity-10 p-4 rounded-lg">
                        <p class="text-sm">Sao lưu gần nhất</p>
                        <p id="last-backup-time" class="text-xl font-bold mt-1">--</p>
                    </div>
                    <div class="bg-white bg-opacity-10 p-4 rounded-lg">
                        <p class="text-sm">Tổng số bản sao lưu</p>
                        <p id="total-backups" class="text-xl font-bold mt-1">--</p>
                    </div>
                    <div class="bg-white bg-opacity-10 p-4 rounded-lg">
                        <p class="text-sm">Kích thước lưu trữ</p>
                        <p id="total-storage" class="text-xl font-bold mt-1">--</p>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-plus text-green-600 text-2xl"></i>
                    </div>
                    <h5 class="font-bold mb-2">Tạo sao lưu thủ công</h5>
                    <p class="text-gray-600 text-sm mb-4">Sao lưu toàn bộ dữ liệu hiện tại</p>
                    <button onclick="createManualBackup()" class="bg-green-600 text-white w-full py-2 rounded-lg hover:bg-green-700">
                        Tạo ngay
                    </button>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-history text-blue-600 text-2xl"></i>
                    </div>
                    <h5 class="font-bold mb-2">Sao lưu tự động</h5>
                    <p class="text-gray-600 text-sm mb-4">Cấu hình sao lưu định kỳ</p>
                    <button onclick="showAutoBackupModal()" class="bg-blue-600 text-white w-full py-2 rounded-lg hover:bg-blue-700">
                        Cấu hình
                    </button>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                    </div>
                    <h5 class="font-bold mb-2">Khẩn cấp</h5>
                    <p class="text-gray-600 text-sm mb-4">Khôi phục dữ liệu từ sao lưu</p>
                    <button onclick="showRestoreModal()" class="bg-red-600 text-white w-full py-2 rounded-lg hover:bg-red-700">
                        Khôi phục
                    </button>
                </div>
            </div>
            
            <!-- Backup List -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h5 class="font-bold text-lg">Danh sách bản sao lưu</h5>
                    <div class="flex items-center space-x-2">
                        <input type="text" id="search-backup" placeholder="Tìm kiếm..." 
                               class="border border-gray-300 rounded-lg px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button onclick="loadBackups()" class="bg-gray-100 text-gray-700 px-3 py-1 rounded-lg text-sm hover:bg-gray-200">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b">
                                <th class="py-3 px-4 text-left">Tên bản sao lưu</th>
                                <th class="py-3 px-4 text-left">Thời gian</th>
                                <th class="py-3 px-4 text-left">Người tạo</th>
                                <th class="py-3 px-4 text-left">Kích thước</th>
                                <th class="py-3 px-4 text-left">Bản ghi</th>
                                <th class="py-3 px-4 text-left">Hành động</th>
                            </tr>
                        </thead>
                        <tbody id="backups-table-body">
                            <!-- Backups will be loaded here -->
                        </tbody>
                    </table>
                </div>
                
                <div class="text-center mt-6">
                    <button onclick="loadMoreBackups()" id="load-more-btn" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 hidden">
                        <i class="fas fa-chevron-down mr-2"></i> Tải thêm
                    </button>
                </div>
            </div>
            
            <!-- Storage Information -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h5 class="font-bold text-lg mb-4">Thông tin lưu trữ</h5>
                <div class="space-y-4">
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span>Dung lượng đã sử dụng</span>
                            <span id="storage-used">--</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div id="storage-progress" class="bg-blue-600 h-2 rounded-full" style="width: 0%"></div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div class="text-center p-3 bg-blue-50 rounded-lg">
                            <p class="font-semibold">Người dùng</p>
                            <p id="users-count" class="text-2xl font-bold mt-1">--</p>
                        </div>
                        <div class="text-center p-3 bg-green-50 rounded-lg">
                            <p class="font-semibold">Sân</p>
                            <p id="courts-count" class="text-2xl font-bold mt-1">--</p>
                        </div>
                        <div class="text-center p-3 bg-purple-50 rounded-lg">
                            <p class="font-semibold">Đơn đặt</p>
                            <p id="bookings-count" class="text-2xl font-bold mt-1">--</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Warning Message -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-yellow-600 text-2xl mr-4"></i>
                    <div>
                        <h5 class="font-bold text-yellow-800">Lưu ý quan trọng</h5>
                        <p class="text-yellow-700 text-sm mt-1">
                            • Sao lưu dữ liệu định kỳ để đảm bảo an toàn dữ liệu<br>
                            • Không xóa các bản sao lưu cũ trừ khi cần thiết<br>
                            • Kiểm tra kích thước sao lưu thường xuyên<br>
                            • Khôi phục dữ liệu chỉ khi thực sự cần thiết
                        </p>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Add search functionality
    const searchInput = document.getElementById('search-backup');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(searchBackups, 300));
    }
    
    // Load initial data
    await loadBackups();
    await loadSystemStats();
    loadAutoBackupConfig();
}

// Load backups
async function loadBackups() {
    try {
        showLoader();
        const response = await fetch(`${API_BASE_URL}/backup`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            renderBackups(data.data.backups);
            updateBackupStats(data.data);
        } else {
            throw new Error(data.error || 'Lỗi không xác định');
        }
    } catch (error) {
        console.error('Error loading backups:', error);
        showNotification('Lỗi tải danh sách sao lưu: ' + error.message, 'error');
    } finally {
        hideLoader();
    }
}

// Search backups
function searchBackups() {
    const searchTerm = document.getElementById('search-backup').value.toLowerCase();
    const rows = document.querySelectorAll('#backups-table-body tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
}

// Render backups
function renderBackups(backups) {
    const tbody = document.getElementById('backups-table-body');
    
    if (!backups || backups.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="py-8 text-center text-gray-500">
                    <i class="fas fa-database text-4xl mb-4 text-gray-300"></i>
                    <p class="text-lg">Chưa có bản sao lưu nào</p>
                    <p class="text-sm mt-2">Tạo bản sao lưu đầu tiên để bảo vệ dữ liệu</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = backups.map((backup, index) => `
        <tr class="border-b hover:bg-gray-50 ${index >= 5 ? 'more-backup hidden' : ''}" data-backup-name="${backup.name}">
            <td class="py-3 px-4">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-database text-blue-600"></i>
                    </div>
                    <div>
                        <p class="font-semibold">${backup.name}</p>
                        <p class="text-xs text-gray-600">${backup.created_at ? formatDateTime(backup.created_at) : '--'}</p>
                    </div>
                </div>
            </td>
            <td class="py-3 px-4">
                <p>${backup.created_at ? formatDate(backup.created_at) : '--'}</p>
                <p class="text-sm text-gray-600">${backup.created_at ? formatTime(backup.created_at) : '--'}</p>
            </td>
            <td class="py-3 px-4">
                <p>${backup.created_by || 'Hệ thống'}</p>
            </td>
            <td class="py-3 px-4">
                <p>${backup.file_size ? formatFileSize(backup.file_size) : '--'}</p>
            </td>
            <td class="py-3 px-4">
                <div class="text-sm">
                    <p>👥 ${backup.records?.users || 0} users</p>
                    <p>🏸 ${backup.records?.courts || 0} courts</p>
                    <p>📅 ${backup.records?.bookings || 0} bookings</p>
                </div>
            </td>
            <td class="py-3 px-4">
                <div class="flex space-x-2">
                    <button onclick="viewBackupInfo('${backup.name}')" class="text-blue-600 hover:text-blue-800 p-1" title="Xem chi tiết">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button onclick="downloadBackup('${backup.name}')" class="text-green-600 hover:text-green-800 p-1" title="Tải về">
                        <i class="fas fa-download"></i>
                    </button>
                    <button onclick="restoreBackup('${backup.name}')" class="text-yellow-600 hover:text-yellow-800 p-1" title="Khôi phục">
                        <i class="fas fa-history"></i>
                    </button>
                    <button onclick="deleteBackup('${backup.name}')" class="text-red-600 hover:text-red-800 p-1" title="Xóa">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
    
    // Show/Hide load more button
    const loadMoreBtn = document.getElementById('load-more-btn');
    const moreBackups = document.querySelectorAll('.more-backup');
    if (moreBackups.length > 0) {
        loadMoreBtn.classList.remove('hidden');
    } else {
        loadMoreBtn.classList.add('hidden');
    }
}

// Load more backups
function loadMoreBackups() {
    const moreBackups = document.querySelectorAll('.more-backup');
    moreBackups.forEach(backup => backup.classList.remove('hidden'));
    document.getElementById('load-more-btn').classList.add('hidden');
}

// Format file size
function formatFileSize(bytes) {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Update backup stats
function updateBackupStats(data) {
    const { backups, total } = data;
    
    if (backups && backups.length > 0) {
        const latestBackup = backups[0];
        document.getElementById('last-backup-time').textContent = 
            latestBackup.created_at ? formatDateTime(latestBackup.created_at) : '--';
        document.getElementById('total-backups').textContent = total || backups.length;
        
        // Calculate total storage
        let totalSize = 0;
        backups.forEach(backup => {
            if (backup.file_size) {
                totalSize += backup.file_size;
            }
        });
        document.getElementById('total-storage').textContent = formatFileSize(totalSize);
    } else {
        document.getElementById('last-backup-time').textContent = 'Chưa có';
        document.getElementById('total-backups').textContent = '0';
        document.getElementById('total-storage').textContent = '0 MB';
    }
}

// Load system stats
async function loadSystemStats() {
    try {
        showLoader();
        // Get counts from different endpoints
        const [usersRes, courtsRes, bookingsRes] = await Promise.all([
            fetch(`${API_BASE_URL}/users?limit=1`, {
                headers: { 'Authorization': `Bearer ${token}` }
            }),
            fetch(`${API_BASE_URL}/courts?limit=1`, {
                headers: { 'Authorization': `Bearer ${token}` }
            }),
            fetch(`${API_BASE_URL}/bookings?limit=1`, {
                headers: { 'Authorization': `Bearer ${token}` }
            })
        ]);
        
        const usersData = await usersRes.json();
        const courtsData = await courtsRes.json();
        const bookingsData = await bookingsRes.json();
        
        if (usersData.success) {
            document.getElementById('users-count').textContent = usersData.data.pagination?.total || '--';
        }
        
        if (courtsData.success) {
            document.getElementById('courts-count').textContent = courtsData.data.pagination?.total || '--';
        }
        
        if (bookingsData.success) {
            document.getElementById('bookings-count').textContent = bookingsData.data.pagination?.total || '--';
        }
        
        // Update storage usage
        const totalRecords = 
            (usersData.data.pagination?.total || 0) * 0.1 + // Approx 0.1KB per user
            (courtsData.data.pagination?.total || 0) * 0.2 + // Approx 0.2KB per court
            (bookingsData.data.pagination?.total || 0) * 0.5; // Approx 0.5KB per booking
        
        const storageMB = totalRecords / 1024; // Convert to MB
        document.getElementById('storage-used').textContent = storageMB.toFixed(2) + ' MB';
        
        const progress = Math.min((storageMB / 100) * 100, 100); // Assuming 100MB max
        document.getElementById('storage-progress').style.width = `${progress}%`;
        
    } catch (error) {
        console.error('Error loading system stats:', error);
    } finally {
        hideLoader();
    }
}

// Create manual backup
async function createManualBackup() {
    if (!confirm('Bạn có chắc chắn muốn tạo bản sao lưu mới? Quá trình này có thể mất vài phút.')) {
        return;
    }
    
    try {
        showLoader();
        const response = await fetch(`${API_BASE_URL}/backup`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                name: `backup_manual_${new Date().getTime()}`,
                description: 'Sao lưu thủ công'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Tạo sao lưu thành công', 'success');
            await loadBackups();
            await loadSystemStats();
        } else {
            throw new Error(data.error || 'Lỗi không xác định');
        }
    } catch (error) {
        console.error('Error creating backup:', error);
        showNotification(`Lỗi: ${error.message}`, 'error');
    } finally {
        hideLoader();
    }
}

// Create database backup
async function createDatabaseBackup() {
    if (!confirm('Bạn có chắc chắn muốn tạo backup toàn bộ database? Đây là thao tác nâng cao.')) {
        return;
    }
    
    try {
        showLoader();
        const response = await fetch(`${API_BASE_URL}/backup/database`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Backup database thành công', 'success');
            await loadBackups();
        } else {
            throw new Error(data.error || 'Lỗi không xác định');
        }
    } catch (error) {
        console.error('Error creating database backup:', error);
        showNotification(`Lỗi: ${error.message}`, 'error');
    } finally {
        hideLoader();
    }
}

// View backup info
async function viewBackupInfo(backupName) {
    try {
        showLoader();
        const response = await fetch(`${API_BASE_URL}/backup/${backupName}`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showBackupInfoModal(data.data);
        } else {
            throw new Error(data.error || 'Lỗi không xác định');
        }
    } catch (error) {
        console.error('Error viewing backup info:', error);
        showNotification(`Lỗi: ${error.message}`, 'error');
    } finally {
        hideLoader();
    }
}

// Show backup info modal
function showBackupInfoModal(backupInfo) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold">Chi tiết bản sao lưu</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="space-y-6">
                    <!-- Backup Info -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-database text-blue-600 text-xl"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-lg">${backupInfo.name}</h4>
                                <p class="text-sm text-gray-600">Tạo bởi: ${backupInfo.created_by || 'Hệ thống'}</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600">Thời gian tạo</p>
                                <p class="font-medium">${formatDateTime(backupInfo.created_at)}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Kích thước</p>
                                <p class="font-medium">${backupInfo.file_size ? formatFileSize(backupInfo.file_size) : '--'}</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Records Summary -->
                    <div>
                        <h5 class="font-bold mb-4">Thống kê dữ liệu</h5>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <p class="text-2xl font-bold text-blue-600">${backupInfo.records?.users || 0}</p>
                                <p class="text-sm text-gray-600">Người dùng</p>
                            </div>
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <p class="text-2xl font-bold text-green-600">${backupInfo.records?.courts || 0}</p>
                                <p class="text-sm text-gray-600">Sân</p>
                            </div>
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <p class="text-2xl font-bold text-purple-600">${backupInfo.records?.bookings || 0}</p>
                                <p class="text-sm text-gray-600">Đơn đặt</p>
                            </div>
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <p class="text-2xl font-bold text-yellow-600">${backupInfo.records?.payments || 0}</p>
                                <p class="text-sm text-gray-600">Thanh toán</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- File Information -->
                    <div>
                        <h5 class="font-bold mb-4">Thông tin file</h5>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Đường dẫn:</span>
                                <span class="font-mono text-sm">${backupInfo.path || '--'}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Phiên bản:</span>
                                <span>${backupInfo.version || '1.0'}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Định dạng:</span>
                                <span>${backupInfo.format || 'JSON'}</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex justify-end space-x-3 pt-4">
                        <button onclick="downloadBackup('${backupInfo.name}')" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                            <i class="fas fa-download mr-2"></i> Tải về
                        </button>
                        <button onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                            Đóng
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('modal-container').innerHTML = '';
    document.getElementById('modal-container').appendChild(modal);
}

// Download backup
async function downloadBackup(backupName) {
    try {
        showLoader();
        const response = await fetch(`${API_BASE_URL}/backup/${backupName}/download`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        if (response.ok) {
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${backupName}.backup`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            showNotification('Đang tải file sao lưu...', 'success');
        } else {
            throw new Error('Không thể tải file');
        }
    } catch (error) {
        console.error('Error downloading backup:', error);
        showNotification('Lỗi tải file: ' + error.message, 'error');
    } finally {
        hideLoader();
    }
}

// Restore backup
function restoreBackup(backupName) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold">Khôi phục dữ liệu</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="space-y-4">
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle text-yellow-600 text-xl mr-3"></i>
                            <div>
                                <p class="font-bold text-yellow-800">Cảnh báo quan trọng!</p>
                                <p class="text-yellow-700 text-sm mt-1">
                                    Hành động này sẽ ghi đè dữ liệu hiện tại bằng dữ liệu từ bản sao lưu.
                                    Vui lòng đảm bảo bạn đã sao lưu dữ liệu hiện tại trước khi tiếp tục.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <p class="font-medium mb-2">Bản sao lưu: <span class="text-blue-600">${backupName}</span></p>
                        <p class="text-sm text-gray-600">Chế độ khôi phục:</p>
                    </div>
                    
                    <div class="space-y-2">
                        <label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer">
                            <input type="radio" name="restore-mode" value="safe" checked class="mr-2">
                            <span>Chế độ an toàn (chỉ khôi phục nếu không có dữ liệu)</span>
                        </label>
                        <label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer">
                            <input type="radio" name="restore-mode" value="overwrite" class="mr-2">
                            <span>Ghi đè hoàn toàn (xóa dữ liệu hiện tại)</span>
                        </label>
                        <label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer">
                            <input type="radio" name="restore-mode" value="merge" class="mr-2">
                            <span>Gộp dữ liệu (giữ lại cả dữ liệu cũ và mới)</span>
                        </label>
                    </div>
                    
                    <div class="pt-4 flex justify-end space-x-3">
                        <button onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                            Hủy
                        </button>
                        <button onclick="confirmRestore('${backupName}')" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
                            <i class="fas fa-history mr-2"></i> Khôi phục
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('modal-container').innerHTML = '';
    document.getElementById('modal-container').appendChild(modal);
}

// Confirm restore
async function confirmRestore(backupName) {
    const mode = document.querySelector('input[name="restore-mode"]:checked').value;
    
    if (!confirm(`Bạn có CHẮC CHẮN muốn khôi phục từ bản sao lưu "${backupName}"? Hành động này KHÔNG THỂ hoàn tác.`)) {
        return;
    }
    
    try {
        showLoader();
        const response = await fetch(`${API_BASE_URL}/backup/${backupName}/restore`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ mode })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Khôi phục dữ liệu thành công. Hệ thống sẽ reload...', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            throw new Error(data.error || 'Lỗi không xác định');
        }
    } catch (error) {
        console.error('Error restoring backup:', error);
        showNotification(`Lỗi: ${error.message}`, 'error');
    } finally {
        hideLoader();
        closeModal();
    }
}

// Delete backup
async function deleteBackup(backupName) {
    if (!confirm(`Bạn có chắc chắn muốn xóa bản sao lưu "${backupName}"? Hành động này không thể hoàn tác.`)) {
        return;
    }
    
    try {
        showLoader();
        const response = await fetch(`${API_BASE_URL}/backup/${backupName}`, {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Xóa bản sao lưu thành công', 'success');
            await loadBackups();
        } else {
            throw new Error(data.error || 'Lỗi không xác định');
        }
    } catch (error) {
        console.error('Error deleting backup:', error);
        showNotification(`Lỗi: ${error.message}`, 'error');
    } finally {
        hideLoader();
    }
}

// Show auto backup modal
function showAutoBackupModal() {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold">Cấu hình sao lưu tự động</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form id="auto-backup-form" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tần suất sao lưu</label>
                        <select name="frequency" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="daily">Hàng ngày</option>
                            <option value="weekly">Hàng tuần</option>
                            <option value="monthly">Hàng tháng</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Giờ sao lưu</label>
                        <select name="backup-hour" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            ${Array.from({length: 24}, (_, i) => `
                                <option value="${i}" ${i === 2 ? 'selected' : ''}>
                                    ${i.toString().padStart(2, '0')}:00
                                </option>
                            `).join('')}
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Giữ lại bản sao lưu</label>
                        <div class="flex items-center">
                            <input type="number" name="keep_last" min="1" max="100" value="10"
                                   class="w-20 border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <span class="ml-2">bản gần nhất</span>
                        </div>
                        <p class="text-sm text-gray-600 mt-1">Các bản sao lưu cũ hơn sẽ tự động bị xóa</p>
                    </div>
                    
                    <div class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer">
                        <input type="checkbox" name="enabled" id="auto-backup-enabled" checked
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="auto-backup-enabled" class="ml-2 block text-sm text-gray-900">
                            Kích hoạt sao lưu tự động
                        </label>
                    </div>
                    
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <p class="font-medium text-blue-800">Lịch sao lưu tiếp theo</p>
                        <p class="text-sm text-blue-700 mt-1" id="next-backup-time">Đang tính toán...</p>
                    </div>
                    
                    <div class="pt-4 flex justify-end space-x-3">
                        <button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                            Hủy
                        </button>
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            Lưu cấu hình
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.getElementById('modal-container').innerHTML = '';
    document.getElementById('modal-container').appendChild(modal);
    
    // Calculate next backup time
    calculateNextBackupTime();
    
    // Load saved config
    loadAutoBackupConfigToForm();
    
    // Handle form submission
    document.getElementById('auto-backup-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        await saveAutoBackupConfig(new FormData(e.target));
    });
}

// Calculate next backup time
function calculateNextBackupTime() {
    const now = new Date();
    const next = new Date(now);
    next.setDate(next.getDate() + 1);
    next.setHours(2, 0, 0, 0); // Default to 2:00 AM tomorrow
    
    const nextTimeElement = document.getElementById('next-backup-time');
    if (nextTimeElement) {
        nextTimeElement.textContent = formatDateTime(next.toISOString());
    }
}

// Load auto backup config to form
function loadAutoBackupConfigToForm() {
    const config = getAutoBackupConfig();
    if (config) {
        const form = document.getElementById('auto-backup-form');
        if (form) {
            form.frequency.value = config.frequency || 'daily';
            form['backup-hour'].value = config.backupHour || 2;
            form.keep_last.value = config.keepLast || 10;
            form.enabled.checked = config.enabled !== false;
        }
    }
}

// Get auto backup config from localStorage
function getAutoBackupConfig() {
    try {
        const config = localStorage.getItem('autoBackupConfig');
        return config ? JSON.parse(config) : null;
    } catch {
        return null;
    }
}

// Save auto backup config
async function saveAutoBackupConfig(formData) {
    try {
        const config = {
            frequency: formData.get('frequency'),
            backupHour: parseInt(formData.get('backup-hour')),
            keepLast: parseInt(formData.get('keep_last')),
            enabled: formData.get('enabled') === 'on',
            updatedAt: new Date().toISOString()
        };
        
        // Save to localStorage
        localStorage.setItem('autoBackupConfig', JSON.stringify(config));
        
        // Save to server if API available
        try {
            await fetch(`${API_BASE_URL}/backup/config`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(config)
            });
        } catch (serverError) {
            console.log('Saving config locally only');
        }
        
        showNotification('Đã lưu cấu hình sao lưu tự động', 'success');
        closeModal();
        
        // Start/stop auto backup interval
        setupAutoBackup(config);
        
    } catch (error) {
        console.error('Error saving auto backup config:', error);
        showNotification('Lỗi lưu cấu hình: ' + error.message, 'error');
    }
}

// Load auto backup config on page load
function loadAutoBackupConfig() {
    const config = getAutoBackupConfig();
    if (config) {
        setupAutoBackup(config);
    }
}

// Setup auto backup interval
function setupAutoBackup(config) {
    if (autoBackupInterval) {
        clearInterval(autoBackupInterval);
        autoBackupInterval = null;
    }
    
    if (config.enabled) {
        const interval = getBackupInterval(config.frequency);
        autoBackupInterval = setInterval(async () => {
            const now = new Date();
            const backupHour = config.backupHour || 2;
            
            if (now.getHours() === backupHour && now.getMinutes() === 0) {
                try {
                    await createManualBackup();
                    console.log('Auto backup completed at', new Date().toISOString());
                } catch (error) {
                    console.error('Auto backup failed:', error);
                }
            }
        }, 60000); // Check every minute
    }
}

// Get backup interval in milliseconds
function getBackupInterval(frequency) {
    switch (frequency) {
        case 'daily':
            return 24 * 60 * 60 * 1000;
        case 'weekly':
            return 7 * 24 * 60 * 60 * 1000;
        case 'monthly':
            return 30 * 24 * 60 * 60 * 1000;
        default:
            return 24 * 60 * 60 * 1000;
    }
}

// Show restore modal (from quick action)
function showRestoreModal() {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold">Khôi phục dữ liệu từ file</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="space-y-6">
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle text-yellow-600 text-xl mr-3"></i>
                            <div>
                                <p class="font-bold text-yellow-800">Cảnh báo quan trọng!</p>
                                <p class="text-yellow-700 text-sm mt-1">
                                    Hành động này sẽ thay thế toàn bộ dữ liệu hiện tại bằng dữ liệu từ file sao lưu.
                                    Vui lòng đảm bảo bạn đã sao lưu dữ liệu hiện tại trước khi tiếp tục.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Chọn file sao lưu</label>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-blue-500 transition-colors">
                            <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                            <p class="text-gray-600 mb-2">Kéo thả file vào đây hoặc click để chọn</p>
                            <p class="text-sm text-gray-500 mb-4">Hỗ trợ file .backup, .json, .zip</p>
                            <input type="file" id="backup-file" accept=".backup,.json,.zip" class="hidden">
                            <button onclick="document.getElementById('backup-file').click()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                                Chọn file
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4">
                        <button onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                            Hủy
                        </button>
                        <button onclick="uploadAndRestoreBackup()" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
                            <i class="fas fa-history mr-2"></i> Khôi phục từ file
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('modal-container').innerHTML = '';
    document.getElementById('modal-container').appendChild(modal);
}

// Upload and restore from file
async function uploadAndRestoreBackup() {
    const fileInput = document.getElementById('backup-file');
    if (!fileInput.files.length) {
        showNotification('Vui lòng chọn file sao lưu', 'error');
        return;
    }
    
    const file = fileInput.files[0];
    const formData = new FormData();
    formData.append('backup', file);
    
    if (!confirm('Bạn có CHẮC CHẮN muốn khôi phục từ file này? Hành động này KHÔNG THỂ hoàn tác.')) {
        return;
    }
    
    try {
        showLoader();
        const response = await fetch(`${API_BASE_URL}/backup/upload`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`
            },
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Khôi phục từ file thành công. Hệ thống sẽ reload...', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            throw new Error(data.error || 'Lỗi không xác định');
        }
    } catch (error) {
        console.error('Error restoring from file:', error);
        showNotification(`Lỗi: ${error.message}`, 'error');
    } finally {
        hideLoader();
        closeModal();
    }
}

// Close modal
function closeModal() {
    document.getElementById('modal-container').innerHTML = '';
}

// Show loader
function showLoader() {
    let loader = document.getElementById('loader');
    if (!loader) {
        loader = document.createElement('div');
        loader.id = 'loader';
        loader.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        loader.innerHTML = `
            <div class="bg-white rounded-xl p-6 flex flex-col items-center">
                <div class="w-16 h-16 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mb-4"></div>
                <p class="text-gray-700">Đang xử lý...</p>
            </div>
        `;
        document.body.appendChild(loader);
    }
}

// Hide loader
function hideLoader() {
    const loader = document.getElementById('loader');
    if (loader) {
        loader.remove();
    }
}

// Debounce function for search
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Date formatting functions
function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('vi-VN');
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('vi-VN');
}

function formatTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
}