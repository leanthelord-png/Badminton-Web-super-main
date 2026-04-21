const express = require('express');
const router = express.Router();
const { body, validationResult } = require('express-validator');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const prisma = require('../config/database');

const JWT_SECRET = process.env.JWT_SECRET || 'admin-secret-key-2024';

// Admin login
router.post('/login', [
  body('username').trim().notEmpty().withMessage('Username is required'),
  body('password').notEmpty().withMessage('Password is required')
], async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ success: false, errors: errors.array() });
    }
    
    const { username, password } = req.body;
    
    // Find user
    const user = await prisma.users.findUnique({
      where: { username }
    });
    
    if (!user) {
      return res.status(401).json({ success: false, error: 'Invalid credentials' });
    }
    
    // Check if user is admin or staff
    if (!['admin', 'staff'].includes(user.role)) {
      return res.status(403).json({ 
        success: false, 
        error: 'Access denied. Admin or staff privileges required.' 
      });
    }
    
    // Verify password
    const isValidPassword = await bcrypt.compare(password, user.password_hash);
    if (!isValidPassword) {
      return res.status(401).json({ success: false, error: 'Invalid credentials' });
    }
    
    // Generate token
    const token = jwt.sign(
      { 
        userId: user.user_id,
        role: user.role,
        username: user.username
      },
      JWT_SECRET,
      { expiresIn: '24h' }
    );
    
    // Remove password from response
    const userResponse = {
      user_id: user.user_id,
      username: user.username,
      full_name: user.full_name,
      email: user.email,
      phone_number: user.phone_number,
      role: user.role,
      created_at: user.created_at
    };
    
    res.json({
      success: true,
      message: 'Login successful',
      token,
      user: userResponse
    });
    
  } catch (error) {
    console.error('Login error:', error);
    res.status(500).json({ success: false, error: 'Internal server error' });
  }
});

// Get current admin user
router.get('/me', async (req, res) => {
  try {
    const token = req.header('Authorization')?.replace('Bearer ', '');
    
    if (!token) {
      return res.status(401).json({ success: false, error: 'No token provided' });
    }
    
    const decoded = jwt.verify(token, JWT_SECRET);
    
    const user = await prisma.users.findUnique({
      where: { user_id: decoded.userId },
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
    
    if (!user) {
      return res.status(404).json({ success: false, error: 'User not found' });
    }
    
    res.json({ success: true, user });
    
  } catch (error) {
    res.status(401).json({ success: false, error: 'Invalid token' });
  }
});

// Create initial admin (first time setup)
router.post('/setup', async (req, res) => {
  try {
    // Check if any admin exists
    const existingAdmin = await prisma.users.findFirst({
      where: { role: 'admin' }
    });
    
    if (existingAdmin) {
      return res.status(400).json({ 
        success: false, 
        error: 'Admin already exists. Use regular registration.' 
      });
    }
    
    const { username, password, full_name, email, phone_number } = req.body;
    
    if (!username || !password || !full_name || !phone_number) {
      return res.status(400).json({ 
        success: false, 
        error: 'All fields are required' 
      });
    }
    
    // Hash password
    const salt = await bcrypt.genSalt(10);
    const password_hash = await bcrypt.hash(password, salt);
    
    // Create admin user
    const admin = await prisma.users.create({
      data: {
        username,
        password_hash,
        full_name,
        email,
        phone_number,
        role: 'admin'
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
    
    // Generate token
    const token = jwt.sign(
      { 
        userId: admin.user_id,
        role: admin.role,
        username: admin.username
      },
      JWT_SECRET,
      { expiresIn: '24h' }
    );
    
    res.status(201).json({
      success: true,
      message: 'Admin account created successfully',
      token,
      user: admin
    });
    
  } catch (error) {
    console.error('Setup error:', error);
    res.status(500).json({ success: false, error: 'Internal server error' });
  }
});

// Change password
router.post('/change-password', [
  body('currentPassword').notEmpty().withMessage('Current password is required'),
  body('newPassword').isLength({ min: 6 }).withMessage('New password must be at least 6 characters')
], async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ success: false, errors: errors.array() });
    }
    
    const token = req.header('Authorization')?.replace('Bearer ', '');
    
    if (!token) {
      return res.status(401).json({ success: false, error: 'No token provided' });
    }
    
    const decoded = jwt.verify(token, JWT_SECRET);
    const { currentPassword, newPassword } = req.body;
    
    // Get user with password
    const user = await prisma.users.findUnique({
      where: { user_id: decoded.userId }
    });
    
    if (!user) {
      return res.status(404).json({ success: false, error: 'User not found' });
    }
    
    // Verify current password
    const isValidPassword = await bcrypt.compare(currentPassword, user.password_hash);
    if (!isValidPassword) {
      return res.status(401).json({ success: false, error: 'Current password is incorrect' });
    }
    
    // Hash new password
    const salt = await bcrypt.genSalt(10);
    const password_hash = await bcrypt.hash(newPassword, salt);
    
    // Update password
    await prisma.users.update({
      where: { user_id: decoded.userId },
      data: { password_hash }
    });
    
    res.json({
      success: true,
      message: 'Password changed successfully'
    });
    
  } catch (error) {
    console.error('Change password error:', error);
    res.status(500).json({ success: false, error: 'Failed to change password' });
  }
});

module.exports = router;