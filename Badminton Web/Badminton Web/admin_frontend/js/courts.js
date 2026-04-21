// Courts Management
let currentCourtsPage = 1;
let totalCourtsPages = 1;

async function loadCourtsPage() {
    pageContent.innerHTML = `
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between">
                <div>
                    <h3 class="text-2xl font-bold text-gray-800">Quản lý Sân</h3>
                    <p class="text-gray-600">Quản lý thông tin sân cầu lông</p>
                </div>
                <div class="flex space-x-3 mt-4 md:mt-0">
                    <button onclick="exportCourts()" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 flex items-center">
                        <i class="fas fa-download mr-2"></i> Xuất
                    </button>
                    <button onclick="showAddCourtModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center">
                        <i class="fas fa-plus mr-2"></i> Thêm sân mới
                    </button>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tìm kiếm</label>
                        <input type="text" id="search-courts" placeholder="Tên sân, mô tả..." 
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Trạng thái</label>
                        <select id="filter-status" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="all">Tất cả</option>
                            <option value="active">Đang hoạt động</option>
                            <option value="inactive">Ngừng hoạt động</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Loại sân</label>
                        <select id="filter-type" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="all">Tất cả</option>
                            <option value="Tiêu chuẩn">Tiêu chuẩn</option>
                            <option value="VIP">VIP</option>
                            <option value="Trong nhà">Trong nhà</option>
                            <option value="Ngoài trời">Ngoài trời</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button onclick="loadCourtsData()" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-search mr-2"></i> Lọc
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-lg mr-4">
                            <i class="fas fa-map-marked-alt text-blue-600 text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-600">Tổng số sân</p>
                            <h3 id="total-courts" class="text-3xl font-bold mt-1">--</h3>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-3 rounded-lg mr-4">
                            <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-600">Sân đang hoạt động</p>
                            <h3 id="active-courts" class="text-3xl font-bold mt-1">--</h3>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center">
                        <div class="bg-yellow-100 p-3 rounded-lg mr-4">
                            <i class="fas fa-chart-line text-yellow-600 text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-600">Doanh thu tháng</p>
                            <h3 id="month-revenue" class="text-3xl font-bold mt-1">--</h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Courts Table -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="py-3 px-6 text-left">Sân</th>
                                <th class="py-3 px-6 text-left">Loại sân</th>
                                <th class="py-3 px-6 text-left">Giá/giờ</th>
                                <th class="py-3 px-6 text-left">Trạng thái</th>
                                <th class="py-3 px-6 text-left">Lượt đặt</th>
                                <th class="py-3 px-6 text-left">Doanh thu</th>
                                <th class="py-3 px-6 text-left">Hành động</th>
                            </tr>
                        </thead>
                        <tbody id="courts-table-body" class="divide-y divide-gray-200">
                            <!-- Courts will be loaded here -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Hiển thị <span id="courts-start">1</span>-<span id="courts-end">10</span> của <span id="courts-total">0</span>
                    </div>
                    <div class="flex space-x-2">
                        <button onclick="changeCourtsPage(-1)" id="prev-courts-page" class="px-3 py-1 border border-gray-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <span class="px-3 py-1 bg-blue-600 text-white rounded-lg" id="current-courts-page">1</span>
                        <button onclick="changeCourtsPage(1)" id="next-courts-page" class="px-3 py-1 border border-gray-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Load initial data
    await loadCourtsData();
    
    // Setup event listeners for filters
    document.getElementById('search-courts').addEventListener('input', debounce(loadCourtsData, 500));
    document.getElementById('filter-status').addEventListener('change', loadCourtsData);
    document.getElementById('filter-type').addEventListener('change', loadCourtsData);
}

// Load courts data
async function loadCourtsData() {
    const search = document.getElementById('search-courts').value;
    const status = document.getElementById('filter-status').value;
    const courtType = document.getElementById('filter-type').value;
    
    let is_active = null;
    if (status === 'active') is_active = 'true';
    if (status === 'inactive') is_active = 'false';
    
    try {
        const response = await fetch(`${API_BASE_URL}/courts?page=${currentCourtsPage}&limit=10&search=${search}&is_active=${is_active}&court_type=${courtType !== 'all' ? courtType : ''}`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            renderCourtsTable(data.data);
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        console.error('Error loading courts:', error);
        showNotification('Lỗi tải danh sách sân', 'error');
    }
}

// Render courts table
function renderCourtsTable(data) {
    const { courts, pagination } = data;
    
    // Update stats (we need to fetch separately or calculate)
    document.getElementById('total-courts').textContent = pagination?.total || '--';
    
    // Calculate active courts
    const activeCount = courts.filter(court => court.is_active).length;
    document.getElementById('active-courts').textContent = activeCount;
    
    // Update pagination
    currentCourtsPage = pagination.page;
    totalCourtsPages = pagination.totalPages;
    
    document.getElementById('courts-start').textContent = Math.min((currentCourtsPage - 1) * pagination.limit + 1, pagination.total);
    document.getElementById('courts-end').textContent = Math.min(currentCourtsPage * pagination.limit, pagination.total);
    document.getElementById('courts-total').textContent = pagination.total;
    document.getElementById('current-courts-page').textContent = currentCourtsPage;
    
    document.getElementById('prev-courts-page').disabled = currentCourtsPage <= 1;
    document.getElementById('next-courts-page').disabled = currentCourtsPage >= totalCourtsPages;
    
    // Render table rows
    const tbody = document.getElementById('courts-table-body');
    
    if (courts.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="py-8 text-center text-gray-500">
                    <i class="fas fa-map-marked-alt text-4xl mb-4 text-gray-300"></i>
                    <p class="text-lg">Không tìm thấy sân nào</p>
                    <p class="text-sm mt-2">Thử thay đổi bộ lọc hoặc thêm sân mới</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = courts.map(court => `
        <tr class="hover:bg-gray-50">
            <td class="py-4 px-6">
                <div class="flex items-center">
                    <div class="w-10 h-10 ${court.is_active ? 'bg-green-100' : 'bg-red-100'} rounded-lg flex items-center justify-center mr-3">
                        <i class="fas ${court.is_active ? 'fa-check-circle text-green-600' : 'fa-times-circle text-red-600'}"></i>
                    </div>
                    <div>
                        <p class="font-semibold">${court.court_name}</p>
                        <p class="text-sm text-gray-600 truncate max-w-xs">${court.description || 'Không có mô tả'}</p>
                    </div>
                </div>
            </td>
            <td class="py-4 px-6">
                <span class="inline-block px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800">
                    ${court.court_type || 'Tiêu chuẩn'}
                </span>
            </td>
            <td class="py-4 px-6">
                <p class="font-semibold">${formatCurrency(court.price_per_hour)}</p>
                <p class="text-sm text-gray-600">/giờ</p>
            </td>
            <td class="py-4 px-6">
                ${court.is_active ? `
                    <span class="inline-block px-3 py-1 rounded-full text-sm bg-green-100 text-green-800">
                        <i class="fas fa-circle mr-1" style="font-size: 8px;"></i> Hoạt động
                    </span>
                ` : `
                    <span class="inline-block px-3 py-1 rounded-full text-sm bg-red-100 text-red-800">
                        <i class="fas fa-circle mr-1" style="font-size: 8px;"></i> Ngừng hoạt động
                    </span>
                `}
            </td>
            <td class="py-4 px-6">
                <p class="font-semibold">${court._count?.bookings || 0}</p>
                <p class="text-sm text-gray-600">lượt</p>
            </td>
            <td class="py-4 px-6">
                <p class="font-semibold">--</p>
                <p class="text-sm text-gray-600">VND</p>
            </td>
            <td class="py-4 px-6">
                <div class="flex space-x-2">
                    <button onclick="viewCourt(${court.court_id})" class="text-blue-600 hover:text-blue-800" title="Xem chi tiết">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button onclick="editCourt(${court.court_id})" class="text-green-600 hover:text-green-800" title="Chỉnh sửa">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="toggleCourtStatus(${court.court_id}, ${court.is_active})" class="${court.is_active ? 'text-yellow-600 hover:text-yellow-800' : 'text-green-600 hover:text-green-800'}" title="${court.is_active ? 'Ngừng hoạt động' : 'Kích hoạt'}">
                        <i class="fas ${court.is_active ? 'fa-pause' : 'fa-play'}"></i>
                    </button>
                    <button onclick="deleteCourt(${court.court_id}, '${court.court_name}')" class="text-red-600 hover:text-red-800" title="Xóa">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// Change courts page
function changeCourtsPage(delta) {
    const newPage = currentCourtsPage + delta;
    if (newPage >= 1 && newPage <= totalCourtsPages) {
        currentCourtsPage = newPage;
        loadCourtsData();
    }
}

// Show add court modal
function showAddCourtModal() {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold">Thêm sân mới</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form id="add-court-form" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tên sân *</label>
                        <input type="text" name="court_name" required 
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               placeholder="Ví dụ: Sân 1, Sân VIP...">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Loại sân</label>
                        <select name="court_type" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Chọn loại sân</option>
                            <option value="Tiêu chuẩn">Tiêu chuẩn</option>
                            <option value="VIP">VIP</option>
                            <option value="Trong nhà">Trong nhà</option>
                            <option value="Ngoài trời">Ngoài trời</option>
                            <option value="Sân tập">Sân tập</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Giá mỗi giờ (VND) *</label>
                        <input type="number" name="price_per_hour" required min="10000" step="1000"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="50000">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Mô tả</label>
                        <textarea name="description" rows="3"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Mô tả về sân..."></textarea>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" name="is_active" id="is_active" checked
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="is_active" class="ml-2 block text-sm text-gray-900">
                            Kích hoạt sân ngay
                        </label>
                    </div>
                    
                    <div class="pt-4 flex justify-end space-x-3">
                        <button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                            Hủy
                        </button>
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            Thêm sân
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.getElementById('modal-container').innerHTML = '';
    document.getElementById('modal-container').appendChild(modal);
    
    // Handle form submission
    document.getElementById('add-court-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        await addCourt(new FormData(e.target));
    });
}

// Add court
async function addCourt(formData) {
    const data = Object.fromEntries(formData.entries());
    data.is_active = data.is_active === 'on';
    
    try {
        const response = await fetch(`${API_BASE_URL}/courts`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Thêm sân thành công', 'success');
            closeModal();
            loadCourtsData();
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        showNotification(`Lỗi: ${error.message}`, 'error');
    }
}

// View court details
async function viewCourt(courtId) {
    try {
        const response = await fetch(`${API_BASE_URL}/courts/${courtId}`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showCourtModal(data.data);
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        showNotification(`Lỗi: ${error.message}`, 'error');
    }
}

// Show court modal
function showCourtModal(data) {
    const { court, statistics, recentBookings } = data;
    
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold">Chi tiết sân: ${court.court_name}</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="space-y-6">
                    <!-- Court Info -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl p-6 text-white">
                            <div class="flex items-center mb-4">
                                <div class="bg-white bg-opacity-20 p-3 rounded-lg mr-4">
                                    <i class="fas fa-map-marked-alt text-2xl"></i>
                                </div>
                                <div>
                                    <h4 class="text-2xl font-bold">${court.court_name}</h4>
                                    <p>${court.court_type || 'Tiêu chuẩn'}</p>
                                </div>
                            </div>
                            <p class="text-blue-100">${court.description || 'Không có mô tả'}</p>
                        </div>
                        
                        <div class="bg-white border border-gray-200 rounded-xl p-6">
                            <h5 class="font-bold mb-4">Thông tin giá</h5>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Giá cơ bản:</span>
                                    <span class="font-bold">${formatCurrency(court.price_per_hour)}/giờ</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Trạng thái:</span>
                                    ${court.is_active ? `
                                        <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-sm">
                                            Đang hoạt động
                                        </span>
                                    ` : `
                                        <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-sm">
                                            Ngừng hoạt động
                                        </span>
                                    `}
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white border border-gray-200 rounded-xl p-6">
                            <h5 class="font-bold mb-4">Thống kê</h5>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Tổng lượt đặt:</span>
                                    <span class="font-bold">${statistics.totalBookings}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Doanh thu tổng:</span>
                                    <span class="font-bold">${formatCurrency(statistics.totalRevenue)}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Doanh thu trung bình:</span>
                                    <span class="font-bold">${formatCurrency(statistics.averageRevenue)}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Bookings -->
                    <div class="bg-white border border-gray-200 rounded-xl p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h5 class="font-bold">Đơn đặt gần đây</h5>
                            <button onclick="viewCourtSchedule(${court.court_id})" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                <i class="fas fa-calendar-alt mr-1"></i> Xem lịch sân
                            </button>
                        </div>
                        
                        ${recentBookings && recentBookings.length > 0 ? `
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead>
                                        <tr class="border-b">
                                            <th class="py-2 px-4 text-left">Khách hàng</th>
                                            <th class="py-2 px-4 text-left">Thời gian</th>
                                            <th class="py-2 px-4 text-left">Tổng tiền</th>
                                            <th class="py-2 px-4 text-left">Trạng thái</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${recentBookings.map(booking => `
                                            <tr class="border-b hover:bg-gray-50">
                                                <td class="py-3 px-4">${booking.customer}</td>
                                                <td class="py-3 px-4">
                                                    <p class="font-medium">${formatDate(booking.startTime)}</p>
                                                    <p class="text-sm text-gray-600">
                                                        ${new Date(booking.startTime).getHours().toString().padStart(2, '0')}:00 - 
                                                        ${new Date(booking.endTime).getHours().toString().padStart(2, '0')}:00
                                                    </p>
                                                </td>
                                                <td class="py-3 px-4 font-bold">${formatCurrency(booking.totalPrice)}</td>
                                                <td class="py-3 px-4">
                                                    <span class="px-2 py-1 text-xs rounded-full ${getStatusColor(booking.status)}">
                                                        ${getStatusText(booking.status)}
                                                    </span>
                                                </td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        ` : `
                            <p class="text-gray-500 text-center py-4">Chưa có đơn đặt nào</p>
                        `}
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex justify-end space-x-3 pt-4">
                        <button onclick="editCourt(${court.court_id})" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                            <i class="fas fa-edit mr-2"></i> Chỉnh sửa
                        </button>
                        <button onclick="viewCourtSchedule(${court.court_id})" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-calendar mr-2"></i> Xem lịch
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

// View court schedule
async function viewCourtSchedule(courtId) {
    const today = new Date().toISOString().split('T')[0];
    
    try {
        const response = await fetch(`${API_BASE_URL}/courts/${courtId}/schedule?date=${today}`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showScheduleModal(data.data);
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        showNotification(`Lỗi: ${error.message}`, 'error');
    }
}

// Show schedule modal
function showScheduleModal(data) {
    const { date, court, schedule } = data;
    
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="text-xl font-bold">Lịch sân: ${court.court_name}</h3>
                        <p class="text-gray-600">Ngày ${formatDate(date)} - Giá: ${formatCurrency(court.price_per_hour)}/giờ</p>
                    </div>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="mb-4">
                    <input type="date" id="schedule-date" value="${date}" 
                           class="border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    ${schedule.map(slot => `
                        <div class="border border-gray-200 rounded-lg p-4 ${slot.isBooked ? 'bg-red-50 border-red-200' : 'bg-green-50 border-green-200'}">
                            <div class="flex justify-between items-center mb-2">
                                <span class="font-semibold">${slot.timeRange}</span>
                                ${slot.isBooked ? `
                                    <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-sm">
                                        Đã đặt
                                    </span>
                                ` : `
                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-sm">
                                        Trống
                                    </span>
                                `}
                            </div>
                            
                            ${slot.isBooked ? `
                                <div class="mt-3 p-3 bg-white rounded border">
                                    <p class="font-medium">${slot.booking.customer}</p>
                                    <p class="text-sm text-gray-600">${slot.booking.phone}</p>
                                    <p class="text-sm mt-2">
                                        <span class="px-2 py-1 text-xs rounded-full ${getStatusColor(slot.booking.status)}">
                                            ${getStatusText(slot.booking.status)}
                                        </span>
                                    </p>
                                </div>
                            ` : `
                                <p class="text-gray-600 text-sm mt-2">Sân trống, có thể đặt</p>
                            `}
                        </div>
                    `).join('')}
                </div>
                
                <div class="flex justify-end mt-6">
                    <button onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Đóng
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('modal-container').innerHTML = '';
    document.getElementById('modal-container').appendChild(modal);
    
    // Add event listener for date change
    document.getElementById('schedule-date').addEventListener('change', function() {
        viewCourtSchedule(data.court.court_id, this.value);
    });
}

// Edit court
async function editCourt(courtId) {
    try {
        const response = await fetch(`${API_BASE_URL}/courts/${courtId}`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showEditCourtModal(data.data.court);
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        showNotification(`Lỗi: ${error.message}`, 'error');
    }
}

// Show edit court modal
function showEditCourtModal(court) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold">Chỉnh sửa sân</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form id="edit-court-form" class="space-y-4">
                    <input type="hidden" name="court_id" value="${court.court_id}">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tên sân *</label>
                        <input type="text" name="court_name" value="${court.court_name}" required 
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Loại sân</label>
                        <select name="court_type" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Chọn loại sân</option>
                            <option value="Tiêu chuẩn" ${court.court_type === 'Tiêu chuẩn' ? 'selected' : ''}>Tiêu chuẩn</option>
                            <option value="VIP" ${court.court_type === 'VIP' ? 'selected' : ''}>VIP</option>
                            <option value="Trong nhà" ${court.court_type === 'Trong nhà' ? 'selected' : ''}>Trong nhà</option>
                            <option value="Ngoài trời" ${court.court_type === 'Ngoài trời' ? 'selected' : ''}>Ngoài trời</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Giá mỗi giờ (VND) *</label>
                        <input type="number" name="price_per_hour" value="${court.price_per_hour}" required min="10000" step="1000"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Mô tả</label>
                        <textarea name="description" rows="3"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">${court.description || ''}</textarea>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" name="is_active" id="edit_is_active" ${court.is_active ? 'checked' : ''}
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="edit_is_active" class="ml-2 block text-sm text-gray-900">
                            Sân đang hoạt động
                        </label>
                    </div>
                    
                    <div class="pt-4 flex justify-end space-x-3">
                        <button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                            Hủy
                        </button>
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            Cập nhật
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.getElementById('modal-container').innerHTML = '';
    document.getElementById('modal-container').appendChild(modal);
    
    // Handle form submission
    document.getElementById('edit-court-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        await updateCourt(new FormData(e.target));
    });
}

// Update court
async function updateCourt(formData) {
    const data = Object.fromEntries(formData.entries());
    const courtId = data.court_id;
    data.is_active = data.is_active === 'on';
    delete data.court_id;
    
    try {
        const response = await fetch(`${API_BASE_URL}/courts/${courtId}`, {
            method: 'PUT',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Cập nhật sân thành công', 'success');
            closeModal();
            loadCourtsData();
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        showNotification(`Lỗi: ${error.message}`, 'error');
    }
}

// Toggle court status
async function toggleCourtStatus(courtId, isActive) {
    const action = isActive ? 'ngừng hoạt động' : 'kích hoạt';
    
    if (!confirm(`Bạn có chắc chắn muốn ${action} sân này?`)) {
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE_URL}/courts/${courtId}`, {
            method: 'PUT',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                is_active: !isActive
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(`Đã ${action} sân thành công`, 'success');
            loadCourtsData();
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        showNotification(`Lỗi: ${error.message}`, 'error');
    }
}

// Delete court
async function deleteCourt(courtId, courtName) {
    if (!confirm(`Bạn có chắc chắn muốn xóa sân "${courtName}"? Hành động này không thể hoàn tác.`)) {
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE_URL}/courts/${courtId}`, {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Xóa sân thành công', 'success');
            loadCourtsData();
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        showNotification(`Lỗi: ${error.message}`, 'error');
    }
}

// Export courts
function exportCourts() {
    showNotification('Tính năng xuất dữ liệu đang được phát triển', 'info');
}

// Make functions available globally
window.loadCourtsPage = loadCourtsPage;
window.loadCourtsData = loadCourtsData;
window.changeCourtsPage = changeCourtsPage;
window.showAddCourtModal = showAddCourtModal;
window.viewCourt = viewCourt;
window.editCourt = editCourt;
window.toggleCourtStatus = toggleCourtStatus;
window.deleteCourt = deleteCourt;
window.exportCourts = exportCourts;
window.viewCourtSchedule = viewCourtSchedule;