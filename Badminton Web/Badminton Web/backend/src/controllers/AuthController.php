<?php
// controllers/AuthController.php
require_once 'config/database.php';

class AuthController {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error'] = 'Method not allowed';
            header('Location: index.php');
            exit;
        }
        
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = trim($_POST['role'] ?? 'customer');
        
        if (empty($username) || empty($password)) {
            $_SESSION['error'] = 'Tên đăng nhập và mật khẩu là bắt buộc';
            header('Location: index.php');
            exit;
        }
        
        $stmt = $this->pdo->prepare("SELECT user_id, username, full_name, password_hash, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $_SESSION['error'] = 'Tên đăng nhập hoặc mật khẩu không đúng';
            header('Location: index.php');
            exit;
        }
        
        // Kiểm tra role
        if ($role === 'owner' && $user['role'] !== 'owner') {
            $_SESSION['error'] = 'Tài khoản này không phải Chủ sân';
            header('Location: index.php');
            exit;
        }
        
        if ($role === 'customer' && $user['role'] === 'owner') {
            $_SESSION['error'] = 'Đây là tài khoản Chủ sân. Vui lòng dùng form "Đăng nhập Chủ sân"';
            header('Location: index.php');
            exit;
        }
        
        if (!password_verify($password, $user['password_hash'])) {
            $_SESSION['error'] = 'Tên đăng nhập hoặc mật khẩu không đúng';
            header('Location: index.php');
            exit;
        }
        
        // Đăng nhập thành công
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['user_role'] = $user['role'];
        
        $_SESSION['success'] = 'Đăng nhập thành công!';
        header('Location: index.php');
        exit;
    }
    
    public function register() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php');
            exit;
        }
        
        $fullname = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $phone = trim($_POST['phone_number'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($fullname) || empty($username) || empty($phone) || empty($password)) {
            $_SESSION['error'] = 'Tất cả các trường là bắt buộc';
            header('Location: index.php');
            exit;
        }
        
        // Kiểm tra tồn tại
        $stmt = $this->pdo->prepare("SELECT user_id FROM users WHERE username = ? OR phone_number = ?");
        $stmt->execute([$username, $phone]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = 'Tên đăng nhập hoặc số điện thoại đã tồn tại';
            header('Location: index.php');
            exit;
        }
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("INSERT INTO users (username, password_hash, full_name, phone_number, email, role) VALUES (?, ?, ?, ?, ?, 'customer')");
        
        if ($stmt->execute([$username, $hashedPassword, $fullname, $phone, $email])) {
            $_SESSION['success'] = 'Đăng ký thành công! Vui lòng đăng nhập.';
        } else {
            $_SESSION['error'] = 'Đăng ký thất bại';
        }
        
        header('Location: index.php');
        exit;
    }
    
    public function registerOwner() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php');
            exit;
        }
        
        $fullname = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $phone = trim($_POST['phone_number'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($fullname) || empty($username) || empty($phone) || empty($password)) {
            $_SESSION['error'] = 'Tất cả các trường là bắt buộc';
            header('Location: index.php');
            exit;
        }
        
        if (strlen($password) < 6) {
            $_SESSION['error'] = 'Mật khẩu phải có ít nhất 6 ký tự';
            header('Location: index.php');
            exit;
        }
        
        // Kiểm tra tồn tại
        $stmt = $this->pdo->prepare("SELECT user_id FROM users WHERE username = ? OR phone_number = ?");
        $stmt->execute([$username, $phone]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = 'Tên đăng nhập hoặc số điện thoại đã tồn tại';
            header('Location: index.php');
            exit;
        }
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("INSERT INTO users (username, password_hash, full_name, phone_number, email, role, user_balance, profile_image_url) VALUES (?, ?, ?, ?, NULLIF(?, ''), 'owner', 0, NULL)");
        
        if ($stmt->execute([$username, $hashedPassword, $fullname, $phone, $email])) {
            $_SESSION['success'] = 'Đăng ký chủ sân thành công! Vui lòng đăng nhập.';
        } else {
            $_SESSION['error'] = 'Đăng ký thất bại';
        }
        
        header('Location: index.php');
        exit;
    }
    
    public function logout() {
        session_destroy();
        header('Location: index.php');
        exit;
    }
}