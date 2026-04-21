<?php
require_once __DIR__ . '/../config/database.php';

// Handle login
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $message = 'Vui lòng nhập tên đăng nhập và mật khẩu';
        $messageType = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT user_id, username, full_name, password_hash, role FROM users WHERE username = ? AND role IN ('admin', 'staff')");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_start();
                $_SESSION['admin_user_id'] = $user['user_id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_full_name'] = $user['full_name'];
                $_SESSION['admin_role'] = $user['role'];

                header('Location: index.php');
                exit;
            } else {
                $message = 'Tên đăng nhập hoặc mật khẩu không đúng';
                $messageType = 'error';
            }
        } catch (Exception $e) {
            $message = 'Lỗi hệ thống: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Admin Quản lý Sân Cầu Lông</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom styles -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
        }

        .login-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .login-card {
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        .logo-glow {
            text-shadow: 0 0 20px rgba(99, 102, 241, 0.5);
        }

        .input-focus:focus {
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .btn-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .alert-shake {
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6b7280;
        }

        .password-toggle:hover {
            color: #374151;
        }
    </style>
</head>
<body class="login-bg min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div class="login-card bg-white rounded-2xl p-8">
            <div class="text-center">
                <div class="flex justify-center mb-6">
                    <div class="bg-gradient-to-r from-blue-600 to-purple-600 p-4 rounded-full">
                        <i class="fas fa-table-tennis-paddle-ball text-3xl text-white"></i>
                    </div>
                </div>
                <h2 class="text-3xl font-bold text-gray-900 mb-2">
                    Đăng nhập Admin
                </h2>
                <p class="text-gray-600 text-sm">
                    Quản lý hệ thống sân cầu lông
                </p>
            </div>

            <?php if ($message): ?>
            <div class="mt-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'; ?> alert-shake">
                <div class="flex items-center">
                    <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" class="mt-8 space-y-6">
                <input type="hidden" name="action" value="login">
                <div class="space-y-4">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-user mr-1"></i>Tên đăng nhập
                        </label>
                        <div class="relative">
                            <input
                                id="username"
                                name="username"
                                type="text"
                                required
                                value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                class="input-focus appearance-none rounded-lg relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                placeholder="Nhập tên đăng nhập"
                            >
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-lock mr-1"></i>Mật khẩu
                        </label>
                        <div class="relative">
                            <input
                                id="password"
                                name="password"
                                type="password"
                                required
                                class="input-focus appearance-none rounded-lg relative block w-full px-3 py-3 pr-10 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                placeholder="Nhập mật khẩu"
                            >
                            <div class="password-toggle" onclick="togglePassword()">
                                <i id="password-icon" class="fas fa-eye"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <button
                        type="submit"
                        class="btn-hover group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200"
                    >
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <i class="fas fa-sign-in-alt text-white group-hover:text-blue-100"></i>
                        </span>
                        <i class="fas fa-spinner fa-spin mr-2 hidden" id="loading-icon"></i>
                        Đăng nhập
                    </button>
                </div>

                <div class="text-center">
                    <a href="../index.php" class="text-sm text-blue-600 hover:text-blue-500 transition-colors duration-200">
                        <i class="fas fa-arrow-left mr-1"></i>Quay lại trang chủ
                    </a>
                </div>
            </form>
        </div>

        <div class="text-center">
            <p class="text-white text-sm opacity-80">
                © 2026 BadmintonPro Admin Panel. All rights reserved (Nhóm 10 thực hiện)
            </p>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('password-icon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                passwordIcon.className = 'fas fa-eye';
            }
        }

        // Show loading on form submit
        document.querySelector('form').addEventListener('submit', function() {
            const loadingIcon = document.getElementById('loading-icon');
            const submitButton = this.querySelector('button[type="submit"]');

            loadingIcon.classList.remove('hidden');
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Đang đăng nhập...';
        });

        // Auto-focus username field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });
    </script>
</body>
</html>