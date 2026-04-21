// Users Management
async function loadUsersPage() {
    pageContent.innerHTML = `
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between">
                <div>
                    <h3 class="text-2xl font-bold text-gray-800">Quản lý Người dùng</h3>
                    <p class="text-gray-600">Quản lý khách hàng, nhân viên và quản trị viên</p>
                </div>
                <div class="flex space-x-3 mt-4 md:mt-0">
                    <button onclick="exportUsers()" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 flex items-center">
                        <i class="fas fa-download mr-2"></i> Xuất
                    </button>
                    <button onclick="showAddUserModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center">
                        <i class="fas fa-plus mr-2"></i> Thêm người dùng
                    </button>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tìm kiếm</label>
                        <input type="text" id="search-users" placeholder="Tên, email, số điện thoại..." 
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Vai trò</label>
                        <select id="filter-role" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="all">Tất cả</option>
                            <option value="customer">Khách hàng</option>
                            <option value="staff">Nhân viên</option>
                            <option value="admin">Quản trị viên</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sắp xếp</label>
                        <select id="sort-users" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="created_at:desc">Mới nhất</option>
                            <option value="created_at:asc">Cũ nhất</option>
                            <option value="full_name:asc">Tên A-Z</option>
                            <option value="full_name:desc">Tên Z-A</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button onclick="loadUsersData()" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
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
                            <i class="fas fa-users text-blue-600 text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-600">Tổng người dùng</p>
                            <h3 id="total-users" class="text-3xl font-bold mt-1">--</h3>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-3 rounded-lg mr-4">
                            <i class="fas fa-user-tie text-green-600 text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-600">Nhân viên</p>
                            <h3 id="staff-count" class="text-3xl font-bold mt-1">--</h3>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center">
                        <div class="bg-purple-100 p-3 rounded-lg mr-4">
                            <i class="fas fa-user-shield text-purple-600 text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-600">Quản trị viên</p>
                            <h3 id="admin-count" class="text-3xl font-bold mt-1">--</h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Users Table -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="py-3 px-6 text-left">Người dùng</th>
                                <th class="py-3 px-6 text-left">Thông tin liên hệ</th>
                                <th class="py-3 px-6 text-left">Vai trò</th>
                                <th class="py-3 px-6 text-left">Ngày đăng ký</th>
                                <th class="py-3 px-6 text-left">Lượt đặt</th>
                                <th class="py-3 px-6 text-left">Hành động</th>
                            </tr>
                        </thead>
                        <tbody id="users-table-body" class="divide-y divide-gray-200">
                            <!-- Users will be loaded here -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Hiển thị <span id="users-start">1</span>-<span id="users-end">10</span> của <span id="users-total">0</span>
                    </div>
                    <div class="flex space-x-2">
                        <button onclick="changeUsersPage(-1)" id="prev-page" class="px-3 py-1 border border-gray-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <span class="px-3 py-1 bg-blue-600 text-white rounded-lg" id="current-page">1</span>
                        <button onclick="changeUsersPage(1)" id="next-page" class="px-3 py-1 border border-gray-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Load initial data
    await loadUsersData();
    
    // Setup event listeners for filters
    document.getElementById('search-users').addEventListener('input', debounce(loadUsersData, 500));
    document.getElementById('filter-role').addEventListener('change', loadUsersData);
    document.getElementById('sort-users').addEventListener('change', loadUsersData);
}

// Load users data
let currentUsersPage = 1;
let totalUsersPages = 1;

async function loadUsersData() {
    const search = document.getElementById('search-users').value;
    const role = document.getElementById('filter-role').value;
    const sort = document.getElementById('sort-users').value;
    const [sortBy, sortOrder] = sort.split(':');
    
    try {
        const response = await fetch(`${API_BASE_URL}/users?page=${currentUsersPage}&limit=10&search=${search}&role=${role}&sortBy=${sortBy}&sortOrder=${sortOrder}`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            renderUsersTable(data.data);
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        console.error('Error loading users:', error);
        showNotification('Lỗi tải danh sách người dùng', 'error');
    }
}

// Render users table
function renderUsersTable(data) {
    const { users, pagination, stats } = data;
    
    // Update stats
    document.getElementById('total-users').textContent = stats?.total || '--';
    document.getElementById('staff-count').textContent = stats?.staff || '--';
    document.getElementById('admin-count').textContent = stats?.admin || '--';
    
    // Update pagination
    currentUsersPage = pagination.page;
    totalUsersPages = pagination.totalPages;
    
    document.getElementById('users-start').textContent = Math.min((currentUsersPage - 1) * pagination.limit + 1, pagination.total);
    document.getElementById('users-end').textContent = Math.min(currentUsersPage * pagination.limit, pagination.total);
    document.getElementById('users-total').textContent = pagination.total;
    document.getElementById('current-page').textContent = currentUsersPage;
    
    document.getElementById('prev-page').disabled = currentUsersPage <= 1;
    document.getElementById('next-page').disabled = currentUsersPage >= totalUsersPages;
    
    // Render table rows
    const tbody = document.getElementById('users-table-body');
    
    if (users.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="py-8 text-center text-gray-500">
                    <i class="fas fa-users text-4xl mb-4 text-gray-300"></i>
                    <p class="text-lg">Không tìm thấy người dùng nào</p>
                    <p class="text-sm mt-2">Thử thay đổi bộ lọc hoặc thêm người dùng mới</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = users.map(user => `
        <tr class="hover:bg-gray-50">
            <td class="py-4 px-6">
                <div class="flex items-center">
                    <div class="w-10 h-10 ${getRoleColor(user.role)} rounded-full flex items-center justify-center mr-3">
                        <i class="fas fa-user text-white"></i>
                    </div>
                    <div>
                        <p class="font-semibold">${user.full_name}</p>
                        <p class="text-sm text-gray-600">@${user.username}</p>
                    </div>
                </div>
            </td>
            <td class="py-4 px-6">
                <p class="font-medium">${user.email || 'Chưa có email'}</p>
                <p class="text-sm text-gray-600">${user.phone_number}</p>
            </td>
            <td class="py-4 px-6">
                <span class="inline-block px-3 py-1 rounded-full text-sm ${getRoleBadgeColor(user.role)}">
                    ${getRoleText(user.role)}
                </span>
            </td>
            <td class="py-4 px-6">
                <p>${formatDate(user.created_at)}</p>
                <p class="text-sm text-gray-600">${formatTime(user.created_at)}</p>
            </td>
            <td class="py-4 px-6">
                <p class="font-semibold">${user._count?.bookings || 0}</p>
                <p class="text-sm text-gray-600">lượt đặt</p>
            </td>
            <td class="py-4 px-6">
                <div class="flex space-x-2">
                    <button onclick="viewUser(${user.user_id})" class="text-blue-600 hover:text-blue-800" title="Xem chi tiết">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button onclick="editUser(${user.user_id})" class="text-green-600 hover:text-green-800" title="Chỉnh sửa">
                        <i class="fas fa-edit"></i>
                    </button>
                    ${user.role !== 'admin' || user.user_id !== currentUser?.user_id ? `
                        <button onclick="deleteUser(${user.user_id}, '${user.full_name}')" class="text-red-600 hover:text-red-800" title="Xóa">
                            <i class="fas fa-trash"></i>
                        </button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `).join('');
}

// Change users page
function changeUsersPage(delta) {
    const newPage = currentUsersPage + delta;
    if (newPage >= 1 && newPage <= totalUsersPages) {
        currentUsersPage = newPage;
        loadUsersData();
    }
}

// Show add user modal
function showAddUserModal() {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold">Thêm người dùng mới</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form id="add-user-form" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Họ và tên *</label>
                        <input type="text" name="full_name" required 
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tên đăng nhập *</label>
                        <input type="text" name="username" required 
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Mật khẩu *</label>
                        <input type="password" name="password" required minlength="6"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" name="email"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Số điện thoại *</label>
                        <input type="tel" name="phone_number" required pattern="[0-9]{10,11}"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Vai trò *</label>
                        <select name="role" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="customer">Khách hàng</option>
                            <option value="staff">Nhân viên</option>
                            <option value="admin">Quản trị viên</option>
                        </select>
                    </div>
                    
                    <div class="pt-4 flex justify-end space-x-3">
                        <button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                            Hủy
                        </button>
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            Thêm người dùng
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.getElementById('modal-container').innerHTML = '';
    document.getElementById('modal-container').appendChild(modal);
    
    // Handle form submission
    document.getElementById('add-user-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        await addUser(new FormData(e.target));
    });
}

// Add user
async function addUser(formData) {
    const data = Object.fromEntries(formData.entries());
    
    try {
        const response = await fetch(`${API_BASE_URL}/users`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Thêm người dùng thành công', 'success');
            closeModal();
            loadUsersData();
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        showNotification(`Lỗi: ${error.message}`, 'error');
    }
}

// View user details
async function viewUser(userId) {
    try {
        const response = await fetch(`${API_BASE_URL}/users/${userId}`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showUserModal(data.data);
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        showNotification(`Lỗi: ${error.message}`, 'error');
    }
}

// Show user modal
function showUserModal(data) {
    const { user, statistics } = data;
    
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold">Chi tiết người dùng</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="space-y-6">
                    <!-- User Info -->
                    <div class="flex items-start space-x-6">
                        <div class="w-24 h-24 ${getRoleColor(user.role)} rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-white text-4xl"></i>
                        </div>
                        <div class="flex-1">
                            <h4 class="text-2xl font-bold">${user.full_name}</h4>
                            <p class="text-gray-600">@${user.username}</p>
                            <span class="inline-block mt-2 px-3 py-1 rounded-full text-sm ${getRoleBadgeColor(user.role)}">
                                ${getRoleText(user.role)}
                            </span>
                            
                            <div class="grid grid-cols-2 gap-4 mt-4">
                                <div>
                                    <p class="text-sm text-gray-600">Email</p>
                                    <p class="font-medium">${user.email || 'Chưa có'}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Số điện thoại</p>
                                    <p class="font-medium">${user.phone_number}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Ngày đăng ký</p>
                                    <p class="font-medium">${formatDate(user.created_at)}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Thời gian</p>
                                    <p class="font-medium">${formatTime(user.created_at)}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statistics -->
                    <div class="bg-gray-50 rounded-lg p-6">
                        <h5 class="font-bold mb-4">Thống kê đặt sân</h5>
                        <div class="grid grid-cols-3 gap-4">
                            <div class="text-center">
                                <p class="text-3xl font-bold text-blue-600">${statistics.totalBookings}</p>
                                <p class="text-sm text-gray-600">Tổng lượt đặt</p>
                            </div>
                            <div class="text-center">
                                <p class="text-3xl font-bold text-green-600">${formatCurrency(statistics.totalSpent, true)}</p>
                                <p class="text-sm text-gray-600">Tổng chi tiêu</p>
                            </div>
                            <div class="text-center">
                                <p class="text-3xl font-bold text-purple-600">${formatCurrency(statistics.averageBookingValue, true)}</p>
                                <p class="text-sm text-gray-600">Trung bình/đơn</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Bookings -->
                    <div>
                        <h5 class="font-bold mb-4">Đơn đặt gần đây</h5>
                        ${user.bookings && user.bookings.length > 0 ? `
                            <div class="space-y-3">
                                ${user.bookings.slice(0, 5).map(booking => `
                                    <div class="flex items-center justify-between p-3 bg-white border border-gray-200 rounded-lg">
                                        <div>
                                            <p class="font-medium">${booking.courts?.court_name || 'Sân'}</p>
                                            <p class="text-sm text-gray-600">${formatDate(booking.created_at)}</p>
                                        </div>
                                        <div class="text-right">
                                            <span class="inline-block px-2 py-1 text-xs rounded-full ${getStatusColor(booking.status)}">
                                                ${getStatusText(booking.status)}
                                            </span>
                                            <p class="font-semibold mt-1">${formatCurrency(booking.total_price)}</p>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        ` : `
                            <p class="text-gray-500 text-center py-4">Chưa có đơn đặt nào</p>
                        `}
                    </div>
                    
                    <div class="pt-4 flex justify-end">
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

// Edit user
function editUser(userId) {
    // Implement edit user functionality
    showNotification('Tính năng đang phát triển', 'info');
}

// Delete user
async function deleteUser(userId, userName) {
    if (!confirm(`Bạn có chắc chắn muốn xóa người dùng "${userName}"?`)) {
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE_URL}/users/${userId}`, {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Xóa người dùng thành công', 'success');
            loadUsersData();
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        showNotification(`Lỗi: ${error.message}`, 'error');
    }
}

// Export users
function exportUsers() {
    showNotification('Tính năng đang phát triển', 'info');
}

// Close modal
function closeModal() {
    document.getElementById('modal-container').innerHTML = '';
}

// Utility functions for users
function getRoleColor(role) {
    const colors = {
        'admin': 'bg-purple-600',
        'staff': 'bg-green-600',
        'customer': 'bg-blue-600'
    };
    return colors[role] || 'bg-gray-600';
}

function getRoleBadgeColor(role) {
    const colors = {
        'admin': 'bg-purple-100 text-purple-800',
        'staff': 'bg-green-100 text-green-800',
        'customer': 'bg-blue-100 text-blue-800'
    };
    return colors[role] || 'bg-gray-100 text-gray-800';
}

function getRoleText(role) {
    const texts = {
        'admin': 'Quản trị viên',
        'staff': 'Nhân viên',
        'customer': 'Khách hàng'
    };
    return texts[role] || role;
}

function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('vi-VN');
}

function formatTime(dateString) {
    return new Date(dateString).toLocaleTimeString('vi-VN', { 
        hour: '2-digit', 
        minute: '2-digit' 
    });
}

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

// Make functions available globally
window.loadUsersPage = loadUsersPage;
window.loadUsersData = loadUsersData;
window.changeUsersPage = changeUsersPage;
window.showAddUserModal = showAddUserModal;
window.viewUser = viewUser;
window.editUser = editUser;
window.deleteUser = deleteUser;
window.exportUsers = exportUsers;
window.closeModal = closeModal;