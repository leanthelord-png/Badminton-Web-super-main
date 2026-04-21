const express = require('express');
const cors = require('cors');
const morgan = require('morgan');
const dotenv = require('dotenv');

// Import routes
const authRoutes = require('./routes/authRoutes');
const userRoutes = require('./routes/userRoutes');
const courtRoutes = require('./routes/courtRoutes');
const bookingRoutes = require('./routes/bookingRoutes');
const pricingRoutes = require('./routes/pricingRoutes');
const reportRoutes = require('./routes/reportRoutes');
const backupRoutes = require('./routes/backupRoutes');

// Import middleware
const { authenticate, authorize } = require('./middleware/auth');

dotenv.config();

const app = express();
const PORT = process.env.ADMIN_PORT || 3001;

// Middleware
app.use(cors());
app.use(morgan('dev'));
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Public routes
app.use('/api/admin/auth', authRoutes);

// Protected admin routes
app.use('/api/admin/users', authenticate, authorize(['admin']), userRoutes);
app.use('/api/admin/courts', authenticate, authorize(['admin', 'staff']), courtRoutes);
app.use('/api/admin/bookings', authenticate, authorize(['admin', 'staff']), bookingRoutes);
app.use('/api/admin/pricing', authenticate, authorize(['admin']), pricingRoutes);
app.use('/api/admin/reports', authenticate, authorize(['admin', 'staff']), reportRoutes);
app.use('/api/admin/backup', authenticate, authorize(['admin']), backupRoutes);

// Admin dashboard endpoint
app.get('/api/admin/dashboard', authenticate, authorize(['admin', 'staff']), async (req, res) => {
  try {
    res.json({ 
      success: true,
      message: 'Admin dashboard',
      user: req.user 
    });
  } catch (error) {
    res.status(500).json({ success: false, error: error.message });
  }
});

// Health check
app.get('/api/admin/health', (req, res) => {
  res.json({ 
    success: true,
    message: 'Admin API is running',
    timestamp: new Date().toISOString()
  });
});

// Error handling middleware
app.use((err, req, res, next) => {
  console.error('Error:', err);
  res.status(err.status || 500).json({
    success: false,
    error: err.message || 'Internal server error'
  });
});

// 404 handler
app.use((req, res) => {
  res.status(404).json({ 
    success: false,
    error: 'Endpoint not found' 
  });
});

app.listen(PORT, () => {
  console.log(`🚀 Admin Server running on port ${PORT}`);
  console.log(`📊 Health check: http://localhost:${PORT}/api/admin/health`);
  console.log(`🔑 Auth endpoints: http://localhost:${PORT}/api/admin/auth`);
});