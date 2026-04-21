const express = require('express');
const router = express.Router();
const pricingController = require('../controllers/pricingController');

// Khung giờ giá
router.get('/slots', pricingController.getPricingSlots);
router.post('/slots', pricingController.createPricingSlot);
router.put('/slots/:id', pricingController.updatePricingSlot);
router.delete('/slots/:id', pricingController.deletePricingSlot);

// Giá sân theo khung giờ
router.get('/courts/:court_id', pricingController.getCourtPricing);
router.put('/courts/:court_id', pricingController.updateCourtPricing);
router.get('/courts/:court_id/preview', pricingController.getPricePreview);

// Tính giá booking
router.post('/calculate', pricingController.calculateBookingPrice);

module.exports = router;