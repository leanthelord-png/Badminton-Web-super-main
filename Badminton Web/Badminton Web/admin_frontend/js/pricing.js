// Pricing Management
async function loadPricingPage() {
    pageContent.innerHTML = `
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between">
                <div>
                    <h3 class="text-2xl font-bold text-gray-800">Quản lý Giá & Khung giờ</h3>
                    <p class="text-gray-600">Cấu hình giá theo khung giờ cao điểm/thường</p>
                </div>
                <div class="flex space-x-3 mt-4 md:mt-0">
                    <button onclick="showPricePreview()" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 flex items-center">
                        <i class="fas fa-eye mr-2"></i> Xem trước giá
                    </button>
                    <button onclick="showAddPricingSlotModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center">
                        <i class="fas fa-plus mr-2"></i> Thêm khung giờ
                    </button>
                </div>
            </div>
            
            <!-- Pricing System Info -->
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-xl font-bold mb-2">Hệ thống giá theo khung giờ</h4>
                        <p class="text-blue-100">Giá sân = Giá cơ bản × Hệ số khung giờ</p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-3 rounded-lg">
                        <i class="fas fa-tags text-3xl"></i>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    <div class="bg-white bg-opacity-10 p-4 rounded-lg">
                        <p class="text-sm">Giờ thường</p>
                        <p class="text-2xl font-bold">×1.0 - 1.2</p>
                    </div>
                    <div class="bg-white bg-opacity-10 p-4 rounded-lg">
                        <p class="text-sm">Giờ cao điểm</p>
                        <p class="text-2xl font-bold">×1.5 - 2.0</p>
                    </div>
                    <div class="bg-white bg-opacity-10 p-4 rounded-lg">
                        <p class="text-sm">Cuối tuần</p>
                        <p class="text-2xl font-bold">+20% - 50%</p>
                    </div>
                </div>
            </div>
            
            <!-- Pricing Slots -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h5 class="font-bold text-lg">Khung giờ giá</h5>
                    <div class="flex space-x-2">
                        <select id="filter-day-type" class="border border-gray-300 rounded-lg px-3 py-1 text-sm">
                            <option value="all">Tất cả ngày</option>
                            <option value="weekday">Ngày thường</option>
                            <option value="weekend">Cuối tuần</option>
                            <option value="holiday">Ngày lễ</option>
                        </select>
                        <select id="filter-peak" class="border border-gray-300 rounded-lg px-3 py-1 text-sm">
                            <option value="all">Tất cả</option>
                            <option value="peak">Giờ cao điểm</option>
                            <option value="normal">Giờ thường</option>
                        </select>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b">
                                <th class="py-3 px-4 text-left">Khung giờ</th>
                                <th class="py-3 px-4 text-left">Thời gian</th>
                                <th class="py-3 px-4 text-left">Loại ngày</th>
                                <th class="py-3 px-4 text-left">Hệ số</th>
                                <th class="py-3 px-4 text-left">Cao điểm</th>
                                <th class="py-3 px-4 text-left">Trạng thái</th>
                                <th class="py-3 px-4 text-left">Hành động</th>
                            </tr>
                        </thead>
                        <tbody id="pricing-slots-table">
                            <tr>
                                <td colspan="7" class="py-8 text-center">
                                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
                                    <p class="mt-4 text-gray-600">Đang tải khung giờ...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Court Specific Pricing -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h5 class="font-bold text-lg mb-6">Giá riêng cho từng sân</h5>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Chọn sân để cấu hình giá</label>
                    <select id="court-for-pricing" class="w-full md:w-auto border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Chọn sân...</option>
                        <!-- Courts will be loaded dynamically -->
                    </select>
                </div>
                
                <div id="court-pricing-details">
                    <p class="text-gray-500 text-center py-8">Vui lòng chọn sân để xem và cấu hình giá</p>
                </div>
            </div>
        </div>
    `;
    
    // Load pricing slots
    await loadPricingSlots();
    
    // Load courts for dropdown
    await loadCourtsForPricing();
    
    // Setup event listeners
    document.getElementById('filter-day-type').addEventListener('change', loadPricingSlots);
    document.getElementById('filter-peak').addEventListener('change', loadPricingSlots);
    document.getElementById('court-for-pricing').addEventListener('change', function() {
        if (this.value) {
            loadCourtPricing(this.value);
        }
    });
}

// Load pricing slots
async function loadPricingSlots() {
    const dayType = document.getElementById('filter-day-type').value;
    const isPeak = document.getElementById('filter-peak').value;
    
    let url = `${API_BASE_URL}/pricing/slots`;
    const params = [];
    
    if (dayType !== 'all') params.push(`day_type=${dayType}`);
    if (isPeak !== 'all') params.push(`is_peak=${isPeak === 'peak'}`);
    
    if (params.length > 0) url += `?${params.join('&')}`;
    
    try {
        const response = await fetch(url, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            renderPricingSlots(data.data);
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        console.error('Error loading pricing slots:', error);
        showNotification('Lỗi tải khung giờ giá', 'error');
    }
}

// Render pricing slots
function renderPricingSlots(slots) {
    const tbody = document.getElementById('pricing-slots-table');
    
    if (slots.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="py-8 text-center text-gray-500">
                    <i class="fas fa-clock text-4xl mb-4 text-gray-300"></i>
                    <p class="text-lg">Không có khung giờ nào</p>
                    <p class="text-sm mt-2">Thêm khung giờ mới để bắt đầu</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = slots.map(slot => `
        <tr class="border-b hover:bg-gray-50">
            <td class="py-3 px-4">
                <p class="font-semibold">${slot.slot_name}</p>
                <p class="text-sm text-gray-600">${slot.description || 'Không có mô tả'}</p>
            </td>
            <td class="py-3 px-4">
                <p class="font-medium">${slot.time_display}</p>
            </td>
            <td class="py-3 px-4">
                <span class="px-2 py-1 rounded text-sm ${getDayTypeColor(slot.day_type)}">
                    ${getDayTypeText(slot.day_type)}
                </span>
            </td>
            <td class="py-3 px-4">
                <span class="px-3 py-1 rounded-full text-sm ${slot.is_peak ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'}">
                    ×${slot.multiplier.toFixed(2)}
                </span>
            </td>
            <td class="py-3 px-4">
                ${slot.is_peak ? `
                    <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-sm">
                        <i class="fas fa-fire mr-1"></i> Cao điểm
                    </span>
                ` : `
                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-sm">
                        <i class="fas fa-leaf mr-1"></i> Thường
                    </span>
                `}
            </td>
            <td class="py-3 px-4">
                ${slot.is_active ? `
                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-sm">
                        <i class="fas fa-check-circle mr-1"></i> Đang áp dụng
                    </span>
                ` : `
                    <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded text-sm">
                        <i class="fas fa-pause-circle mr-1"></i> Tạm ngừng
                    </span>
                `}
            </td>
            <td class="py-3 px-4">
                <div class="flex space-x-2">
                    <button onclick="editPricingSlot(${slot.slot_id})" class="text-blue-600 hover:text-blue-800" title="Chỉnh sửa">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="togglePricingSlot(${slot.slot_id}, ${slot.is_active})" 
                            class="${slot.is_active ? 'text-yellow-600 hover:text-yellow-800' : 'text-green-600 hover:text-green-800'}" 
                            title="${slot.is_active ? 'Tạm ngừng' : 'Kích hoạt'}">
                        <i class="fas ${slot.is_active ? 'fa-pause' : 'fa-play'}"></i>
                    </button>
                    <button onclick="deletePricingSlot(${slot.slot_id}, '${slot.slot_name}')" class="text-red-600 hover:text-red-800" title="Xóa">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// Load courts for pricing dropdown
async function loadCourtsForPricing() {
    try {
        const response = await fetch(`${API_BASE_URL}/courts?limit=100&is_active=true`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('court-for-pricing');
            select.innerHTML = '<option value="">Chọn sân...</option>';
            
            data.data.courts.forEach(court => {
                const option = document.createElement('option');
                option.value = court.court_id;
                option.textContent = `${court.court_name} - ${formatCurrency(court.price_per_hour)}/giờ`;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading courts for pricing:', error);
    }
}

// Load court pricing
async function loadCourtPricing(courtId) {
    try {
        const response = await fetch(`${API_BASE_URL}/pricing/courts/${courtId}`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            renderCourtPricing(data.data);
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        console.error('Error loading court pricing:', error);
        showNotification('Lỗi tải giá sân', 'error');
    }
}

// Render court pricing
function renderCourtPricing(data) {
    const { court, pricing } = data;
    
    const container = document.getElementById('court-pricing-details');
    
    container.innerHTML = `
        <div class="space-y-4">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="font-semibold">${court.court_name}</p>
                        <p class="text-sm text-gray-600">Giá cơ bản: ${formatCurrency(court.base_price)}/giờ</p>
                    </div>
                    <button onclick="updateCourtPricing(${court.court_id})" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">
                        <i class="fas fa-save mr-1"></i> Lưu thay đổi
                    </button>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="py-2 px-4 text-left">Khung giờ</th>
                            <th class="py-2 px-4 text-left">Thời gian</th>
                            <th class="py-2 px-4 text-left">Giá mặc định</th>
                            <th class="py-2 px-4 text-left">Giá tùy chỉnh</th>
                            <th class="py-2 px-4 text-left">Giá áp dụng</th>
                            <th class="py-2 px-4 text-left">Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody id="court-pricing-table">
                        ${pricing.map(slot => `
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 px-4">
                                    <p class="font-medium">${slot.slot_name}</p>
                                    <p class="text-xs text-gray-600">${slot.day_type === 'weekday' ? 'Ngày thường' : slot.day_type === 'weekend' ? 'Cuối tuần' : 'Tất cả'}</p>
                                </td>
                                <td class="py-3 px-4">${slot.time_range}</td>
                                <td class="py-3 px-4">
                                    <p class="font-medium">${formatCurrency(slot.calculated_price)}</p>
                                    <p class="text-xs text-gray-600">${formatCurrency(court.base_price)} × ${slot.multiplier}</p>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <input type="number" data-slot-id="${slot.slot_id}" 
                                               value="${slot.custom_price || ''}" min="0" step="1000"
                                               class="w-32 border border-gray-300 rounded-lg px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                               placeholder="Giá tùy chỉnh">
                                        <button onclick="clearCustomPrice(${court.court_id}, ${slot.slot_id})" class="ml-2 text-red-600 hover:text-red-800" title="Xóa giá tùy chỉnh">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <p class="font-bold ${slot.has_custom_price ? 'text-green-600' : ''}">
                                        ${formatCurrency(slot.final_price)}
                                    </p>
                                </td>
                                <td class="py-3 px-4">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" data-slot-id="${slot.slot_id}" 
                                               ${slot.is_active ? 'checked' : ''}
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm">Áp dụng</span>
                                    </label>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
            
            <div class="text-sm text-gray-600 mt-4">
                <p><i class="fas fa-info-circle mr-2"></i> Giá tùy chỉnh sẽ ghi đè giá tính toán tự động. Để trống nếu muốn sử dụng giá tự động.</p>
            </div>
        </div>
    `;
}

// Clear custom price
function clearCustomPrice(courtId, slotId) {
    const input = document.querySelector(`input[data-slot-id="${slotId}"]`);
    if (input) {
        input.value = '';
    }
}

// Update court pricing
async function updateCourtPricing(courtId) {
    const pricingRows = document.querySelectorAll('#court-pricing-table tr');
    const pricingData = [];
    
    pricingRows.forEach(row => {
        const slotId = row.querySelector('input[data-slot-id]')?.getAttribute('data-slot-id');
        const customPrice = row.querySelector('input[data-slot-id]')?.value;
        const isActive = row.querySelector('input[type="checkbox"]')?.checked;
        
        if (slotId) {
            pricingData.push({
                slot_id: parseInt(slotId),
                custom_price: customPrice ? parseFloat(customPrice) : null,
                is_active: isActive
            });
        }
    });
    
    try {
        const response = await fetch(`${API_BASE_URL}/pricing/courts/${courtId}`, {
            method: 'PUT',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ pricing: pricingData })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Cập nhật giá sân thành công', 'success');
            loadCourtPricing(courtId); // Reload data
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        showNotification(`Lỗi: ${error.message}`, 'error');
    }
}

// Show add pricing slot modal
function showAddPricingSlotModal() {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold">Thêm khung giờ mới</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form id="add-pricing-slot-form" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tên khung giờ *</label>
                        <input type="text" name="slot_name" required 
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Ví dụ: Giờ cao điểm chiều">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Giờ bắt đầu *</label>
                            <select name="start_hour" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                ${Array.from({length: 24}, (_, i) => `
                                    <option value="${i}" ${i === 17 ? 'selected' : ''}>${i.toString().padStart(2, '0')}:00</option>
                                `).join('')}
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Giờ kết thúc *</label>
                            <select name="end_hour" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                ${Array.from({length: 24}, (_, i) => `
                                    <option value="${i+1}" ${i+1 === 22 ? 'selected' : ''}>${(i+1).toString().padStart(2, '0')}:00</option>
                                `).join('')}
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Loại ngày *</label>
                        <select name="day_type" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="weekday">Ngày thường (Thứ 2 - Thứ 6)</option>
                            <option value="weekend">Cuối tuần (Thứ 7, Chủ nhật)</option>
                            <option value="holiday">Ngày lễ</option>
                            <option value="all">Tất cả các ngày</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Hệ số giá *</label>
                        <div class="flex items-center">
                            <span class="mr-2">×</span>
                            <input type="number" name="multiplier" required min="0.5" max="3" step="0.1" value="1.5"
                                   class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <div class="ml-4 text-sm text-gray-600">
                                <p>1.0 = Giá gốc</p>
                                <p>1.5 = +50% giá</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center">
                            <input type="checkbox" name="is_peak" id="is_peak" checked
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="is_peak" class="ml-2 block text-sm text-gray-900">
                                Giờ cao điểm
                            </label>
                        </div>
                        
                        <div class="flex items-center">
                            <input type="checkbox" name="is_active" id="is_active" checked
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="is_active" class="ml-2 block text-sm text-gray-900">
                                Kích hoạt ngay
                            </label>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Mô tả</label>
                        <textarea name="description" rows="2"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Mô tả khung giờ..."></textarea>
                    </div>
                    
                    <div class="pt-4 flex justify-end space-x-3">
                        <button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                            Hủy
                        </button>
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            Thêm khung giờ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.getElementById('modal-container').innerHTML = '';
    document.getElementById('modal-container').appendChild(modal);
    
    // Handle form submission
    document.getElementById('add-pricing-slot-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        await addPricingSlot(new FormData(e.target));
    });
}

// Add pricing slot
async function addPricingSlot(formData) {
    const data = Object.fromEntries(formData.entries());
    
    data.start_hour = parseInt(data.start_hour);
    data.end_hour = parseInt(data.end_hour);
    data.multiplier = parseFloat(data.multiplier);
    data.is_peak = data.is_peak === 'on';
    data.is_active = data.is_active === 'on';
    
    try {
        const response = await fetch(`${API_BASE_URL}/pricing/slots`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Thêm khung giờ thành công', 'success');
            closeModal();
            loadPricingSlots();
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        showNotification(`Lỗi: ${error.message}`, 'error');
    }
}

// Edit pricing slot
function editPricingSlot(slotId) {
    showNotification('Tính năng chỉnh sửa khung giờ đang được phát triển', 'info');
}

// Toggle pricing slot status
async function togglePricingSlot(slotId, isActive) {
    const action = isActive ? 'tạm ngừng' : 'kích hoạt';
    
    if (!confirm(`Bạn có chắc chắn muốn ${action} khung giờ này?`)) {
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE_URL}/pricing/slots/${slotId}`, {
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
            showNotification(`Đã ${action} khung giờ thành công`, 'success');
            loadPricingSlots();
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        showNotification(`Lỗi: ${error.message}`, 'error');
    }
}

// Delete pricing slot
async function deletePricingSlot(slotId, slotName) {
    if (!confirm(`Bạn có chắc chắn muốn xóa khung giờ "${slotName}"?`)) {
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE_URL}/pricing/slots/${slotId}`, {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Xóa khung giờ thành công', 'success');
            loadPricingSlots();
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        showNotification(`Lỗi: ${error.message}`, 'error');
    }
}

// Show price preview
async function showPricePreview() {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold">Xem trước giá sân</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Chọn sân để xem giá</label>
                    <select id="preview-court-select" class="w-full md:w-auto border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Chọn sân...</option>
                        <!-- Courts will be loaded dynamically -->
                    </select>
                </div>
                
                <div id="price-preview-content">
                    <p class="text-gray-500 text-center py-8">Vui lòng chọn sân để xem bảng giá</p>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('modal-container').innerHTML = '';
    document.getElementById('modal-container').appendChild(modal);
    
    // Load courts for preview
    await loadCourtsForPreview();
    
    // Add event listener
    document.getElementById('preview-court-select').addEventListener('change', async function() {
        if (this.value) {
            await loadPricePreview(this.value);
        }
    });
}

// Load courts for preview
async function loadCourtsForPreview() {
    try {
        const response = await fetch(`${API_BASE_URL}/courts?limit=100&is_active=true`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('preview-court-select');
            
            data.data.courts.forEach(court => {
                const option = document.createElement('option');
                option.value = court.court_id;
                option.textContent = `${court.court_name} - ${formatCurrency(court.price_per_hour)}/giờ`;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading courts for preview:', error);
    }
}

// Load price preview
async function loadPricePreview(courtId) {
    try {
        const response = await fetch(`${API_BASE_URL}/pricing/courts/${courtId}/preview`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            renderPricePreview(data.data);
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        console.error('Error loading price preview:', error);
        showNotification('Lỗi tải bảng giá', 'error');
    }
}

// Render price preview
function renderPricePreview(data) {
    const { court, preview } = data;
    
    const container = document.getElementById('price-preview-content');
    
    container.innerHTML = `
        <div class="space-y-6">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h4 class="font-bold text-lg mb-2">${court.court_name}</h4>
                <p class="text-gray-700">Giá cơ bản: <span class="font-bold">${formatCurrency(court.base_price)}</span>/giờ</p>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="py-3 px-4 text-left">Khung giờ</th>
                            <th class="py-3 px-4 text-left">Loại ngày</th>
                            <th class="py-3 px-4 text-left">Giá/giờ</th>
                            <th class="py-3 px-4 text-left">2 giờ</th>
                            <th class="py-3 px-4 text-left">3 giờ</th>
                            <th class="py-3 px-4 text-left">Loại giá</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${preview.map(slot => `
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 px-4">
                                    <p class="font-medium">${slot.time}</p>
                                    ${slot.is_peak ? `
                                        <span class="inline-block px-2 py-1 bg-red-100 text-red-800 rounded text-xs mt-1">
                                            <i class="fas fa-fire mr-1"></i> Cao điểm
                                        </span>
                                    ` : `
                                        <span class="inline-block px-2 py-1 bg-green-100 text-green-800 rounded text-xs mt-1">
                                            <i class="fas fa-leaf mr-1"></i> Thường
                                        </span>
                                    `}
                                </td>
                                <td class="py-3 px-4">
                                    ${slot.day_type === 'weekday' ? 'Ngày thường' : 'Cuối tuần'}
                                </td>
                                <td class="py-3 px-4">
                                    <p class="font-bold">${formatCurrency(slot.final_price)}</p>
                                    <p class="text-xs text-gray-600">
                                        ${formatCurrency(slot.base_price)} × ${slot.multiplier}
                                        ${slot.is_custom ? ' (tùy chỉnh)' : ''}
                                    </p>
                                </td>
                                <td class="py-3 px-4">
                                    <p class="font-semibold">${formatCurrency(slot.final_price * 2)}</p>
                                </td>
                                <td class="py-3 px-4">
                                    <p class="font-semibold">${formatCurrency(slot.final_price * 3)}</p>
                                </td>
                                <td class="py-3 px-4">
                                    ${slot.is_custom ? `
                                        <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded text-sm">
                                            <i class="fas fa-star mr-1"></i> Tùy chỉnh
                                        </span>
                                    ` : `
                                        <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded text-sm">
                                            <i class="fas fa-calculator mr-1"></i> Tự động
                                        </span>
                                    `}
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
            
            <div class="text-sm text-gray-600">
                <p><i class="fas fa-info-circle mr-2"></i> Giá tự động = Giá cơ bản × Hệ số khung giờ</p>
                <p><i class="fas fa-info-circle mr-2"></i> Giá tùy chỉnh được đặt riêng cho từng sân</p>
            </div>
        </div>
    `;
}

// Utility functions for day types
function getDayTypeColor(dayType) {
    const colors = {
        'weekday': 'bg-blue-100 text-blue-800',
        'weekend': 'bg-green-100 text-green-800',
        'holiday': 'bg-red-100 text-red-800',
        'all': 'bg-gray-100 text-gray-800'
    };
    return colors[dayType] || 'bg-gray-100 text-gray-800';
}

function getDayTypeText(dayType) {
    const texts = {
        'weekday': 'Ngày thường',
        'weekend': 'Cuối tuần',
        'holiday': 'Ngày lễ',
        'all': 'Tất cả'
    };
    return texts[dayType] || dayType;
}

// Make functions available globally
window.loadPricingPage = loadPricingPage;
window.showAddPricingSlotModal = showAddPricingSlotModal;
window.editPricingSlot = editPricingSlot;
window.togglePricingSlot = togglePricingSlot;
window.deletePricingSlot = deletePricingSlot;
window.showPricePreview = showPricePreview;
window.updateCourtPricing = updateCourtPricing;
window.clearCustomPrice = clearCustomPrice;