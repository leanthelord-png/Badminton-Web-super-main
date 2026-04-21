// Template câu trả lời cho chatbot

const greeting = `🏸 **Chào bạn! Mình là trợ lý đặt sân cầu lông.**

Mình có thể giúp bạn:
• 🔍 Tìm sân trống (vd: "tìm sân 19h tối mai")
• 📅 Đặt sân (vd: "đặt sân A lúc 20h")
• 📋 Kiểm tra lịch đặt
• 💰 Xem giá các sân
• 🏸 Gợi ý sân theo thói quen

Bạn cần mình giúp gì ạ?`;

const fallback = `🤔 Mình chưa hiểu ý bạn lắm.

Bạn có thể thử hỏi:
• "Tìm sân trống 19h tối nay"
• "Đặt sân 1 lúc 20h ngày mai"
• "Xem lịch đặt của tôi"
• "Giá sân bao nhiêu"
• "Gợi ý sân"

Hoặc gõ "help" để xem hướng dẫn chi tiết.`;

const help = `📖 **HƯỚNG DẪN SỬ DỤNG**

1. Tìm sân: "tìm sân trống 20h tối mai"
2. Đặt sân: "đặt sân 1 lúc 19h ngày mai"
3. Xem lịch: "xem lịch đặt của tôi"
4. Xem giá: "giá sân bao nhiêu"

💡 Mẹo: Bạn có thể nói tự nhiên, mình sẽ hiểu!`;

const priceList = `💰 **BẢNG GIÁ SÂN**

🏸 Sân 1: 50.000đ/giờ
🏸 Sân 2: 80.000đ/giờ
🏸 Sân 3: 100.000đ/giờ

⏰ Giảm 10% cho khung giờ 6h-9h sáng
🎉 Giảm 20% khi đặt 2 giờ liên tiếp

Đặt ngay hôm nay để nhận ưu đãi!`;

function formatBookings(bookings) {
    if (!bookings || bookings.length === 0) {
        return "📋 Bạn chưa có lịch đặt sân nào.";
    }
    
    let response = "📋 **LỊCH ĐẶT SÂN CỦA BẠN**\n\n";
    bookings.forEach(b => {
        const startTime = new Date(b.start_time);
        const endTime = new Date(b.end_time);
        response += `🏸 ${b.courts?.court_name || 'Sân cầu lông'}\n`;
        response += `   📅 ${startTime.toLocaleDateString('vi-VN')}\n`;
        response += `   ⏰ ${startTime.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })} - ${endTime.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })}\n`;
        response += `   💰 ${b.total_price.toLocaleString()}đ | Trạng thái: ${b.status === 'confirmed' ? '✅ Đã xác nhận' : '⏳ Đang xử lý'}\n\n`;
    });
    return response;
}

module.exports = { greeting, fallback, help, priceList, formatBookings };