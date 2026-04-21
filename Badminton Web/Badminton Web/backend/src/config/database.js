const { PrismaClient } = require('@prisma/client');
const dotenv = require('dotenv');

dotenv.config();

const globalForPrisma = global;

const prisma = globalForPrisma.prisma || new PrismaClient({
  log: process.env.NODE_ENV === 'development'
    ? ['query', 'info', 'warn', 'error']
    : ['error'],
});

if (process.env.NODE_ENV !== 'production') {
  globalForPrisma.prisma = prisma;
}

async function testDatabaseConnection() {
  try {
    await prisma.$connect();
    console.log('✅ Database connected successfully');
    return true;
  } catch (error) {
    console.error('❌ Database connection failed:', error);
    return false;
  }
}

process.on('beforeExit', async () => {
  console.log('Shutting down Prisma client...');
  await prisma.$disconnect();
});

module.exports = prisma;
module.exports.testDatabaseConnection = testDatabaseConnection;
