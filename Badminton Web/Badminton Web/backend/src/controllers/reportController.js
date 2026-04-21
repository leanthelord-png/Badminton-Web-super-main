const prisma = require('../config/database');
const { startOfDay, endOfDay, startOfMonth, endOfMonth, startOfYear, endOfYear,
  subDays, subMonths, format, parseISO } = require('date-fns');

// [1] Báo cáo doanh thu
exports.getRevenueReport = async (req, res) => {
  try {
    const { 
      period = 'month', 
      start_date, 
      end_date,
      court_id,
      group_by = 'day'
    } = req.query;
    
    let startDate, endDate;
    const now = new Date();
    
    // Set date range based on period
    if (start_date && end_date) {
      startDate = parseISO(start_date);
      endDate = parseISO(end_date);
    } else {
      switch (period) {
        case 'today':
          startDate = startOfDay(now);
          endDate = endOfDay(now);
          break;
        case 'yesterday':
          startDate = startOfDay(subDays(now, 1));
          endDate = endOfDay(subDays(now, 1));
          break;
        case 'week':
          startDate = startOfDay(subDays(now, 7));
          endDate = endOfDay(now);
          break;
        case 'month':
          startDate = startOfMonth(now);
          endDate = endOfMonth(now);
          break;
        case 'last_month':
          startDate = startOfMonth(subMonths(now, 1));
          endDate = endOfMonth(subMonths(now, 1));
          break;
        case 'year':
          startDate = startOfYear(now);
          endDate = endOfYear(now);
          break;
        default:
          startDate = startOfMonth(now);
          endDate = endOfMonth(now);
      }
    }
    
    // Build where clause
    const where = {
      status: { in: ['confirmed', 'completed'] },
      created_at: {
        gte: startDate,
        lte: endDate
      }
    };
    
    if (court_id) {
      where.court_id = parseInt(court_id);
    }
    
    // Get bookings for the period
    const bookings = await prisma.bookings.findMany({
      where,
      include: {
        courts: {
          select: { court_name: true, court_type: true }
        },
        users: {
          select: { full_name: true, phone_number: true }
        },
        payments: {
          select: { amount_paid: true, payment_method: true }
        }
      },
      orderBy: { created_at: 'asc' }
    });
    
    // Calculate totals
    const totals = await prisma.bookings.aggregate({
      where,
      _sum: { total_price: true },
      _count: { booking_id: true },
      _avg: { total_price: true }
    });
    
    // Group by date/court
    const revenueByDate = {};
    const revenueByCourt = {};
    
    bookings.forEach(booking => {
      const dateKey = group_by === 'day' 
        ? format(new Date(booking.created_at), 'yyyy-MM-dd')
        : format(new Date(booking.created_at), 'yyyy-MM');
      
      // Group by date
      if (!revenueByDate[dateKey]) {
        revenueByDate[dateKey] = {
          date: dateKey,
          revenue: 0,
          bookings: 0,
          courts: {}
        };
      }
      
      revenueByDate[dateKey].revenue += Number(booking.total_price);
      revenueByDate[dateKey].bookings += 1;
      
      // Group by court
      const courtName = booking.courts?.court_name || 'Unknown';
      if (!revenueByCourt[courtName]) {
        revenueByCourt[courtName] = {
          court_name: courtName,
          revenue: 0,
          bookings: 0,
          court_type: booking.courts?.court_type || 'N/A'
        };
      }
      
      revenueByCourt[courtName].revenue += Number(booking.total_price);
      revenueByCourt[courtName].bookings += 1;
    });
    
    // Convert to arrays
    const dateData = Object.values(revenueByDate).map(item => ({
      ...item,
      revenue: Number(item.revenue.toFixed(2))
    }));
    
    const courtData = Object.values(revenueByCourt)
      .map(item => ({
        ...item,
        revenue: Number(item.revenue.toFixed(2))
      }))
      .sort((a, b) => b.revenue - a.revenue);
    
    // Payment methods summary
    const paymentMethods = {};
    bookings.forEach(booking => {
      booking.payments.forEach(payment => {
        const method = payment.payment_method || 'Unknown';
        paymentMethods[method] = (paymentMethods[method] || 0) + Number(payment.amount_paid);
      });
    });
    
    const paymentData = Object.entries(paymentMethods).map(([method, amount]) => ({
      method,
      amount: Number(amount.toFixed(2))
    }));
    
    res.json({
      success: true,
      data: {
        period: {
          start: startDate,
          end: endDate,
          label: period
        },
        summary: {
          total_revenue: Number(totals._sum.total_price) || 0,
          total_bookings: totals._count.booking_id || 0,
          average_booking_value: Number(totals._avg.total_price) || 0
        },
        breakdown: {
          by_date: dateData,
          by_court: courtData,
          by_payment_method: paymentData
        },
        bookings: bookings.map(b => ({
          id: b.booking_id,
          court: b.courts?.court_name,
          customer: b.users?.full_name,
          date: b.created_at,
          total_price: Number(b.total_price),
          status: b.status,
          payment_method: b.payments[0]?.payment_method
        }))
      }
    });
    
  } catch (error) {
    console.error('Revenue report error:', error);
    res.status(500).json({ success: false, error: 'Failed to generate revenue report' });
  }
};

// [2] Báo cáo lượt đặt sân
exports.getBookingReport = async (req, res) => {
  try {
    const { 
      period = 'month',
      start_date,
      end_date,
      status,
      court_type
    } = req.query;
    
    let startDate, endDate;
    const now = new Date();
    
    if (start_date && end_date) {
      startDate = parseISO(start_date);
      endDate = parseISO(end_date);
    } else {
      switch (period) {
        case 'today':
          startDate = startOfDay(now);
          endDate = endOfDay(now);
          break;
        case 'week':
          startDate = startOfDay(subDays(now, 7));
          endDate = endOfDay(now);
          break;
        case 'month':
          startDate = startOfMonth(now);
          endDate = endOfMonth(now);
          break;
        case 'year':
          startDate = startOfYear(now);
          endDate = endOfYear(now);
          break;
        default:
          startDate = startOfMonth(now);
          endDate = endOfMonth(now);
      }
    }
    
    // Build where clause
    const where = {
      created_at: {
        gte: startDate,
        lte: endDate
      }
    };
    
    if (status && status !== 'all') {
      where.status = status;
    }
    
    // Get bookings with court info
    const bookings = await prisma.bookings.findMany({
      where,
      include: {
        courts: {
          select: { court_name: true, court_type: true }
        },
        users: {
          select: { full_name: true, phone_number: true }
        }
      },
      orderBy: { created_at: 'desc' }
    });
    
    // Filter by court type if specified
    let filteredBookings = bookings;
    if (court_type && court_type !== 'all') {
      filteredBookings = bookings.filter(b => 
        b.courts?.court_type === court_type
      );
    }
    
    // Calculate statistics
    const totalBookings = filteredBookings.length;
    
    // Group by status
    const statusCounts = {};
    filteredBookings.forEach(booking => {
      statusCounts[booking.status] = (statusCounts[booking.status] || 0) + 1;
    });
    
    // Group by hour of day
    const hourlyDistribution = Array(24).fill(0);
    filteredBookings.forEach(booking => {
      const hour = new Date(booking.start_time).getHours();
      hourlyDistribution[hour]++;
    });
    
    const hourlyData = hourlyDistribution.map((count, hour) => ({
      hour: `${hour.toString().padStart(2, '0')}:00`,
      bookings: count
    }));
    
    // Group by day of week
    const dailyDistribution = Array(7).fill(0);
    const dayNames = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
    
    filteredBookings.forEach(booking => {
      const day = new Date(booking.start_time).getDay();
      dailyDistribution[day]++;
    });
    
    const dailyData = dailyDistribution.map((count, day) => ({
      day: dayNames[day],
      bookings: count
    }));
    
    // Court utilization
    const courtUtilization = {};
    filteredBookings.forEach(booking => {
      const courtName = booking.courts?.court_name || 'Unknown';
      courtUtilization[courtName] = (courtUtilization[courtName] || 0) + 1;
    });
    
    const courtData = Object.entries(courtUtilization)
      .map(([court, count]) => ({ court, bookings: count }))
      .sort((a, b) => b.bookings - a.bookings);
    
    // Peak hours analysis
    const peakHours = hourlyData
      .filter(hour => hour.bookings > 0)
      .sort((a, b) => b.bookings - a.bookings)
      .slice(0, 5);
    
    res.json({
      success: true,
      data: {
        period: {
          start: startDate,
          end: endDate,
          label: period
        },
        summary: {
          total_bookings: totalBookings,
          by_status: statusCounts,
          completion_rate: statusCounts.completed 
            ? ((statusCounts.completed / totalBookings) * 100).toFixed(2) + '%'
            : '0%'
        },
        analysis: {
          hourly_distribution: hourlyData,
          daily_distribution: dailyData,
          court_utilization: courtData,
          peak_hours: peakHours
        },
        bookings: filteredBookings.map(b => ({
          id: b.booking_id,
          court: b.courts?.court_name,
          court_type: b.courts?.court_type,
          customer: b.users?.full_name,
          start_time: b.start_time,
          end_time: b.end_time,
          total_price: Number(b.total_price),
          status: b.status,
          created_at: b.created_at
        }))
      }
    });
    
  } catch (error) {
    console.error('Booking report error:', error);
    res.status(500).json({ success: false, error: 'Failed to generate booking report' });
  }
};

// [3] Dashboard statistics
exports.getDashboardStats = async (req, res) => {
  try {
    const now = new Date();
    const todayStart = startOfDay(now);
    const todayEnd = endOfDay(now);
    const monthStart = startOfMonth(now);
    const monthEnd = endOfMonth(now);
    
    // Parallel queries for efficiency
    const [
      totalUsers,
      totalCourts,
      todayBookings,
      todayRevenue,
      monthRevenue,
      activeBookings,
      pendingPayments,
      courtUtilization
    ] = await Promise.all([
      // Total users
      prisma.users.count(),
      
      // Total courts
      prisma.courts.count({ where: { is_active: true } }),
      
      // Today's bookings
      prisma.bookings.count({
        where: {
          created_at: { gte: todayStart, lte: todayEnd }
        }
      }),
      
      // Today's revenue
      prisma.bookings.aggregate({
        where: {
          status: { in: ['confirmed', 'completed'] },
          created_at: { gte: todayStart, lte: todayEnd }
        },
        _sum: { total_price: true }
      }),
      
      // Monthly revenue
      prisma.bookings.aggregate({
        where: {
          status: { in: ['confirmed', 'completed'] },
          created_at: { gte: monthStart, lte: monthEnd }
        },
        _sum: { total_price: true }
      }),
      
      // Active bookings (today and future)
      prisma.bookings.count({
        where: {
          status: { in: ['pending', 'confirmed'] },
          start_time: { gte: todayStart }
        }
      }),
      
      // Pending payments
      prisma.bookings.aggregate({
        where: {
          status: 'confirmed',
          payments: { none: {} }
        },
        _sum: { total_price: true }
      }),
      
      // Court utilization (bookings per court)
      prisma.courts.findMany({
        where: { is_active: true },
        select: {
          court_id: true,
          court_name: true,
          _count: {
            select: {
              bookings: {
                where: {
                  created_at: { gte: monthStart, lte: monthEnd }
                }
              }
            }
          }
        },
        orderBy: { court_name: 'asc' }
      })
    ]);
    
    // Recent bookings
    const recentBookings = await prisma.bookings.findMany({
      take: 10,
      orderBy: { created_at: 'desc' },
      include: {
        courts: {
          select: { court_name: true }
        },
        users: {
          select: { full_name: true, phone_number: true }
        }
      }
    });
    
    // Revenue trend (last 7 days)
    const revenueTrend = [];
    for (let i = 6; i >= 0; i--) {
      const date = subDays(now, i);
      const dayStart = startOfDay(date);
      const dayEnd = endOfDay(date);
      
      const dayRevenue = await prisma.bookings.aggregate({
        where: {
          status: { in: ['confirmed', 'completed'] },
          created_at: { gte: dayStart, lte: dayEnd }
        },
        _sum: { total_price: true }
      });
      
      revenueTrend.push({
        date: format(date, 'yyyy-MM-dd'),
        day: format(date, 'EEE'),
        revenue: Number(dayRevenue._sum.total_price) || 0
      });
    }
    
    res.json({
      success: true,
      data: {
        overview: {
          total_users: totalUsers,
          total_courts: totalCourts,
          today_bookings: todayBookings,
          today_revenue: Number(todayRevenue._sum.total_price) || 0,
          month_revenue: Number(monthRevenue._sum.total_price) || 0,
          active_bookings: activeBookings,
          pending_payments: Number(pendingPayments._sum.total_price) || 0
        },
        recent_bookings: recentBookings.map(b => ({
          id: b.booking_id,
          court: b.courts?.court_name,
          customer: b.users?.full_name,
          time: `${format(new Date(b.start_time), 'HH:mm')} - ${format(new Date(b.end_time), 'HH:mm')}`,
          status: b.status,
          amount: Number(b.total_price)
        })),
        revenue_trend: revenueTrend,
        court_utilization: courtUtilization.map(court => ({
          court_id: court.court_id,
          court_name: court.court_name,
          bookings_count: court._count.bookings
        }))
      }
    });
    
  } catch (error) {
    console.error('Dashboard stats error:', error);
    res.status(500).json({ success: false, error: 'Failed to fetch dashboard statistics' });
  }
};

// [4] Xuất báo cáo CSV/Excel
exports.exportReport = async (req, res) => {
  try {
    const { 
      report_type = 'revenue',
      format = 'csv',
      start_date,
      end_date 
    } = req.query;
    
    let startDate = start_date ? parseISO(start_date) : startOfMonth(new Date());
    let endDate = end_date ? parseISO(end_date) : endOfMonth(new Date());
    
    let data;
    let filename;
    
    switch (report_type) {
      case 'revenue':
        const revenueBookings = await prisma.bookings.findMany({
          where: {
            status: { in: ['confirmed', 'completed'] },
            created_at: { gte: startDate, lte: endDate }
          },
          include: {
            courts: { select: { court_name: true } },
            users: { select: { full_name: true, phone_number: true } },
            payments: { select: { payment_method: true, amount_paid: true } }
          },
          orderBy: { created_at: 'asc' }
        });
        
        data = revenueBookings.map(booking => ({
          'Mã đơn': booking.booking_id,
          'Sân': booking.courts?.court_name || 'N/A',
          'Khách hàng': booking.users?.full_name || 'N/A',
          'SĐT': booking.users?.phone_number || 'N/A',
          'Ngày đặt': format(new Date(booking.created_at), 'dd/MM/yyyy'),
          'Giờ bắt đầu': format(new Date(booking.start_time), 'HH:mm'),
          'Giờ kết thúc': format(new Date(booking.end_time), 'HH:mm'),
          'Tổng tiền': Number(booking.total_price),
          'Trạng thái': booking.status,
          'Phương thức thanh toán': booking.payments[0]?.payment_method || 'N/A',
          'Đã thanh toán': booking.payments[0]?.amount_paid || 0
        }));
        
        filename = `bao-cao-doanh-thu-${format(startDate, 'dd-MM-yyyy')}-${format(endDate, 'dd-MM-yyyy')}`;
        break;
        
      case 'bookings':
        const allBookings = await prisma.bookings.findMany({
          where: {
            created_at: { gte: startDate, lte: endDate }
          },
          include: {
            courts: { select: { court_name: true, court_type: true } },
            users: { select: { full_name: true, phone_number: true } }
          },
          orderBy: { created_at: 'asc' }
        });
        
        data = allBookings.map(booking => ({
          'Mã đơn': booking.booking_id,
          'Sân': booking.courts?.court_name || 'N/A',
          'Loại sân': booking.courts?.court_type || 'N/A',
          'Khách hàng': booking.users?.full_name || 'N/A',
          'SĐT': booking.users?.phone_number || 'N/A',
          'Ngày đặt': format(new Date(booking.created_at), 'dd/MM/yyyy HH:mm'),
          'Giờ bắt đầu': format(new Date(booking.start_time), 'dd/MM/yyyy HH:mm'),
          'Giờ kết thúc': format(new Date(booking.end_time), 'dd/MM/yyyy HH:mm'),
          'Tổng tiền': Number(booking.total_price),
          'Trạng thái': booking.status,
          'Thời lượng (giờ)': ((new Date(booking.end_time) - new Date(booking.start_time)) / (1000 * 60 * 60)).toFixed(1)
        }));
        
        filename = `bao-cao-dat-san-${format(startDate, 'dd-MM-yyyy')}-${format(endDate, 'dd-MM-yyyy')}`;
        break;
        
      case 'users':
        const users = await prisma.users.findMany({
          select: {
            user_id: true,
            username: true,
            full_name: true,
            email: true,
            phone_number: true,
            role: true,
            created_at: true
          },
          orderBy: { created_at: 'desc' }
        });
        
        data = users.map(user => ({
          'Mã KH': user.user_id,
          'Tên đăng nhập': user.username,
          'Họ tên': user.full_name,
          'Email': user.email || 'N/A',
          'SĐT': user.phone_number,
          'Vai trò': user.role,
          'Ngày đăng ký': format(new Date(user.created_at), 'dd/MM/yyyy HH:mm'),
          'Số lượt đặt (cần query riêng)': 'N/A'
        }));
        
        filename = `danh-sach-khach-hang-${format(new Date(), 'dd-MM-yyyy')}`;
        break;
        
      default:
        return res.status(400).json({ 
          success: false, 
          error: 'Invalid report type' 
        });
    }
    
    if (format === 'csv') {
      // Convert to CSV
      const csvHeaders = Object.keys(data[0] || {}).join(',');
      const csvRows = data.map(row => 
        Object.values(row).map(value => 
          `"${String(value).replace(/"/g, '""')}"`
        ).join(',')
      );
      const csvContent = [csvHeaders, ...csvRows].join('\n');
      
      res.header('Content-Type', 'text/csv');
      res.header('Content-Disposition', `attachment; filename="${filename}.csv"`);
      res.send(csvContent);
    } else if (format === 'json') {
      res.json({
        success: true,
        data: {
          report_type,
          period: { start_date: startDate, end_date: endDate },
          records: data.length,
          data
        }
      });
    } else {
      res.status(400).json({ 
        success: false, 
        error: 'Unsupported format. Use csv or json.' 
      });
    }
    
  } catch (error) {
    console.error('Export report error:', error);
    res.status(500).json({ success: false, error: 'Failed to export report' });
  }
};