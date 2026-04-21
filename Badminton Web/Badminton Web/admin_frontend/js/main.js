// Admin Panel Main JavaScript
const API_BASE_URL = 'http://localhost:3001/api/admin';
let currentUser = null;
let token = localStorage.getItem('admin_token');

// DOM Elements
const loadingScreen = document.getElementById('loading-screen');
const loginContainer = document.getElementById('login-container');
const dashboardContainer = document.getElementById('dashboard-container');
const pageContent = document.getElementById('page-content');
const pageTitle = document.getElementById('page-title');
const pageSubtitle = document.getElementById('page-subtitle');

// Initialize admin panel
document.addEventListener('DOMContentLoaded', () => {
    // Update date and time
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    // Check authentication
    checkAuth();
    
    // Setup event listeners
    setupEventListeners();
});

// Check authentication status
async function checkAuth() {
    if (token) {
        try {
            // Verify token by getting user info
            const response = await fetch(`${API_BASE_URL}/auth/me`, {
                headers: {
                    'Authorization': `Bearer ${token}`
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    currentUser = data.user;
                    showDashboard();
                    loadPage('dashboard');
                    return;
                }
            }
        } catch (error) {
            console.error('Auth check error:', error);
        }
    }
    
    // If no valid token, show login
    showLogin();
}

// Setup event listeners
function setupEventListeners() {
    // Login form
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
    
    // Logout button
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', handleLogout);
    }
    
    // Navigation items
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const page = item.getAttribute('data-page');
            loadPage(page);
            
            // Update active state
            document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
            item.classList.add('active');
        });
    });
    
    // Toggle sidebar
    const toggleSidebar = document.getElementById('toggle-sidebar');
    if (toggleSidebar) {
        toggleSidebar.addEventListener('click', () => {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('collapsed');
        });
    }
}

// Handle login
async function handleLogin(e) {
    e.preventDefault();
    
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    const loginBtn = document.getElementById('login-btn');
    const errorDiv = document.getElementById('login-error');
    
    // Show loading
    loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang đăng nhập...';
    loginBtn.disabled = true;
    errorDiv.classList.add('hidden');
    
    try {
        const response = await fetch(`${API_BASE_URL}/auth/login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ username, password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Save token and user info
            token = data.token;
            currentUser = data.user;
            localStorage.setItem('admin_token', token);
            localStorage.setItem('admin_user', JSON.stringify(currentUser));
            
            // Show success message
            showNotification('Đăng nhập thành công!', 'success');
            
            // Show dashboard
            showDashboard();
            loadPage('dashboard');
        } else {
            // Show error
            errorDiv.classList.remove('hidden');
            document.getElementById('error-message').textContent = data.error || 'Đăng nhập thất bại';
        }
    } catch (error) {
        console.error('Login error:', error);
        errorDiv.classList.remove('hidden');
        document.getElementById('error-message').textContent = 'Lỗi kết nối đến server';
    } finally {
        // Reset button
        loginBtn.innerHTML = 'Đăng nhập';
        loginBtn.disabled = false;
    }
}

// Handle logout
function handleLogout() {
    if (confirm('Bạn có chắc chắn muốn đăng xuất?')) {
        localStorage.removeItem('admin_token');
        localStorage.removeItem('admin_user');
        token = null;
        currentUser = null;
        showLogin();
        showNotification('Đã đăng xuất thành công', 'info');
    }
}

// Show login screen
function showLogin() {
    loadingScreen.classList.add('hidden');
    loginContainer.classList.remove('hidden');
    dashboardContainer.classList.add('hidden');
    
    // Clear form
    const loginForm = document.getElementById('login-form');
    if (loginForm) loginForm.reset();
}

// Show dashboard
function showDashboard() {
    loadingScreen.classList.add('hidden');
    loginContainer.classList.add('hidden');
    dashboardContainer.classList.remove('hidden');
    
    // Update user info
    if (currentUser) {
        document.getElementById('user-name').textContent = currentUser.full_name;
        document.getElementById('user-role').textContent = currentUser.role === 'admin' ? 'Quản trị viên' : 'Nhân viên';
    }
}

// Load page content
async function loadPage(page) {
    // Show loading
    pageContent.innerHTML = `
        <div class="flex items-center justify-center h-64">
            <div class="text-center">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                <p class="text-gray-600">Đang tải...</p>
            </div>
        </div>
    `;
    
    // Update page title
    const titles = {
        'dashboard': { title: 'Dashboard', subtitle: 'Tổng quan hệ thống' },
        'users': { title: 'Quản lý Người dùng', subtitle: 'Quản lý khách hàng và nhân viên' },
        'courts': { title: 'Quản lý Sân', subtitle: 'Quản lý thông tin sân cầu lông' },
        'bookings': { title: 'Quản lý Đặt sân', subtitle: 'Xem và quản lý các đơn đặt sân' },
        'pricing': { title: 'Giá & Khung giờ', subtitle: 'Cấu hình giá theo khung giờ' },
        'reports': { title: 'Báo cáo & Thống kê', subtitle: 'Thống kê doanh thu và lượt đặt' },
        'backup': { title: 'Sao lưu & Khôi phục', subtitle: 'Quản lý dữ liệu hệ thống' },
        'settings': { title: 'Cài đặt', subtitle: 'Cấu hình hệ thống' }
    };
    
    if (titles[page]) {
        pageTitle.textContent = titles[page].title;
        pageSubtitle.textContent = titles[page].subtitle;
    }
    
    // Load page content
    try {
        switch (page) {
            case 'dashboard':
                await loadDashboard();
                break;
            case 'users':
                await loadUsersPage();
                break;
            case 'courts':
                await loadCourtsPage();
                break;
            case 'bookings':
                await loadBookingsPage();
                break;
            case 'pricing':
                await loadPricingPage();
                break;
            case 'reports':
                await loadReportsPage();
                break;
            case 'backup':
                await loadBackupPage();
                break;
            case 'settings':
                await loadSettingsPage();
                break;
            default:
                pageContent.innerHTML = '<div class="text-center py-12"><h3 class="text-2xl font-bold text-gray-800">Trang không tồn tại</h3></div>';
        }
    } catch (error) {
        console.error(`Error loading page ${page}:`, error);
        pageContent.innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-lg p-6">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl mr-3"></i>
                    <div>
                        <h3 class="font-bold text-red-800">Lỗi tải trang</h3>
                        <p class="text-red-700">${error.message}</p>
                    </div>
                </div>
            </div>
        `;
    }
}

// Load dashboard content
async function loadDashboard() {
    try {
        const response = await fetch(`${API_BASE_URL}/reports/dashboard`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            renderDashboard(data.data);
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        console.error('Error loading dashboard:', error);
        renderDashboardError(error.message);
    }
}

// Render dashboard
function renderDashboard(data) {
    const { overview, recent_bookings, revenue_trend, court_utilization } = data;
    
    pageContent.innerHTML = `
        <div class="space-y-6">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="stat-card bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Tổng người dùng</p>
                            <h3 class="text-3xl font-bold mt-2">${overview.total_users}</h3>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-full">
                            <i class="fas fa-users text-blue-600 text-2xl"></i>
                        </div>
                    </div>
                    <p class="text-green-600 text-sm mt-4">
                        <i class="fas fa-arrow-up mr-1"></i> Mới hôm nay: ${overview.today_bookings}
                    </p>
                </div>
                
                <div class="stat-card bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Doanh thu hôm nay</p>
                            <h3 class="text-3xl font-bold mt-2">${formatCurrency(overview.today_revenue)}</h3>
                        </div>
                        <div class="bg-green-100 p-3 rounded-full">
                            <i class="fas fa-money-bill-wave text-green-600 text-2xl"></i>
                        </div>
                    </div>
                    <p class="text-green-600 text-sm mt-4">
                        <i class="fas fa-arrow-up mr-1"></i> Tổng tháng: ${formatCurrency(overview.month_revenue)}
                    </p>
                </div>
                
                <div class="stat-card bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Đơn đặt hôm nay</p>
                            <h3 class="text-3xl font-bold mt-2">${overview.today_bookings}</h3>
                        </div>
                        <div class="bg-purple-100 p-3 rounded-full">
                            <i class="fas fa-calendar-check text-purple-600 text-2xl"></i>
                        </div>
                    </div>
                    <p class="text-gray-600 text-sm mt-4">
                        <i class="fas fa-clock mr-1"></i> Đang hoạt động: ${overview.active_bookings}
                    </p>
                </div>
                
                <div class="stat-card bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Tổng sân</p>
                            <h3 class="text-3xl font-bold mt-2">${overview.total_courts}</h3>
                        </div>
                        <div class="bg-yellow-100 p-3 rounded-full">
                            <i class="fas fa-map-marked-alt text-yellow-600 text-2xl"></i>
                        </div>
                    </div>
                    <p class="text-gray-600 text-sm mt-4">
                        <i class="fas fa-chart-bar mr-1"></i> Sử dụng: ${court_utilization.length} sân
                    </p>
                </div>
            </div>
            
            <!-- Charts and Recent Bookings -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Revenue Chart -->
                <div class="lg:col-span-2 bg-white rounded-xl shadow-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold">Doanh thu 7 ngày qua</h3>
                        <select class="border border-gray-300 rounded-lg px-3 py-1 text-sm">
                            <option>7 ngày</option>
                            <option>30 ngày</option>
                            <option>90 ngày</option>
                        </select>
                    </div>
                    <div class="h-64">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
                
                <!-- Recent Bookings -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-semibold mb-6">Đơn đặt gần đây</h3>
                    <div class="space-y-4">
                        ${recent_bookings.slice(0, 5).map(booking => `
                            <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg">
                                <div>
                                    <p class="font-medium">${booking.customer}</p>
                                    <p class="text-sm text-gray-600">${booking.court} • ${booking.time}</p>
                                </div>
                                <div class="text-right">
                                    <span class="inline-block px-2 py-1 text-xs rounded-full ${getStatusColor(booking.status)}">
                                        ${getStatusText(booking.status)}
                                    </span>
                                    <p class="font-semibold mt-1">${formatCurrency(booking.amount)}</p>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                    <a href="#" data-page="bookings" class="nav-item block text-center mt-6 text-blue-600 hover:text-blue-800 font-medium">
                        Xem tất cả <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
            
            <!-- Court Utilization -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold mb-6">Sử dụng sân (tháng này)</h3>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-3 px-4">Sân</th>
                                <th class="text-left py-3 px-4">Số lượt đặt</th>
                                <th class="text-left py-3 px-4">Tỉ lệ sử dụng</th>
                                <th class="text-left py-3 px-4">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${court_utilization.map(court => `
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3 px-4">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                                <i class="fas fa-map-marker-alt text-blue-600"></i>
                                            </div>
                                            <span class="font-medium">${court.court_name}</span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="font-semibold">${court.bookings_count}</span> lượt
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-blue-600 h-2 rounded-full" style="width: ${Math.min(court.bookings_count * 10, 100)}%"></div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                            Chi tiết
                                        </button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    `;
    
    // Render revenue chart
    renderRevenueChart(revenue_trend);
    
    // Add event listeners for nav items in content
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const page = item.getAttribute('data-page');
            loadPage(page);
        });
    });
}

// Render revenue chart
function renderRevenueChart(data) {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map(item => item.day),
            datasets: [{
                label: 'Doanh thu (VND)',
                data: data.map(item => item.revenue),
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return formatCurrency(value, true);
                        }
                    }
                }
            }
        }
    });
}

// Render dashboard error
function renderDashboardError(error) {
    pageContent.innerHTML = `
        <div class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                ${[1,2,3,4].map(i => `
                    <div class="stat-card bg-white rounded-xl shadow-lg p-6 border-l-4 border-gray-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm">Đang tải...</p>
                                <h3 class="text-3xl font-bold mt-2">--</h3>
                            </div>
                            <div class="bg-gray-100 p-3 rounded-full">
                                <i class="fas fa-spinner fa-spin text-gray-400 text-2xl"></i>
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>
            
            <div class="bg-red-50 border border-red-200 rounded-lg p-6">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl mr-3"></i>
                    <div>
                        <h3 class="font-bold text-red-800">Lỗi tải dashboard</h3>
                        <p class="text-red-700">${error}</p>
                        <button onclick="loadPage('dashboard')" class="mt-3 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
                            <i class="fas fa-redo mr-2"></i> Thử lại
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Utility functions
function updateDateTime() {
    const now = new Date();
    const dateStr = now.toLocaleDateString('vi-VN');
    const timeStr = now.toLocaleTimeString('vi-VN');
    
    document.getElementById('current-date').textContent = dateStr;
    document.getElementById('current-time').textContent = timeStr;
}

function formatCurrency(amount, short = false) {
    if (short && amount >= 1000000) {
        return (amount / 1000000).toFixed(1) + 'M';
    }
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
    }).format(amount);
}

function getStatusColor(status) {
    const colors = {
        'pending': 'bg-yellow-100 text-yellow-800',
        'confirmed': 'bg-green-100 text-green-800',
        'cancelled': 'bg-red-100 text-red-800',
        'completed': 'bg-blue-100 text-blue-800'
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
}

function getStatusText(status) {
    const texts = {
        'pending': 'Chờ xác nhận',
        'confirmed': 'Đã xác nhận',
        'cancelled': 'Đã hủy',
        'completed': 'Hoàn thành'
    };
    return texts[status] || status;
}

function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existing = document.querySelectorAll('.notification-toast');
    existing.forEach(el => el.remove());
    
    const colors = {
        'success': 'bg-green-500',
        'error': 'bg-red-500',
        'warning': 'bg-yellow-500',
        'info': 'bg-blue-500'
    };
    
    const icons = {
        'success': 'fas fa-check-circle',
        'error': 'fas fa-exclamation-circle',
        'warning': 'fas fa-exclamation-triangle',
        'info': 'fas fa-info-circle'
    };
    
    const notification = document.createElement('div');
    notification.className = `notification-toast fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg flex items-center z-50`;
    notification.innerHTML = `
        <i class="${icons[type]} mr-3 text-xl"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        notification.remove();
    }, 5000);
}

// Make functions globally available for inline event handlers
window.loadPage = loadPage;
window.showNotification = showNotification;