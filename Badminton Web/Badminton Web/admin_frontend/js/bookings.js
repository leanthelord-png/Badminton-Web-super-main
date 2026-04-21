 // Quản lý đặt sân (CRUD + thanh toán)
 // Bookings Management
let currentBookingsPage = 1;
let totalBookingsPages = 1;
let bookingsFilters = {
    status: 'all',
    court_id: '',
    start_date: '',
    end_date: ''
};

async function loadBookingsPage() {
    pageContent.innerHTML = `
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between">
                <div>
                    <h3 class="text-2xl font-bold text-gray-800">Quản lý Đặt sân</h3>
                    <p class="text-gray-600">Xem và quản lý các đơn đặt sân</p>
                </div>
                <div class="flex space-x-3 mt-4 md:mt-0">
                    <button onclick="exportBookings()" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 flex items-center">
                        <i class="fas fa-download mr-2"></i> Xuất
                    </button>
                    <button onclick="showAddBookingModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center">
                        <i class="fas fa-plus mr-2"></i> Tạo đơn đặt
                    </button>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Trạng thái</label>
                        <select id="filter-booking-status" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="all">Tất cả</option>
                            <option value="pending">Chờ xác nhận</option>
                            <option value="confirmed">Đã xác nhận</option>
                            <option value="cancelled">Đã hủy</option>
                            <option value="completed">Hoàn thành</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sân</label>
                        <select id="filter-booking-court" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Tất cả sân</option>
                            <!-- Courts will be loaded dynamically -->
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Từ ngày</label>
                        <input type="date" id="filter-start-date" 
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Đến ngày</label>
                        <input type="date" id="filter-end-date" 
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="flex items-end">
                        <button onclick="loadBookingsData()" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-search mr-2"></i> Lọc
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-lg mr-4">
                            <i class="fas fa-calendar-alt text-blue-600 text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-600">Tổng đơn đặt</p>
                            <h3 id="total-bookings" class="text-3xl font-bold mt-1">--</h3>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-3 rounded-lg mr-4">
                            <i class="fas fa-money-bill-wave text-green-600 text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-600">Doanh thu tổng</p>
                            <h3 id="total-revenue" class="text-3xl font-bold mt-1">--</h3>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center">
                        <div class="bg-yellow-100 p-3 rounded-lg mr-4">
                            <i class="fas fa-clock text-yellow-600 text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-600">Đang chờ xác nhận</p>
                            <h3 id="pending-bookings" class="text-3xl font-bold mt-1">--</h3>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center">
                        <div class="bg-purple-100 p-3 rounded-lg mr-4">
                            <i class="fas fa-check-circle text-purple-600 text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-600">Đã hoàn thành</p>
                            <h3 id="completed-bookings" class="text-3xl font-bold mt-1">--</h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Bookings Table -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="py-3 px-6 text-left">Mã đơn</th>
                                <th class="py-3 px-6 text-left">Sân & Khách hàng</th>
                                <th class="py-3 px-6 text-left">Thời gian</th>
                                <th class="py-3 px-6 text-left">Tổng tiền</th>
                                <th class="py-3 px-6 text-left">Trạng thái</th>
                                <th class="py-3 px-6 text-left">Thanh toán</th>
                                <th class="py-3 px-6 text-left">Hành động</th>
                            </tr>
                        </thead>
                        <tbody id="bookings-table-body" class="divide-y divide-gray-200">
                            <!-- Bookings will be loaded here -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Hiển thị <span id="bookings-start">1</span>-<span id="bookings-end">10</span> của <span id="bookings-total">0</span>
                    </div>
                    <div class="flex space-x-2">
                        <button onclick="changeBookingsPage(-1)" id="prev-bookings-page" class="px-3 py-1 border border-gray-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <span class="px-3 py-1 bg-blue-600 text-white rounded-lg" id="current-bookings-page">1</span>
                        <button onclick="changeBookingsPage(1)" id="next-bookings-page" class="px-3 py-1 border border-gray-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Load courts for filter
    await loadCourtsForFilter();
    
    // Load initial data
    await loadBookingsData();
    
    // Setup event listeners for filters
    document.getElementById('filter-booking-status').addEventListener('change', function() {
        bookingsFilters.status = this.value;
        loadBookingsData();
    });
    
    document.getElementById('filter-booking-court').addEventListener('change', function() {
        bookingsFilters.court_id = this.value;
        loadBookingsData();
    });
    
    document.getElementById('filter-start-date').addEventListener('change', function() {
        bookingsFilters.start_date = this.value;
        loadBookingsData();
    });
    
    document.getElementById('filter-end-date').addEventListener('change', function() {
        bookingsFilters.end_date = this.value;
        loadBookingsData();
    });
}

// Load courts for filter dropdown
async function loadCourtsForFilter() {
    try {
        const response = await fetch(`${API_BASE_URL}/courts?limit=100`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('filter-booking-court');
            data.data.courts.forEach(court => {
                if (court.is_active) {
                    const option = document.createElement('option');
                    option.value = court.court_id;
                    option.textContent = court.court_name;
                    select.appendChild(option);
                }
            });
        }
    } catch (error) {
        console.error('Error loading courts for filter:', error);
    }
}

// Load bookings data
async function loadBookingsData() {
    const { status, court_id, start_date, end_date } = bookingsFilters;
    
    let url = `${API_BASE_URL}/bookings?page=${currentBookingsPage}&limit=10`;
    
    if (status && status !== 'all') url += `&status=${status}`;
    if (court_id) url += `&court_id=${court_id}`;
    if (start_date) url += `&start_date=${start_date}`;
    if (end_date) url += `&end_date=${end_date}`;
    
    try {
        const response = await fetch(url, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            renderBookingsTable(data.data);
            updateBookingsStats(data.data);
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        console.error('Error loading bookings:', error);
        showNotification('Lỗi tải danh sách đơn đặt', 'error');
    }
}

// Render bookings table
function renderBookingsTable(data) {
    const { bookings, pagination } = data;
    
    // Update pagination
    currentBookingsPage = pagination.page;
    totalBookingsPages = pagination.totalPages;
    
    document.getElementById('bookings-start').textContent = Math.min((currentBookingsPage - 1) * pagination.limit + 1, pagination.total);
    document.getElementById('bookings-end').textContent = Math.min(currentBookingsPage * pagination.limit, pagination.total);
    document.getElementById('bookings-total').textContent = pagination.total;
    document.getElementById('current-bookings-page').textContent = currentBookingsPage;
    
    document.getElementById('prev-bookings-page').disabled = currentBookingsPage <= 1;
    document.getElementById('next-bookings-page').disabled = currentBookingsPage >= totalBookingsPages;
    
    // Render table rows
    const tbody = document.getElementById('bookings-table-body');
    
    if (bookings.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="py-8 text-center text-gray-500">
                    <i class="fas fa-calendar-times text-4xl mb-4 text-gray-300"></i>
                    <p class="text-lg">Không tìm thấy đơn đặt nào</p>
                    <p class="text-sm mt-2">Thử thay đổi bộ lọc hoặc tạo đơn đặt mới</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = bookings.map(booking => `
        <tr class="hover:bg-gray-50">
            <td class="py-4 px-6">
                <p class="font-mono font-bold">#${booking.id.toString().padStart(4, '0')}</p>
                <p class="text-xs text-gray-600">${formatDateTime(booking.createdAt)}</p>
            </td>
            <td class="py-4 px-6">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-map-marker-alt text-blue-600"></i>
                    </div>
                    <div>
                        <p class="font-semibold">${booking.court}</p>
                        <p class="text-sm text-gray-600">${booking.customer}</p>
                        ${booking.phone ? `<p class="text-xs text-gray-500">${booking.phone}</p>` : ''}
                    </div>
                </div>
            </td>
            <td class="py-4 px-6">
                <p class="font-medium">${formatDate(booking.startTime)}</p>
                <p class="text-sm text-gray-600">
                    ${new Date(booking.startTime).getHours().toString().padStart(2, '0')}:00 - 
                    ${new Date(booking.endTime).getHours().toString().padStart(2, '0')}:00
                </p>
            </td>
            <td class="py-4 px-6">
                <p class="font-bold text-lg">${formatCurrency(booking.totalPrice)}</p>
            </td>
            <td class="py-4 px-6">
                <span class="inline-block px-3 py-1 rounded-full text-sm ${getStatusColor(booking.status)}">
                    ${getStatusText(booking.status)}
                </span>
            </td>
            <td class="py-4 px-6">
                ${booking.status === 'confirmed' || booking.status === 'completed' ? `
                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-sm">
                        <i class="fas fa-check-circle mr-1"></i> Đã thanh toán
                    </span>
                ` : `
                    <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-sm">
                        <i class="fas fa-clock mr-1"></i> Chưa thanh toán
                    </span>
                `}
            </td>
            <td class="py-4 px-6">
                <div class="flex space-x-2">
                    <button onclick="viewBooking(${booking.id})" class="text-blue-600 hover:text-blue-800" title="Xem chi tiết">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button onclick="editBooking(${booking.id})" class="text-green-600 hover:text-green-800" title="Chỉnh sửa">
                        <i class="fas fa-edit"></i>
                    </button>
                    ${booking.status === 'pending' || booking.status === 'confirmed' ? `
                        <button onclick="cancelBooking(${booking.id})" class="text-red-600 hover:text-red-800" title="Hủy đơn">
                            <i class="fas fa-times"></i>
                        </button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `).join('');
}

// Update bookings statistics
function updateBookingsStats(data) {
    const { totals } = data;
    
    document.getElementById('total-bookings').textContent = totals?.totalBookings || '--';
    document.getElementById('total-revenue').textContent = formatCurrency(totals?.totalRevenue || 0, true);
    
    // These would need separate API calls or additional data
    document.getElementById('pending-bookings').textContent = '--';
    document.getElementById('completed-bookings').textContent = '--';
}

// Change bookings page
function changeBookingsPage(delta) {
    const newPage = currentBookingsPage + delta;
    if (newPage >= 1 && newPage <= totalBookingsPages) {
        currentBookingsPage = newPage;
        loadBookingsData();
    }
}

// Show add booking modal
function showAddBookingModal() {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold">Tạo đơn đặt mới</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form id="add-booking-form" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Chọn sân *</label>
                            <select name="court_id" required id="booking-court-select"
                                   class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Chọn sân...</option>
                                <!-- Courts will be loaded dynamically -->
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Chọn khách hàng *</label>
                            <select name="user_id" required id="booking-user-select"
                                   class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Chọn khách hàng...</option>
                                <!-- Users will be loaded dynamically -->
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Ngày đặt *</label>
                            <input type="date" name="booking_date" required 
                                   class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Giờ bắt đầu *</label>
                            <input type="time" name="start_time" required 
                                   class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Giờ kết thúc *</label>
                            <input type="time" name="end_time" required 
                                   class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tổng tiền (VND)</label>
                            <input type="number" name="total_price" required min="0"
                                   class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="Tự động tính toán...">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Ghi chú</label>
                        <textarea name="notes" rows="2"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Ghi chú cho đơn đặt..."></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Trạng thái</label>
                        <select name="status" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="pending">Chờ xác nhận</option>
                            <option value="confirmed">Đã xác nhận</option>
                            <option value="completed">Hoàn thành</option>
                        </select>
                    </div>
                    
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-medium">Thông tin giá</p>
                                <p class="text-sm text-gray-600">Giá sẽ được tính tự động</p>
                            </div>
                            <button type="button" onclick="calculateBookingPrice()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                                Tính giá
                            </button>
                        </div>
                        <div id="price-calculation" class="mt-2 text-sm text-gray-700">
                            <!-- Price calculation will be shown here -->
                        </div>
                    </div>
                    
                    <div class="pt-4 flex justify-end space-x-3">
                        <button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                            Hủy
                        </button>
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            Tạo đơn đặt
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.getElementById('modal-container').innerHTML = '';
    document.getElementById('modal-container').appendChild(modal);
    
    // Load courts and users for dropdowns
    loadCourtsForBooking();
    loadUsersForBooking();
    
    // Set default date and time
    const today = new Date().toISOString().split('T')[0];
    document.querySelector('input[name="booking_date"]').value = today;
    document.querySelector('input[name="start_time"]').value = '08:00';
    document.querySelector('input[name="end_time"]').value = '10:00';
    
    // Handle form submission
    document.getElementById('add-booking-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        await addBooking(new FormData(e.target));
    });
}

// Load courts for booking form
async function loadCourtsForBooking() {
    try {
        const response = await fetch(`${API_BASE_URL}/courts?limit=100&is_active=true`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('booking-court-select');
            select.innerHTML = '<option value="">Chọn sân...</option>';
            
            data.data.courts.forEach(court => {
                const option = document.createElement('option');
                option.value = court.court_id;
                option.textContent = `${court.court_name} - ${formatCurrency(court.price_per_hour)}/giờ`;
                option.setAttribute('data-price', court.price_per_hour);
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading courts for booking:', error);
    }
}

// Load users for booking form
async function loadUsersForBooking() {
    try {
        const response = await fetch(`${API_BASE_URL}/users?limit=100`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('booking-user-select');
            select.innerHTML = '<option value="">Chọn khách hàng...</option>';
            
            data.data.users.forEach(user => {
                const option = document.createElement('option');
                option.value = user.user_id;
                option.textContent = `${user.full_name} (${user.phone_number})`;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading users for booking:', error);
    }
}

// Calculate booking price
async function calculateBookingPrice() {
    const courtId = document.querySelector('select[name="court_id"]').value;
    const bookingDate = document.querySelector('input[name="booking_date"]').value;
    const startTime = document.querySelector('input[name="start_time"]').value;
    const endTime = document.querySelector('input[name="end_time"]').value;
    
    if (!courtId || !bookingDate || !startTime || !endTime) {
        showNotification('Vui lòng chọn đầy đủ thông tin sân và thời gian', 'warning');
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE_URL}/pricing/calculate`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                court_id: parseInt(courtId),
                start_time: `${bookingDate}T${startTime}`,
                end_time: `${bookingDate}T${endTime}`
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            const calculation = data.data.calculation;
            const totalPrice = calculation.total_price;
            
            // Update total price field
            document.querySelector('input[name="total_price"]').value = totalPrice;
            
            // Show price breakdown
            const priceDiv = document.getElementById('price-calculation');
            priceDiv.innerHTML = `
                <div class="mt-2">
                    <p class="font-medium">Chi tiết tính giá:</p>
                    <ul class="mt-1 space-y-1">
                        ${calculation.hour_details.map(hour => `
                            <li class="flex justify-between">
                                <span>${hour.hour}: ${hour.hours_booked.toFixed(1)} giờ × ${formatCurrency(hour.price_per_hour)}</span>
                                <span>${formatCurrency(hour.subtotal)}</span>
                            </li>
                        `).join('')}
                    </ul>
                    <div class="border-t mt-2 pt-2 flex justify-between font-bold">
                        <span>Tổng cộng:</span>
                        <span>${formatCurrency(totalPrice)}</span>
                    </div>
                </div>
            `;
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        showNotification(`Lỗi tính giá: ${error.message}`, 'error');
    }
}

// Add booking
async function addBooking(formData) {
    const data = Object.fromEntries(formData.entries());
    
    // Format date and time
    const bookingDate = data.booking_date;
    const startTime = `${bookingDate}T${data.start_time}`;
    const endTime = `${bookingDate}T${data.end_time}`;
    
    const bookingData = {
        court_id: parseInt(data.court_id),
        user_id: parseInt(data.user_id),
        start_time: new Date(startTime).toISOString(),
        end_time: new Date(endTime).toISOString(),
        total_price: parseFloat(data.total_price),
        status: data.status,
        notes: data.notes
    };
    
    try {
        const response = await fetch(`${API_BASE_URL}/bookings`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(bookingData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Tạo đơn đặt thành công', 'success');
            closeModal();
            loadBookingsData();
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        showNotification(`Lỗi: ${error.message}`, 'error');
    }
}

// View booking details
async function viewBooking(bookingId) {
    try {
        const response = await fetch(`${API_BASE_URL}/bookings/${bookingId}`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showBookingModal(data.data);
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        showNotification(`Lỗi: ${error.message}`, 'error');
    }
}

// Show booking modal
function showBookingModal(data) {
    const { booking, payments } = data;
    
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="text-xl font-bold">Chi tiết đơn đặt #${booking.id.toString().padStart(4, '0')}</h3>
                        <p class="text-gray-600">Ngày tạo: ${formatDateTime(booking.createdAt)}</p>
                    </div>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="space-y-6">
                    <!-- Booking Info -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-white border border-gray-200 rounded-xl p-6">
                            <h5 class="font-bold mb-4">Thông tin sân</h5>
                            <div class="space-y-3">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-map-marker-alt text-blue-600"></i>
                                    </div>
                                    <div>
                                        <p class="font-semibold">${booking.court.court_name}</p>
                                        <p class="text-sm text-gray-600">${booking.court.court_type || 'Tiêu chuẩn'}</p>
                                    </div>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Giá/giờ:</span>
                                    <span class="font-bold">${formatCurrency(booking.court.price_per_hour)}</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white border border-gray-200 rounded-xl p-6">
                            <h5 class="font-bold mb-4">Thông tin khách hàng</h5>
                            <div class="space-y-3">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-user text-green-600"></i>
                                    </div>
                                    <div>
                                        <p class="font-semibold">${booking.customer.full_name}</p>
                                        <p class="text-sm text-gray-600">${booking.customer.phone_number}</p>
                                    </div>
                                </div>
                                ${booking.customer.email ? `
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Email:</span>
                                        <span>${booking.customer.email}</span>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Booking Details -->
                    <div class="bg-white border border-gray-200 rounded-xl p-6">
                        <h5 class="font-bold mb-4">Chi tiết đơn đặt</h5>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Ngày đặt:</span>
                                        <span class="font-medium">${formatDate(booking.startTime)}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Giờ bắt đầu:</span>
                                        <span class="font-medium">${new Date(booking.startTime).getHours().toString().padStart(2, '0')}:00</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Giờ kết thúc:</span>
                                        <span class="font-medium">${new Date(booking.endTime).getHours().toString().padStart(2, '0')}:00</span>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Tổng thời gian:</span>
                                        <span class="font-medium">${((new Date(booking.endTime) - new Date(booking.startTime)) / (1000 * 60 * 60)).toFixed(1)} giờ</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Trạng thái:</span>
                                        <span class="px-2 py-1 rounded text-sm ${getStatusColor(booking.status)}">
                                            ${getStatusText(booking.status)}
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Tổng tiền:</span>
                                        <span class="font-bold text-lg">${formatCurrency(booking.totalPrice)}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payments -->
                    <div class="bg-white border border-gray-200 rounded-xl p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h5 class="font-bold">Lịch sử thanh toán</h5>
                            ${booking.status === 'confirmed' ? `
                                <button onclick="addPayment(${booking.id})" class="bg-blue-600 text-white px-3 py-1 rounded-lg text-sm hover:bg-blue-700">
                                    <i class="fas fa-plus mr-1"></i> Thêm thanh toán
                                </button>
                            ` : ''}
                        </div>
                        
                        ${payments && payments.length > 0 ? `
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead>
                                        <tr class="border-b">
                                            <th class="py-2 px-4 text-left">Ngày</th>
                                            <th class="py-2 px-4 text-left">Phương thức</th>
                                            <th class="py-2 px-4 text-left">Số tiền</th>
                                            <th class="py-2 px-4 text-left">Mã giao dịch</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${payments.map(payment => `
                                            <tr class="border-b hover:bg-gray-50">
                                                <td class="py-3 px-4">${formatDateTime(payment.date)}</td>
                                                <td class="py-3 px-4">${payment.method || 'Tiền mặt'}</td>
                                                <td class="py-3 px-4 font-bold">${formatCurrency(payment.amount)}</td>
                                                <td class="py-3 px-4 font-mono text-sm">${payment.reference || '--'}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        ` : `
                            <p class="text-gray-500 text-center py-4">Chưa có thanh toán nào</p>
                        `}
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex justify-end space-x-3">
                        ${booking.status === 'pending' ? `
                            <button onclick="updateBookingStatus(${booking.id}, 'confirmed')" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                                <i class="fas fa-check mr-2"></i> Xác nhận đơn
                            </button>
                        ` : ''}
                        
                        ${booking.status === 'confirmed' && (!payments || payments.length === 0) ? `
                            <button onclick="updateBookingStatus(${booking.id}, 'completed')" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                                <i class="fas fa-check-double mr-2"></i> Hoàn thành
                            </button>
                        ` : ''}
                        
                        ${booking.status === 'pending' || booking.status === 'confirmed' ? `
                            <button onclick="cancelBooking(${booking.id})" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
                                <i class="fas fa-times mr-2"></i> Hủy đơn
                            </button>
                        ` : ''}
                        
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

// Edit booking
function editBooking(bookingId) {
    showNotification('Tính năng chỉnh sửa đơn đặt đang được phát triển', 'info');
}

// Update booking status
async function updateBookingStatus(bookingId, status) {
    const statusText = {
        'confirmed': 'xác nhận',
        'cancelled': 'hủy',
        'completed': 'hoàn thành'
    }[status];
    
    if (!confirm(`Bạn có chắc chắn muốn ${statusText} đơn đặt này?`)) {
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE_URL}/bookings/${bookingId}/status`, {
            method: 'PATCH',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ status })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(`Đã ${statusText} đơn đặt thành công`, 'success');
            closeModal();
            loadBookingsData();
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        showNotification(`Lỗi: ${error.message}`, 'error');
    }
}

// Cancel booking
async function cancelBooking(bookingId) {
    if (!confirm('Bạn có chắc chắn muốn hủy đơn đặt này?')) {
        return;
    }
    
    await updateBookingStatus(bookingId, 'cancelled');
}

// Add payment
function addPayment(bookingId) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold">Thêm thanh toán</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form id="add-payment-form" class="space-y-4">
                    <input type="hidden" name="booking_id" value="${bookingId}">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Số tiền (VND) *</label>
                        <input type="number" name="amount_paid" required min="0" step="1000"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Phương thức thanh toán *</label>
                        <select name="payment_method" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Chọn phương thức...</option>
                            <option value="cash">Tiền mặt</option>
                            <option value="bank_transfer">Chuyển khoản</option>
                            <option value="credit_card">Thẻ tín dụng</option>
                            <option value="momo">Ví MoMo</option>
                            <option value="zalopay">ZaloPay</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Mã giao dịch</label>
                        <input type="text" name="transaction_reference"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Nếu có">
                    </div>
                    
                    <div class="pt-4 flex justify-end space-x-3">
                        <button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                            Hủy
                        </button>
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            Thêm thanh toán
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.getElementById('modal-container').innerHTML = '';
    document.getElementById('modal-container').appendChild(modal);
    
    // Handle form submission
    document.getElementById('add-payment-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        await submitPayment(new FormData(e.target));
    });
}

// Submit payment
async function submitPayment(formData) {
    const data = Object.fromEntries(formData.entries());
    const bookingId = data.booking_id;
    
    try {
        const response = await fetch(`${API_BASE_URL}/bookings/${bookingId}/payment`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                amount_paid: parseFloat(data.amount_paid),
                payment_method: data.payment_method,
                transaction_reference: data.transaction_reference
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Thêm thanh toán thành công', 'success');
            closeModal();
            viewBooking(bookingId); // Reload booking view
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        showNotification(`Lỗi: ${error.message}`, 'error');
    }
}

// Export bookings
function exportBookings() {
    showNotification('Tính năng xuất dữ liệu đang được phát triển', 'info');
}

// Utility function for date time formatting
function formatDateTime(dateString) {
    const date = new Date(dateString);
    return `${date.toLocaleDateString('vi-VN')} ${date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })}`;
}

// Make functions available globally
window.loadBookingsPage = loadBookingsPage;
window.loadBookingsData = loadBookingsData;
window.changeBookingsPage = changeBookingsPage;
window.showAddBookingModal = showAddBookingModal;
window.viewBooking = viewBooking;
window.editBooking = editBooking;
window.cancelBooking = cancelBooking;
window.updateBookingStatus = updateBookingStatus;
window.addPayment = addPayment;
window.exportBookings = exportBookings;
window.calculateBookingPrice = calculateBookingPrice;