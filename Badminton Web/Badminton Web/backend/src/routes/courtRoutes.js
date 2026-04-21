const express = require('express');
const router = express.Router();
const courtController = require('../controllers/courtController');

// GET /api/admin/courts - Get all courts
router.get('/', courtController.getAllCourts);

// GET /api/admin/courts/stats - Get court statistics
router.get('/stats', courtController.getCourtStats);

// GET /api/admin/courts/:id - Get court by ID
router.get('/:id', courtController.getCourtById);

// POST /api/admin/courts - Create new court
router.post('/', courtController.createCourt);

// PUT /api/admin/courts/:id - Update court
router.put('/:id', courtController.updateCourt);

// DELETE /api/admin/courts/:id - Delete court
router.delete('/:id', courtController.deleteCourt);

// GET /api/admin/courts/:id/schedule - Get court schedule
router.get('/:id/schedule', courtController.getCourtSchedule);

// GET /api/admin/courts/:id/bookings - Get court bookings
router.get('/:id/bookings', courtController.getCourtBookings);

module.exports = router;