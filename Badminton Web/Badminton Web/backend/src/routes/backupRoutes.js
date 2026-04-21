const express = require('express');
const router = express.Router();
const backupController = require('../controllers/backupController');

// Quản lý backup
router.get('/', backupController.getBackupList);
router.post('/', backupController.createBackup);
router.post('/database', backupController.createDatabaseBackup);
router.post('/schedule', backupController.scheduleAutoBackup);

// Backup cụ thể
router.get('/:backup_name', backupController.getBackupInfo);
router.post('/:backup_name/restore', backupController.restoreBackup);
router.delete('/:backup_name', backupController.deleteBackup);

module.exports = router;