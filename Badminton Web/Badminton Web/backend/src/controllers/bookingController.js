const { body, validationResult } = require('express-validator');
const prisma = require('../config/database');

exports.getAllBookings = async (req, res) => {
  try {
    const { 
      page = 1, 
      limit = 10, 
      status, 
      court_id, 
      user_id,
      start_date, 
      end_date,
      sortBy = 'created_at', 
      sortOrder = 'desc'
    } = req.query;
    
    const skip = (parseInt(page) - 1) * parseInt(limit);
    
    // Build where clause
    const where = {};
    
    if (status && status !== 'all') {
      where.status = status;
    }
    
    if (court_id) {
      where.court_id = parseInt(court_id);
    }
    
    if (user_id) {
      where.user_id = parseInt(user_id);
    }
    
    if (start_date || end_date) {
      where.created_at = {};
      if (start_date) {
        where.created_at.gte = new Date(start_date);
      }
      if (end_date) {
        where.created_at.lte = new Date(end_date);
      }
    }
    
    // Get total count
    const total = await prisma.bookings.count({ where });
    
    // Get bookings with pagination
    const bookings = await prisma.bookings.findMany({
      where,
      skip,
      take: parseInt(limit),
      orderBy: { [sortBy]: sortOrder },
      include: {
        users: {
          select: { 
            user_id: true,
            full_name: true, 
            phone_number: true,
            email: true 
          }
        },
        courts: {
          select: { 
            court_id: true,
            court_name: true, 
            price_per_hour: true 
          }
        }
      }
    });
    
    // Calculate totals
    const totals = await prisma.bookings.aggregate({
      where: {
        ...where,
        status: { in: ['confirmed', 'completed'] }
      },
      _sum: { total_price: true },
      _count: { booking_id: true }
    });
    
    res.json({
      success: true,
      data: {
        bookings: bookings.map(booking => ({
          id: booking.booking_id,
          court: booking.courts?.court_name,
          court_id: booking.court_id,
          customer: booking.users?.full_name,
          customer_id: booking.user_id,
          phone: booking.users?.phone_number,
          email: booking.users?.email,
          startTime: booking.start_time,
          endTime: booking.end_time,
          totalPrice: Number(booking.total_price),
          status: booking.status,
          createdAt: booking.created_at
        })),
        pagination: {
          page: parseInt(page),
          limit: parseInt(limit),
          total,
          totalPages: Math.ceil(total / parseInt(limit))
        },
        totals: {
          totalRevenue: Number(totals._sum.total_price) || 0,
          totalBookings: totals._count.booking_id || 0
        }
      }
    });
    
  } catch (error) {
    console.error('Get bookings error:', error);
    res.status(500).json({ success: false, error: 'Failed to fetch bookings' });
  }
};

exports.getBookingStats = async (req, res) => {
  try {
    const now = new Date();
    const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
    
    // Get status counts
    const statusCounts = await prisma.bookings.groupBy({
      by: ['status'],
      _count: { booking_id: true }
    });
    
    const statusStats = {};
    statusCounts.forEach(item => {
      statusStats[item.status] = item._count.booking_id;
    });
    
    // Get monthly revenue
    const monthlyRevenue = await prisma.bookings.aggregate({
      where: {
        status: { in: ['confirmed', 'completed'] },
        created_at: { gte: startOfMonth }
      },
      _sum: { total_price: true },
      _count: { booking_id: true }
    });
    
    // Get today's stats
    const startOfToday = new Date();
    startOfToday.setHours(0, 0, 0, 0);
    
    const endOfToday = new Date();
    endOfToday.setHours(23, 59, 59, 999);
    
    const todayStats = await prisma.bookings.aggregate({
      where: {
        created_at: { gte: startOfToday, lte: endOfToday }
      },
      _sum: { total_price: true },
      _count: { booking_id: true }
    });
    
    res.json({
      success: true,
      data: {
        statusDistribution: statusStats,
        monthly: {
          revenue: Number(monthlyRevenue._sum.total_price) || 0,
          bookings: monthlyRevenue._count.booking_id || 0
        },
        today: {
          revenue: Number(todayStats._sum.total_price) || 0,
          bookings: todayStats._count.booking_id || 0
        }
      }
    });
    
  } catch (error) {
    console.error('Get booking stats error:', error);
    res.status(500).json({ success: false, error: 'Failed to fetch booking statistics' });
  }
};

exports.getBookingById = async (req, res) => {
  try {
    const { id } = req.params;
    
    const booking = await prisma.bookings.findUnique({
      where: { booking_id: parseInt(id) },
      include: {
        users: {
          select: { 
            user_id: true,
            username: true,
            full_name: true, 
            phone_number: true,
            email: true 
          }
        },
        courts: {
          select: { 
            court_id: true,
            court_name: true, 
            court_type: true,
            price_per_hour: true 
          }
        },
        payments: {
          select: {
            amount_paid: true,
            payment_method: true,
            payment_date: true,
            transaction_reference: true
          }
        }
      }
    });
    
    if (!booking) {
      return res.status(404).json({ success: false, error: 'Booking not found' });
    }
    
    res.json({
      success: true,
      data: {
        booking: {
          id: booking.booking_id,
          court: booking.courts,
          customer: booking.users,
          startTime: booking.start_time,
          endTime: booking.end_time,
          totalPrice: Number(booking.total_price),
          status: booking.status,
          createdAt: booking.created_at
        },
        payments: booking.payments.map(payment => ({
          amount: Number(payment.amount_paid),
          method: payment.payment_method,
          date: payment.payment_date,
          reference: payment.transaction_reference
        }))
      }
    });
    
  } catch (error) {
    console.error('Get booking error:', error);
    res.status(500).json({ success: false, error: 'Failed to fetch booking details' });
  }
};

exports.updateBooking = [
  body('court_id').optional().isInt(),
  body('user_id').optional().isInt(),
  body('start_time').optional().isISO8601(),
  body('end_time').optional().isISO8601(),
  body('total_price').optional().isFloat({ gt: 0 }),
  
  async (req, res) => {
    try {
      const errors = validationResult(req);
      if (!errors.isEmpty()) {
        return res.status(400).json({ success: false, errors: errors.array() });
      }
      
      const { id } = req.params;
      const updateData = req.body;
      
      // Check if booking exists
      const existingBooking = await prisma.bookings.findUnique({
        where: { booking_id: parseInt(id) }
      });
      
      if (!existingBooking) {
        return res.status(404).json({ success: false, error: 'Booking not found' });
      }
      
      // Convert numeric fields
      if (updateData.total_price) {
        updateData.total_price = parseFloat(updateData.total_price);
      }
      
      const updatedBooking = await prisma.bookings.update({
        where: { booking_id: parseInt(id) },
        data: updateData,
        include: {
          courts: { select: { court_name: true } },
          users: { select: { full_name: true } }
        }
      });
      
      res.json({
        success: true,
        message: 'Booking updated successfully',
        data: {
          id: updatedBooking.booking_id,
          court: updatedBooking.courts?.court_name,
          customer: updatedBooking.users?.full_name,
          startTime: updatedBooking.start_time,
          endTime: updatedBooking.end_time,
          totalPrice: Number(updatedBooking.total_price),
          status: updatedBooking.status
        }
      });
      
    } catch (error) {
      console.error('Update booking error:', error);
      res.status(500).json({ success: false, error: 'Failed to update booking' });
    }
  }
];

exports.deleteBooking = async (req, res) => {
  try {
    const { id } = req.params;
    
    await prisma.bookings.delete({
      where: { booking_id: parseInt(id) }
    });
    
    res.json({
      success: true,
      message: 'Booking deleted successfully'
    });
    
  } catch (error) {
    console.error('Delete booking error:', error);
    
    if (error.code === 'P2025') {
      return res.status(404).json({ success: false, error: 'Booking not found' });
    }
    
    res.status(500).json({ success: false, error: 'Failed to delete booking' });
  }
};

exports.updateBookingStatus = [
  body('status').isIn(['pending', 'confirmed', 'cancelled', 'completed']),
  body('notes').optional(),
  
  async (req, res) => {
    try {
      const errors = validationResult(req);
      if (!errors.isEmpty()) {
        return res.status(400).json({ success: false, errors: errors.array() });
      }
      
      const { id } = req.params;
      const { status, notes } = req.body;
      
      const updatedBooking = await prisma.bookings.update({
        where: { booking_id: parseInt(id) },
        data: { status },
        include: {
          courts: { select: { court_name: true } },
          users: { select: { full_name: true, phone_number: true } }
        }
      });
      
      // Log status change
      console.log(`Booking ${id} status changed to ${status} by admin ${req.user.username}`);
      
      res.json({
        success: true,
        message: `Booking status updated to ${status}`,
        data: {
          id: updatedBooking.booking_id,
          court: updatedBooking.courts?.court_name,
          customer: updatedBooking.users?.full_name,
          status: updatedBooking.status,
          totalPrice: Number(updatedBooking.total_price)
        }
      });
      
    } catch (error) {
      console.error('Update booking status error:', error);
      res.status(500).json({ success: false, error: 'Failed to update booking status' });
    }
  }
];

exports.addPayment = [
  body('amount_paid').isFloat({ gt: 0 }),
  body('payment_method').notEmpty(),
  body('transaction_reference').optional(),
  
  async (req, res) => {
    try {
      const errors = validationResult(req);
      if (!errors.isEmpty()) {
        return res.status(400).json({ success: false, errors: errors.array() });
      }
      
      const { id } = req.params;
      const { amount_paid, payment_method, transaction_reference } = req.body;
      
      // Check if booking exists
      const booking = await prisma.bookings.findUnique({
        where: { booking_id: parseInt(id) },
        select: { total_price: true, status: true }
      });
      
      if (!booking) {
        return res.status(404).json({ success: false, error: 'Booking not found' });
      }
      
      // Create payment
      const payment = await prisma.payments.create({
        data: {
          booking_id: parseInt(id),
          amount_paid: parseFloat(amount_paid),
          payment_method,
          transaction_reference,
          payment_date: new Date()
        }
      });
      
      // Update booking status to completed if fully paid
      if (parseFloat(amount_paid) >= Number(booking.total_price)) {
        await prisma.bookings.update({
          where: { booking_id: parseInt(id) },
          data: { status: 'completed' }
        });
      }
      
      res.status(201).json({
        success: true,
        message: 'Payment added successfully',
        data: {
          paymentId: payment.payment_id,
          amount: Number(payment.amount_paid),
          method: payment.payment_method,
          date: payment.payment_date
        }
      });
      
    } catch (error) {
      console.error('Add payment error:', error);
      res.status(500).json({ success: false, error: 'Failed to add payment' });
    }
  }
];

exports.getBookingPayments = async (req, res) => {
  try {
    const { id } = req.params;
    
    const payments = await prisma.payments.findMany({
      where: { booking_id: parseInt(id) },
      orderBy: { payment_date: 'desc' }
    });
    
    const totalPaid = payments.reduce((sum, payment) => 
      sum + Number(payment.amount_paid), 0
    );
    
    res.json({
      success: true,
      data: {
        payments: payments.map(payment => ({
          id: payment.payment_id,
          amount: Number(payment.amount_paid),
          method: payment.payment_method,
          date: payment.payment_date,
          reference: payment.transaction_reference
        })),
        summary: {
          totalPaid,
          paymentCount: payments.length
        }
      }
    });
    
  } catch (error) {
    console.error('Get booking payments error:', error);
    res.status(500).json({ success: false, error: 'Failed to fetch payments' });
  }
};