const fs = require('fs').promises;
const path = require('path');
const { exec } = require('child_process');
const util = require('util');
const execPromise = util.promisify(exec);

const prisma = require('../config/database');
const BACKUP_DIR = path.join(__dirname, '../../backups');

// Đảm bảo thư mục backup tồn tại
const ensureBackupDir = async () => {
  try {
    await fs.access(BACKUP_DIR);
  } catch {
    await fs.mkdir(BACKUP_DIR, { recursive: true });
  }
};

// [1] Tạo backup thủ công
exports.createBackup = async (req, res) => {
  try {
    await ensureBackupDir();
    
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    const backupName = `backup-${timestamp}`;
    const backupPath = path.join(BACKUP_DIR, backupName);
    
    // Create backup directory
    await fs.mkdir(backupPath);
    
    // Backup các bảng chính
    const backupData = {
      metadata: {
        created_at: new Date().toISOString(),
        created_by: req.user.user_id,
        version: '1.0'
      },
      users: await prisma.users.findMany({
        select: {
          user_id: true,
          username: true,
          full_name: true,
          email: true,
          phone_number: true,
          role: true,
          created_at: true
        }
      }),
      courts: await prisma.courts.findMany(),
      bookings: await prisma.bookings.findMany({
        include: {
          courts: {
            select: { court_name: true, court_type: true }
          },
          users: {
            select: { full_name: true, phone_number: true }
          },
          payments: true
        }
      }),
      payments: await prisma.payments.findMany(),
      pricing_slots: await prisma.pricing_slots.findMany(),
      court_pricing: await prisma.court_pricing.findMany()
    };
    
    // Lưu backup file
    const backupFile = path.join(backupPath, 'data.json');
    await fs.writeFile(backupFile, JSON.stringify(backupData, null, 2));
    
    // Tạo file thông tin
    const infoFile = path.join(backupPath, 'info.json');
    await fs.writeFile(infoFile, JSON.stringify({
      name: backupName,
      created_at: new Date().toISOString(),
      created_by: req.user.username,
      records: {
        users: backupData.users.length,
        courts: backupData.courts.length,
        bookings: backupData.bookings.length,
        payments: backupData.payments.length,
        pricing_slots: backupData.pricing_slots?.length || 0,
        court_pricing: backupData.court_pricing?.length || 0
      }
    }, null, 2));
    
    res.json({
      success: true,
      message: 'Backup created successfully',
      data: {
        name: backupName,
        path: backupPath,
        records: {
          users: backupData.users.length,
          courts: backupData.courts.length,
          bookings: backupData.bookings.length,
          payments: backupData.payments.length
        }
      }
    });
    
  } catch (error) {
    console.error('Create backup error:', error);
    res.status(500).json({ success: false, error: 'Failed to create backup' });
  }
};

// [2] Lấy danh sách backups
exports.getBackupList = async (req, res) => {
  try {
    await ensureBackupDir();
    
    const items = await fs.readdir(BACKUP_DIR, { withFileTypes: true });
    const backups = [];
    
    for (const item of items) {
      if (item.isDirectory()) {
        const backupPath = path.join(BACKUP_DIR, item.name);
        const infoPath = path.join(backupPath, 'info.json');
        
        try {
          const infoContent = await fs.readFile(infoPath, 'utf8');
          const info = JSON.parse(infoContent);
          backups.push(info);
        } catch {
          // Backup không có info file
          const stat = await fs.stat(backupPath);
          backups.push({
            name: item.name,
            created_at: stat.birthtime.toISOString(),
            created_by: 'Unknown',
            records: { users: 0, courts: 0, bookings: 0, payments: 0 }
          });
        }
      }
    }
    
    // Sắp xếp theo thời gian tạo (mới nhất trước)
    backups.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    
    res.json({
      success: true,
      data: {
        backups,
        total: backups.length,
        backup_dir: BACKUP_DIR
      }
    });
    
  } catch (error) {
    console.error('Get backup list error:', error);
    res.status(500).json({ success: false, error: 'Failed to get backup list' });
  }
};

// [3] Khôi phục từ backup
exports.restoreBackup = async (req, res) => {
  try {
    const { backup_name } = req.params;
    const { mode = 'safe' } = req.query; // safe or overwrite
    
    const backupPath = path.join(BACKUP_DIR, backup_name);
    const dataFile = path.join(backupPath, 'data.json');
    
    // Kiểm tra backup tồn tại
    try {
      await fs.access(dataFile);
    } catch {
      return res.status(404).json({ 
        success: false, 
        error: 'Backup not found or corrupted' 
      });
    }
    
    // Đọc dữ liệu backup
    const backupContent = await fs.readFile(dataFile, 'utf8');
    const backupData = JSON.parse(backupContent);
    
    // Trong chế độ safe, kiểm tra xem có dữ liệu hiện tại không
    if (mode === 'safe') {
      const existingCounts = {
        users: await prisma.users.count(),
        courts: await prisma.courts.count(),
        bookings: await prisma.bookings.count(),
        payments: await prisma.payments.count()
      };
      
      const hasData = Object.values(existingCounts).some(count => count > 0);
      
      if (hasData) {
        return res.status(400).json({
          success: false,
          error: 'Database contains existing data. Use mode=overwrite to force restore.',
          existing_data: existingCounts
        });
      }
    }
    
    // Bắt đầu transaction để restore
    let restoredCounts = {
      users: 0,
      courts: 0,
      bookings: 0,
      payments: 0,
      pricing_slots: 0,
      court_pricing: 0
    };
    
    try {
      // Xóa dữ liệu cũ nếu mode là overwrite
      if (mode === 'overwrite') {
        await prisma.$transaction([
          prisma.payments.deleteMany(),
          prisma.bookings.deleteMany(),
          prisma.courts.deleteMany(),
          prisma.users.deleteMany(),
          prisma.court_pricing.deleteMany?.(),
          prisma.pricing_slots.deleteMany?.()
        ].filter(Boolean));
      }
      
      // Restore users (cần recreate vì password_hash)
      if (backupData.users && backupData.users.length > 0) {
        // Note: Không thể restore password_hash, cần reset password
        const usersToCreate = backupData.users.map(user => ({
          username: user.username,
          password_hash: '$2b$10$defaultpasswordhashforrestoredusers', // Default hash
          full_name: user.full_name,
          email: user.email,
          phone_number: user.phone_number,
          role: user.role,
          created_at: new Date(user.created_at)
        }));
        
        await prisma.users.createMany({
          data: usersToCreate,
          skipDuplicates: true
        });
        
        restoredCounts.users = usersToCreate.length;
      }
      
      // Restore courts
      if (backupData.courts && backupData.courts.length > 0) {
        const courtsToCreate = backupData.courts.map(court => ({
          court_id: court.court_id, // Giữ nguyên ID nếu có thể
          court_name: court.court_name,
          court_type: court.court_type,
          price_per_hour: court.price_per_hour,
          is_active: court.is_active,
          description: court.description,
          image_url: court.image_url
        }));
        
        await prisma.courts.createMany({
          data: courtsToCreate,
          skipDuplicates: true
        });
        
        restoredCounts.courts = courtsToCreate.length;
      }
      
      // Restore pricing slots (nếu có)
      if (backupData.pricing_slots && backupData.pricing_slots.length > 0) {
        const slotsToCreate = backupData.pricing_slots.map(slot => ({
          slot_id: slot.slot_id,
          slot_name: slot.slot_name,
          start_hour: slot.start_hour,
          end_hour: slot.end_hour,
          day_type: slot.day_type,
          multiplier: slot.multiplier,
          is_peak: slot.is_peak,
          description: slot.description,
          is_active: slot.is_active,
          created_at: new Date(slot.created_at)
        }));
        
        await prisma.pricing_slots.createMany({
          data: slotsToCreate,
          skipDuplicates: true
        });
        
        restoredCounts.pricing_slots = slotsToCreate.length;
      }
      
      // Restore court pricing (nếu có)
      if (backupData.court_pricing && backupData.court_pricing.length > 0) {
        const pricingToCreate = backupData.court_pricing.map(cp => ({
          court_id: cp.court_id,
          slot_id: cp.slot_id,
          custom_price: cp.custom_price,
          is_active: cp.is_active,
          created_at: new Date(cp.created_at)
        }));
        
        await prisma.court_pricing.createMany({
          data: pricingToCreate,
          skipDuplicates: true
        });
        
        restoredCounts.court_pricing = pricingToCreate.length;
      }
      
      // Restore bookings
      if (backupData.bookings && backupData.bookings.length > 0) {
        const bookingsToCreate = backupData.bookings.map(booking => ({
          booking_id: booking.booking_id,
          user_id: booking.user_id,
          court_id: booking.court_id,
          start_time: new Date(booking.start_time),
          end_time: new Date(booking.end_time),
          total_price: booking.total_price,
          status: booking.status,
          created_at: new Date(booking.created_at)
        }));
        
        await prisma.bookings.createMany({
          data: bookingsToCreate,
          skipDuplicates: true
        });
        
        restoredCounts.bookings = bookingsToCreate.length;
      }
      
      // Restore payments
      if (backupData.payments && backupData.payments.length > 0) {
        const paymentsToCreate = backupData.payments.map(payment => ({
          payment_id: payment.payment_id,
          booking_id: payment.booking_id,
          amount_paid: payment.amount_paid,
          payment_date: payment.payment_date ? new Date(payment.payment_date) : null,
          payment_method: payment.payment_method,
          transaction_reference: payment.transaction_reference
        }));
        
        await prisma.payments.createMany({
          data: paymentsToCreate,
          skipDuplicates: true
        });
        
        restoredCounts.payments = paymentsToCreate.length;
      }
      
      res.json({
        success: true,
        message: 'Backup restored successfully',
        data: {
          backup_name,
          mode,
          restored: restoredCounts
        }
      });
      
    } catch (restoreError) {
      console.error('Restore transaction error:', restoreError);
      res.status(500).json({ 
        success: false, 
        error: 'Restore failed. Database may be in inconsistent state.',
        details: restoreError.message 
      });
    }
    
  } catch (error) {
    console.error('Restore backup error:', error);
    res.status(500).json({ success: false, error: 'Failed to restore backup' });
  }
};

// [4] Xóa backup
exports.deleteBackup = async (req, res) => {
  try {
    const { backup_name } = req.params;
    
    const backupPath = path.join(BACKUP_DIR, backup_name);
    
    // Kiểm tra backup tồn tại
    try {
      await fs.access(backupPath);
    } catch {
      return res.status(404).json({ 
        success: false, 
        error: 'Backup not found' 
      });
    }
    
    // Xóa thư mục backup
    await fs.rm(backupPath, { recursive: true, force: true });
    
    res.json({
      success: true,
      message: 'Backup deleted successfully',
      data: { backup_name }
    });
    
  } catch (error) {
    console.error('Delete backup error:', error);
    res.status(500).json({ success: false, error: 'Failed to delete backup' });
  }
};

// [5] Tạo backup database MySQL (mysqldump)
exports.createDatabaseBackup = async (req, res) => {
  try {
    await ensureBackupDir();

    const databaseUrl = process.env.DATABASE_URL;
    if (!databaseUrl) {
      return res.status(500).json({ success: false, error: 'DATABASE_URL not configured' });
    }

    const url = new URL(databaseUrl);
    const dbName = url.pathname.substring(1);
    const dbUser = decodeURIComponent(url.username);
    const dbPass = decodeURIComponent(url.password);
    const dbHost = url.hostname;
    const dbPort = url.port || 3306;

    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    const backupFile = path.join(BACKUP_DIR, `db-backup-${timestamp}.sql`);
    const passwordPart = dbPass ? `-p"${dbPass}" ` : '';
    const dumpCmd = `mysqldump -h ${dbHost} -P ${dbPort} -u ${dbUser} ${passwordPart}${dbName} > "${backupFile}"`;

    try {
      await execPromise(dumpCmd);
      const stats = await fs.stat(backupFile);

      res.json({
        success: true,
        message: 'Database backup created successfully',
        data: {
          filename: `db-backup-${timestamp}.sql`,
          path: backupFile,
          size: (stats.size / 1024 / 1024).toFixed(2) + ' MB',
          created_at: new Date().toISOString()
        }
      });
    } catch (execError) {
      console.error('mysqldump error:', execError);
      res.status(500).json({ success: false, error: 'Failed to execute mysqldump. Make sure MySQL client tools are installed and accessible.' });
    }
  } catch (error) {
    console.error('Database backup error:', error);
    res.status(500).json({ success: false, error: 'Failed to create database backup' });
  }
};

// [6] Lấy thông tin backup
exports.getBackupInfo = async (req, res) => {
  try {
    const { backup_name } = req.params;
    
    const backupPath = path.join(BACKUP_DIR, backup_name);
    const infoPath = path.join(backupPath, 'info.json');
    const dataPath = path.join(backupPath, 'data.json');
    
    try {
      const [infoContent, stats] = await Promise.all([
        fs.readFile(infoPath, 'utf8'),
        fs.stat(dataPath)
      ]);
      
      const info = JSON.parse(infoContent);
      
      res.json({
        success: true,
        data: {
          ...info,
          file_size: (stats.size / 1024 / 1024).toFixed(2) + ' MB',
          path: backupPath
        }
      });
      
    } catch {
      return res.status(404).json({ 
        success: false, 
        error: 'Backup info not found' 
      });
    }
    
  } catch (error) {
    console.error('Get backup info error:', error);
    res.status(500).json({ success: false, error: 'Failed to get backup info' });
  }
};

// [7] Tự động hóa backup (cron job)
exports.scheduleAutoBackup = async (req, res) => {
  try {
    const { frequency, keep_last = 10 } = req.body; // daily, weekly, monthly
    
    // Lưu cấu hình backup tự động
    const config = {
      frequency,
      keep_last: parseInt(keep_last),
      enabled: true,
      last_run: null,
      next_run: calculateNextRun(frequency)
    };
    
    const configPath = path.join(BACKUP_DIR, 'auto-backup-config.json');
    await fs.writeFile(configPath, JSON.stringify(config, null, 2));
    
    // Ghi nhận vào log
    const logPath = path.join(BACKUP_DIR, 'backup-log.json');
    let log = [];
    
    try {
      const logContent = await fs.readFile(logPath, 'utf8');
      log = JSON.parse(logContent);
    } catch {
      // File log chưa tồn tại
    }
    
    log.push({
      action: 'schedule_auto_backup',
      frequency,
      keep_last,
      scheduled_by: req.user.username,
      scheduled_at: new Date().toISOString()
    });
    
    await fs.writeFile(logPath, JSON.stringify(log, null, 2));
    
    res.json({
      success: true,
      message: `Auto-backup scheduled (${frequency}). Keeping last ${keep_last} backups.`,
      data: config
    });
    
  } catch (error) {
    console.error('Schedule auto-backup error:', error);
    res.status(500).json({ success: false, error: 'Failed to schedule auto-backup' });
  }
};

// Hàm helper tính thời gian chạy tiếp theo
function calculateNextRun(frequency) {
  const now = new Date();
  const next = new Date(now);
  
  switch (frequency) {
    case 'daily':
      next.setDate(next.getDate() + 1);
      next.setHours(2, 0, 0, 0); // 2:00 AM
      break;
    case 'weekly':
      next.setDate(next.getDate() + 7);
      next.setHours(2, 0, 0, 0);
      break;
    case 'monthly':
      next.setMonth(next.getMonth() + 1);
      next.setHours(2, 0, 0, 0);
      break;
    default:
      next.setDate(next.getDate() + 1);
      next.setHours(2, 0, 0, 0);
  }
  
  return next.toISOString();
}