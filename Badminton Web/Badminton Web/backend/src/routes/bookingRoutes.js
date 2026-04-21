const express = require('express');
const router = express.Router();
const bookingController = require('../controllers/bookingController');

// GET /api/admin/bookings - Get all bookings
router.get('/', bookingController.getAllBookings);

// GET /api/admin/bookings/stats - Get booking statistics
router.get('/stats', bookingController.getBookingStats);

// GET /api/admin/bookings/:id - Get booking by ID
router.get('/:id', bookingController.getBookingById);

// PUT /api/admin/bookings/:id - Update booking
router.put('/:id', bookingController.updateBooking);

// DELETE /api/admin/bookings/:id - Delete booking
router.delete('/:id', bookingController.deleteBooking);

// PATCH /api/admin/bookings/:id/status - Update booking status
router.patch('/:id/status', bookingController.updateBookingStatus);

// POST /api/admin/bookings/:id/payment - Add payment to booking
router.post('/:id/payment', bookingController.addPayment);

// GET /api/admin/bookings/:id/payments - Get booking payments
router.get('/:id/payments', bookingController.getBookingPayments);

module.exports = router;