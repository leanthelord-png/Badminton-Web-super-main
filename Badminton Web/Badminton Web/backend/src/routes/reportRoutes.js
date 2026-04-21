const express = require('express');
const router = express.Router();
const reportController = require('../controllers/reportController');

// Dashboard thống kê
router.get('/dashboard', reportController.getDashboardStats);

// Báo cáo doanh thu
router.get('/revenue', reportController.getRevenueReport);

// Báo cáo lượt đặt sân
router.get('/bookings', reportController.getBookingReport);

// Xuất báo cáo
router.get('/export', reportController.exportReport);

module.exports = router;