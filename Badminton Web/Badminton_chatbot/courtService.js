// Xử lý tìm kiếm sân và gợi ý
const { PrismaClient } = require('@prisma/client');
const prisma = new PrismaClient();

async function findAvailableCourts(date, time) {
    try {
        // Tạo datetime cho start_time và end_time
        const startDateTime = new Date(`${date}T${time}:00`);
        const endDateTime = new Date(startDateTime);
        endDateTime.setHours(endDateTime.getHours() + 1);
        
        // Lấy tất cả sân đang hoạt động
        const allCourts = await prisma.courts.findMany({
            where: { is_active: true },
            select: {
                court_id: true,
                court_name: true,
                court_type: true,
                price_per_hour: true,
                description: true
            }
        });
        
        // Kiểm tra sân nào đã có booking trùng giờ
        const bookedCourtIds = await prisma.bookings.findMany({
            where: {
                status: { in: ['pending', 'confirmed'] },
                start_time: { lt: endDateTime },
                end_time: { gt: startDateTime }
            },
            select: { court_id: true },
            distinct: ['court_id']
        });
        
        const bookedIds = new Set(bookedCourtIds.map(b => b.court_id));
        const availableCourts = allCourts.filter(c => !bookedIds.has(c.court_id));
        
        if (availableCourts.length === 0) {
            return {
                found: false,
                message: "😢 Rất tiếc, không có sân trống vào khung giờ bạn muốn. Bạn muốn xem khung giờ khác không?"
            };
        }
        
        let response = "🏸 **Các sân còn trống:**\n\n";
        availableCourts.forEach((c, idx) => {
            response += `${idx+1}. ${c.court_name} (${c.court_type || 'Sân cầu lông'})\n`;
            response += `   💰 ${c.price_per_hour.toLocaleString()}đ/giờ\n\n`;
        });
        response += "👉 Trả lời *đặt [số thứ tự]* để đặt ngay!";
        
        return { found: true, message: response, courts: availableCourts };
    } catch (error) {
        console.error('Find available courts error:', error);
        return { found: false, message: "Có lỗi xảy ra, vui lòng thử lại sau." };
    }
}

async function getSmartSuggestion(userId) {
    try {
        // Lấy top 3 sân user hay đặt nhất
        const suggestions = await prisma.bookings.groupBy({
            by: ['court_id'],
            where: { user_id: userId, status: 'confirmed' },
            _count: { court_id: true },
            orderBy: { _count: { court_id: 'desc' } },
            take: 3
        });
        
        if (suggestions.length === 0) {
            return "📝 Bạn chưa đặt sân lần nào. Hãy thử *tìm sân trống* để bắt đầu nhé!";
        }
        
        // Lấy thông tin chi tiết của các sân
        const courtIds = suggestions.map(s => s.court_id);
        const courts = await prisma.courts.findMany({
            where: { court_id: { in: courtIds } },
            select: { court_id: true, court_name: true, price_per_hour: true }
        });
        
        const courtMap = new Map(courts.map(c => [c.court_id, c]));
        
        let response = "📊 **Dựa trên lịch sử của bạn:**\n\n";
        suggestions.forEach(s => {
            const court = courtMap.get(s.court_id);
            if (court) {
                response += `• ${court.court_name} (đã đặt ${s._count.court_id} lần) - ${court.price_per_hour.toLocaleString()}đ/giờ\n`;
            }
        });
        response += "\n💡 Muốn đặt lại sân nào không? Hãy nói *đặt [tên sân]*";
        return response;
    } catch (error) {
        console.error('Smart suggestion error:', error);
        return "Hiện tại mình chưa thể gợi ý được. Bạn thử tìm sân trống nhé!";
    }
}

module.exports = { findAvailableCourts, getSmartSuggestion };