/**
 * Badminton Web - Main Server
 * This is the main server that provides APIs for user authentication, court booking, and more
 */

const express = require('express');
const cors = require('cors');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const { PrismaClient } = require('@prisma/client');
const path = require('path');

// ========== CHATBOT IMPORTS ==========
const chatbotIntent = require('./chatbot/intentHandler');
const courtService = require('./chatbot/courtService');
const zaloService = require('./chatbot/zaloService');
const responseTemplates = require('./chatbot/responseTemplates');

const app = express();
const PORT = 3000;
const JWT_SECRET = 'badminton-user-jwt-secret-dev'; // Change in production

// Initialize Prisma
const prisma = new PrismaClient();

// Middleware
app.use(cors());
app.use(express.json());

// Serve static files for admin frontend
app.use('/admin', express.static(path.join(__dirname, 'admin_frontend')));

// Serve admin index.php for any admin route
app.get('/admin/*', (req, res) => {
    res.sendFile(path.join(__dirname, 'admin_frontend', 'index.php'));
});

// Authentication middleware
const authenticate = (req, res, next) => {
    const token = req.header('Authorization')?.replace('Bearer ', '');
    if (!token) {
        return res.status(401).json({ message: 'Access denied. No token provided.' });
    }

    try {
        const decoded = jwt.verify(token, JWT_SECRET);
        req.user = decoded;
        next();
    } catch (error) {
        res.status(400).json({ message: 'Invalid token.' });
    }
};

// Routes

// Health check
app.get('/api/health', async (req, res) => {
    try {
        await prisma.$queryRaw`SELECT 1`;
        res.json({ status: 'success', message: 'Database connection successful' });
    } catch (error) {
        res.status(500).json({ status: 'error', message: 'Database connection failed' });
    }
});

// Auth routes
app.post('/api/auth/register', async (req, res) => {
    try {
        const { username, password, full_name, phone_number, email } = req.body;

        // Check if user exists
        const existingUser = await prisma.users.findFirst({
            where: {
                OR: [
                    { username },
                    { phone_number },
                    ...(email ? [{ email }] : [])
                ]
            }
        });

        if (existingUser) {
            return res.status(400).json({ message: 'User already exists' });
        }

        // Hash password
        const salt = await bcrypt.genSalt(10);
        const password_hash = await bcrypt.hash(password, salt);

        // Create user
        const user = await prisma.users.create({
            data: {
                username,
                password_hash,
                full_name,
                phone_number,
                email
            },
            select: {
                user_id: true,
                username: true,
                full_name: true,
                phone_number: true,
                email: true,
                role: true,
                created_at: true
            }
        });

        res.status(201).json({ message: 'User registered successfully', user });
    } catch (error) {
        console.error('Registration error:', error);
        res.status(500).json({ message: 'Registration failed' });
    }
});

app.post('/api/auth/login', async (req, res) => {
    try {
        const { username, password } = req.body;

        // Find user
        const user = await prisma.users.findUnique({
            where: { username }
        });

        if (!user) {
            return res.status(400).json({ message: 'Invalid credentials' });
        }

        // Check password
        const validPassword = await bcrypt.compare(password, user.password_hash);
        if (!validPassword) {
            return res.status(400).json({ message: 'Invalid credentials' });
        }

        // Generate token
        const token = jwt.sign(
            { user_id: user.user_id, username: user.username, role: user.role },
            JWT_SECRET,
            { expiresIn: '24h' }
        );

        const userResponse = {
            user_id: user.user_id,
            username: user.username,
            full_name: user.full_name,
            phone_number: user.phone_number,
            email: user.email,
            role: user.role
        };

        res.json({ message: 'Login successful', token, user: userResponse });
    } catch (error) {
        console.error('Login error:', error);
        res.status(500).json({ message: 'Login failed' });
    }
});

app.get('/api/auth/me', authenticate, async (req, res) => {
    try {
        const user = await prisma.users.findUnique({
            where: { user_id: req.user.user_id },
            select: {
                user_id: true,
                username: true,
                full_name: true,
                phone_number: true,
                email: true,
                role: true
            }
        });

        if (!user) {
            return res.status(404).json({ message: 'User not found' });
        }

        res.json(user);
    } catch (error) {
        console.error('Get user error:', error);
        res.status(500).json({ message: 'Failed to get user info' });
    }
});

// Courts routes
app.get('/api/courts', async (req, res) => {
    try {
        const courts = await prisma.courts.findMany({
            where: { is_active: true },
            select: {
                court_id: true,
                court_name: true,
                court_type: true,
                price_per_hour: true,
                description: true,
                image_url: true,
                is_active: true
            }
        });

        res.json(courts);
    } catch (error) {
        console.error('Get courts error:', error);
        res.status(500).json({ message: 'Failed to get courts' });
    }
});

// ========== BOOKINGS ROUTES (ĐÃ THÊM GỬI ZALO) ==========

// Get user bookings
app.get('/api/bookings', authenticate, async (req, res) => {
    try {
        const bookings = await prisma.bookings.findMany({
            where: { user_id: req.user.user_id },
            include: {
                courts: {
                    select: {
                        court_name: true,
                        price_per_hour: true
                    }
                }
            },
            orderBy: { created_at: 'desc' }
        });

        res.json(bookings);
    } catch (error) {
        console.error('Get bookings error:', error);
        res.status(500).json({ message: 'Failed to get bookings' });
    }
});

// Create booking (ĐÃ THÊM GỬI ZALO)
app.post('/api/bookings', authenticate, async (req, res) => {
    try {
        const { court_id, start_time, end_time } = req.body;

        // Check if court exists and is active
        const court = await prisma.courts.findFirst({
            where: { court_id, is_active: true }
        });

        if (!court) {
            return res.status(400).json({ message: 'Court not found or unavailable' });
        }

        // Check for conflicting bookings
        const conflictingBooking = await prisma.bookings.findFirst({
            where: {
                court_id,
                status: { in: ['pending', 'confirmed'] },
                OR: [
                    {
                        AND: [
                            { start_time: { lte: new Date(start_time) } },
                            { end_time: { gt: new Date(start_time) } }
                        ]
                    },
                    {
                        AND: [
                            { start_time: { lt: new Date(end_time) } },
                            { end_time: { gte: new Date(end_time) } }
                        ]
                    }
                ]
            }
        });

        if (conflictingBooking) {
            return res.status(400).json({ message: 'Court is already booked for this time' });
        }

        // Calculate total price
        const start = new Date(start_time);
        const end = new Date(end_time);
        const hours = (end - start) / (1000 * 60 * 60);
        const total_price = hours * court.price_per_hour;

        // Create booking
        const booking = await prisma.bookings.create({
            data: {
                user_id: req.user.user_id,
                court_id,
                start_time: start,
                end_time: end,
                total_price
            },
            include: {
                courts: {
                    select: {
                        court_name: true,
                        price_per_hour: true,
                        address: true
                    }
                }
            }
        });

        // ========== GỬI XÁC NHẬN ZALO SAU KHI ĐẶT SÂN THÀNH CÔNG ==========
        try {
            // Lấy thông tin user
            const user = await prisma.users.findUnique({
                where: { user_id: req.user.user_id },
                select: {
                    user_id: true,
                    phone_number: true,
                    full_name: true,
                    zalo_followed: true
                }
            });
            
            // Kiểm tra nếu user đã follow Zalo OA
            if (user && user.zalo_followed === true) {
                const startTimeFormatted = start.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
                const endTimeFormatted = end.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
                const dateFormatted = start.toLocaleDateString('vi-VN');
                
                const confirmationMessage = `✅ XÁC NHẬN ĐẶT SÂN THÀNH CÔNG

🏸 Sân: ${booking.courts.court_name}
📅 Ngày: ${dateFormatted}
⏰ Giờ: ${startTimeFormatted} - ${endTimeFormatted}
💰 Giá: ${total_price.toLocaleString('vi-VN')}đ

Mã đơn: #${booking.booking_id}

Cảm ơn ${user.full_name || 'bạn'} đã sử dụng dịch vụ BadmintonPro! 🏸
💡 Hãy đến đúng giờ nhé!`;

                const zaloResult = await zaloService.sendZaloMessage(user.phone_number, confirmationMessage);
                
                if (zaloResult.success) {
                    console.log(`✅ Zalo confirmation sent to ${user.phone_number} for booking #${booking.booking_id}`);
                } else {
                    console.log(`⚠️ Failed to send Zalo confirmation: ${zaloResult.error}`);
                }
            } else {
                console.log(`ℹ️ User ${req.user.user_id} has not followed Zalo OA, skipping Zalo notification`);
            }
        } catch (zaloError) {
            console.error('Zalo confirmation error:', zaloError.message);
        }
        // ========== KẾT THÚC GỬI ZALO ==========

        res.status(201).json({ 
            message: 'Booking created successfully', 
            booking: {
                booking_id: booking.booking_id,
                court_name: booking.courts.court_name,
                start_time: booking.start_time,
                end_time: booking.end_time,
                total_price: booking.total_price,
                status: booking.status
            }
        });
        
    } catch (error) {
        console.error('Create booking error:', error);
        res.status(500).json({ message: 'Failed to create booking' });
    }
});

// Database inspection routes
app.get('/api/tables', async (req, res) => {
    try {
        const tables = await prisma.$queryRaw`
            SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = 'public'
            ORDER BY table_name
        `;
        res.json(tables.map(row => row.table_name));
    } catch (error) {
        res.status(500).json({ message: 'Failed to fetch tables' });
    }
});

app.get('/api/tables/:tableName/schema', async (req, res) => {
    try {
        const { tableName } = req.params;

        if (!/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(tableName)) {
            return res.status(400).json({
                status: 'error',
                message: 'Invalid table name',
            });
        }

        const result = await prisma.$queryRaw`
            SELECT
                column_name,
                data_type,
                is_nullable,
                column_default
            FROM information_schema.columns
            WHERE table_name = ${tableName}
            AND table_schema = 'public'
            ORDER BY ordinal_position
        `;

        res.json(result);
    } catch (error) {
        console.error('Error fetching table schema:', error);
        res.status(500).json({
            status: 'error',
            message: 'Failed to fetch table schema',
            error: error.message,
        });
    }
});

app.get('/api/tables/:tableName/data', async (req, res) => {
    try {
        const { tableName } = req.params;
        const limit = parseInt(req.query.limit) || 100;
        const offset = parseInt(req.query.offset) || 0;

        if (!/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(tableName)) {
            return res.status(400).json({
                status: 'error',
                message: 'Invalid table name',
            });
        }

        const result = await prisma.$queryRaw`
            SELECT * FROM ${tableName}
            LIMIT ${limit} OFFSET ${offset}
        `;

        res.json({
            table: tableName,
            count: result.length,
            data: result,
        });
    } catch (error) {
        console.error('Error fetching table data:', error);
        res.status(500).json({
            status: 'error',
            message: 'Failed to fetch table data',
            error: error.message,
        });
    }
});

app.post('/api/query', async (req, res) => {
    try {
        const { query } = req.body;

        if (!query) {
            return res.status(400).json({
                status: 'error',
                message: 'Query parameter is required',
            });
        }

        const result = await prisma.$queryRaw(query);

        res.json({
            status: 'success',
            rows: result,
            rowCount: result.length,
        });
    } catch (error) {
        console.error('Error executing query:', error);
        res.status(500).json({
            status: 'error',
            message: 'Query execution failed',
            error: error.message,
        });
    }
});

// ========== CHATBOT API ==========

// Chatbot endpoint
app.post('/api/chatbot/message', authenticate, async (req, res) => {
    try {
        const { message } = req.body;
        const userId = req.user.user_id;
        
        if (!message) {
            return res.status(400).json({ response: "Vui lòng nhập tin nhắn." });
        }
        
        const intent = chatbotIntent.detectIntent(message);
        let response;
        
        switch(intent) {
            case 'greeting':
                response = responseTemplates.greeting;
                break;
                
            case 'find_court':
                const time = chatbotIntent.extractTime(message) || '19:00';
                const date = chatbotIntent.extractDate(message) || new Date().toISOString().split('T')[0];
                const result = await courtService.findAvailableCourts(date, time);
                response = result.message;
                break;
                
            case 'check_booking':
                const bookings = await prisma.bookings.findMany({
                    where: { user_id: userId },
                    include: { courts: true },
                    orderBy: { created_at: 'desc' },
                    take: 5
                });
                response = responseTemplates.formatBookings(bookings);
                break;
                
            case 'suggestion':
                response = await courtService.getSmartSuggestion(userId);
                break;
                
            case 'price':
                response = responseTemplates.priceList;
                break;
                
            case 'guide':
            case 'help':
                response = responseTemplates.help;
                break;
                
            default:
                response = responseTemplates.fallback;
        }
        
        // Lưu log chat
        try {
            await prisma.$executeRaw`
                INSERT INTO chat_logs (user_id, message, intent, response, created_at)
                VALUES (${userId}, ${message}, ${intent}, ${response}, NOW())
            `;
        } catch (logError) {
            console.log('Chat log error:', logError.message);
        }
        
        res.json({ intent, response });
        
    } catch (error) {
        console.error('Chatbot error:', error);
        res.status(500).json({ response: "Xin lỗi, đã có lỗi xảy ra. Vui lòng thử lại sau." });
    }
});

// 404 Handler
app.use((req, res) => {
    res.status(404).json({
        status: 'error',
        message: 'Endpoint not found',
        path: req.path,
    });
});

// Error Handler
app.use((err, req, res, next) => {
    console.error('Server error:', err);
    res.status(500).json({
        status: 'error',
        message: 'Internal server error',
        error: err.message,
    });
});

// Start server
app.listen(PORT, () => {
    console.log(`🏸 Badminton Web Server is running on http://localhost:${PORT}`);
    console.log(`📡 API Base: http://localhost:${PORT}/api`);
    console.log(`🤖 Chatbot API: http://localhost:${PORT}/api/chatbot/message`);
    console.log(`💬 Zalo integration: ${process.env.ZALO_OA_TOKEN ? 'ENABLED' : 'DISABLED (no token)'}`);
});

// Graceful shutdown
process.on('SIGTERM', async () => {
    console.log('SIGTERM signal received: closing server');
    await prisma.$disconnect();
    process.exit(0);
});

process.on('SIGINT', async () => {
    console.log('SIGINT signal received: closing server');
    await prisma.$disconnect();
    process.exit(0);
});