const express = require('express');
const router = express.Router();
const userController = require('../controllers/userController');

// GET /api/admin/users - Lấy danh sách người dùng
router.get('/', userController.getAllUsers);

// GET /api/admin/users/stats - Thống kê người dùng
router.get('/stats', userController.getUserStats);

// GET /api/admin/users/search - Tìm kiếm người dùng
router.get('/search', userController.searchUsers);

// GET /api/admin/users/:id - Lấy chi tiết người dùng
router.get('/:id', userController.getUserById);

// POST /api/admin/users - Tạo người dùng mới
router.post('/', userController.createUser);

// PUT /api/admin/users/:id - Cập nhật người dùng
router.put('/:id', userController.updateUser);

// DELETE /api/admin/users/:id - Xóa người dùng
router.delete('/:id', userController.deleteUser);

// PATCH /api/admin/users/:id/role - Phân quyền người dùng
router.patch('/:id/role', userController.changeUserRole);

module.exports = router;