const { body, validationResult } = require('express-validator');
const prisma = require('../config/database');

// [1] Lấy danh sách khung giờ giá
exports.getPricingSlots = async (req, res) => {
  try {
    const { is_peak, day_type, is_active } = req.query;
    
    const where = {};
    
    if (is_peak !== undefined) {
      where.is_peak = is_peak === 'true';
    }
    
    if (day_type && day_type !== 'all') {
      where.day_type = day_type;
    }
    
    if (is_active !== undefined) {
      where.is_active = is_active === 'true';
    }
    
    const slots = await prisma.pricing_slots.findMany({
      where,
      orderBy: [
        { day_type: 'asc' },
        { start_hour: 'asc' }
      ]
    });
    
    // Format time display
    const formattedSlots = slots.map(slot => ({
      ...slot,
      time_display: `${slot.start_hour.toString().padStart(2, '0')}:00 - ${slot.end_hour.toString().padStart(2, '0')}:00`,
      multiplier: Number(slot.multiplier)
    }));
    
    res.json({
      success: true,
      data: formattedSlots
    });
    
  } catch (error) {
    console.error('Get pricing slots error:', error);
    res.status(500).json({ success: false, error: 'Failed to fetch pricing slots' });
  }
};

// [2] Tạo khung giờ giá mới
exports.createPricingSlot = [
  body('slot_name').trim().notEmpty().withMessage('Slot name is required'),
  body('start_hour').isInt({ min: 0, max: 23 }).withMessage('Start hour must be 0-23'),
  body('end_hour').isInt({ min: 1, max: 24 }).withMessage('End hour must be 1-24'),
  body('day_type').isIn(['weekday', 'weekend', 'holiday', 'all']).withMessage('Invalid day type'),
  body('multiplier').isFloat({ min: 0.5, max: 3 }).withMessage('Multiplier must be between 0.5 and 3'),
  body('is_peak').isBoolean().withMessage('is_peak must be boolean'),
  body('description').optional().trim(),
  
  async (req, res) => {
    try {
      const errors = validationResult(req);
      if (!errors.isEmpty()) {
        return res.status(400).json({ success: false, errors: errors.array() });
      }
      
      const { slot_name, start_hour, end_hour, day_type, multiplier, is_peak, description } = req.body;
      
      // Validate time range
      if (start_hour >= end_hour) {
        return res.status(400).json({ 
          success: false, 
          error: 'Start hour must be less than end hour' 
        });
      }
      
      // Check for overlapping slots
      const overlappingSlot = await prisma.pricing_slots.findFirst({
        where: {
          day_type: { in: [day_type, 'all'] },
          OR: [
            { start_hour: { lt: end_hour }, end_hour: { gt: start_hour } }
          ]
        }
      });
      
      if (overlappingSlot) {
        return res.status(400).json({ 
          success: false, 
          error: 'Overlapping time slot exists' 
        });
      }
      
      const slot = await prisma.pricing_slots.create({
        data: {
          slot_name,
          start_hour: parseInt(start_hour),
          end_hour: parseInt(end_hour),
          day_type,
          multiplier: parseFloat(multiplier),
          is_peak: Boolean(is_peak),
          description,
          is_active: true
        }
      });
      
      res.status(201).json({
        success: true,
        message: 'Pricing slot created successfully',
        data: {
          ...slot,
          multiplier: Number(slot.multiplier),
          time_display: `${slot.start_hour.toString().padStart(2, '0')}:00 - ${slot.end_hour.toString().padStart(2, '0')}:00`
        }
      });
      
    } catch (error) {
      console.error('Create pricing slot error:', error);
      res.status(500).json({ success: false, error: 'Failed to create pricing slot' });
    }
  }
];

// [3] Cập nhật khung giờ giá
exports.updatePricingSlot = [
  body('slot_name').optional().trim(),
  body('start_hour').optional().isInt({ min: 0, max: 23 }),
  body('end_hour').optional().isInt({ min: 1, max: 24 }),
  body('day_type').optional().isIn(['weekday', 'weekend', 'holiday', 'all']),
  body('multiplier').optional().isFloat({ min: 0.5, max: 3 }),
  body('is_peak').optional().isBoolean(),
  body('is_active').optional().isBoolean(),
  body('description').optional().trim(),
  
  async (req, res) => {
    try {
      const errors = validationResult(req);
      if (!errors.isEmpty()) {
        return res.status(400).json({ success: false, errors: errors.array() });
      }
      
      const { id } = req.params;
      const updateData = req.body;
      
      // Get existing slot
      const existingSlot = await prisma.pricing_slots.findUnique({
        where: { slot_id: parseInt(id) }
      });
      
      if (!existingSlot) {
        return res.status(404).json({ success: false, error: 'Pricing slot not found' });
      }
      
      // Validate time range if updating hours
      if ((updateData.start_hour !== undefined || updateData.end_hour !== undefined) && 
          (updateData.day_type !== undefined || existingSlot.day_type)) {
        
        const startHour = updateData.start_hour !== undefined 
          ? parseInt(updateData.start_hour) 
          : existingSlot.start_hour;
        
        const endHour = updateData.end_hour !== undefined 
          ? parseInt(updateData.end_hour) 
          : existingSlot.end_hour;
        
        const dayType = updateData.day_type || existingSlot.day_type;
        
        if (startHour >= endHour) {
          return res.status(400).json({ 
            success: false, 
            error: 'Start hour must be less than end hour' 
          });
        }
        
        // Check for overlapping slots (excluding current)
        const overlappingSlot = await prisma.pricing_slots.findFirst({
          where: {
            slot_id: { not: parseInt(id) },
            day_type: { in: [dayType, 'all'] },
            OR: [
              { start_hour: { lt: endHour }, end_hour: { gt: startHour } }
            ]
          }
        });
        
        if (overlappingSlot) {
          return res.status(400).json({ 
            success: false, 
            error: 'Overlapping time slot exists' 
          });
        }
      }
      
      // Convert numeric fields
      if (updateData.start_hour !== undefined) {
        updateData.start_hour = parseInt(updateData.start_hour);
      }
      if (updateData.end_hour !== undefined) {
        updateData.end_hour = parseInt(updateData.end_hour);
      }
      if (updateData.multiplier !== undefined) {
        updateData.multiplier = parseFloat(updateData.multiplier);
      }
      
      const updatedSlot = await prisma.pricing_slots.update({
        where: { slot_id: parseInt(id) },
        data: updateData
      });
      
      res.json({
        success: true,
        message: 'Pricing slot updated successfully',
        data: {
          ...updatedSlot,
          multiplier: Number(updatedSlot.multiplier),
          time_display: `${updatedSlot.start_hour.toString().padStart(2, '0')}:00 - ${updatedSlot.end_hour.toString().padStart(2, '0')}:00`
        }
      });
      
    } catch (error) {
      console.error('Update pricing slot error:', error);
      res.status(500).json({ success: false, error: 'Failed to update pricing slot' });
    }
  }
];

// [4] Xóa khung giờ giá
exports.deletePricingSlot = async (req, res) => {
  try {
    const { id } = req.params;
    
    // Check if slot is used in court pricing
    const courtPricingCount = await prisma.court_pricing.count({
      where: { slot_id: parseInt(id) }
    });
    
    if (courtPricingCount > 0) {
      return res.status(400).json({ 
        success: false, 
        error: 'Cannot delete slot that is in use. Deactivate instead.' 
      });
    }
    
    await prisma.pricing_slots.delete({
      where: { slot_id: parseInt(id) }
    });
    
    res.json({
      success: true,
      message: 'Pricing slot deleted successfully'
    });
    
  } catch (error) {
    console.error('Delete pricing slot error:', error);
    
    if (error.code === 'P2025') {
      return res.status(404).json({ success: false, error: 'Pricing slot not found' });
    }
    
    res.status(500).json({ success: false, error: 'Failed to delete pricing slot' });
  }
};

// [5] Lấy giá sân theo khung giờ
exports.getCourtPricing = async (req, res) => {
  try {
    const { court_id } = req.params;
    
    // Verify court exists
    const court = await prisma.courts.findUnique({
      where: { court_id: parseInt(court_id) },
      select: { court_id: true, court_name: true, price_per_hour: true }
    });
    
    if (!court) {
      return res.status(404).json({ success: false, error: 'Court not found' });
    }
    
    // Get all pricing slots
    const allSlots = await prisma.pricing_slots.findMany({
      where: { is_active: true },
      orderBy: [
        { day_type: 'asc' },
        { start_hour: 'asc' }
      ]
    });
    
    // Get custom pricing for this court
    const customPricing = await prisma.court_pricing.findMany({
      where: { court_id: parseInt(court_id) },
      include: {
        pricing_slots: true
      }
    });
    
    // Combine data
    const pricingData = allSlots.map(slot => {
      const customPrice = customPricing.find(cp => cp.slot_id === slot.slot_id);
      
      const basePrice = Number(court.price_per_hour);
      const multiplier = Number(slot.multiplier);
      const calculatedPrice = basePrice * multiplier;
      const finalPrice = customPrice?.custom_price 
        ? Number(customPrice.custom_price)
        : calculatedPrice;
      
      return {
        slot_id: slot.slot_id,
        slot_name: slot.slot_name,
        time_range: `${slot.start_hour.toString().padStart(2, '0')}:00 - ${slot.end_hour.toString().padStart(2, '0')}:00`,
        day_type: slot.day_type,
        is_peak: slot.is_peak,
        base_price: basePrice,
        multiplier: multiplier,
        calculated_price: calculatedPrice,
        custom_price: customPrice?.custom_price ? Number(customPrice.custom_price) : null,
        final_price: finalPrice,
        has_custom_price: !!customPrice,
        is_active: customPrice ? customPrice.is_active : true
      };
    });
    
    res.json({
      success: true,
      data: {
        court: {
          court_id: court.court_id,
          court_name: court.court_name,
          base_price: Number(court.price_per_hour)
        },
        pricing: pricingData
      }
    });
    
  } catch (error) {
    console.error('Get court pricing error:', error);
    res.status(500).json({ success: false, error: 'Failed to fetch court pricing' });
  }
};

// [6] Cập nhật giá sân theo khung giờ
exports.updateCourtPricing = [
  body('pricing').isArray().withMessage('Pricing must be an array'),
  body('pricing.*.slot_id').isInt().withMessage('Slot ID must be integer'),
  body('pricing.*.custom_price').optional().isFloat({ min: 0 }),
  body('pricing.*.is_active').optional().isBoolean(),
  
  async (req, res) => {
    try {
      const errors = validationResult(req);
      if (!errors.isEmpty()) {
        return res.status(400).json({ success: false, errors: errors.array() });
      }
      
      const { court_id } = req.params;
      const { pricing } = req.body;
      
      // Verify court exists
      const court = await prisma.courts.findUnique({
        where: { court_id: parseInt(court_id) }
      });
      
      if (!court) {
        return res.status(404).json({ success: false, error: 'Court not found' });
      }
      
      // Process each pricing update
      const results = [];
      
      for (const item of pricing) {
        const { slot_id, custom_price, is_active = true } = item;
        
        // Verify slot exists
        const slot = await prisma.pricing_slots.findUnique({
          where: { slot_id: parseInt(slot_id) }
        });
        
        if (!slot) {
          results.push({ slot_id, status: 'error', message: 'Slot not found' });
          continue;
        }
        
        try {
          // Check if pricing entry exists
          const existingPricing = await prisma.court_pricing.findUnique({
            where: {
              court_id_slot_id: {
                court_id: parseInt(court_id),
                slot_id: parseInt(slot_id)
              }
            }
          });
          
          if (existingPricing) {
            // Update existing
            const updated = await prisma.court_pricing.update({
              where: {
                court_id_slot_id: {
                  court_id: parseInt(court_id),
                  slot_id: parseInt(slot_id)
                }
              },
              data: {
                custom_price: custom_price !== undefined ? parseFloat(custom_price) : null,
                is_active: Boolean(is_active)
              }
            });
            
            results.push({ 
              slot_id, 
              status: 'updated', 
              data: updated 
            });
          } else {
            // Create new
            const created = await prisma.court_pricing.create({
              data: {
                court_id: parseInt(court_id),
                slot_id: parseInt(slot_id),
                custom_price: custom_price !== undefined ? parseFloat(custom_price) : null,
                is_active: Boolean(is_active)
              }
            });
            
            results.push({ 
              slot_id, 
              status: 'created', 
              data: created 
            });
          }
        } catch (error) {
          results.push({ 
            slot_id, 
            status: 'error', 
            message: error.message 
          });
        }
      }
      
      res.json({
        success: true,
        message: 'Court pricing updated successfully',
        data: results
      });
      
    } catch (error) {
      console.error('Update court pricing error:', error);
      res.status(500).json({ success: false, error: 'Failed to update court pricing' });
    }
  }
];

// [7] Tính giá cho booking
exports.calculateBookingPrice = async (req, res) => {
  try {
    const { court_id, start_time, end_time } = req.body;
    
    if (!court_id || !start_time || !end_time) {
      return res.status(400).json({ 
        success: false, 
        error: 'Court ID, start time and end time are required' 
      });
    }
    
    const start = new Date(start_time);
    const end = new Date(end_time);
    
    if (start >= end) {
      return res.status(400).json({ 
        success: false, 
        error: 'Start time must be before end time' 
      });
    }
    
    // Get court base price
    const court = await prisma.courts.findUnique({
      where: { court_id: parseInt(court_id) },
      select: { court_id: true, court_name: true, price_per_hour: true }
    });
    
    if (!court) {
      return res.status(404).json({ success: false, error: 'Court not found' });
    }
    
    const basePrice = Number(court.price_per_hour);
    
    // Get all active pricing slots
    const pricingSlots = await prisma.pricing_slots.findMany({
      where: { is_active: true },
      orderBy: { start_hour: 'asc' }
    });
    
    // Get custom pricing for this court
    const customPricing = await prisma.court_pricing.findMany({
      where: { 
        court_id: parseInt(court_id),
        is_active: true 
      },
      include: {
        pricing_slots: true
      }
    });
    
    // Calculate price by hour
    let totalPrice = 0;
    const hourDetails = [];
    
    const currentHour = new Date(start);
    currentHour.setMinutes(0, 0, 0);
    
    while (currentHour < end) {
      const hourStart = new Date(currentHour);
      const hourEnd = new Date(currentHour);
      hourEnd.setHours(hourEnd.getHours() + 1);
      
      // Determine which hours are actually booked
      const bookingStart = start > hourStart ? start : hourStart;
      const bookingEnd = end < hourEnd ? end : hourEnd;
      
      const hoursBooked = (bookingEnd - bookingStart) / (1000 * 60 * 60);
      
      if (hoursBooked > 0) {
        const dayOfWeek = hourStart.getDay(); // 0 = Sunday, 1 = Monday, etc.
        const hour = hourStart.getHours();
        
        // Determine day type
        let dayType = 'weekday';
        if (dayOfWeek === 0 || dayOfWeek === 6) {
          dayType = 'weekend';
        }
        // Note: holiday detection would need additional logic
        
        // Find applicable pricing slot
        let applicableSlot = pricingSlots.find(slot => 
          (slot.day_type === dayType || slot.day_type === 'all') &&
          hour >= slot.start_hour && hour < slot.end_hour
        );
        
        if (!applicableSlot) {
          applicableSlot = { multiplier: 1, is_peak: false };
        }
        
        // Check for custom price
        const customPrice = customPricing.find(cp => 
          cp.pricing_slots.slot_id === applicableSlot.slot_id
        );
        
        const multiplier = Number(applicableSlot.multiplier);
        const calculatedPrice = basePrice * multiplier;
        const finalPrice = customPrice?.custom_price 
          ? Number(customPrice.custom_price)
          : calculatedPrice;
        
        const hourPrice = finalPrice * hoursBooked;
        totalPrice += hourPrice;
        
        hourDetails.push({
          hour: `${hour.toString().padStart(2, '0')}:00`,
          day_type: dayType,
          is_peak: applicableSlot.is_peak,
          multiplier: multiplier,
          custom_price: customPrice?.custom_price ? Number(customPrice.custom_price) : null,
          price_per_hour: finalPrice,
          hours_booked: hoursBooked,
          subtotal: hourPrice
        });
      }
      
      currentHour.setHours(currentHour.getHours() + 1);
    }
    
    res.json({
      success: true,
      data: {
        court: {
          court_id: court.court_id,
          court_name: court.court_name,
          base_price: basePrice
        },
        booking_period: {
          start_time: start,
          end_time: end,
          total_hours: (end - start) / (1000 * 60 * 60)
        },
        calculation: {
          total_price: totalPrice,
          hour_details: hourDetails
        }
      }
    });
    
  } catch (error) {
    console.error('Calculate price error:', error);
    res.status(500).json({ success: false, error: 'Failed to calculate price' });
  }
};

// [8] Lấy báo giá mẫu
exports.getPricePreview = async (req, res) => {
  try {
    const { court_id } = req.params;
    
    const court = await prisma.courts.findUnique({
      where: { court_id: parseInt(court_id) },
      select: { court_id: true, court_name: true, price_per_hour: true }
    });
    
    if (!court) {
      return res.status(404).json({ success: false, error: 'Court not found' });
    }
    
    const basePrice = Number(court.price_per_hour);
    
    // Example time slots for preview
    const timeSlots = [
      { time: '08:00 - 10:00', day_type: 'weekday', is_peak: false },
      { time: '17:00 - 19:00', day_type: 'weekday', is_peak: true },
      { time: '20:00 - 22:00', day_type: 'weekday', is_peak: true },
      { time: '09:00 - 11:00', day_type: 'weekend', is_peak: false },
      { time: '15:00 - 17:00', day_type: 'weekend', is_peak: true }
    ];
    
    const pricingSlots = await prisma.pricing_slots.findMany({
      where: { is_active: true }
    });
    
    const customPricing = await prisma.court_pricing.findMany({
      where: { court_id: parseInt(court_id) },
      include: { pricing_slots: true }
    });
    
    const preview = timeSlots.map(slot => {
      // Find applicable multiplier
      const [startHour] = slot.time.split(' - ')[0].split(':').map(Number);
      let applicableSlot = pricingSlots.find(p => 
        (p.day_type === slot.day_type || p.day_type === 'all') &&
        startHour >= p.start_hour && startHour < p.end_hour
      );
      
      if (!applicableSlot) {
        applicableSlot = { multiplier: 1, is_peak: false };
      }
      
      // Check for custom price
      const customPrice = customPricing.find(cp => 
        cp.pricing_slots.slot_id === applicableSlot.slot_id
      );
      
      const multiplier = Number(applicableSlot.multiplier);
      const calculatedPrice = basePrice * multiplier;
      const finalPrice = customPrice?.custom_price 
        ? Number(customPrice.custom_price)
        : calculatedPrice;
      
      return {
        ...slot,
        base_price: basePrice,
        multiplier: multiplier,
        calculated_price: calculatedPrice,
        custom_price: customPrice?.custom_price ? Number(customPrice.custom_price) : null,
        final_price: finalPrice,
        is_custom: !!customPrice
      };
    });
    
    res.json({
      success: true,
      data: {
        court: {
          court_id: court.court_id,
          court_name: court.court_name,
          base_price: basePrice
        },
        preview
      }
    });
    
  } catch (error) {
    console.error('Price preview error:', error);
    res.status(500).json({ success: false, error: 'Failed to generate price preview' });
  }
};