<?php
// controllers/BookingController.php

class BookingController {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function create() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php');
            exit;
        }
        
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['error'] = 'Vui lòng đăng nhập để đặt sân';
            header('Location: index.php');
            exit;
        }
        
        $court_id = (int)($_POST['court_id'] ?? 0);
        $booking_date = $_POST['booking_date'] ?? '';
        $start_time = $_POST['start_time'] ?? '';
        $duration = (float)($_POST['duration'] ?? 1);
        
        if (!$court_id || empty($booking_date) || empty($start_time)) {
            $_SESSION['error'] = 'Tất cả các trường đặt sân là bắt buộc';
            header('Location: index.php');
            exit;
        }
        
        $start_datetime = $booking_date . ' ' . $start_time;
        $end_datetime = date('Y-m-d H:i:s', strtotime($start_datetime . ' + ' . $duration . ' hours'));
        
        // Kiểm tra conflict
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as conflicts FROM bookings 
            WHERE court_id = ? AND status IN ('pending', 'confirmed') AND (
                (start_time <= ? AND end_time > ?) OR
                (start_time < ? AND end_time >= ?) OR
                (start_time >= ? AND end_time <= ?)
            )
        ");
        $stmt->execute([$court_id, $start_datetime, $start_datetime, $end_datetime, $end_datetime, $start_datetime, $end_datetime]);
        $conflict = $stmt->fetch();
        
        if ($conflict['conflicts'] > 0) {
            $_SESSION['error'] = 'Khung giờ này đã được đặt';
            header('Location: index.php');
            exit;
        }
        
        // Tính giá
        $stmt = $this->pdo->prepare("SELECT price_per_hour FROM courts WHERE court_id = ?");
        $stmt->execute([$court_id]);
        $court = $stmt->fetch();
        
        if (!$court) {
            $_SESSION['error'] = 'Sân không tồn tại';
            header('Location: index.php');
            exit;
        }
        
        $total_price = $court['price_per_hour'] * $duration;
        
        $stmt = $this->pdo->prepare("INSERT INTO bookings (user_id, court_id, start_time, end_time, total_price, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        
        if ($stmt->execute([$_SESSION['user_id'], $court_id, $start_datetime, $end_datetime, $total_price])) {
            $_SESSION['success'] = 'Đặt sân thành công!';
        } else {
            $_SESSION['error'] = 'Đặt sân thất bại';
        }
        
        header('Location: index.php');
        exit;
    }
}