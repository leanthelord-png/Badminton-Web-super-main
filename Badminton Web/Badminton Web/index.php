<?php
// index.php - Badminton Web Main Page - NÂNG CẤP V2.0
require_once 'config/database.php';
session_start();

// ========== HELPER FUNCTIONS ==========
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function sendJSONResponse($success, $message, $data = null) {
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

// Handle AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_available_slots':
            $court_id = (int)($_POST['court_id'] ?? 0);
            $date = $_POST['date'] ?? date('Y-m-d');
            
            if (!$court_id) {
                sendJSONResponse(false, 'Invalid court ID');
            }
            
            try {
                $stmt = $pdo->prepare("
                    SELECT start_time, end_time FROM bookings 
                    WHERE court_id = ? AND DATE(start_time) = ? AND status IN ('pending', 'confirmed', 'paid')
                ");
                $stmt->execute([$court_id, $date]);
                $bookings = $stmt->fetchAll();
                
                $bookedSlots = [];
                foreach ($bookings as $booking) {
                    $startHour = date('H', strtotime($booking['start_time']));
                    $bookedSlots[] = $startHour;
                }
                
                $availableSlots = [];
                for ($i = 6; $i <= 21; $i++) {
                    if (!in_array($i, $bookedSlots)) {
                        $availableSlots[] = sprintf("%02d:00", $i);
                    }
                }
                
                sendJSONResponse(true, 'Success', ['slots' => $availableSlots]);
            } catch (Exception $e) {
                sendJSONResponse(false, $e->getMessage());
            }
            break;
            
        case 'get_court_details':
            $court_id = (int)($_GET['court_id'] ?? 0);
            if (!$court_id) {
                sendJSONResponse(false, 'Invalid court ID');
            }
            
            try {
                $stmt = $pdo->prepare("
                    SELECT c.*, u.full_name as owner_name, u.phone_number as owner_phone
                    FROM courts c
                    LEFT JOIN users u ON c.owner_id = u.user_id
                    WHERE c.court_id = ? AND c.is_active = 1
                ");
                $stmt->execute([$court_id]);
                $court = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($court) {
                    $stmt = $pdo->prepare("
                        SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews
                        FROM court_reviews
                        WHERE court_id = ?
                    ");
                    $stmt->execute([$court_id]);
                    $reviews = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $court['avg_rating'] = round($reviews['avg_rating'] ?? 0, 1);
                    $court['total_reviews'] = $reviews['total_reviews'] ?? 0;
                    
                    sendJSONResponse(true, 'Success', ['court' => $court]);
                } else {
                    sendJSONResponse(false, 'Court not found');
                }
            } catch (Exception $e) {
                sendJSONResponse(false, $e->getMessage());
            }
            break;
            
        case 'toggle_favorite':
            if (!isset($_SESSION['user_id'])) {
                sendJSONResponse(false, 'Vui lòng đăng nhập');
            }
            
            $court_id = (int)($_POST['court_id'] ?? 0);
            $user_id = $_SESSION['user_id'];
            
            try {
                $stmt = $pdo->prepare("SELECT id FROM user_favorites WHERE user_id = ? AND court_id = ?");
                $stmt->execute([$user_id, $court_id]);
                $exists = $stmt->fetch();
                
                if ($exists) {
                    $stmt = $pdo->prepare("DELETE FROM user_favorites WHERE user_id = ? AND court_id = ?");
                    $stmt->execute([$user_id, $court_id]);
                    sendJSONResponse(true, 'Đã xóa khỏi yêu thích', ['favorited' => false]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO user_favorites (user_id, court_id) VALUES (?, ?)");
                    $stmt->execute([$user_id, $court_id]);
                    sendJSONResponse(true, 'Đã thêm vào yêu thích', ['favorited' => true]);
                }
            } catch (Exception $e) {
                sendJSONResponse(false, $e->getMessage());
            }
            break;
            
        case 'get_favorites':
            if (!isset($_SESSION['user_id'])) {
                sendJSONResponse(false, 'Vui lòng đăng nhập');
            }
            
            $user_id = $_SESSION['user_id'];
            
            try {
                $stmt = $pdo->prepare("
                    SELECT c.* FROM courts c
                    INNER JOIN user_favorites uf ON c.court_id = uf.court_id
                    WHERE uf.user_id = ? AND c.is_active = 1
                ");
                $stmt->execute([$user_id]);
                $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                sendJSONResponse(true, 'Success', ['favorites' => $favorites]);
            } catch (Exception $e) {
                sendJSONResponse(false, $e->getMessage());
            }
            break;
    }
    exit;
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $message = 'Lỗi bảo mật, vui lòng thử lại';
        $messageType = 'error';
    } elseif (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'register':
                $fullname = sanitizeInput($_POST['full_name'] ?? '');
                $username = sanitizeInput($_POST['username'] ?? '');
                $phone = sanitizeInput($_POST['phone_number'] ?? '');
                $email = sanitizeInput($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';

                if (empty($fullname) || empty($username) || empty($phone) || empty($password)) {
                    $message = 'Tất cả các trường là bắt buộc';
                    $messageType = 'error';
                } elseif (strlen($password) < 6) {
                    $message = 'Mật khẩu phải có ít nhất 6 ký tự';
                    $messageType = 'error';
                } else {
                    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? OR phone_number = ?");
                    $stmt->execute([$username, $phone]);
                    if ($stmt->fetch()) {
                        $message = 'Tên đăng nhập hoặc số điện thoại đã tồn tại';
                        $messageType = 'error';
                    } else {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, phone_number, email, role, user_balance, created_at) VALUES (?, ?, ?, ?, ?, 'customer', 0, NOW())");
                        if ($stmt->execute([$username, $hashedPassword, $fullname, $phone, $email ?: null])) {
                            $newUserId = $pdo->lastInsertId();
                            
                            $stmtWallet = $pdo->prepare("
                                INSERT INTO user_wallets (user_id, balance, frozen_balance, total_recharge, total_spent, updated_at)
                                VALUES (?, 0, 0, 0, 0, NOW())
                            ");
                            $stmtWallet->execute([$newUserId]);
                            
                            $message = 'Đăng ký thành công! Vui lòng đăng nhập.';
                            $messageType = 'success';
                        } else {
                            $message = 'Đăng ký thất bại';
                            $messageType = 'error';
                        }
                    }
                }
                break;

            case 'login':
                $username = sanitizeInput($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                $role = sanitizeInput($_POST['role'] ?? 'customer');

                if (empty($username) || empty($password)) {
                    $message = 'Tên đăng nhập và mật khẩu là bắt buộc';
                    $messageType = 'error';
                } else {
                    $stmt = $pdo->prepare("SELECT user_id, username, full_name, password_hash, role, user_balance, is_active FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    $user = $stmt->fetch();

                    if (!$user) {
                        $message = 'Tên đăng nhập hoặc mật khẩu không đúng';
                        $messageType = 'error';
                    } elseif ($user['is_active'] == 0) {
                        $message = 'Tài khoản đã bị khóa. Vui lòng liên hệ hỗ trợ.';
                        $messageType = 'error';
                    } elseif ($role === 'owner' && $user['role'] !== 'owner') {
                        $message = 'Tài khoản này không phải Chủ sân';
                        $messageType = 'error';
                    } elseif ($role === 'customer' && $user['role'] === 'owner') {
                        $message = 'Đây là tài khoản Chủ sân. Vui lòng dùng form "Đăng nhập Chủ sân"';
                        $messageType = 'error';
                    } elseif (password_verify($password, $user['password_hash'])) {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['user_balance'] = $user['user_balance'] ?? 0;
                        
                        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                        $stmt->execute([$user['user_id']]);
                        
                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit;
                    } else {
                        $message = 'Tên đăng nhập hoặc mật khẩu không đúng';
                        $messageType = 'error';
                    }
                }
                break;

            case 'register_owner':
                $fullname = sanitizeInput($_POST['full_name'] ?? '');
                $username = sanitizeInput($_POST['username'] ?? '');
                $phone = sanitizeInput($_POST['phone_number'] ?? '');
                $email = sanitizeInput($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';

                if (empty($fullname) || empty($username) || empty($phone) || empty($password)) {
                    $message = 'Tất cả các trường là bắt buộc';
                    $messageType = 'error';
                } else if (strlen($password) < 6) {
                    $message = 'Mật khẩu phải có ít nhất 6 ký tự';
                    $messageType = 'error';
                } else if (!preg_match('/^[0-9]{10,11}$/', $phone)) {
                    $message = 'Số điện thoại không hợp lệ';
                    $messageType = 'error';
                } else {
                    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? OR phone_number = ?");
                    $stmt->execute([$username, $phone]);
                    if ($stmt->fetch()) {
                        $message = 'Tên đăng nhập hoặc số điện thoại đã tồn tại';
                        $messageType = 'error';
                    } else {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        try {
                            if (!empty($email)) {
                                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
                                $stmt->execute([$email]);
                                if ($stmt->fetch()) {
                                    $message = 'Email đã tồn tại';
                                    $messageType = 'error';
                                    break;
                                }
                            }
                            
                            $stmt = $pdo->prepare("
                                INSERT INTO users (username, password_hash, full_name, phone_number, email, role, user_balance, created_at) 
                                VALUES (?, ?, ?, ?, ?, 'owner', 0, NOW())
                            ");
                            if ($stmt->execute([$username, $hashedPassword, $fullname, $phone, $email ?: null])) {
                                $newUserId = $pdo->lastInsertId();
                                $stmtWallet = $pdo->prepare("
                                    INSERT INTO user_wallets (user_id, balance, frozen_balance, total_recharge, total_spent, updated_at)
                                    VALUES (?, 0, 0, 0, 0, NOW())
                                ");
                                $stmtWallet->execute([$newUserId]);
                                
                                $message = 'Đăng ký chủ sân thành công! Vui lòng đăng nhập.';
                                $messageType = 'success';
                            } else {
                                $message = 'Đăng ký thất bại';
                                $messageType = 'error';
                            }
                        } catch (PDOException $e) {
                            $errorMsg = $e->getMessage();
                            if (strpos($errorMsg, 'duplicate key') !== false || strpos($errorMsg, 'UNIQUE') !== false) {
                                $message = 'Tên đăng nhập, số điện thoại hoặc email đã tồn tại';
                            } else {
                                $message = 'Đăng ký thất bại: ' . $errorMsg;
                            }
                            $messageType = 'error';
                        }
                    }
                }
                break;

            case 'topup':
    if (!isset($_SESSION['user_id'])) {
        $message = 'Vui lòng đăng nhập để nạp tiền';
        $messageType = 'error';
    } else {
        $amount = floatval($_POST['amount'] ?? 0);
        $payment_method = sanitizeInput($_POST['payment_method'] ?? 'bank_transfer');
        $minAmount = 10000;
        $maxAmount = 10000000;
        
        if ($amount < $minAmount || $amount > $maxAmount) {
            $message = "Số tiền nạp phải từ " . number_format($minAmount) . " đến " . number_format($maxAmount) . " VNĐ";
            $messageType = 'error';
        } else {
            $userId = $_SESSION['user_id'];
            try {
                $pdo->beginTransaction();
                
                $transactionCode = 'NAP' . date('Ymd') . rand(1000, 9999);
                
                // Insert vào payments (không có booking_id)
                $stmt = $pdo->prepare("
                    INSERT INTO payments (user_id, amount, status, payment_method, transaction_code, description, created_at) 
                    VALUES (?, ?, 'pending', ?, ?, 'Nạp tiền vào ví', NOW())
                ");
                $stmt->execute([$userId, $amount, $payment_method, $transactionCode]);
                $paymentId = $pdo->lastInsertId(); // Lấy 'id' vừa insert
                
                $pdo->commit();
                
                // Chuyển đến trang hướng dẫn thanh toán
                header("Location: topup_instruction.php?payment_id=" . $paymentId . "&amount=" . $amount . "&method=" . $payment_method);
                exit;
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $message = 'Tạo yêu cầu nạp tiền thất bại: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    break;

            case 'booking':
                if (!isset($_SESSION['user_id'])) {
                    $message = 'Vui lòng đăng nhập để đặt sân';
                    $messageType = 'error';
                } else {
                    $court_id = (int)($_POST['court_id'] ?? 0);
                    $booking_date = $_POST['booking_date'] ?? '';
                    $start_time = $_POST['start_time'] ?? '';
                    $duration = (float)($_POST['duration'] ?? 1);
                    
                    if (!$court_id || empty($booking_date) || empty($start_time)) {
                        $message = 'Tất cả các trường đặt sân là bắt buộc';
                        $messageType = 'error';
                    } else {
                        $start_datetime = $booking_date . ' ' . $start_time;
                        $end_datetime = date('Y-m-d H:i:s', strtotime($start_datetime . ' + ' . $duration . ' hours'));
                        
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) as conflicts FROM bookings
                            WHERE court_id = ? AND status IN ('pending', 'confirmed', 'paid') AND (
                                (start_time <= ? AND end_time > ?) OR
                                (start_time < ? AND end_time >= ?) OR
                                (start_time >= ? AND end_time <= ?)
                            )
                        ");
                        $stmt->execute([$court_id, $start_datetime, $start_datetime, $end_datetime, $end_datetime, $start_datetime, $end_datetime]);
                        $conflict = $stmt->fetch();
                        
                        if ($conflict['conflicts'] > 0) {
                            $message = 'Khung giờ này đã được đặt';
                            $messageType = 'error';
                        } else {
                            $stmt = $pdo->prepare("SELECT price_per_hour, court_name, owner_id FROM courts WHERE court_id = ?");
                            $stmt->execute([$court_id]);
                            $court = $stmt->fetch();
                            
                            if ($court) {
                                $total_price = $court['price_per_hour'] * $duration;
                                
                                $pdo->beginTransaction();
                                
                                $stmt = $pdo->prepare("
                                    INSERT INTO bookings (user_id, court_id, start_time, end_time, total_price, status, booking_code, created_at) 
                                    VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW())
                                ");
                                $bookingCode = 'BK' . date('Ymd') . rand(1000, 9999);
                                $stmt->execute([$_SESSION['user_id'], $court_id, $start_datetime, $end_datetime, $total_price, $bookingCode]);
                                $bookingId = $pdo->lastInsertId();
                                
                                // Kiểm tra cấu trúc bảng payments
                                $stmt = $pdo->query("SHOW COLUMNS FROM payments");
                                $paymentColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                
                                if (in_array('booking_id', $paymentColumns)) {
                                    $stmt = $pdo->prepare("
                                        INSERT INTO payments (booking_id, user_id, amount, status, payment_method, created_at)
                                        VALUES (?, ?, ?, 'pending', 'wallet', NOW())
                                    ");
                                    $stmt->execute([$bookingId, $_SESSION['user_id'], $total_price]);
                                } else {
                                    $stmt = $pdo->prepare("
                                        INSERT INTO payments (user_id, amount, status, payment_method, created_at)
                                        VALUES (?, ?, 'pending', 'wallet', NOW())
                                    ");
                                    $stmt->execute([$_SESSION['user_id'], $total_price]);
                                }
                                
                                $pdo->commit();
                                
                                header("Location: payment.php?booking_id=" . $bookingId);
                                exit;
                            } else {
                                $message = 'Sân không tồn tại';
                                $messageType = 'error';
                            }
                        }
                    }
                }
                break;
                
            case 'submit_review':
                if (!isset($_SESSION['user_id'])) {
                    $message = 'Vui lòng đăng nhập để đánh giá';
                    $messageType = 'error';
                } else {
                    $court_id = (int)($_POST['court_id'] ?? 0);
                    $rating = (int)($_POST['rating'] ?? 0);
                    $comment = sanitizeInput($_POST['comment'] ?? '');
                    $booking_id = (int)($_POST['booking_id'] ?? 0);
                    
                    if ($rating < 1 || $rating > 5) {
                        $message = 'Đánh giá từ 1-5 sao';
                        $messageType = 'error';
                    } else {
                        try {
                            $stmt = $pdo->prepare("
                                INSERT INTO court_reviews (court_id, user_id, booking_id, rating, comment, created_at)
                                VALUES (?, ?, ?, ?, ?, NOW())
                                ON DUPLICATE KEY UPDATE rating = ?, comment = ?, updated_at = NOW()
                            ");
                            $stmt->execute([$court_id, $_SESSION['user_id'], $booking_id, $rating, $comment, $rating, $comment]);
                            
                            $stmt = $pdo->prepare("
                                UPDATE courts SET 
                                    avg_rating = (SELECT AVG(rating) FROM court_reviews WHERE court_id = ?),
                                    total_reviews = (SELECT COUNT(*) FROM court_reviews WHERE court_id = ?)
                                WHERE court_id = ?
                            ");
                            $stmt->execute([$court_id, $court_id, $court_id]);
                            
                            $message = 'Cảm ơn bạn đã đánh giá!';
                            $messageType = 'success';
                        } catch (Exception $e) {
                            $message = 'Đánh giá thất bại: ' . $e->getMessage();
                            $messageType = 'error';
                        }
                    }
                }
                break;

            case 'change_password':
                if (!isset($_SESSION['user_id'])) {
                    $message = 'Vui lòng đăng nhập để đổi mật khẩu';
                    $messageType = 'error';
                } else {
                    $old = $_POST['old_password'] ?? '';
                    $new = $_POST['new_password'] ?? '';
                    if (empty($old) || empty($new)) {
                        $message = 'Vui lòng nhập đầy đủ thông tin';
                        $messageType = 'error';
                    } else if (strlen($new) < 6) {
                        $message = 'Mật khẩu mới phải có ít nhất 6 ký tự';
                        $messageType = 'error';
                    } else {
                        $userId = $_SESSION['user_id'];
                        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
                        $stmt->execute([$userId]);
                        $user = $stmt->fetch();
                        if (!$user || !password_verify($old, $user['password_hash'])) {
                            $message = 'Mật khẩu cũ không chính xác';
                            $messageType = 'error';
                        } else {
                            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                            if ($stmt->execute([password_hash($new, PASSWORD_DEFAULT), $userId])) {
                                $message = 'Đổi mật khẩu thành công!';
                                $messageType = 'success';
                            } else {
                                $message = 'Đổi mật khẩu thất bại';
                                $messageType = 'error';
                            }
                        }
                    }
                }
                break;

            case 'support_ticket':
                if (!isset($_SESSION['user_id'])) {
                    $message = 'Vui lòng đăng nhập để gửi yêu cầu hỗ trợ';
                    $messageType = 'error';
                } else {
                    $sub = trim($_POST['ticket_subject'] ?? '');
                    $ms = trim($_POST['ticket_message'] ?? '');
                    if (empty($sub) || empty($ms)) {
                        $message = 'Vui lòng nhập đầy đủ chủ đề và nội dung';
                        $messageType = 'error';
                    } else {
                        $uid = $_SESSION['user_id'];
                        $stmt = $pdo->prepare("INSERT INTO support_tickets (user_id, subject) VALUES (?, ?)");
                        $stmt->execute([$uid, $sub]);
                        $ticketId = $pdo->lastInsertId();
                        $stmt = $pdo->prepare("INSERT INTO support_messages (ticket_id, sender, message) VALUES (?, 'customer', ?)");
                        if ($stmt->execute([$ticketId, $ms])) {
                            $message = 'Yêu cầu hỗ trợ đã được gửi!';
                            $messageType = 'success';
                        } else {
                            $message = 'Gửi yêu cầu thất bại';
                            $messageType = 'error';
                        }
                    }
                }
                break;

            case 'logout':
                session_destroy();
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
        }
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$min_price = isset($_GET['min_price']) ? (int)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (int)$_GET['max_price'] : 1000000;
$court_type = isset($_GET['court_type']) ? sanitizeInput($_GET['court_type']) : '';
$sort_by = isset($_GET['sort_by']) ? sanitizeInput($_GET['sort_by']) : 'newest';
$show_favorites = isset($_GET['favorites']) && $_GET['favorites'] == 1;

$courts = [];
$totalCourts = 0;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 6;
$offset = ($currentPage - 1) * $perPage;

try {
    $whereConditions = ["c.is_active = 1"];
    $params = [];
    
    if ($search) {
        $whereConditions[] = "(c.court_name LIKE ? OR c.address LIKE ? OR c.description LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if ($min_price > 0) {
        $whereConditions[] = "c.price_per_hour >= ?";
        $params[] = $min_price;
    }
    
    if ($max_price < 1000000) {
        $whereConditions[] = "c.price_per_hour <= ?";
        $params[] = $max_price;
    }
    
    if ($court_type) {
        $whereConditions[] = "c.court_type = ?";
        $params[] = $court_type;
    }
    
    if ($show_favorites && isset($_SESSION['user_id'])) {
        $whereConditions[] = "EXISTS (SELECT 1 FROM user_favorites uf WHERE uf.court_id = c.court_id AND uf.user_id = ?)";
        $params[] = $_SESSION['user_id'];
    }
    
    $whereClause = implode(" AND ", $whereConditions);
    
    $countQuery = "SELECT COUNT(*) as total FROM courts c WHERE $whereClause";
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalCourts = $stmt->fetch()['total'];
    
    $orderBy = "c.created_at DESC";
    switch ($sort_by) {
        case 'price_asc':
            $orderBy = "c.price_per_hour ASC";
            break;
        case 'price_desc':
            $orderBy = "c.price_per_hour DESC";
            break;
        case 'rating':
            $orderBy = "c.avg_rating DESC";
            break;
        case 'popular':
            $orderBy = "c.total_bookings DESC";
            break;
    }
    
    $query = "
        SELECT c.*, 
               COALESCE(c.avg_rating, 0) as avg_rating,
               COALESCE(c.total_reviews, 0) as total_reviews,
               (SELECT COUNT(*) FROM user_favorites uf WHERE uf.court_id = c.court_id AND uf.user_id = ?) as is_favorited
        FROM courts c 
        WHERE $whereClause 
        ORDER BY $orderBy 
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $pdo->prepare($query);
    $finalParams = $params;
    if (isset($_SESSION['user_id'])) {
        array_unshift($finalParams, $_SESSION['user_id']);
    } else {
        array_unshift($finalParams, 0);
    }
    $finalParams[] = $perPage;
    $finalParams[] = $offset;
    $stmt->execute($finalParams);
    $courts = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Courts query error: " . $e->getMessage());
}

$totalPages = ceil($totalCourts / $perPage);

$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? '';
$userRole = $_SESSION['user_role'] ?? 'customer';
$userBalance = $_SESSION['user_balance'] ?? 0;
$userBookings = [];
$userTransactions = [];

if ($isLoggedIn) {
    $userId = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("
        SELECT b.*, c.court_name, c.court_id 
        FROM bookings b 
        JOIN courts c ON b.court_id = c.court_id 
        WHERE b.user_id = ? 
        ORDER BY b.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $userBookings = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("
        SELECT * FROM transactions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $userTransactions = $stmt->fetchAll();
    
    if ($userRole === 'owner') {
        header('Location: owner_dashboard.php');
        exit;
    }
}

$courtTypes = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT court_type FROM courts WHERE court_type IS NOT NULL AND court_type != ''");
    $courtTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $courtTypes = ['Sân trong nhà', 'Sân ngoài trời', 'Sân cao cấp'];
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BadmintonPro | Đặt Sân Cầu Lông Chuyên Nghiệp</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .animate-fadeInUp { animation: fadeInUp 0.6s ease-out; }
        .animate-slideIn { animation: slideIn 0.4s ease-out; }
        
        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .card-hover:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
        }
        
        .hero-pattern {
            background-image: url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"%3E%3Cpath fill="%23ffffff" fill-opacity="0.1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,154.7C960,171,1056,181,1152,165.3C1248,149,1344,107,1392,85.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"%3E%3C/path%3E%3C/svg%3E');
            background-repeat: no-repeat;
            background-position: bottom;
            background-size: cover;
        }
        
        .payment-method-card {
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .payment-method-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .payment-method-card.selected {
            border-color: #10b981;
            background-color: #f0fdf4;
        }
        
        .amount-preset-btn {
            transition: all 0.2s ease;
        }
        .amount-preset-btn.active {
            background-color: #10b981;
            color: white;
        }
        
        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #10b981;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #10b981; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #059669; }
        
        .modal-enter {
            animation: modalEnter 0.3s ease-out;
        }
        
        @keyframes modalEnter {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        
        .toast {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 1000;
            animation: slideInRight 0.3s ease-out;
        }
        
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
<nav class="bg-white shadow-lg sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center space-x-3">
                <a href="index.php" class="flex items-center space-x-3 hover:opacity-80 transition">
                    <div class="bg-gradient-to-r from-green-500 to-emerald-600 p-2 rounded-xl">
                        <i class="fas fa-shuttlecock text-white text-xl"></i>
                    </div>
                    <span class="font-extrabold text-2xl bg-gradient-to-r from-green-600 to-emerald-600 bg-clip-text text-transparent">
                        BadmintonPro
                    </span>
                </a>
            </div>
            
            <div class="hidden md:flex items-center flex-1 max-w-md mx-8">
                <form method="GET" action="" class="w-full">
                    <div class="relative">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Tìm kiếm sân cầu lông..." 
                               class="w-full px-4 py-2 pl-10 pr-4 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-200">
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    </div>
                </form>
            </div>
            
            <?php if ($isLoggedIn): ?>
            <div class="flex items-center space-x-4">
                <div class="relative">
                    <button id="notificationBtn" class="text-gray-600 hover:text-green-600 transition">
                        <i class="fas fa-bell text-xl"></i>
                        <span id="notificationBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center hidden">0</span>
                    </button>
                </div>
                
                <div class="relative group">
                    <button class="flex items-center space-x-3 bg-gray-100 hover:bg-gray-200 rounded-full px-4 py-2 transition duration-300">
                        <div class="w-8 h-8 bg-gradient-to-r from-green-500 to-emerald-600 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-white text-sm"></i>
                        </div>
                        <span class="font-medium text-gray-700"><?php echo htmlspecialchars($userName); ?></span>
                        <i class="fas fa-chevron-down text-gray-500 text-xs"></i>
                    </button>
                    <div class="absolute right-0 mt-2 w-72 bg-white rounded-xl shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 z-50">
                        <div class="p-4 border-b border-gray-100">
                            <p class="text-sm text-gray-500">Số dư hiện tại</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo number_format((float)$userBalance, 0, ',', '.'); ?> ₫</p>
                            <button onclick="showModal('topup')" class="mt-2 text-sm text-green-600 hover:text-green-700 font-medium">
                                <i class="fas fa-plus-circle mr-1"></i>Nạp tiền
                            </button>
                        </div>
                        <div class="py-2">
                            <a href="my_bookings.php" class="flex items-center px-4 py-2 hover:bg-gray-50 transition">
                                <i class="fas fa-calendar-alt w-5 text-blue-500"></i>
                                <span class="ml-3">Lịch đặt của tôi</span>
                            </a>
                            <a href="my_favorites.php" class="flex items-center px-4 py-2 hover:bg-gray-50 transition">
                                <i class="fas fa-heart w-5 text-red-500"></i>
                                <span class="ml-3">Sân yêu thích</span>
                            </a>
                            <button onclick="showModal('changePassword')" class="w-full text-left flex items-center px-4 py-2 hover:bg-gray-50 transition">
                                <i class="fas fa-key w-5 text-yellow-600"></i>
                                <span class="ml-3">Đổi mật khẩu</span>
                            </button>
                            <button onclick="showModal('registerOwner')" class="w-full text-left flex items-center px-4 py-2 hover:bg-gray-50 transition">
                                <i class="fas fa-store w-5 text-purple-600"></i>
                                <span class="ml-3">Đăng ký làm Chủ sân</span>
                            </button>
                            <hr class="my-2">
                            <form method="POST" class="block">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="logout">
                                <button type="submit" class="w-full text-left flex items-center px-4 py-2 hover:bg-red-50 transition text-red-600">
                                    <i class="fas fa-sign-out-alt w-5"></i>
                                    <span class="ml-3">Đăng xuất</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="flex items-center space-x-3">
                <!-- Nút Đăng nhập thường -->
                <button onclick="showModal('login')" class="text-gray-700 hover:text-green-600 font-medium px-4 py-2 transition duration-300">
                    <i class="fas fa-user mr-1"></i>Đăng nhập
                </button>
                
                <!-- Nút Đăng nhập Chủ sân MỚI ở header -->
                <button onclick="showModal('ownerLogin')" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-xl transition duration-300 flex items-center">
                    <i class="fas fa-user-tie mr-2"></i>Chủ sân
                </button>
                
                <button onclick="showModal('register')" class="bg-gradient-to-r from-green-600 to-emerald-600 text-white px-6 py-2 rounded-xl hover:shadow-lg transition duration-300">
                    Đăng ký miễn phí
                </button>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="md:hidden py-3">
            <form method="GET" action="">
                <div class="relative">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Tìm kiếm sân cầu lông..." 
                           class="w-full px-4 py-2 pl-10 pr-4 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                </div>
            </form>
        </div>
    </div>
</nav>

    <!-- Toast Messages -->
    <?php if ($message): ?>
    <div class="toast" id="toastMessage">
        <div class="p-4 rounded-xl shadow-lg max-w-md <?php echo $messageType === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'; ?>">
            <div class="flex items-center space-x-3">
                <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> text-xl"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-auto text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
    <script>
        setTimeout(function() {
            var toast = document.getElementById('toastMessage');
            if(toast) toast.style.display = 'none';
        }, 5000);
    </script>
    <?php endif; ?>

    <!-- Hero Section -->
    <section class="relative bg-gradient-to-br from-green-600 via-emerald-600 to-teal-700 text-white overflow-hidden">
        <div class="absolute inset-0 hero-pattern opacity-20"></div>
        <div class="relative max-w-7xl mx-auto px-4 py-16 lg:py-24">
            <div class="text-center animate-fadeInUp">
                <div class="inline-flex items-center space-x-2 bg-white/20 backdrop-blur-sm rounded-full px-4 py-2 mb-6">
                    <i class="fas fa-shuttlecock text-yellow-300"></i>
                    <span class="text-sm font-medium">Hệ thống đặt sân thông minh #1 Việt Nam</span>
                </div>
                <h1 class="text-4xl md:text-6xl lg:text-7xl font-extrabold mb-6 leading-tight">
                    Đặt Sân Cầu Lông<br>
                    <span class="text-yellow-300">Dễ Dàng Hơn</span>
                </h1>
                <p class="text-lg md:text-xl mb-8 text-green-100 max-w-2xl mx-auto">
                    Hơn 500+ sân cầu lông chuyên nghiệp trên toàn quốc. 
                    Đặt ngay hôm nay và nhận ưu đãi đặc biệt!
                </p>
                <div class="flex flex-col sm:flex-row justify-center gap-4">
                    <?php if ($isLoggedIn): ?>
                    <button onclick="showModal('booking')" class="bg-white text-green-600 px-8 py-3 rounded-xl font-bold text-lg hover:shadow-2xl transition duration-300 transform hover:scale-105 inline-flex items-center justify-center space-x-2">
                        <i class="fas fa-calendar-check"></i>
                        <span>Đặt Sân Ngay</span>
                    </button>
                    <?php else: ?>
                    <button onclick="showModal('login')" class="bg-white text-green-600 px-8 py-3 rounded-xl font-semibold hover:shadow-xl transition duration-300">
                        <i class="fas fa-sign-in-alt mr-2"></i>Đăng nhập
                    </button>
                    <button onclick="showModal('ownerLogin')" class="bg-yellow-400 text-gray-800 px-8 py-3 rounded-xl font-semibold hover:shadow-xl transition duration-300">
                        <i class="fas fa-user-tie mr-2"></i>Đăng nhập Chủ sân
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <svg class="absolute bottom-0 w-full" viewBox="0 0 1440 120" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0 64L60 74.7C120 85.3 240 107 360 106.7C480 106.7 600 85.3 720 80C840 74.7 960 85.3 1080 90.7C1200 96 1320 96 1380 96L1440 96L1440 120L1380 120C1320 120 1200 120 1080 120C960 120 840 120 720 120C600 120 480 120 360 120C240 120 120 120 60 120L0 120Z" fill="#f3f4f6"/>
        </svg>
    </section>

    <!-- Stats Section -->
    <section class="bg-gray-50 py-12">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
                <div class="bg-white rounded-2xl p-6 shadow-sm">
                    <div class="text-3xl md:text-4xl font-bold text-green-600 mb-2">500+</div>
                    <div class="text-gray-600 font-medium">Sân Cầu Lông</div>
                </div>
                <div class="bg-white rounded-2xl p-6 shadow-sm">
                    <div class="text-3xl md:text-4xl font-bold text-green-600 mb-2">10K+</div>
                    <div class="text-gray-600 font-medium">Lượt Đặt Sân</div>
                </div>
                <div class="bg-white rounded-2xl p-6 shadow-sm">
                    <div class="text-3xl md:text-4xl font-bold text-green-600 mb-2">4.8★</div>
                    <div class="text-gray-600 font-medium">Đánh Giá</div>
                </div>
                <div class="bg-white rounded-2xl p-6 shadow-sm">
                    <div class="text-3xl md:text-4xl font-bold text-green-600 mb-2">24/7</div>
                    <div class="text-gray-600 font-medium">Hỗ Trợ</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Filters Section -->
    <section class="py-8 bg-white border-b border-gray-200 sticky top-16 z-40 shadow-sm">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap gap-3">
                    <button onclick="toggleFilters()" class="px-4 py-2 bg-gray-100 rounded-xl hover:bg-gray-200 transition flex items-center space-x-2">
                        <i class="fas fa-filter"></i>
                        <span>Bộ lọc</span>
                    </button>
                    
                    <select id="sortSelect" onchange="updateSort()" class="px-4 py-2 bg-gray-100 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Mới nhất</option>
                        <option value="price_asc" <?php echo $sort_by == 'price_asc' ? 'selected' : ''; ?>>Giá: Thấp đến Cao</option>
                        <option value="price_desc" <?php echo $sort_by == 'price_desc' ? 'selected' : ''; ?>>Giá: Cao đến Thấp</option>
                        <option value="rating" <?php echo $sort_by == 'rating' ? 'selected' : ''; ?>>Đánh giá cao nhất</option>
                        <option value="popular" <?php echo $sort_by == 'popular' ? 'selected' : ''; ?>>Phổ biến nhất</option>
                    </select>
                    
                    <?php if ($isLoggedIn): ?>
                    <button onclick="toggleFavorites()" class="px-4 py-2 <?php echo $show_favorites ? 'bg-red-500 text-white' : 'bg-gray-100'; ?> rounded-xl hover:shadow transition">
                        <i class="fas fa-heart"></i>
                        <span class="ml-2">Yêu thích</span>
                    </button>
                    <?php endif; ?>
                </div>
                
                <div class="text-sm text-gray-500">
                    Tìm thấy <span class="font-semibold text-gray-700"><?php echo $totalCourts; ?></span> sân
                </div>
            </div>
            
            <div id="filterPanel" class="hidden mt-4 p-4 bg-gray-50 rounded-xl animate-slideIn">
                <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <?php if ($search): ?>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <?php endif; ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Loại sân</label>
                        <select name="court_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500">
                            <option value="">Tất cả</option>
                            <?php foreach ($courtTypes as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $court_type == $type ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Giá tối thiểu (VNĐ)</label>
                        <input type="number" name="min_price" value="<?php echo $min_price; ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500"
                               step="10000" min="0">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Giá tối đa (VNĐ)</label>
                        <input type="number" name="max_price" value="<?php echo $max_price; ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500"
                               step="10000" max="1000000">
                    </div>
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="flex-1 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                            <i class="fas fa-search mr-2"></i>Áp dụng
                        </button>
                        <a href="?" class="flex-1 bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition text-center">
                            <i class="fas fa-undo mr-2"></i>Đặt lại
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- Courts Section -->
    <section class="py-12 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($courts as $court): ?>
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden card-hover group">
                    <div class="relative h-56 bg-gradient-to-br from-green-400 to-emerald-500 overflow-hidden">
                        <?php if (!empty($court['court_image'])): ?>
                        <img src="uploads/courts/<?php echo htmlspecialchars($court['court_image']); ?>" 
                             alt="<?php echo htmlspecialchars($court['court_name']); ?>" 
                             class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                        <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center">
                            <i class="fas fa-shuttlecock text-white text-7xl opacity-50"></i>
                        </div>
                        <?php endif; ?>
                        
                        <div class="absolute top-4 left-4 bg-yellow-400 text-gray-800 px-2 py-1 rounded-lg text-sm font-bold flex items-center">
                            <i class="fas fa-star text-yellow-600 mr-1"></i>
                            <?php echo number_format($court['avg_rating'] ?? 0, 1); ?>
                            <span class="text-xs ml-1">(<?php echo $court['total_reviews'] ?? 0; ?>)</span>
                        </div>
                        
                        <?php if ($isLoggedIn): ?>
                        <button onclick="toggleFavorite(<?php echo $court['court_id']; ?>, this)" 
                                class="absolute top-4 right-4 bg-white/90 hover:bg-white rounded-full w-10 h-10 flex items-center justify-center transition shadow-md">
                            <i class="fas fa-heart <?php echo ($court['is_favorited'] ?? 0) ? 'text-red-500' : 'text-gray-400'; ?>"></i>
                        </button>
                        <?php endif; ?>
                        
                        <div class="absolute bottom-4 right-4 bg-black/70 backdrop-blur-sm rounded-lg px-3 py-1">
                            <span class="text-white font-bold"><?php echo number_format($court['price_per_hour'], 0, ',', '.'); ?>₫</span>
                            <span class="text-gray-300 text-xs">/giờ</span>
                        </div>
                    </div>
                    
                    <div class="p-5">
                        <h3 class="text-xl font-bold text-gray-800 mb-2 line-clamp-1">
                            <?php echo htmlspecialchars($court['court_name']); ?>
                        </h3>
                        
                        <?php if (!empty($court['address'])): ?>
                        <p class="text-sm text-gray-500 mb-3 flex items-start">
                            <i class="fas fa-map-marker-alt text-green-600 mr-2 mt-0.5"></i>
                            <span class="line-clamp-2"><?php echo htmlspecialchars($court['address']); ?></span>
                        </p>
                        <?php endif; ?>
                        
                        <?php if (!empty($court['court_type'])): ?>
                        <div class="flex items-center mb-3">
                            <i class="fas fa-tag text-gray-400 text-sm mr-2"></i>
                            <span class="text-xs bg-gray-100 px-2 py-1 rounded-full"><?php echo htmlspecialchars($court['court_type']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                            <div class="flex items-center space-x-1">
                                <i class="fas fa-clock text-gray-400 text-sm"></i>
                                <span class="text-xs text-gray-500">06:00 - 22:00</span>
                            </div>
                            <a href="court.php?id=<?php echo $court['court_id']; ?>" 
                               class="bg-gradient-to-r from-green-600 to-emerald-600 text-white px-4 py-2 rounded-xl hover:shadow-lg transition duration-300 text-sm font-semibold inline-flex items-center">
                                Xem Chi Tiết
                                <i class="fas fa-arrow-right ml-2 text-xs"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($courts)): ?>
            <div class="text-center py-16">
                <i class="fas fa-search text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-600 mb-2">Không tìm thấy sân nào</h3>
                <p class="text-gray-500">Vui lòng thử lại với bộ lọc khác</p>
                <a href="?" class="inline-block mt-4 text-green-600 hover:text-green-700 font-medium">
                    <i class="fas fa-undo mr-2"></i>Xóa bộ lọc
                </a>
            </div>
            <?php endif; ?>
            
            <?php if ($totalPages > 1): ?>
            <div class="flex justify-center mt-12">
                <nav class="flex items-center space-x-2">
                    <?php if ($currentPage > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $currentPage - 1])); ?>" 
                       class="px-4 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 transition">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php 
                    $startPage = max(1, $currentPage - 2);
                    $endPage = min($totalPages, $currentPage + 2);
                    ?>
                    
                    <?php if ($startPage > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" 
                       class="px-4 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 transition">1</a>
                    <?php if ($startPage > 2): ?>
                    <span class="px-2">...</span>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                       class="px-4 py-2 rounded-lg <?php echo $i === $currentPage ? 'bg-gradient-to-r from-green-600 to-emerald-600 text-white' : 'bg-white border border-gray-300 hover:bg-gray-50'; ?> transition font-medium">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                    <span class="px-2">...</span>
                    <?php endif; ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>" 
                       class="px-4 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 transition"><?php echo $totalPages; ?></a>
                    <?php endif; ?>
                    
                    <?php if ($currentPage < $totalPages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $currentPage + 1])); ?>" 
                       class="px-4 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 transition">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-extrabold text-gray-900 mb-4">Tại Sao Chọn BadmintonPro?</h2>
                <p class="text-lg text-gray-600">Trải nghiệm đặt sân thông minh và tiện lợi nhất</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center p-6 group">
                    <div class="w-20 h-20 bg-green-100 rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:bg-green-200 transition">
                        <i class="fas fa-bolt text-green-600 text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Đặt Sân Nhanh Chóng</h3>
                    <p class="text-gray-600">Chỉ vài cú click chuột, bạn đã có thể đặt được sân cầu lông ưng ý</p>
                </div>
                <div class="text-center p-6 group">
                    <div class="w-20 h-20 bg-green-100 rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:bg-green-200 transition">
                        <i class="fas fa-shield-alt text-green-600 text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Thanh Toán An Toàn</h3>
                    <p class="text-gray-600">Hệ thống thanh toán bảo mật, đa dạng phương thức thanh toán</p>
                </div>
                <div class="text-center p-6 group">
                    <div class="w-20 h-20 bg-green-100 rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:bg-green-200 transition">
                        <i class="fas fa-headset text-green-600 text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Hỗ Trợ 24/7</h3>
                    <p class="text-gray-600">Đội ngũ hỗ trợ luôn sẵn sàng giải đáp mọi thắc mắc của bạn</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Owner Registration Banner -->
    <section class="py-16 bg-gradient-to-r from-yellow-500 via-orange-500 to-red-500 text-white relative overflow-hidden">
        <div class="absolute inset-0 bg-black opacity-20"></div>
        <div class="relative max-w-7xl mx-auto px-4">
            <div class="grid md:grid-cols-2 gap-8 items-center">
                <div class="text-center md:text-left">
                    <h2 class="text-3xl md:text-4xl font-extrabold mb-4">Bạn Có Sân Cầu Lông?</h2>
                    <p class="text-lg mb-6 opacity-95">Hãy trở thành đối tác của chúng tôi và tăng doanh thu lên đến 200%!</p>
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-center justify-center md:justify-start">
                            <i class="fas fa-check-circle text-2xl mr-3"></i>
                            <span>Quản lý lịch đặt sân thông minh</span>
                        </li>
                        <li class="flex items-center justify-center md:justify-start">
                            <i class="fas fa-chart-line text-2xl mr-3"></i>
                            <span>Báo cáo doanh thu chi tiết</span>
                        </li>
                        <li class="flex items-center justify-center md:justify-start">
                            <i class="fas fa-users text-2xl mr-3"></i>
                            <span>Tiếp cận hàng ngàn khách hàng mỗi ngày</span>
                        </li>
                    </ul>
                    <button onclick="showModal('ownerRegisterQuick')" class="bg-white text-orange-600 px-8 py-3 rounded-xl font-bold text-lg hover:shadow-xl transition duration-300 transform hover:scale-105">
                        Đăng Ký Làm Chủ Sân Ngay
                        <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-white/20 backdrop-blur-sm rounded-2xl p-6 text-center">
                        <div class="text-3xl font-bold mb-2">100+</div>
                        <p class="font-semibold">Đối Tác</p>
                    </div>
                    <div class="bg-white/20 backdrop-blur-sm rounded-2xl p-6 text-center">
                        <div class="text-3xl font-bold mb-2">50K+</div>
                        <p class="font-semibold">Khách Hàng</p>
                    </div>
                    <div class="bg-white/20 backdrop-blur-sm rounded-2xl p-6 text-center">
                        <div class="text-3xl font-bold mb-2">4.9★</div>
                        <p class="font-semibold">Đánh Giá</p>
                    </div>
                    <div class="bg-white/20 backdrop-blur-sm rounded-2xl p-6 text-center">
                        <div class="text-3xl font-bold mb-2">99%</div>
                        <p class="font-semibold">Hài Lòng</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if ($isLoggedIn && $userRole === 'customer'): ?>
    <section class="py-16 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4">
            <h2 class="text-3xl font-extrabold text-gray-900 text-center mb-12">Lịch Sử Của Bạn</h2>
            <div class="grid md:grid-cols-2 gap-8">
                <div class="bg-white rounded-2xl shadow-lg p-6">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-calendar-check text-blue-600"></i>
                        </div>
                        <h3 class="text-xl font-bold">Đặt Sân Gần Đây</h3>
                    </div>
                    <?php if (count($userBookings) === 0): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-calendar-alt text-4xl text-gray-300 mb-3"></i>
                            <p class="text-gray-500">Chưa có đặt sân nào.</p>
                            <button onclick="showModal('booking')" class="mt-3 text-green-600 hover:text-green-700 font-medium">
                                <i class="fas fa-plus-circle mr-1"></i>Đặt sân ngay
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($userBookings as $b): ?>
                            <div class="border border-gray-100 rounded-xl p-4 hover:shadow-md transition">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($b['court_name']); ?></p>
                                        <p class="text-sm text-gray-500">
                                            <i class="far fa-calendar-alt mr-1"></i>
                                            <?php echo date('d/m/Y H:i', strtotime($b['start_time'])); ?> - <?php echo date('H:i', strtotime($b['end_time'])); ?>
                                        </p>
                                    </div>
                                    <span class="px-2 py-1 rounded-lg text-xs font-medium 
                                        <?php echo $b['status'] === 'paid' ? 'bg-green-100 text-green-700' : 
                                            ($b['status'] === 'confirmed' ? 'bg-blue-100 text-blue-700' : 
                                            ($b['status'] === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700')); ?>">
                                        <?php 
                                        $statusText = [
                                            'paid' => 'Đã thanh toán',
                                            'confirmed' => 'Đã xác nhận',
                                            'pending' => 'Chờ xác nhận',
                                            'cancelled' => 'Đã hủy'
                                        ];
                                        echo $statusText[$b['status']] ?? $b['status'];
                                        ?>
                                    </span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <p class="text-green-600 font-bold"><?php echo number_format($b['total_price'], 0, ',', '.'); ?> ₫</p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-4 text-center">
                            <a href="my_bookings.php" class="text-green-600 hover:text-green-700 text-sm font-medium">
                                Xem tất cả <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="bg-white rounded-2xl shadow-lg p-6">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-history text-green-600"></i>
                        </div>
                        <h3 class="text-xl font-bold">Lịch Sử Giao Dịch</h3>
                    </div>
                    <?php if (count($userTransactions) === 0): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-receipt text-4xl text-gray-300 mb-3"></i>
                            <p class="text-gray-500">Chưa có giao dịch nào.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($userTransactions as $t): ?>
                            <div class="border border-gray-100 rounded-xl p-4">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="font-semibold text-gray-800">
                                            <?php echo $t['type'] === 'topup' ? 'Nạp tiền' : 'Thanh toán'; ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?>
                                        </p>
                                    </div>
                                    <p class="font-bold <?php echo $t['type'] === 'topup' ? 'text-green-600' : 'text-red-600'; ?>">
                                        <?php echo $t['type'] === 'topup' ? '+' : '-'; ?>
                                        <?php echo number_format($t['amount'], 0, ',', '.'); ?> ₫
                                    </p>
                                </div>
                                <?php if (!empty($t['description'])): ?>
                                <p class="text-xs text-gray-400 mt-1"><?php echo htmlspecialchars($t['description']); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-300 py-12">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <div class="flex items-center space-x-2 mb-4">
                        <div class="bg-gradient-to-r from-green-500 to-emerald-600 p-2 rounded-xl">
                            <i class="fas fa-shuttlecock text-white"></i>
                        </div>
                        <span class="font-bold text-xl text-white">BadmintonPro</span>
                    </div>
                    <p class="text-sm">Hệ thống đặt sân cầu lông thông minh số 1 Việt Nam</p>
                </div>
                <div>
                    <h4 class="font-bold text-white mb-4">Liên Kết Nhanh</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="hover:text-green-400 transition">Về Chúng Tôi</a></li>
                        <li><a href="#" class="hover:text-green-400 transition">Chính Sách Bảo Mật</a></li>
                        <li><a href="#" class="hover:text-green-400 transition">Điều Khoản Sử Dụng</a></li>
                        <li><a href="#" class="hover:text-green-400 transition">Liên Hệ</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-bold text-white mb-4">Kết Nối</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="hover:text-green-400 transition"><i class="fab fa-facebook mr-2"></i>Facebook</a></li>
                        <li><a href="#" class="hover:text-green-400 transition"><i class="fab fa-instagram mr-2"></i>Instagram</a></li>
                        <li><a href="#" class="hover:text-green-400 transition"><i class="fab fa-tiktok mr-2"></i>TikTok</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-bold text-white mb-4">Liên Hệ</h4>
                    <ul class="space-y-2 text-sm">
                        <li><i class="fas fa-phone-alt mr-2"></i>1900 1234</li>
                        <li><i class="fas fa-envelope mr-2"></i>support@badmintonpro.com</li>
                        <li><i class="fas fa-map-marker-alt mr-2"></i>Hà Nội, Việt Nam</li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-800 mt-8 pt-8 text-center text-sm">
                <p>&copy; 2026 BadmintonPro. All rights reserved (Nhóm 10 thực hiện).</p>
            </div>
        </div>
    </footer>

    <!-- ========== MODALS ========== -->
    
    <!-- Modal Nạp Tiền - ĐÃ SỬA, BỎ PHƯƠNG THỨC VÍ BADMINTONPRO -->
    <div id="topup-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 overflow-y-auto py-8">
        <div class="bg-white rounded-2xl max-w-2xl w-full mx-4 modal-enter">
            <div class="p-6 border-b border-gray-100">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-2xl font-bold">Nạp Tiền Vào Ví</h3>
                        <p class="text-gray-500 text-sm mt-1">Chọn phương thức và số tiền muốn nạp</p>
                    </div>
                    <button onclick="hideModal('topup')" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            
            <form method="POST" id="topupForm" class="p-6">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="topup">
                <input type="hidden" name="payment_method" id="selectedPaymentMethod" value="bank_transfer">
                
                <!-- Số tiền nạp -->
                <div class="mb-6">
                    <label class="block text-gray-700 font-semibold mb-3">Số tiền nạp</label>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-3">
                        <button type="button" onclick="setTopupAmount(50000)" class="amount-preset-btn py-2 px-3 bg-gray-100 rounded-lg hover:bg-green-100 transition text-sm font-medium">50,000₫</button>
                        <button type="button" onclick="setTopupAmount(100000)" class="amount-preset-btn py-2 px-3 bg-gray-100 rounded-lg hover:bg-green-100 transition text-sm font-medium">100,000₫</button>
                        <button type="button" onclick="setTopupAmount(200000)" class="amount-preset-btn py-2 px-3 bg-gray-100 rounded-lg hover:bg-green-100 transition text-sm font-medium">200,000₫</button>
                        <button type="button" onclick="setTopupAmount(500000)" class="amount-preset-btn py-2 px-3 bg-gray-100 rounded-lg hover:bg-green-100 transition text-sm font-medium">500,000₫</button>
                        <button type="button" onclick="setTopupAmount(1000000)" class="amount-preset-btn py-2 px-3 bg-gray-100 rounded-lg hover:bg-green-100 transition text-sm font-medium">1,000,000₫</button>
                    </div>
                    <div class="relative">
                        <span class="absolute left-4 top-3 text-gray-500">₫</span>
                        <input type="number" name="amount" id="topupAmount" step="10000" min="10000" max="10000000" 
                               class="w-full px-4 py-3 pl-8 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-200" 
                               placeholder="Nhập số tiền" required oninput="validateAmount(this)">
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Tối thiểu 10,000 ₫ - Tối đa 10,000,000 ₫</p>
                </div>
                
                <!-- Phương thức thanh toán - ĐÃ BỎ VÍ BADMINTONPRO -->
                <div class="mb-6">
                    <label class="block text-gray-700 font-semibold mb-3">Phương thức thanh toán</label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <!-- Chuyển khoản ngân hàng -->
                        <div class="payment-method-card border-2 rounded-xl p-4 selected" onclick="selectPaymentMethod('bank_transfer')" id="method-bank_transfer">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-university text-blue-600 text-xl"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="font-semibold text-gray-800">Chuyển khoản ngân hàng</div>
                                    <div class="text-xs text-gray-500">Hỗ trợ tất cả các ngân hàng</div>
                                </div>
                                <div class="w-5 h-5 rounded-full border-2 border-green-500 flex items-center justify-center">
                                    <div class="w-3 h-3 rounded-full bg-green-500"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Thẻ ATM nội địa -->
                        <div class="payment-method-card border-2 rounded-xl p-4" onclick="selectPaymentMethod('atm_card')" id="method-atm_card">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-credit-card text-purple-600 text-xl"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="font-semibold text-gray-800">Thẻ ATM nội địa</div>
                                    <div class="text-xs text-gray-500">Napas, Visa, Mastercard</div>
                                </div>
                                <div class="w-5 h-5 rounded-full border-2 border-gray-300 flex items-center justify-center">
                                    <div class="w-3 h-3 rounded-full bg-green-500 hidden"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Momo -->
                        <div class="payment-method-card border-2 rounded-xl p-4" onclick="selectPaymentMethod('momo')" id="method-momo">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-mobile-alt text-red-600 text-xl"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="font-semibold text-gray-800">Ví Momo</div>
                                    <div class="text-xs text-gray-500">Thanh toán qua ứng dụng Momo</div>
                                </div>
                                <div class="w-5 h-5 rounded-full border-2 border-gray-300 flex items-center justify-center">
                                    <div class="w-3 h-3 rounded-full bg-green-500 hidden"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Thông tin khuyến mãi -->
                <div class="mb-6 p-4 bg-yellow-50 rounded-xl border border-yellow-200">
                    <div class="flex items-start">
                        <i class="fas fa-gift text-yellow-600 mt-0.5 mr-3"></i>
                        <div>
                            <p class="text-sm font-semibold text-yellow-800">🎉 Khuyến mãi đặc biệt</p>
                            <p class="text-xs text-yellow-700">Nạp lần đầu tặng ngay 10% giá trị giao dịch. Nạp từ 500,000₫ nhận thêm 50,000₫</p>
                        </div>
                    </div>
                </div>
                
                <!-- Nút xác nhận -->
                <button type="submit" class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white py-3 rounded-xl font-semibold hover:shadow-lg transition duration-300">
                    <i class="fas fa-arrow-right mr-2"></i>
                    Tiến hành nạp tiền
                </button>
            </form>
        </div>
    </div>

    <!-- Modal Đăng Ký Làm Chủ Sân -->
    <div id="registerOwner-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 overflow-y-auto py-8">
        <div class="bg-white rounded-2xl p-8 max-w-md w-full mx-4 my-auto modal-enter">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold">Đăng Ký Làm Chủ Sân</h3>
                <button onclick="hideModal('registerOwner')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="register_owner">
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 font-medium">Họ và Tên *</label>
                    <input type="text" name="full_name" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 font-medium">Tên Đăng Nhập *</label>
                    <input type="text" name="username" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 font-medium">Số Điện Thoại *</label>
                    <input type="tel" name="phone_number" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 font-medium">Email</label>
                    <input type="email" name="email" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500">
                </div>
                <div class="mb-6">
                    <label class="block text-gray-700 mb-2 font-medium">Mật Khẩu *</label>
                    <input type="password" name="password" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500" required>
                    <p class="text-xs text-gray-500 mt-1">Tối thiểu 6 ký tự</p>
                </div>
                <button type="submit" class="w-full bg-gradient-to-r from-yellow-500 to-orange-500 text-white py-3 rounded-xl font-semibold hover:shadow-lg transition duration-300">
                    Đăng Ký Làm Chủ Sân
                </button>
            </form>
        </div>
    </div>

    <!-- Modal Đăng Ký Làm Chủ Sân (Quick) -->
    <div id="ownerRegisterQuick-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 overflow-y-auto py-8">
        <div class="bg-white rounded-2xl p-8 max-w-md w-full mx-4 my-auto modal-enter">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h3 class="text-2xl font-bold">Trở Thành Chủ Sân</h3>
                    <p class="text-sm text-gray-500 mt-1">Bắt đầu kiếm tiền ngay hôm nay</p>
                </div>
                <button onclick="hideModal('ownerRegisterQuick')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="register_owner">
                <div class="mb-3">
                    <label class="block text-gray-700 mb-1 font-medium">Họ và Tên *</label>
                    <input type="text" name="full_name" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-orange-500" required placeholder="Nguyễn Văn A">
                </div>
                <div class="mb-3">
                    <label class="block text-gray-700 mb-1 font-medium">Tên Đăng Nhập *</label>
                    <input type="text" name="username" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-orange-500" required placeholder="sanbadminton">
                </div>
                <div class="mb-3">
                    <label class="block text-gray-700 mb-1 font-medium">Số Điện Thoại *</label>
                    <input type="tel" name="phone_number" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-orange-500" required placeholder="0912345678">
                </div>
                <div class="mb-3">
                    <label class="block text-gray-700 mb-1 font-medium">Email *</label>
                    <input type="email" name="email" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-orange-500" required placeholder="you@example.com">
                </div>
                <div class="mb-6">
                    <label class="block text-gray-700 mb-1 font-medium">Mật Khẩu *</label>
                    <input type="password" name="password" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-orange-500" required placeholder="••••••••">
                    <p class="text-xs text-gray-500 mt-1">Tối thiểu 6 ký tự</p>
                </div>
                <button type="submit" class="w-full bg-gradient-to-r from-yellow-500 to-orange-500 text-white py-3 rounded-xl font-semibold hover:shadow-lg transition duration-300">
                    Đăng Ký Ngay
                </button>
                <p class="text-center text-sm text-gray-600 mt-4">
                    Đã có tài khoản? 
                    <a href="#" onclick="hideModal('ownerRegisterQuick'); showModal('ownerLogin'); return false;" class="text-orange-600 font-semibold hover:text-orange-700">Đăng Nhập</a>
                </p>
            </form>
        </div>
    </div>

    <!-- Modal Đăng Nhập -->
    <div id="login-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-8 max-w-md w-full mx-4 modal-enter">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold">Đăng Nhập</h3>
                <button onclick="hideModal('login')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="role" value="customer">
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 font-medium">Tên Đăng Nhập</label>
                    <input type="text" name="username" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 font-medium">Mật Khẩu</label>
                    <input type="password" name="password" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500" required>
                </div>
                <button type="submit" class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white py-3 rounded-xl font-semibold hover:shadow-lg transition duration-300">
                    Đăng Nhập
                </button>
                <p class="text-center mt-4">
                    <a href="#" onclick="hideModal('login'); showModal('passwordResetRequest'); return false;" class="text-sm text-green-600 hover:text-green-700">Quên mật khẩu?</a>
                </p>
                <p class="text-center text-sm text-gray-600 mt-4">
                    Chưa có tài khoản? 
                    <a href="#" onclick="hideModal('login'); showModal('register'); return false;" class="text-green-600 font-semibold">Đăng ký ngay</a>
                </p>
            </form>
        </div>
    </div>

    <!-- Modal Đăng Nhập Chủ Sân -->
    <div id="ownerLogin-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-8 max-w-md w-full mx-4 modal-enter">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold">Đăng Nhập Chủ Sân</h3>
                <button onclick="hideModal('ownerLogin')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="role" value="owner">
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 font-medium">Tên Đăng Nhập</label>
                    <input type="text" name="username" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-orange-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 font-medium">Mật Khẩu</label>
                    <input type="password" name="password" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-orange-500" required>
                </div>
                <button type="submit" class="w-full bg-gradient-to-r from-yellow-500 to-orange-500 text-white py-3 rounded-xl font-semibold hover:shadow-lg transition duration-300">
                    Đăng Nhập Chủ Sân
                </button>
                <p class="text-center mt-4">
                    <a href="#" onclick="hideModal('ownerLogin'); showModal('ownerRegisterQuick'); return false;" class="text-sm text-orange-600 hover:text-orange-700">Chưa có tài khoản? Đăng ký ngay</a>
                </p>
            </form>
        </div>
    </div>

    <!-- Modal Đăng Ký -->
    <div id="register-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 overflow-y-auto py-8">
        <div class="bg-white rounded-2xl p-8 max-w-md w-full mx-4 my-auto modal-enter">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold">Đăng Ký Tài Khoản</h3>
                <button onclick="hideModal('register')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="register">
                <div class="mb-3">
                    <label class="block text-gray-700 mb-1 font-medium">Họ và Tên *</label>
                    <input type="text" name="full_name" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500" required>
                </div>
                <div class="mb-3">
                    <label class="block text-gray-700 mb-1 font-medium">Tên Đăng Nhập *</label>
                    <input type="text" name="username" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500" required>
                </div>
                <div class="mb-3">
                    <label class="block text-gray-700 mb-1 font-medium">Số Điện Thoại *</label>
                    <input type="tel" name="phone_number" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500" required>
                </div>
                <div class="mb-3">
                    <label class="block text-gray-700 mb-1 font-medium">Email</label>
                    <input type="email" name="email" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 mb-1 font-medium">Mật Khẩu *</label>
                    <input type="password" name="password" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500" required>
                    <p class="text-xs text-gray-500 mt-1">Tối thiểu 6 ký tự</p>
                </div>
                <button type="submit" class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white py-3 rounded-xl font-semibold hover:shadow-lg transition duration-300">
                    Đăng Ký
                </button>
                <p class="text-center text-sm text-gray-600 mt-4">
                    Đã có tài khoản? 
                    <a href="#" onclick="hideModal('register'); showModal('login'); return false;" class="text-green-600 font-semibold">Đăng nhập</a>
                </p>
            </form>
        </div>
    </div>

    <!-- Modal Đặt Sân -->
    <div id="booking-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-8 max-w-lg w-full mx-4 modal-enter">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold">Đặt Sân</h3>
                <button onclick="hideModal('booking')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form method="POST" id="bookingForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="booking">
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 font-medium">Chọn Sân</label>
                    <select name="court_id" id="court-select" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500" required>
                        <option value="">Chọn sân cầu lông</option>
                        <?php foreach ($courts as $court): ?>
                        <option value="<?php echo $court['court_id']; ?>" data-price="<?php echo $court['price_per_hour']; ?>">
                            <?php echo htmlspecialchars($court['court_name']); ?> - <?php echo number_format($court['price_per_hour'], 0, ',', '.'); ?> ₫/giờ
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 font-medium">Ngày Đặt</label>
                    <input type="date" id="booking-date" name="booking_date" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 font-medium">Giờ Bắt Đầu</label>
                    <select id="booking-start-time" name="start_time" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500" required>
                        <option value="">Chọn giờ</option>
                    </select>
                    <div id="loading-slots" class="hidden text-center py-2">
                        <div class="loading-spinner mx-auto"></div>
                        <p class="text-xs text-gray-500 mt-1">Đang tải khung giờ...</p>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 font-medium">Thời Gian (giờ)</label>
                    <select id="booking-duration" name="duration" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500">
                        <option value="1">1 giờ</option>
                        <option value="1.5">1.5 giờ</option>
                        <option value="2">2 giờ</option>
                        <option value="2.5">2.5 giờ</option>
                        <option value="3">3 giờ</option>
                    </select>
                </div>
                <div class="mb-6 p-4 bg-green-50 rounded-xl">
                    <p class="text-gray-700 font-medium">Tổng Tiền: <span id="total-price" class="text-2xl font-bold text-green-600">0 ₫</span></p>
                </div>
                <button type="submit" class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white py-3 rounded-xl font-semibold hover:shadow-lg transition duration-300">
                    Xác Nhận Đặt Sân
                </button>
            </form>
        </div>
    </div>

    <!-- Modal Đổi Mật Khẩu -->
    <div id="changePassword-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-8 max-w-md w-full mx-4 modal-enter">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold">Đổi Mật Khẩu</h3>
                <button onclick="hideModal('changePassword')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="change_password">
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 font-medium">Mật Khẩu Hiện Tại</label>
                    <input type="password" name="old_password" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 font-medium">Mật Khẩu Mới</label>
                    <input type="password" name="new_password" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500" required>
                    <p class="text-xs text-gray-500 mt-1">Tối thiểu 6 ký tự</p>
                </div>
                <button type="submit" class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white py-3 rounded-xl font-semibold hover:shadow-lg transition duration-300">
                    Cập Nhật Mật Khẩu
                </button>
            </form>
        </div>
    </div>

    <!-- Modal Yêu Cầu Đặt Lại Mật Khẩu -->
    <div id="passwordResetRequest-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-8 max-w-md w-full mx-4 modal-enter">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold">Quên Mật Khẩu</h3>
                <button onclick="hideModal('passwordResetRequest')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="request_password_reset">
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 font-medium">Email</label>
                    <input type="email" name="email" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500" required>
                    <p class="text-xs text-gray-500 mt-1">Chúng tôi sẽ gửi mã xác nhận qua email của bạn</p>
                </div>
                <button type="submit" class="w-full bg-gradient-to-r from-yellow-500 to-orange-500 text-white py-3 rounded-xl font-semibold hover:shadow-lg transition duration-300">
                    Gửi Yêu Cầu
                </button>
            </form>
        </div>
    </div>

    <!-- Modal Hỗ Trợ -->
    <div id="supportModal-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-8 max-w-md w-full mx-4 modal-enter">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold">Gửi Yêu Cầu Hỗ Trợ</h3>
                <button onclick="hideModal('supportModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="support_ticket">
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 font-medium">Chủ Đề</label>
                    <input type="text" name="ticket_subject" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 font-medium">Nội Dung</label>
                    <textarea name="ticket_message" rows="4" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500" required></textarea>
                </div>
                <button type="submit" class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white py-3 rounded-xl font-semibold hover:shadow-lg transition duration-300">
                    Gửi Yêu Cầu
                </button>
            </form>
        </div>
    </div>

    <!-- Nút Hỗ Trợ Nổi -->
    <button onclick="showModal('supportModal')" class="fixed bottom-6 right-6 bg-gradient-to-r from-green-600 to-emerald-600 text-white w-14 h-14 rounded-full shadow-lg flex items-center justify-center hover:shadow-2xl transition duration-300 z-40 group">
        <i class="fas fa-headset text-xl group-hover:scale-110 transition"></i>
    </button>

    <script>
        // ========== GLOBAL FUNCTIONS ==========
        
        // Sửa lại hàm showModal để đảm bảo luôn hoạt động
function showModal(modalId) {
    console.log('Opening modal:', modalId);
    var modal = document.getElementById(modalId + '-modal');
    if (modal) {
        modal.classList.remove('hidden');
        // Đảm bảo modal hiển thị đúng cách
        modal.style.display = 'flex';
        if (modalId === 'booking') {
            initBookingModal();
        }
    } else {
        console.error('Modal not found:', modalId + '-modal');
        // Thử tìm kiếm với các ID khác
        var altModal = document.getElementById(modalId);
        if (altModal) {
            altModal.classList.remove('hidden');
            altModal.style.display = 'flex';
        } else {
            alert('Lỗi: Không tìm thấy cửa sổ đăng nhập. Vui lòng tải lại trang.');
        }
    }
}

function hideModal(modalId) {
    console.log('Closing modal:', modalId);
    var modal = document.getElementById(modalId + '-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.style.display = 'none';
    } else {
        var altModal = document.getElementById(modalId);
        if (altModal) {
            altModal.classList.add('hidden');
            altModal.style.display = 'none';
        }
    }
}

        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList && (e.target.classList.contains('bg-black') || e.target.classList.contains('bg-opacity-50'))) {
                e.target.classList.add('hidden');
            }
        });

        // Topup amount presets
        function setTopupAmount(amount) {
            var amountInput = document.getElementById('topupAmount');
            if (amountInput) {
                amountInput.value = amount;
                validateAmount(amountInput);
            }
            
            // Highlight active preset
            document.querySelectorAll('.amount-preset-btn').forEach(btn => {
                btn.classList.remove('active', 'bg-green-600', 'text-white');
                btn.classList.add('bg-gray-100');
            });
            event.target.classList.add('active', 'bg-green-600', 'text-white');
            event.target.classList.remove('bg-gray-100');
        }
        
        function validateAmount(input) {
            var value = parseInt(input.value);
            var min = 10000;
            var max = 10000000;
            
            if (value < min) {
                input.setCustomValidity('Số tiền tối thiểu là 10,000₫');
            } else if (value > max) {
                input.setCustomValidity('Số tiền tối đa là 10,000,000₫');
            } else {
                input.setCustomValidity('');
            }
        }
        
        // Select payment method - ĐÃ CẬP NHẬT
        function selectPaymentMethod(method) {
            document.getElementById('selectedPaymentMethod').value = method;
            
            // Update UI
            const methods = ['bank_transfer', 'atm_card', 'momo'];
            methods.forEach(m => {
                const card = document.getElementById(`method-${m}`);
                if (card) {
                    const radio = card.querySelector('.w-3.h-3');
                    if (m === method) {
                        card.classList.add('selected', 'border-green-500', 'bg-green-50');
                        card.classList.remove('border-gray-300');
                        if (radio) radio.classList.remove('hidden');
                    } else {
                        card.classList.remove('selected', 'border-green-500', 'bg-green-50');
                        card.classList.add('border-gray-300');
                        if (radio) radio.classList.add('hidden');
                    }
                }
            });
        }

        // Filter panel toggle
        function toggleFilters() {
            var panel = document.getElementById('filterPanel');
            if (panel) panel.classList.toggle('hidden');
        }

        // Update sort and reload
        function updateSort() {
            var sortSelect = document.getElementById('sortSelect');
            if (sortSelect) {
                var url = new URL(window.location.href);
                url.searchParams.set('sort_by', sortSelect.value);
                url.searchParams.set('page', '1');
                window.location.href = url.toString();
            }
        }

        // Toggle favorites filter
        function toggleFavorites() {
            var url = new URL(window.location.href);
            if (url.searchParams.get('favorites') === '1') {
                url.searchParams.delete('favorites');
            } else {
                url.searchParams.set('favorites', '1');
            }
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }

        // Toggle favorite for a court (AJAX)
        async function toggleFavorite(courtId, button) {
            if (!<?php echo $isLoggedIn ? 'true' : 'false'; ?>) {
                showModal('login');
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'toggle_favorite');
                formData.append('court_id', courtId);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const icon = button.querySelector('i');
                    if (data.data.favorited) {
                        icon.classList.remove('text-gray-400');
                        icon.classList.add('text-red-500');
                        showToast('Đã thêm vào yêu thích', 'success');
                    } else {
                        icon.classList.remove('text-red-500');
                        icon.classList.add('text-gray-400');
                        showToast('Đã xóa khỏi yêu thích', 'info');
                    }
                } else {
                    showToast(data.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Có lỗi xảy ra', 'error');
            }
        }

        // Show toast notification
        function showToast(message, type = 'success') {
            var toast = document.createElement('div');
            toast.className = 'toast';
            toast.innerHTML = `
                <div class="p-4 rounded-xl shadow-lg max-w-md ${type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'}">
                    <div class="flex items-center space-x-3">
                        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} text-xl"></i>
                        <span>${message}</span>
                        <button onclick="this.parentElement.parentElement.remove()" class="ml-auto text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 5000);
        }

        // Booking modal initialization
        async function initBookingModal() {
            var dateInput = document.getElementById('booking-date');
            if (dateInput && !dateInput.value) {
                var today = new Date().toISOString().split('T')[0];
                dateInput.min = today;
                dateInput.value = today;
            }
            
            var courtSelect = document.getElementById('court-select');
            var dateSelect = document.getElementById('booking-date');
            
            if (courtSelect && dateSelect) {
                courtSelect.addEventListener('change', loadAvailableSlots);
                dateSelect.addEventListener('change', loadAvailableSlots);
                
                if (courtSelect.value) {
                    await loadAvailableSlots();
                }
            }
            
            updateTotalPrice();
        }

        // Load available time slots via AJAX
        async function loadAvailableSlots() {
            var courtId = document.getElementById('court-select')?.value;
            var date = document.getElementById('booking-date')?.value;
            var startTimeSelect = document.getElementById('booking-start-time');
            var loadingDiv = document.getElementById('loading-slots');
            
            if (!courtId || !date) return;
            
            if (loadingDiv) loadingDiv.classList.remove('hidden');
            if (startTimeSelect) startTimeSelect.innerHTML = '<option value="">Đang tải...</option>';
            
            try {
                const formData = new FormData();
                formData.append('action', 'get_available_slots');
                formData.append('court_id', courtId);
                formData.append('date', date);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                if (loadingDiv) loadingDiv.classList.add('hidden');
                
                if (data.success && startTimeSelect) {
                    var slots = data.data.slots;
                    startTimeSelect.innerHTML = '<option value="">Chọn giờ</option>';
                    
                    if (slots.length === 0) {
                        startTimeSelect.innerHTML += '<option value="" disabled>Không có khung giờ trống</option>';
                    } else {
                        slots.forEach(slot => {
                            startTimeSelect.innerHTML += `<option value="${slot}">${slot}</option>`;
                        });
                    }
                }
            } catch (error) {
                console.error('Error loading slots:', error);
                if (loadingDiv) loadingDiv.classList.add('hidden');
                if (startTimeSelect) startTimeSelect.innerHTML = '<option value="">Lỗi tải dữ liệu</option>';
            }
        }

        // Calculate total price for booking
        function updateTotalPrice() {
            var courtSelect = document.getElementById('court-select');
            var durationSelect = document.getElementById('booking-duration');
            var totalPriceSpan = document.getElementById('total-price');

            if (courtSelect && durationSelect && totalPriceSpan && courtSelect.selectedIndex > 0) {
                var selectedOption = courtSelect.options[courtSelect.selectedIndex];
                var pricePerHour = parseFloat(selectedOption.dataset.price) || 0;
                var duration = parseFloat(durationSelect.value);

                var total = pricePerHour * duration;
                totalPriceSpan.textContent = total.toLocaleString('vi-VN') + ' ₫';
            } else {
                totalPriceSpan.textContent = '0 ₫';
            }
        }

        // Setup event listeners when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            var courtSelect = document.getElementById('court-select');
            var durationSelect = document.getElementById('booking-duration');
            
            if (courtSelect) courtSelect.addEventListener('change', updateTotalPrice);
            if (durationSelect) durationSelect.addEventListener('change', updateTotalPrice);
            
            updateTotalPrice();
        });

        // Auto-open owner login if URL includes ?owner_login=1
        if (window.location.search.includes('owner_login=1')) {
            showModal('ownerLogin');
        }

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>

    <!-- Chatbot Widget (chỉ hiển thị khi đã đăng nhập) -->
    <?php if ($isLoggedIn): ?>
    <div class="chat-widget-btn" id="chatWidgetBtn">
        <i class="fas fa-comment-dots"></i>
        <span class="chat-badge" id="chatBadge" style="display: none;">1</span>
    </div>

    <div class="chat-window" id="chatWindow">
        <div class="chat-header">
            <div class="chat-header-left">
                <div class="chat-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="chat-header-info">
                    <h4>Trợ lý BadmintonPro</h4>
                    <p>Online • Hỗ trợ 24/7</p>
                </div>
            </div>
            <button class="chat-close" id="chatCloseBtn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="chat-messages" id="chatMessages">
            <div class="message bot">
                <div class="message-bubble">
                    👋 Chào bạn! Mình là trợ lý AI của BadmintonPro.<br>
                    Mình có thể giúp bạn:<br>
                    • 🔍 Tìm sân trống<br>
                    • 📅 Đặt sân nhanh<br>
                    • 📋 Xem lịch đặt<br>
                    • 💰 Tra cứu giá sân<br>
                    • 🏸 Gợi ý sân theo thói quen<br><br>
                    Bạn cần mình giúp gì ạ?
                </div>
            </div>
        </div>
        
        <div class="quick-replies">
            <span class="quick-reply" data-msg="tìm sân trống 19h tối nay">🔍 Tìm sân 19h</span>
            <span class="quick-reply" data-msg="xem lịch đặt của tôi">📋 Xem lịch đặt</span>
            <span class="quick-reply" data-msg="gợi ý sân">🏸 Gợi ý sân</span>
            <span class="quick-reply" data-msg="giá sân bao nhiêu">💰 Giá sân</span>
        </div>
        
        <div class="chat-input-area">
            <input type="text" id="chatInput" placeholder="Nhập tin nhắn..." onkeypress="if(event.key==='Enter') sendMessage()">
            <button onclick="sendMessage()">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>

    <style>
        .chat-widget-btn {
            position: fixed;
            bottom: 100px;
            right: 24px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #2d8f4e 0%, #1a6b3a 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            z-index: 1000;
        }
        .chat-widget-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }
        .chat-widget-btn i {
            font-size: 28px;
            color: white;
        }
        .chat-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .chat-window {
            position: fixed;
            bottom: 170px;
            right: 24px;
            width: 380px;
            height: 550px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            display: none;
            flex-direction: column;
            overflow: hidden;
            z-index: 1001;
            animation: slideUp 0.3s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .chat-window.open { display: flex; }
        .chat-header {
            background: linear-gradient(135deg, #2d8f4e 0%, #1a6b3a 100%);
            color: white;
            padding: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .chat-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .chat-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        .chat-header-info h4 { margin: 0; font-size: 16px; font-weight: 600; }
        .chat-header-info p { margin: 0; font-size: 11px; opacity: 0.8; }
        .chat-close {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            opacity: 0.8;
        }
        .chat-close:hover { opacity: 1; }
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            background: #f5f7fb;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .message {
            display: flex;
            animation: fadeInMsg 0.3s ease;
        }
        @keyframes fadeInMsg {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .message.bot { justify-content: flex-start; }
        .message.user { justify-content: flex-end; }
        .message-bubble {
            max-width: 80%;
            padding: 10px 14px;
            border-radius: 18px;
            font-size: 14px;
            line-height: 1.4;
            word-wrap: break-word;
        }
        .message.bot .message-bubble {
            background: white;
            color: #1f2937;
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        .message.user .message-bubble {
            background: linear-gradient(135deg, #2d8f4e 0%, #1a6b3a 100%);
            color: white;
            border-bottom-right-radius: 4px;
        }
        .typing-indicator {
            display: flex;
            gap: 4px;
            padding: 10px 14px;
            background: white;
            border-radius: 18px;
            border-bottom-left-radius: 4px;
            width: fit-content;
        }
        .typing-indicator span {
            width: 8px;
            height: 8px;
            background: #9ca3af;
            border-radius: 50%;
            animation: typing 1.4s infinite ease-in-out;
        }
        .typing-indicator span:nth-child(1) { animation-delay: 0s; }
        .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
            30% { transform: translateY(-8px); opacity: 1; }
        }
        .chat-input-area {
            padding: 12px 16px;
            background: white;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 10px;
        }
        .chat-input-area input {
            flex: 1;
            padding: 10px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 25px;
            outline: none;
            font-size: 14px;
        }
        .chat-input-area input:focus { border-color: #2d8f4e; }
        .chat-input-area button {
            background: linear-gradient(135deg, #2d8f4e 0%, #1a6b3a 100%);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            color: white;
            cursor: pointer;
        }
        .quick-replies {
            padding: 8px 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            background: #f5f7fb;
            border-top: 1px solid #e5e7eb;
        }
        .quick-reply {
            background: #e5e7eb;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            cursor: pointer;
        }
        @media (max-width: 480px) {
            .chat-window {
                width: calc(100% - 40px);
                right: 20px;
                left: 20px;
                height: 500px;
            }
        }
    </style>

    <script>
        let isTyping = false;
        let currentUserId = <?php echo $_SESSION['user_id']; ?>;
        
        const chatWidgetBtn = document.getElementById('chatWidgetBtn');
        const chatWindow = document.getElementById('chatWindow');
        const chatCloseBtn = document.getElementById('chatCloseBtn');
        const chatMessages = document.getElementById('chatMessages');
        const chatInput = document.getElementById('chatInput');
        const chatBadge = document.getElementById('chatBadge');
        
        if (chatWidgetBtn) {
            chatWidgetBtn.addEventListener('click', () => {
                chatWindow.classList.toggle('open');
                if (chatBadge) chatBadge.style.display = 'none';
                if (chatInput) chatInput.focus();
            });
        }
        if (chatCloseBtn) {
            chatCloseBtn.addEventListener('click', () => {
                chatWindow.classList.remove('open');
            });
        }
        
        document.querySelectorAll('.quick-reply').forEach(btn => {
            btn.addEventListener('click', () => {
                const msg = btn.dataset.msg;
                if (msg) {
                    chatInput.value = msg;
                    sendMessage();
                }
            });
        });
        
        function addMessage(text, isUser = false) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${isUser ? 'user' : 'bot'}`;
            messageDiv.innerHTML = `<div class="message-bubble">${text}</div>`;
            chatMessages.appendChild(messageDiv);
            scrollToBottom();
        }
        
        function showTyping() {
            if (isTyping) return;
            isTyping = true;
            const typingDiv = document.createElement('div');
            typingDiv.className = 'message bot';
            typingDiv.id = 'typingIndicator';
            typingDiv.innerHTML = `<div class="typing-indicator"><span></span><span></span><span></span></div>`;
            chatMessages.appendChild(typingDiv);
            scrollToBottom();
        }
        
        function hideTyping() {
            const indicator = document.getElementById('typingIndicator');
            if (indicator) indicator.remove();
            isTyping = false;
        }
        
        function scrollToBottom() {
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }
        
        async function sendMessage() {
            const message = chatInput.value.trim();
            if (!message) return;

            addMessage(message, true);
            chatInput.value = '';
            showTyping();

            try {
                const response = await fetch('/Badminton-Web-MySQL-test1/Badminton-Web-super-main/Badminton%20Web/api/chatbot/message.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: currentUserId, message: message })
                });

                const data = await response.json();
                hideTyping();

                if (data.success) {
                    addMessage(data.response || 'Không có phản hồi', false);
                } else {
                    addMessage('😅 ' + (data.response || 'Có lỗi xảy ra!'), false);
                }
            } catch (error) {
                console.error('Chat error:', error);
                hideTyping();
                addMessage('😅 Không thể kết nối đến server. Vui lòng thử lại sau!', false);
            }
        }
        
        if (chatWindow) {
            const observer = new MutationObserver(() => {
                if (chatWindow.classList.contains('open') && chatInput) {
                    chatInput.focus();
                }
            });
            observer.observe(chatWindow, { attributes: true });
        }
    </script>
    <?php endif; ?>
</body>
</html>