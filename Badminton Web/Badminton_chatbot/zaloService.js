// Gửi tin nhắn Zalo OA (miễn phí)
const ZALO_TOKEN = process.env.ZALO_OA_TOKEN || '';

async function sendZaloMessage(phone, message) {
    if (!ZALO_TOKEN) {
        console.log('⚠️ ZALO_OA_TOKEN chưa được cấu hình, bỏ qua gửi Zalo');
        return { success: false, error: 'No Zalo token' };
    }
    
    try {
        const response = await fetch('https://openapi.zalo.me/v2.0/oa/message/cs', {
            method: 'POST',
            headers: {
                'access_token': ZALO_TOKEN,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                phone: phone,
                message: message,
                type: 1  // tin nhắn văn bản
            })
        });
        const data = await response.json();
        
        if (data.error === 0) {
            console.log(`✅ Zalo message sent to ${phone}`);
            return { success: true, data };
        } else {
            console.error(`Zalo error: ${data.error_message}`);
            return { success: false, error: data.error_message };
        }
    } catch (error) {
        console.error('Zalo send error:', error);
        return { success: false, error: error.message };
    }
}

async function sendBookingConfirmation(booking, user) {
    const startTime = new Date(booking.start_time);
    const endTime = new Date(booking.end_time);
    
    const message = `✅ XÁC NHẬN ĐẶT SÂN THÀNH CÔNG

🏸 Sân: ${booking.courts?.court_name || 'Sân cầu lông'}
📅 Ngày: ${startTime.toLocaleDateString('vi-VN')}
⏰ Giờ: ${startTime.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })} - ${endTime.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })}
💰 Giá: ${booking.total_price?.toLocaleString() || 0}đ

Mã đơn: #${booking.booking_id}
Cảm ơn bạn đã sử dụng dịch vụ! 🏸`;
    
    return await sendZaloMessage(user.phone_number, message);
}

module.exports = { sendZaloMessage, sendBookingConfirmation };