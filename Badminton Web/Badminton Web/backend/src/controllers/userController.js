const { body, validationResult } = require('express-validator');
const bcrypt = require('bcryptjs');
const prisma = require('../config/database');

// [1] Lấy danh sách người dùng
exports.getAllUsers = async (req, res) => {
  try {
    const { 
      page = 1, 
      limit = 20, 
      role, 
      search = '',
      sortBy = 'created_at', 
      sortOrder = 'desc'
    } = req.query;
    
    const skip = (parseInt(page) - 1) * parseInt(limit);
    
    // Build where clause
    const where = {};
    
    if (role && role !== 'all') {
      where.role = role;
    }
    
    if (search) {
      where.OR = [
        { full_name: { contains: search, mode: 'insensitive' } },
        { username: { contains: search, mode: 'insensitive' } },
        { email: { contains: search, mode: 'insensitive' } },
        { phone_number: { contains: search, mode: 'insensitive' } }
      ];
    }
    
    // Get total count
    const total = await prisma.users.count({ where });
    
    // Get users with pagination
    const users = await prisma.users.findMany({
      where,
      skip,
      take: parseInt(limit),
      orderBy: { [sortBy]: sortOrder },
      select: {
        user_id: true,
        username: true,
        full_name: true,
        email: true,
        phone_number: true,
        role: true,
        created_at: true,
        _count: {
          select: { bookings: true }
        }
      }
    });
    
    // Get role counts
    const roleCounts = await prisma.users.groupBy({
      by: ['role'],
      _count: { user_id: true }
    });
    
    const roleStats = roleCounts.reduce((acc, curr) => {
      acc[curr.role] = curr._count.user_id;
      return acc;
    }, { admin: 0, staff: 0, customer: 0 });
    
    res.json({
      success: true,
      data: {
        users,
        pagination: {
          page: parseInt(page),
          limit: parseInt(limit),
          total,
          totalPages: Math.ceil(total / parseInt(limit))
        },
        stats: {
          total,
          ...roleStats
        }
      }
    });
    
  } catch (error) {
    console.error('Get users error:', error);
    res.status(500).json({ success: false, error: 'Failed to fetch users' });
  }
};

// [2] Lấy chi tiết người dùng
exports.getUserById = async (req, res) => {
  try {
    const { id } = req.params;
    
    const user = await prisma.users.findUnique({
      where: { user_id: parseInt(id) },
      select: {
        user_id: true,
        username: true,
        full_name: true,
        email: true,
        phone_number: true,
        role: true,
        created_at: true,
        bookings: {
          include: {
            courts: {
              select: { court_name: true, court_type: true }
            },
            payments: {
              select: { amount_paid: true, payment_method: true, payment_date: true }
            }
          },
          orderBy: { created_at: 'desc' },
          take: 10
        }
      }
    });
    
    if (!user) {
      return res.status(404).json({ success: false, error: 'User not found' });
    }
    
    // Get user statistics
    const stats = await prisma.bookings.aggregate({
      where: { user_id: parseInt(id) },
      _sum: { total_price: true },
      _count: { booking_id: true },
      _avg: { total_price: true }
    });
    
    res.json({
      success: true,
      data: {
        user,
        statistics: {
          totalBookings: stats._count.booking_id || 0,
          totalSpent: Number(stats._sum.total_price) || 0,
          averageBookingValue: Number(stats._avg.total_price) || 0
        }
      }
    });
    
  } catch (error) {
    console.error('Get user error:', error);
    res.status(500).json({ success: false, error: 'Failed to fetch user details' });
  }
};

// [3] Tạo người dùng mới (admin/staff)
exports.createUser = [
  body('username').trim().notEmpty().withMessage('Username is required'),
  body('password').isLength({ min: 6 }).withMessage('Password must be at least 6 characters'),
  body('full_name').trim().notEmpty().withMessage('Full name is required'),
  body('email').isEmail().withMessage('Valid email is required'),
  body('phone_number').matches(/^[0-9]{10,11}$/).withMessage('Valid phone number is required'),
  body('role').isIn(['customer', 'staff', 'admin']).withMessage('Invalid role'),
  
  async (req, res) => {
    try {
      const errors = validationResult(req);
      if (!errors.isEmpty()) {
        return res.status(400).json({ success: false, errors: errors.array() });
      }
      
      const { username, password, full_name, email, phone_number, role } = req.body;
      
      // Check for existing username, email, phone
      const existingUser = await prisma.users.findFirst({
        where: {
          OR: [
            { username },
            { email },
            { phone_number }
          ]
        }
      });
      
      if (existingUser) {
        const field = existingUser.username === username ? 'username' :
                     existingUser.email === email ? 'email' : 'phone_number';
        return res.status(400).json({ 
          success: false, 
          error: `${field} already exists` 
        });
      }
      
      // Hash password
      const salt = await bcrypt.genSalt(10);
      const password_hash = await bcrypt.hash(password, salt);
      
      const user = await prisma.users.create({
        data: {
          username,
          password_hash,
          full_name,
          email,
          phone_number,
          role
        },
        select: {
          user_id: true,
          username: true,
          full_name: true,
          email: true,
          phone_number: true,
          role: true,
          created_at: true
        }
      });
      
      res.status(201).json({
        success: true,
        message: 'User created successfully',
        data: user
      });
      
    } catch (error) {
      console.error('Create user error:', error);
      res.status(500).json({ success: false, error: 'Failed to create user' });
    }
  }
];

// [4] Cập nhật người dùng
exports.updateUser = [
  body('full_name').optional().trim(),
  body('email').optional().isEmail(),
  body('phone_number').optional().matches(/^[0-9]{10,11}$/),
  body('role').optional().isIn(['customer', 'staff', 'admin']),
  body('password').optional().isLength({ min: 6 }),
  
  async (req, res) => {
    try {
      const errors = validationResult(req);
      if (!errors.isEmpty()) {
        return res.status(400).json({ success: false, errors: errors.array() });
      }
      
      const { id } = req.params;
      const updateData = req.body;
      
      // Check if user exists
      const existingUser = await prisma.users.findUnique({
        where: { user_id: parseInt(id) }
      });
      
      if (!existingUser) {
        return res.status(404).json({ success: false, error: 'User not found' });
      }
      
      // Check for duplicate email or phone
      if (updateData.email || updateData.phone_number) {
        const duplicateConditions = [];
        
        if (updateData.email) {
          duplicateConditions.push({ email: updateData.email });
        }
        if (updateData.phone_number) {
          duplicateConditions.push({ phone_number: updateData.phone_number });
        }
        
        if (duplicateConditions.length > 0) {
          const duplicateUser = await prisma.users.findFirst({
            where: {
              OR: duplicateConditions,
              user_id: { not: parseInt(id) }
            }
          });
          
          if (duplicateUser) {
            const field = duplicateUser.email === updateData.email ? 'email' : 'phone_number';
            return res.status(400).json({ 
              success: false, 
              error: `${field} already exists` 
            });
          }
        }
      }
      
      // Hash new password if provided
      if (updateData.password) {
        const salt = await bcrypt.genSalt(10);
        updateData.password_hash = await bcrypt.hash(updateData.password, salt);
        delete updateData.password;
      }
      
      const updatedUser = await prisma.users.update({
        where: { user_id: parseInt(id) },
        data: updateData,
        select: {
          user_id: true,
          username: true,
          full_name: true,
          email: true,
          phone_number: true,
          role: true,
          created_at: true
        }
      });
      
      res.json({
        success: true,
        message: 'User updated successfully',
        data: updatedUser
      });
      
    } catch (error) {
      console.error('Update user error:', error);
      res.status(500).json({ success: false, error: 'Failed to update user' });
    }
  }
];

// [5] Xóa người dùng
exports.deleteUser = async (req, res) => {
  try {
    const { id } = req.params;
    
    // Prevent deleting self
    if (parseInt(id) === req.user.user_id) {
      return res.status(400).json({ 
        success: false, 
        error: 'Cannot delete your own account' 
      });
    }
    
    // Check if user has bookings
    const userBookings = await prisma.bookings.count({
      where: { user_id: parseInt(id) }
    });
    
    if (userBookings > 0) {
      return res.status(400).json({ 
        success: false, 
        error: 'Cannot delete user with existing bookings. Deactivate instead.' 
      });
    }
    
    await prisma.users.delete({
      where: { user_id: parseInt(id) }
    });
    
    res.json({
      success: true,
      message: 'User deleted successfully'
    });
    
  } catch (error) {
    console.error('Delete user error:', error);
    
    if (error.code === 'P2025') {
      return res.status(404).json({ success: false, error: 'User not found' });
    }
    
    res.status(500).json({ success: false, error: 'Failed to delete user' });
  }
};

// [6] Phân quyền người dùng
exports.changeUserRole = [
  body('role').isIn(['customer', 'staff', 'admin']).withMessage('Invalid role'),
  
  async (req, res) => {
    try {
      const errors = validationResult(req);
      if (!errors.isEmpty()) {
        return res.status(400).json({ success: false, errors: errors.array() });
      }
      
      const { id } = req.params;
      const { role } = req.body;
      
      // Prevent changing own role to lower privilege
      if (parseInt(id) === req.user.user_id && role !== 'admin') {
        return res.status(400).json({ 
          success: false, 
          error: 'Cannot demote your own account' 
        });
      }
      
      const updatedUser = await prisma.users.update({
        where: { user_id: parseInt(id) },
        data: { role },
        select: {
          user_id: true,
          username: true,
          full_name: true,
          email: true,
          phone_number: true,
          role: true
        }
      });
      
      res.json({
        success: true,
        message: `User role changed to ${role}`,
        data: updatedUser
      });
      
    } catch (error) {
      console.error('Change role error:', error);
      res.status(500).json({ success: false, error: 'Failed to change user role' });
    }
  }
];

// [7] Tìm kiếm người dùng (cho autocomplete)
exports.searchUsers = async (req, res) => {
  try {
    const { query = '', limit = 10 } = req.query;
    
    if (!query || query.length < 2) {
      return res.json({ success: true, data: [] });
    }
    
    const users = await prisma.users.findMany({
      where: {
        OR: [
          { full_name: { contains: query, mode: 'insensitive' } },
          { username: { contains: query, mode: 'insensitive' } },
          { email: { contains: query, mode: 'insensitive' } },
          { phone_number: { contains: query, mode: 'insensitive' } }
        ]
      },
      take: parseInt(limit),
      select: {
        user_id: true,
        username: true,
        full_name: true,
        email: true,
        phone_number: true,
        role: true
      },
      orderBy: { full_name: 'asc' }
    });
    
    res.json({ success: true, data: users });
    
  } catch (error) {
    console.error('Search users error:', error);
    res.status(500).json({ success: false, error: 'Search failed' });
  }
};

// [8] Thống kê người dùng
exports.getUserStats = async (req, res) => {
  try {
    const { period = 'month' } = req.query;
    
    const now = new Date();
    let startDate = new Date();
    
    switch (period) {
      case 'day':
        startDate.setDate(now.getDate() - 1);
        break;
      case 'week':
        startDate.setDate(now.getDate() - 7);
        break;
      case 'month':
        startDate.setMonth(now.getMonth() - 1);
        break;
      case 'year':
        startDate.setFullYear(now.getFullYear() - 1);
        break;
      default:
        startDate.setMonth(now.getMonth() - 1);
    }
    
    // Get new users by period
    const newUsers = await prisma.users.groupBy({
      by: ['created_at'],
      _count: { user_id: true },
      where: {
        created_at: { gte: startDate }
      },
      orderBy: { created_at: 'asc' }
    });
    
    // Get user growth
    const totalUsers = await prisma.users.count();
    const previousPeriodCount = await prisma.users.count({
      where: { created_at: { lt: startDate } }
    });
    
    const growthRate = previousPeriodCount > 0 
      ? ((totalUsers - previousPeriodCount) / previousPeriodCount * 100).toFixed(2)
      : 100;
    
    // Get top customers by bookings
    const topCustomers = await prisma.users.findMany({
      where: {
        bookings: { some: {} },
        role: 'customer'
      },
      select: {
        user_id: true,
        full_name: true,
        email: true,
        phone_number: true,
        _count: {
          select: { bookings: true }
        }
      },
      orderBy: {
        bookings: { _count: 'desc' }
      },
      take: 10
    });
    
    res.json({
      success: true,
      data: {
        totalUsers,
        newUsers: newUsers.length,
        growthRate: `${growthRate}%`,
        newUsersTrend: newUsers.map(item => ({
          date: item.created_at.toISOString().split('T')[0],
          count: item._count.user_id
        })),
        topCustomers: topCustomers.map(user => ({
          ...user,
          bookingCount: user._count.bookings
        }))
      }
    });
    
  } catch (error) {
    console.error('User stats error:', error);
    res.status(500).json({ success: false, error: 'Failed to get user statistics' });
  }
};