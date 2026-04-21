// Reports Management
let currentChart = null;

async function loadReportsPage() {
    pageContent.innerHTML = `
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between">
                <div>
                    <h3 class="text-2xl font-bold text-gray-800">Báo cáo & Thống kê</h3>
                    <p class="text-gray-600">Thống kê doanh thu và lượt đặt sân</p>
                </div>
                <div class="flex space-x-3 mt-4 md:mt-0">
                    <button onclick="printReport()" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 flex items-center">
                        <i class="fas fa-print mr-2"></i> In báo cáo
                    </button>
                    <button onclick="exportReport()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center">
                        <i class="fas fa-download mr-2"></i> Xuất Excel
                    </button>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl p-6 text-white">
                    <div class="flex items-center">
                        <div class="bg-white bg-opacity-20 p-3 rounded-lg mr-4">
                            <i class="fas fa-money-bill-wave text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-sm opacity-90">Doanh thu tháng</p>
                            <h3 id="month-revenue-stat" class="text-3xl font-bold mt-1">--</h3>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl p-6 text-white">
                    <div class="flex items-center">
                        <div class="bg-white bg-opacity-20 p-3 rounded-lg mr-4">
                            <i class="fas fa-calendar-alt text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-sm opacity-90">Lượt đặt tháng</p>
                            <h3 id="month-bookings-stat" class="text-3xl font-bold mt-1">--</h3>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl p-6 text-white">
                    <div class="flex items-center">
                        <div class="bg-white bg-opacity-20 p-3 rounded-lg mr-4">
                            <i class="fas fa-users text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-sm opacity-90">Khách hàng mới</p>
                            <h3 id="new-users-stat" class="text-3xl font-bold mt-1">--</h3>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 rounded-xl p-6 text-white">
                    <div class="flex items-center">
                        <div class="bg-white bg-opacity-20 p-3 rounded-lg mr-4">
                            <i class="fas fa-percentage text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-sm opacity-90">Tỷ lệ lấp đầy</p>
                            <h3 id="occupancy-rate" class="text-3xl font-bold mt-1">--%</h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Loại báo cáo</label>
                        <select id="report-type" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="revenue">Doanh thu</option>
                            <option value="bookings">Lượt đặt sân</option>
                            <option value="users">Khách hàng</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Kỳ báo cáo</label>
                        <select id="report-period" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="today">Hôm nay</option>
                            <option value="week">Tuần này</option>
                            <option value="month" selected>Tháng này</option>
                            <option value="year">Năm nay</option>
                            <option value="custom">Tùy chỉnh</option>
                        </select>
                    </div>
                    
                    <div id="custom-dates" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Từ ngày</label>
                        <input type="date" id="start-date" 
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div id="custom-dates-end" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Đến ngày</label>
                        <input type="date" id="end-date" 
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="flex items-end">
                        <button onclick="generateReport()" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-chart-bar mr-2"></i> Tạo báo cáo
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h5 class="font-bold text-lg">Biểu đồ doanh thu</h5>
                        <div class="flex space-x-2">
                            <button onclick="changeChartType('line')" class="px-3 py-1 text-sm rounded-lg bg-blue-100 text-blue-800">
                                <i class="fas fa-chart-line"></i>
                            </button>
                            <button onclick="changeChartType('bar')" class="px-3 py-1 text-sm rounded-lg bg-gray-100 text-gray-800">
                                <i class="fas fa-chart-bar"></i>
                            </button>
                        </div>
                    </div>
                    <div class="h-80">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h5 class="font-bold text-lg">Phân bố lượt đặt</h5>
                        <select id="distribution-type" class="border border-gray-300 rounded-lg px-3 py-1 text-sm">
                            <option value="hour">Theo giờ</option>
                            <option value="day">Theo ngày</option>
                            <option value="court">Theo sân</option>
                        </select>
                    </div>
                    <div class="h-80">
                        <canvas id="distributionChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Report Details -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h5 class="font-bold text-lg">Chi tiết báo cáo</h5>
                    <div class="text-sm text-gray-600" id="report-period-text">
                        Tháng này
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full" id="report-details-table">
                        <thead>
                            <tr class="border-b">
                                <th class="py-3 px-4 text-left">Ngày</th>
                                <th class="py-3 px-4 text-left">Doanh thu</th>
                                <th class="py-3 px-4 text-left">Lượt đặt</th>
                                <th class="py-3 px-4 text-left">Khách hàng mới</th>
                                <th class="py-3 px-4 text-left">Tỷ lệ lấp đầy</th>
                            </tr>
                        </thead>
                        <tbody id="report-details-body">
                            <!-- Report details will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Top Performers -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h5 class="font-bold text-lg mb-6">Top sân có doanh thu cao</h5>
                    <div class="space-y-4" id="top-courts">
                        <!-- Top courts will be loaded here -->
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h5 class="font-bold text-lg mb-6">Top khách hàng thân thiết</h5>
                    <div class="space-y-4" id="top-customers">
                        <!-- Top customers will be loaded here -->
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h5 class="font-bold text-lg mb-6">Giờ cao điểm</h5>
                    <div class="space-y-4" id="peak-hours">
                        <!-- Peak hours will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Setup event listeners
    document.getElementById('report-period').addEventListener('change', function() {
        const isCustom = this.value === 'custom';
        document.getElementById('custom-dates').classList.toggle('hidden', !isCustom);
        document.getElementById('custom-dates-end').classList.toggle('hidden', !isCustom);
    });
    
    document.getElementById('distribution-type').addEventListener('change', generateReport);
    
    // Generate initial report
    await generateReport();
}

// Generate report
async function generateReport() {
    const reportType = document.getElementById('report-type').value;
    const period = document.getElementById('report-period').value;
    const startDate = document.getElementById('start-date')?.value;
    const endDate = document.getElementById('end-date')?.value;
    
    // Update period text
    const periodText = getPeriodText(period, startDate, endDate);
    document.getElementById('report-period-text').textContent = periodText;
    
    try {
        let url;
        let params = [];
        
        if (period === 'custom' && startDate && endDate) {
            params.push(`start_date=${startDate}`);
            params.push(`end_date=${endDate}`);
        } else if (period !== 'custom') {
            params.push(`period=${period}`);
        }
        
        if (reportType === 'revenue') {
            url = `${API_BASE_URL}/reports/revenue?${params.join('&')}`;
            await loadRevenueReport(url);
        } else if (reportType === 'bookings') {
            url = `${API_BASE_URL}/reports/bookings?${params.join('&')}`;
            await loadBookingsReport(url);
        }
        
        // Also load dashboard stats for quick stats
        await loadDashboardStats();
        
    } catch (error) {
        console.error('Error generating report:', error);
        showNotification('Lỗi tạo báo cáo', 'error');
    }
}

// Load revenue report
async function loadRevenueReport(url) {
    try {
        const response = await fetch(url, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            renderRevenueReport(data.data);
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        console.error('Error loading revenue report:', error);
        throw error;
    }
}

// Render revenue report
function renderRevenueReport(data) {
    const { summary, breakdown, bookings } = data;
    
    // Update quick stats
    document.getElementById('month-revenue-stat').textContent = formatCurrency(summary.total_revenue, true);
    document.getElementById('month-bookings-stat').textContent = summary.total_bookings;
    
    // Render revenue chart
    renderRevenueChart(breakdown.by_date);
    
    // Render distribution chart
    const distributionType = document.getElementById('distribution-type').value;
    if (distributionType === 'court') {
        renderCourtDistributionChart(breakdown.by_court);
    } else if (distributionType === 'hour') {
        // We need hourly data, but API doesn't provide it directly
        // For now, use dummy data or calculate from bookings
        renderHourlyDistributionChart(bookings);
    }
    
    // Render report details
    renderReportDetails(breakdown.by_date);
    
    // Render top courts
    renderTopCourts(breakdown.by_court);
}

// Load bookings report
async function loadBookingsReport(url) {
    try {
        const response = await fetch(url, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            renderBookingsReport(data.data);
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        console.error('Error loading bookings report:', error);
        throw error;
    }
}

// Render bookings report
function renderBookingsReport(data) {
    const { summary, analysis, bookings } = data;
    
    // Update quick stats
    document.getElementById('month-bookings-stat').textContent = summary.total_bookings;
    
    // Calculate total revenue from bookings
    const totalRevenue = bookings.reduce((sum, booking) => sum + booking.total_price, 0);
    document.getElementById('month-revenue-stat').textContent = formatCurrency(totalRevenue, true);
    
    // Render bookings chart
    renderBookingsChart(analysis.hourly_distribution);
    
    // Render court distribution chart
    renderCourtDistributionChart(analysis.court_utilization);
    
    // Render report details
    renderBookingsDetails(analysis.daily_distribution);
    
    // Render peak hours
    renderPeakHours(analysis.peak_hours || analysis.hourly_distribution);
}

// Load dashboard stats for quick stats
async function loadDashboardStats() {
    try {
        const response = await fetch(`${API_BASE_URL}/reports/dashboard`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            const { overview, court_utilization } = data.data;
            
            // Update remaining stats
            document.getElementById('new-users-stat').textContent = '--'; // Would need separate API
            document.getElementById('occupancy-rate').textContent = calculateOccupancyRate(court_utilization);
        }
    } catch (error) {
        console.error('Error loading dashboard stats:', error);
    }
}

// Render revenue chart
function renderRevenueChart(data) {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    
    // Destroy existing chart
    if (currentChart) {
        currentChart.destroy();
    }
    
    const labels = data.map(item => item.date);
    const revenues = data.map(item => item.revenue);
    
    currentChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Doanh thu (VND)',
                data: revenues,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `Doanh thu: ${formatCurrency(context.parsed.y)}`;
                        }
                    }
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
                },
                x: {
                    ticks: {
                        maxRotation: 45,
                        minRotation: 45
                    }
                }
            }
        }
    });
}

// Change chart type
function changeChartType(type) {
    if (currentChart) {
        currentChart.config.type = type;
        currentChart.update();
        
        // Update button styles
        const buttons = document.querySelectorAll('#revenueChart').parentElement.previousElementSibling.querySelectorAll('button');
        buttons.forEach(btn => {
            if (btn.innerHTML.includes(type === 'line' ? 'chart-line' : 'chart-bar')) {
                btn.className = 'px-3 py-1 text-sm rounded-lg bg-blue-100 text-blue-800';
            } else {
                btn.className = 'px-3 py-1 text-sm rounded-lg bg-gray-100 text-gray-800';
            }
        });
    }
}

// Render court distribution chart
function renderCourtDistributionChart(data) {
    const ctx = document.getElementById('distributionChart').getContext('2d');
    
    const labels = data.slice(0, 10).map(item => item.court_name || item.court);
    const values = data.slice(0, 10).map(item => item.bookings || item.revenue);
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: document.getElementById('distribution-type').value === 'court' ? 'Lượt đặt' : 'Doanh thu',
                data: values,
                backgroundColor: [
                    '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                    '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#6366f1'
                ],
                borderWidth: 1
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
                    beginAtZero: true
                }
            }
        }
    });
}

// Render hourly distribution chart
function renderHourlyDistributionChart(bookings) {
    const ctx = document.getElementById('distributionChart').getContext('2d');
    
    // Calculate hourly distribution from bookings
    const hourlyCounts = Array(24).fill(0);
    bookings.forEach(booking => {
        const hour = new Date(booking.start_time || booking.startTime).getHours();
        hourlyCounts[hour]++;
    });
    
    const labels = Array.from({length: 24}, (_, i) => `${i.toString().padStart(2, '0')}:00`);
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Lượt đặt',
                data: hourlyCounts,
                backgroundColor: '#10b981',
                borderWidth: 1
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
                        stepSize: 1
                    }
                }
            }
        }
    });
}

// Render bookings chart
function renderBookingsChart(data) {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    
    // Destroy existing chart
    if (currentChart) {
        currentChart.destroy();
    }
    
    const labels = data.map(item => item.hour);
    const bookings = data.map(item => item.bookings);
    
    currentChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Lượt đặt',
                data: bookings,
                backgroundColor: '#10b981',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

// Render report details
function renderReportDetails(data) {
    const tbody = document.getElementById('report-details-body');
    
    if (data.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="py-8 text-center text-gray-500">
                    Không có dữ liệu cho kỳ báo cáo này
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = data.map(item => `
        <tr class="border-b hover:bg-gray-50">
            <td class="py-3 px-4">
                <p class="font-medium">${item.date}</p>
                <p class="text-sm text-gray-600">${getDayName(item.date)}</p>
            </td>
            <td class="py-3 px-4">
                <p class="font-bold text-blue-600">${formatCurrency(item.revenue)}</p>
            </td>
            <td class="py-3 px-4">
                <p class="font-semibold">${item.bookings}</p>
                <p class="text-sm text-gray-600">lượt</p>
            </td>
            <td class="py-3 px-4">
                <p>--</p>
            </td>
            <td class="py-3 px-4">
                <div class="flex items-center">
                    <div class="w-24 bg-gray-200 rounded-full h-2 mr-3">
                        <div class="bg-green-500 h-2 rounded-full" style="width: ${Math.min(item.bookings * 5, 100)}%"></div>
                    </div>
                    <span class="text-sm">${Math.min(item.bookings * 5, 100)}%</span>
                </div>
            </td>
        </tr>
    `).join('');
}

// Render bookings details
function renderBookingsDetails(data) {
    const tbody = document.getElementById('report-details-body');
    
    if (data.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="py-8 text-center text-gray-500">
                    Không có dữ liệu cho kỳ báo cáo này
                </td>
            </tr>
        `;
        return;
    }
    
    // Calculate totals per day
    const daysData = {};
    data.forEach(item => {
        if (!daysData[item.day]) {
            daysData[item.day] = { bookings: 0, revenue: 0 };
        }
        daysData[item.day].bookings += item.bookings;
    });
    
    const days = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
    
    tbody.innerHTML = days.map((day, index) => {
        const dayData = daysData[day] || { bookings: 0, revenue: 0 };
        return `
            <tr class="border-b hover:bg-gray-50">
                <td class="py-3 px-4">
                    <p class="font-medium">${day}</p>
                </td>
                <td class="py-3 px-4">
                    <p class="font-bold text-blue-600">--</p>
                </td>
                <td class="py-3 px-4">
                    <p class="font-semibold">${dayData.bookings}</p>
                    <p class="text-sm text-gray-600">lượt</p>
                </td>
                <td class="py-3 px-4">
                    <p>--</p>
                </td>
                <td class="py-3 px-4">
                    <div class="flex items-center">
                        <div class="w-24 bg-gray-200 rounded-full h-2 mr-3">
                            <div class="bg-green-500 h-2 rounded-full" style="width: ${Math.min(dayData.bookings * 10, 100)}%"></div>
                        </div>
                        <span class="text-sm">${Math.min(dayData.bookings * 10, 100)}%</span>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

// Render top courts
function renderTopCourts(courts) {
    const container = document.getElementById('top-courts');
    
    if (!courts || courts.length === 0) {
        container.innerHTML = `
            <p class="text-gray-500 text-center py-4">Không có dữ liệu</p>
        `;
        return;
    }
    
    const topCourts = courts.slice(0, 5);
    
    container.innerHTML = topCourts.map((court, index) => `
        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
            <div class="flex items-center">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 
                    ${index === 0 ? 'bg-yellow-100 text-yellow-800' : 
                      index === 1 ? 'bg-gray-100 text-gray-800' : 
                      index === 2 ? 'bg-orange-100 text-orange-800' : 
                      'bg-blue-100 text-blue-800'}">
                    <span class="font-bold">${index + 1}</span>
                </div>
                <div>
                    <p class="font-medium">${court.court_name || court.court}</p>
                    <p class="text-sm text-gray-600">${court.bookings || 0} lượt đặt</p>
                </div>
            </div>
            <div class="text-right">
                <p class="font-bold">${formatCurrency(court.revenue || 0, true)}</p>
                <p class="text-sm text-gray-600">doanh thu</p>
            </div>
        </div>
    `).join('');
}

// Render peak hours
function renderPeakHours(hours) {
    const container = document.getElementById('peak-hours');
    
    if (!hours || hours.length === 0) {
        container.innerHTML = `
            <p class="text-gray-500 text-center py-4">Không có dữ liệu</p>
        `;
        return;
    }
    
    const peakHours = hours.slice(0, 5);
    
    container.innerHTML = peakHours.map(hour => `
        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-fire text-red-600"></i>
                </div>
                <div>
                    <p class="font-medium">${hour.hour || hour.time}</p>
                    <p class="text-sm text-gray-600">${hour.bookings || 0} lượt đặt</p>
                </div>
            </div>
            <div class="text-right">
                <div class="w-24 bg-gray-200 rounded-full h-2">
                    <div class="bg-red-500 h-2 rounded-full" 
                         style="width: ${(hour.bookings || 0) * 10}%"></div>
                </div>
            </div>
        </div>
    `).join('');
}

// Print report
function printReport() {
    const reportType = document.getElementById('report-type').value;
    const periodText = document.getElementById('report-period-text').textContent;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Báo cáo ${reportType === 'revenue' ? 'Doanh thu' : 'Lượt đặt'} - Badminton Court</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    .header { text-align: center; margin-bottom: 30px; }
                    .header h1 { color: #333; margin-bottom: 5px; }
                    .header p { color: #666; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                    th { background-color: #f5f5f5; font-weight: bold; }
                    .summary { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
                    .summary-item { display: inline-block; margin-right: 30px; }
                    .summary-label { color: #666; font-size: 14px; }
                    .summary-value { font-size: 24px; font-weight: bold; color: #333; }
                    .print-date { text-align: right; color: #666; font-size: 12px; margin-bottom: 20px; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>BÁO CÁO ${reportType === 'revenue' ? 'DOANH THU' : 'LƯỢT ĐẶT SÂN'}</h1>
                    <p>Hệ thống Quản lý Sân Cầu lông</p>
                    <p>Kỳ báo cáo: ${periodText}</p>
                </div>
                
                <div class="print-date">
                    In ngày: ${new Date().toLocaleDateString('vi-VN')}
                </div>
                
                <div class="summary">
                    <div class="summary-item">
                        <div class="summary-label">Doanh thu</div>
                        <div class="summary-value" id="print-revenue">${document.getElementById('month-revenue-stat').textContent}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Lượt đặt</div>
                        <div class="summary-value" id="print-bookings">${document.getElementById('month-bookings-stat').textContent}</div>
                    </div>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Ngày</th>
                            <th>Doanh thu</th>
                            <th>Lượt đặt</th>
                            <th>Tỷ lệ lấp đầy</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${document.querySelectorAll('#report-details-body tr').length > 0 ? 
                          document.querySelector('#report-details-body').innerHTML.replace(/class="[^"]*"/g, '') : 
                          '<tr><td colspan="4" style="text-align: center; color: #666;">Không có dữ liệu</td></tr>'}
                    </tbody>
                </table>
                
                <script>
                    window.onload = function() {
                        window.print();
                    }
                </script>
            </body>
        </html>
    `);
    printWindow.document.close();
}

// Export report
async function exportReport() {
    const reportType = document.getElementById('report-type').value;
    const period = document.getElementById('report-period').value;
    const startDate = document.getElementById('start-date')?.value;
    const endDate = document.getElementById('end-date')?.value;
    
    let url = `${API_BASE_URL}/reports/export?report_type=${reportType}&format=csv`;
    
    if (period === 'custom' && startDate && endDate) {
        url += `&start_date=${startDate}&end_date=${endDate}`;
    } else if (period !== 'custom') {
        url += `&period=${period}`;
    }
    
    try {
        const response = await fetch(url, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Create and download CSV file
            const csvContent = convertToCSV(data.data.data);
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            
            const filename = `bao_cao_${reportType}_${new Date().toISOString().split('T')[0]}.csv`;
            link.href = URL.createObjectURL(blob);
            link.download = filename;
            link.click();
            
            showNotification('Xuất báo cáo thành công', 'success');
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        console.error('Error exporting report:', error);
        showNotification('Lỗi xuất báo cáo', 'error');
    }
}

// Convert data to CSV
function convertToCSV(data) {
    if (!data || data.length === 0) return '';
    
    const headers = Object.keys(data[0]);
    const csvRows = [
        headers.join(','),
        ...data.map(row => 
            headers.map(header => {
                const value = row[header];
                return typeof value === 'string' ? `"${value.replace(/"/g, '""')}"` : value;
            }).join(',')
        )
    ];
    
    return csvRows.join('\n');
}

// Utility functions
function getPeriodText(period, startDate, endDate) {
    const texts = {
        'today': 'Hôm nay',
        'week': 'Tuần này',
        'month': 'Tháng này',
        'year': 'Năm nay',
        'custom': startDate && endDate ? 
            `Từ ${formatDate(startDate)} đến ${formatDate(endDate)}` : 
            'Tùy chỉnh'
    };
    return texts[period] || period;
}

function getDayName(dateString) {
    const date = new Date(dateString);
    const days = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
    return days[date.getDay()];
}

function calculateOccupancyRate(courtUtilization) {
    if (!courtUtilization || courtUtilization.length === 0) return '0%';
    
    const totalBookings = courtUtilization.reduce((sum, court) => sum + court.bookings_count, 0);
    const maxPossible = courtUtilization.length * 30 * 8; // Assuming 30 days, 8 hours per day per court
    
    const rate = Math.round((totalBookings / maxPossible) * 100);
    return isNaN(rate) ? '0%' : `${rate}%`;
}

// Make functions available globally
window.loadReportsPage = loadReportsPage;
window.generateReport = generateReport;
window.changeChartType = changeChartType;
window.printReport = printReport;
window.exportReport = exportReport;