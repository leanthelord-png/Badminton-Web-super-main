const { PrismaClient } = require('@prisma/client');

const prisma = new PrismaClient();

async function main() {
  console.log('Seeding database...');

  // Create sample courts
  const courts = await prisma.courts.createMany({
    data: [
      {
        court_name: 'Court 1',
        court_type: 'Indoor',
        price_per_hour: 15.00,
        description: 'Premium indoor badminton court with professional lighting',
        is_active: true
      },
      {
        court_name: 'Court 2',
        court_type: 'Indoor',
        price_per_hour: 15.00,
        description: 'Premium indoor badminton court with professional lighting',
        is_active: true
      },
      {
        court_name: 'Court 3',
        court_type: 'Outdoor',
        price_per_hour: 12.00,
        description: 'Outdoor court available during daylight hours',
        is_active: true
      },
      {
        court_name: 'Court 4',
        court_type: 'Outdoor',
        price_per_hour: 12.00,
        description: 'Outdoor court available during daylight hours',
        is_active: true
      }
    ],
    skipDuplicates: true
  });

  // Create sample pricing slots
  const pricingSlots = await prisma.pricing_slots.createMany({
    data: [
      {
        slot_name: 'Morning (6AM-12PM)',
        start_hour: 6,
        end_hour: 12,
        day_type: 'weekday',
        multiplier: 1.0,
        is_peak: false,
        description: 'Standard morning rates',
        is_active: true
      },
      {
        slot_name: 'Afternoon (12PM-6PM)',
        start_hour: 12,
        end_hour: 18,
        day_type: 'weekday',
        multiplier: 1.2,
        is_peak: true,
        description: 'Peak afternoon rates',
        is_active: true
      },
      {
        slot_name: 'Evening (6PM-10PM)',
        start_hour: 18,
        end_hour: 22,
        day_type: 'weekday',
        multiplier: 1.5,
        is_peak: true,
        description: 'Peak evening rates',
        is_active: true
      },
      {
        slot_name: 'Weekend (6AM-10PM)',
        start_hour: 6,
        end_hour: 22,
        day_type: 'weekend',
        multiplier: 1.3,
        is_peak: false,
        description: 'Weekend rates',
        is_active: true
      }
    ],
    skipDuplicates: true
  });

  console.log('Database seeded successfully!');
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });