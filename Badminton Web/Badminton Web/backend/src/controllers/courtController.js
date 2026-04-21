const { body, validationResult } = require('express-validator');
const prisma = require('../config/database');

exports.getAllCourts = async (req, res) => {
  try {
    const { 
      page = 1, 
      limit = 10, 
      search = '',
      is_active,
      sortBy = 'court_name', 
      sortOrder = 'asc'
    } = req.query;
    
    const skip = (parseInt(page) - 1) * parseInt(limit);
    
    // Build where clause
    const where = {};
    
    if (search) {
      where.OR = [
        { court_name: { contains: search, mode: 'insensitive' } },
        { description: { contains: search, mode: 'insensitive' } },
        { court_type: { contains: search, mode: 'insensitive' } }
      ];
    }
    
    if (is_active !== undefined) {
      where.is_active = is_active === 'true';
    }
    
    // Get total count
    const total = await prisma.courts.count({ where });
    
    // Get courts with pagination
    const courts = await prisma.courts.findMany({
      where,
      skip,
      take: parseInt(limit),
      orderBy: { [sortBy]: sortOrder },
      include: {
        _count: {
          select: { bookings: true }
        }
      }
    });
    
    res.json({
      success: true,
      data: {
        courts: courts.map(court => ({
          ...court,
          price_per_hour: Number(court.price_per_hour)
        })),
        pagination: {
          page: parseInt(page),
          limit: parseInt(limit),
          total,
          totalPages: Math.ceil(total / parseInt(limit))
        }
      }
    });
    
  } catch (error) {
    console.error('Get courts error:', error);
    res.status(500).json({ success: false, error: 'Failed to fetch courts' });
  }
};

exports.getCourtStats = async (req, res) => {
  try {
    const totalCourts = await prisma.courts.count();
    const activeCourts = await prisma.courts.count({ where: { is_active: true } });
    
    // Get court types distribution
    const courtTypes = await prisma.courts.groupBy({
      by: ['court_type'],
      _count: { court_id: true }
    });
    
    res.json({
      success: true,
      data: {
        totalCourts,
        activeCourts,
        inactiveCourts: totalCourts - activeCourts,
        courtTypes: courtTypes.map(type => ({
          type: type.court_type || 'Unknown',
          count: type._count.court_id
        }))
      }
    });
    
  } catch (error) {
    console.error('Get court stats error:', error);
    res.status(500).json({ success: false, error: 'Failed to fetch court statistics' });
  }
};

exports.getCourtById = async (req, res) => {
  try {
    const { id } = req.params;
    
    const court = await prisma.courts.findUnique({
      where: { court_id: parseInt(id) }
    });
    
    if (!court) {
      return res.status(404).json({ success: false, error: 'Court not found' });
    }
    
    // Get recent bookings
    const recentBookings = await prisma.bookings.findMany({
      where: { court_id: parseInt(id) },
      take: 10,
      orderBy: { start_time: 'desc' },
      include: {
        users: {
          select: { full_name: true, phone_number: true }
        }
      }
    });
    
    // Get court statistics
    const stats = await prisma.bookings.aggregate({
      where: { court_id: parseInt(id) },
      _sum: { total_price: true },
      _count: { booking_id: true }
    });
    
    res.json({
      success: true,
      data: {
        court: {
          ...court,
          price_per_hour: Number(court.price_per_hour)
        },
        statistics: {
          totalBookings: stats._count.booking_id || 0,
          totalRevenue: Number(stats._sum.total_price) || 0
        },
        recentBookings: recentBookings.map(booking => ({
          id: booking.booking_id,
          customer: booking.users?.full_name,
          startTime: booking.start_time,
          endTime: booking.end_time,
          totalPrice: Number(booking.total_price),
          status: booking.status
        }))
      }
    });
    
  } catch (error) {
    console.error('Get court error:', error);
    res.status(500).json({ success: false, error: 'Failed to fetch court details' });
  }
};

exports.createCourt = [
  body('court_name').trim().notEmpty().withMessage('Court name is required'),
  body('court_type').optional().trim(),
  body('price_per_hour').isFloat({ gt: 0 }).withMessage('Price must be greater than 0'),
  body('description').optional().trim(),
  
  async (req, res) => {
    try {
      const errors = validationResult(req);
      if (!errors.isEmpty()) {
        return res.status(400).json({ success: false, errors: errors.array() });
      }
      
      const { court_name, court_type, price_per_hour, description } = req.body;
      
      // Check if court name already exists
      const existingCourt = await prisma.courts.findFirst({
        where: { court_name }
      });
      
      if (existingCourt) {
        return res.status(400).json({ 
          success: false, 
          error: 'Court name already exists' 
        });
      }
      
      const court = await prisma.courts.create({
        data: {
          court_name,
          court_type,
          price_per_hour: parseFloat(price_per_hour),
          description,
          is_active: true
        }
      });
      
      res.status(201).json({
        success: true,
        message: 'Court created successfully',
        data: {
          ...court,
          price_per_hour: Number(court.price_per_hour)
        }
      });
      
    } catch (error) {
      console.error('Create court error:', error);
      res.status(500).json({ success: false, error: 'Failed to create court' });
    }
  }
];

exports.updateCourt = [
  body('court_name').optional().trim(),
  body('court_type').optional().trim(),
  body('price_per_hour').optional().isFloat({ gt: 0 }),
  body('description').optional().trim(),
  body('is_active').optional().isBoolean(),
  
  async (req, res) => {
    try {
      const errors = validationResult(req);
      if (!errors.isEmpty()) {
        return res.status(400).json({ success: false, errors: errors.array() });
      }
      
      const { id } = req.params;
      const updateData = req.body;
      
      // Check if court exists
      const existingCourt = await prisma.courts.findUnique({
        where: { court_id: parseInt(id) }
      });
      
      if (!existingCourt) {
        return res.status(404).json({ success: false, error: 'Court not found' });
      }
      
      // Check for duplicate court name
      if (updateData.court_name && updateData.court_name !== existingCourt.court_name) {
        const duplicateCourt = await prisma.courts.findFirst({
          where: { 
            court_name: updateData.court_name,
            court_id: { not: parseInt(id) }
          }
        });
        
        if (duplicateCourt) {
          return res.status(400).json({ 
            success: false, 
            error: 'Court name already exists' 
          });
        }
      }
      
      // Convert price to float if provided
      if (updateData.price_per_hour) {
        updateData.price_per_hour = parseFloat(updateData.price_per_hour);
      }
      
      const updatedCourt = await prisma.courts.update({
        where: { court_id: parseInt(id) },
        data: updateData
      });
      
      res.json({
        success: true,
        message: 'Court updated successfully',
        data: {
          ...updatedCourt,
          price_per_hour: Number(updatedCourt.price_per_hour)
        }
      });
      
    } catch (error) {
      console.error('Update court error:', error);
      res.status(500).json({ success: false, error: 'Failed to update court' });
    }
  }
];

exports.deleteCourt = async (req, res) => {
  try {
    const { id } = req.params;
    
    // Check if court has bookings
    const courtBookings = await prisma.bookings.count({
      where: { court_id: parseInt(id) }
    });
    
    if (courtBookings > 0) {
      return res.status(400).json({ 
        success: false, 
        error: 'Cannot delete court with existing bookings. Deactivate instead.' 
      });
    }
    
    await prisma.courts.delete({
      where: { court_id: parseInt(id) }
    });
    
    res.json({
      success: true,
      message: 'Court deleted successfully'
    });
    
  } catch (error) {
    console.error('Delete court error:', error);
    
    if (error.code === 'P2025') {
      return res.status(404).json({ success: false, error: 'Court not found' });
    }
    
    res.status(500).json({ success: false, error: 'Failed to delete court' });
  }
};

exports.getCourtSchedule = async (req, res) => {
  try {
    const { id } = req.params;
    const { date } = req.query;
    
    const selectedDate = date ? new Date(date) : new Date();
    const startOfDay = new Date(selectedDate);
    startOfDay.setHours(0, 0, 0, 0);
    
    const endOfDay = new Date(selectedDate);
    endOfDay.setHours(23, 59, 59, 999);
    
    const bookings = await prisma.bookings.findMany({
      where: {
        court_id: parseInt(id),
        start_time: { gte: startOfDay, lte: endOfDay }
      },
      include: {
        users: {
          select: { full_name: true, phone_number: true }
        }
      },
      orderBy: { start_time: 'asc' }
    });
    
    // Create hourly schedule
    const schedule = Array.from({ length: 14 }, (_, i) => {
      const hour = i + 7; // From 7:00 to 21:00
      const hourStart = new Date(startOfDay);
      hourStart.setHours(hour, 0, 0, 0);
      
      const hourEnd = new Date(hourStart);
      hourEnd.setHours(hour + 1, 0, 0, 0);
      
      // Find booking for this hour
      const booking = bookings.find(b => {
        const bookingStart = new Date(b.start_time);
        const bookingEnd = new Date(b.end_time);
        return bookingStart < hourEnd && bookingEnd > hourStart;
      });
      
      return {
        hour: `${hour.toString().padStart(2, '0')}:00`,
        timeRange: `${hour.toString().padStart(2, '0')}:00 - ${(hour + 1).toString().padStart(2, '0')}:00`,
        isBooked: !!booking,
        booking: booking ? {
          id: booking.booking_id,
          customer: booking.users?.full_name || 'Unknown',
          phone: booking.users?.phone_number || 'N/A',
          status: booking.status,
          startTime: booking.start_time,
          endTime: booking.end_time
        } : null
      };
    });
    
    const court = await prisma.courts.findUnique({
      where: { court_id: parseInt(id) },
      select: { court_name: true, price_per_hour: true }
    });
    
    res.json({
      success: true,
      data: {
        date: startOfDay.toISOString().split('T')[0],
        court: {
          ...court,
          price_per_hour: Number(court.price_per_hour)
        },
        schedule
      }
    });
    
  } catch (error) {
    console.error('Get court schedule error:', error);
    res.status(500).json({ success: false, error: 'Failed to fetch court schedule' });
  }
};

exports.getCourtBookings = async (req, res) => {
  try {
    const { id } = req.params;
    const { 
      page = 1, 
      limit = 10,
      status,
      start_date,
      end_date
    } = req.query;
    
    const skip = (parseInt(page) - 1) * parseInt(limit);
    
    const where = { court_id: parseInt(id) };
    
    if (status && status !== 'all') {
      where.status = status;
    }
    
    if (start_date || end_date) {
      where.start_time = {};
      if (start_date) {
        where.start_time.gte = new Date(start_date);
      }
      if (end_date) {
        where.start_time.lte = new Date(end_date);
      }
    }
    
    const total = await prisma.bookings.count({ where });
    
    const bookings = await prisma.bookings.findMany({
      where,
      skip,
      take: parseInt(limit),
      orderBy: { start_time: 'desc' },
      include: {
        users: {
          select: { full_name: true, phone_number: true }
        },
        payments: {
          select: { amount_paid: true, payment_method: true }
        }
      }
    });
    
    res.json({
      success: true,
      data: {
        bookings: bookings.map(booking => ({
          id: booking.booking_id,
          customer: booking.users?.full_name,
          phone: booking.users?.phone_number,
          startTime: booking.start_time,
          endTime: booking.end_time,
          totalPrice: Number(booking.total_price),
          status: booking.status,
          payment: booking.payments[0] ? {
            amount: Number(booking.payments[0].amount_paid),
            method: booking.payments[0].payment_method
          } : null
        })),
        pagination: {
          page: parseInt(page),
          limit: parseInt(limit),
          total,
          totalPages: Math.ceil(total / parseInt(limit))
        }
      }
    });
    
  } catch (error) {
    console.error('Get court bookings error:', error);
    res.status(500).json({ success: false, error: 'Failed to fetch court bookings' });
  }
};