// Nhận diện ý định từ tin nhắn người dùng

const intentPatterns = {
    greeting: /^(chào|hi|hello|helo|chào bạn|chào bot|chào cậu|hey)$/i,
    find_court: /(tìm|kiếm|còn|trống).*(sân|sân cầu)|(sân|sân cầu).*(trống|còn)/i,
    booking: /(đặt|book|đăng ký|đặt giúp|cho tôi đặt).*(sân|sân cầu)/i,
    check_booking: /(kiểm tra|xem|lịch sử|tra cứu|xem lại).*(lịch|đặt|sân|đơn)/i,
    cancel_booking: /(hủy|cancel|xoá|hủy bỏ).*(đặt|sân|lịch|đơn)/i,
    price: /(giá|bao nhiêu|tiền|chi phí|giá sân|giá cả)/i,
    guide: /(hướng dẫn|cách|chỉ|bước|làm sao|hướng dẫn đặt)/i,
    suggestion: /(gợi ý|đề xuất|recommend|sân nào|chọn sân|sân phù hợp)/i,
    help: /(help|menu|chức năng|có thể làm gì|\?|giúp với)/i
};

function detectIntent(message) {
    if (!message || typeof message !== 'string') {
        return 'fallback';
    }
    
    const lowerMessage = message.toLowerCase();
    
    for (const [intent, pattern] of Object.entries(intentPatterns)) {
        if (pattern.test(lowerMessage)) {
            return intent;
        }
    }
    return 'fallback';
}

function extractTime(message) {
    if (!message) return null;
    
    const lowerMsg = message.toLowerCase();
    
    // Pattern 1: 19h, 19 giờ, 7h
    let match = message.match(/(\d{1,2})\s*(h|giờ)/i);
    if (match) {
        let hour = parseInt(match[1]);
        return `${hour.toString().padStart(2, '0')}:00`;
    }
    
    // Pattern 2: 19:00, 7:30
    match = message.match(/(\d{1,2}):(\d{2})/);
    if (match) {
        let hour = parseInt(match[1]);
        let minute = match[2];
        return `${hour.toString().padStart(2, '0')}:${minute}`;
    }
    
    // Pattern 3: 7pm, 7am
    match = message.match(/(\d{1,2})\s*(pm|am)/i);
    if (match) {
        let hour = parseInt(match[1]);
        const meridian = match[2].toLowerCase();
        if (meridian === 'pm' && hour < 12) hour += 12;
        if (meridian === 'am' && hour === 12) hour = 0;
        return `${hour.toString().padStart(2, '0')}:00`;
    }
    
    return null;
}

function extractDate(message) {
    if (!message) return null;
    
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);
    
    const dayAfter = new Date(today);
    dayAfter.setDate(dayAfter.getDate() + 2);
    
    const formatDate = (date) => {
        return date.toISOString().split('T')[0];
    };
    
    const lowerMsg = message.toLowerCase();
    
    if (lowerMsg.includes('mai') || lowerMsg.includes('ngày mai')) {
        return formatDate(tomorrow);
    }
    
    if (lowerMsg.includes('hôm nay') || lowerMsg.includes('nay')) {
        return formatDate(today);
    }
    
    if (lowerMsg.includes('mốt') || lowerMsg.includes('ngày mốt')) {
        return formatDate(dayAfter);
    }
    
    // Format YYYY-MM-DD
    const dateMatch = message.match(/(\d{4})-(\d{1,2})-(\d{1,2})/);
    if (dateMatch) {
        return `${dateMatch[1]}-${dateMatch[2].padStart(2, '0')}-${dateMatch[3].padStart(2, '0')}`;
    }
    
    // Format DD/MM/YYYY
    const vnMatch = message.match(/(\d{1,2})\/(\d{1,2})\/(\d{4})/);
    if (vnMatch) {
        return `${vnMatch[3]}-${vnMatch[2].padStart(2, '0')}-${vnMatch[1].padStart(2, '0')}`;
    }
    
    return null;
}

// Hàm phụ trợ: lấy tên intent tiếng Việt
function getIntentName(intent) {
    const intentNames = {
        greeting: 'Chào hỏi',
        find_court: 'Tìm sân',
        booking: 'Đặt sân',
        check_booking: 'Kiểm tra lịch',
        cancel_booking: 'Hủy đặt',
        price: 'Hỏi giá',
        guide: 'Hướng dẫn',
        suggestion: 'Gợi ý',
        help: 'Trợ giúp',
        fallback: 'Không xác định'
    };
    return intentNames[intent] || intent;
}

module.exports = { 
    detectIntent, 
    extractTime, 
    extractDate,
    getIntentName 
};