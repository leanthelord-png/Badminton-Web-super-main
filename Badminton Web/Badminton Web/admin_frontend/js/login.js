// login.js - Xử lý đăng nhập cho Admin Panel

// API Configuration
const API_BASE_URL = 'http://localhost:3000/api';

// DOM Elements
let loginForm, usernameInput, passwordInput, rememberMeCheckbox, loginButton, loginError, errorMessage;

// Initialize login module
function initLogin() {
    // Get DOM elements
    loginForm = document.getElementById('login-form');
    usernameInput = document.getElementById('username');
    passwordInput = document.getElementById('password');
    rememberMeCheckbox = document.getElementById('remember-me');
    loginButton = document.getElementById('login-btn');
    loginError = document.getElementById('login-error');
    errorMessage = document.getElementById('error-message');
    
    // Add event listeners
    if (loginForm) {
        loginForm.addEventListener('submit', handleLoginSubmit);
    }
    
    // Load saved credentials if remember me was checked
    loadSavedCredentials();
}

// Handle login form submission
async function handleLoginSubmit(e) {
    e.preventDefault();
    
    const username = usernameInput.value.trim();
    const password = passwordInput.value;
    const rememberMe = rememberMeCheckbox.checked;
    
    // Basic validation
    if (!username || !password) {
        showLoginError('Vui lòng nhập tên đăng nhập và mật khẩu');
        return;
    }
    
    // Show loading state
    setLoginButtonLoading(true);
    
    try {
        const result = await login(username, password);
        
        if (result.success) {
            // Save token and user info
            saveAuthData(result.token, result.user, rememberMe);
            
            // Show success message
            showNotification('Đăng nhập thành công!', 'success');
            
            // Redirect to dashboard or update UI
            if (window.handleLoginSuccess) {
                window.handleLoginSuccess(result.user);
            } else {
                // Default redirect
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 1000);
            }
        } else {
            showLoginError(result.message || 'Đăng nhập thất bại');
        }
    } catch (error) {
        console.error('Login error:', error);
        showLoginError('Lỗi kết nối đến máy chủ');
    } finally {
        setLoginButtonLoading(false);
    }
}

// Perform login API call
async function login(username, password) {
    try {
        const response = await fetch(`${API_BASE_URL}/auth/login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                username: username,
                password: password
            })
        });
        
        // Check if response is OK
        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`HTTP ${response.status}: ${errorText}`);
        }
        
        const data = await response.json();
        return data;
        
    } catch (error) {
        // Handle network errors
        if (error.name === 'TypeError') {
            throw new Error('Không thể kết nối đến máy chủ. Vui lòng kiểm tra kết nối mạng.');
        }
        throw error;
    }
}

// Save authentication data
function saveAuthData(token, user, rememberMe) {
    // Save token
    if (rememberMe) {
        localStorage.setItem('admin_token', token);
        localStorage.setItem('admin_user', JSON.stringify(user));
    } else {
        sessionStorage.setItem('admin_token', token);
        sessionStorage.setItem('admin_user', JSON.stringify(user));
    }
    
    // Also save to memory for immediate use
    window.authToken = token;
    window.currentUser = user;
    
    // Save remember me preference
    localStorage.setItem('remember_me', rememberMe.toString());
    if (rememberMe) {
        localStorage.setItem('saved_username', user.username);
    } else {
        localStorage.removeItem('saved_username');
    }
}

// Load saved credentials
function loadSavedCredentials() {
    const rememberMe = localStorage.getItem('remember_me') === 'true';
    const savedUsername = localStorage.getItem('saved_username');
    
    if (rememberMe && savedUsername) {
        usernameInput.value = savedUsername;
        rememberMeCheckbox.checked = true;
        passwordInput.focus();
    }
}

// Show login error
function showLoginError(message) {
    errorMessage.textContent = message;
    loginError.classList.remove('hidden');
    
    // Auto hide after 5 seconds
    setTimeout(() => {
        loginError.classList.add('hidden');
    }, 5000);
}

// Set login button loading state
function setLoginButtonLoading(isLoading) {
    if (isLoading) {
        loginButton.disabled = true;
        loginButton.innerHTML = `
            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                <i class="fas fa-spinner fa-spin"></i>
            </span>
            Đang đăng nhập...
        `;
        loginButton.classList.add('opacity-75', 'cursor-not-allowed');
    } else {
        loginButton.disabled = false;
        loginButton.innerHTML = `
            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                <i class="fas fa-sign-in-alt"></i>
            </span>
            Đăng nhập
        `;
        loginButton.classList.remove('opacity-75', 'cursor-not-allowed');
    }
}

// Check if user is already logged in
async function checkAuthStatus() {
    const token = localStorage.getItem('admin_token') || sessionStorage.getItem('admin_token');
    const user = localStorage.getItem('admin_user') || sessionStorage.getItem('admin_user');
    
    if (token && user) {
        try {
            // Verify token with server
            const response = await fetch(`${API_BASE_URL}/auth/verify`, {
                headers: {
                    'Authorization': `Bearer ${token}`
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.valid) {
                    window.authToken = token;
                    window.currentUser = JSON.parse(user);
                    return true;
                }
            }
        } catch (error) {
            console.error('Auth verification failed:', error);
        }
    }
    
    // Clear invalid auth data
    clearAuthData();
    return false;
}

// Clear authentication data
function clearAuthData() {
    localStorage.removeItem('admin_token');
    localStorage.removeItem('admin_user');
    sessionStorage.removeItem('admin_token');
    sessionStorage.removeItem('admin_user');
    delete window.authToken;
    delete window.currentUser;
}

// Logout function
function logout() {
    if (confirm('Bạn có chắc chắn muốn đăng xuất?')) {
        clearAuthData();
        showNotification('Đã đăng xuất thành công', 'info');
        
        // Redirect to login page
        setTimeout(() => {
            window.location.href = 'index.php';
        }, 1000);
    }
}

// Show notification (can be integrated with your notification system)
function showNotification(message, type = 'info') {
    // Check if notification system exists
    if (window.showNotification) {
        window.showNotification(message, type);
        return;
    }
    
    // Fallback simple notification
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg transform transition-all duration-300 translate-x-full`;
    
    const bgColor = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-yellow-500',
        info: 'bg-blue-500'
    }[type];
    
    const icon = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    }[type];
    
    notification.innerHTML = `
        <div class="${bgColor} text-white flex items-center">
            <i class="fas ${icon} mr-3"></i>
            <span>${message}</span>
            <button class="ml-4 text-white hover:text-gray-200" onclick="this.parentElement.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 10);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.classList.add('translate-x-full');
            setTimeout(() => notification.remove(), 300);
        }
    }, 5000);
}

// Password visibility toggle (optional enhancement)
function initPasswordToggle() {
    const passwordInput = document.getElementById('password');
    const toggleButton = document.createElement('button');
    toggleButton.type = 'button';
    toggleButton.className = 'absolute right-3 top-3 text-gray-400 hover:text-gray-600';
    toggleButton.innerHTML = '<i class="fas fa-eye"></i>';
    
    if (passwordInput) {
        passwordInput.parentElement.classList.add('relative');
        passwordInput.parentElement.appendChild(toggleButton);
        
        toggleButton.addEventListener('click', () => {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            toggleButton.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });
    }
}

// Keyboard shortcuts
function initKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
        // Ctrl + Enter to submit login form
        if (e.ctrlKey && e.key === 'Enter') {
            if (loginForm) {
                loginForm.requestSubmit();
            }
        }
        
        // Escape to clear form
        if (e.key === 'Escape') {
            if (document.activeElement === usernameInput || document.activeElement === passwordInput) {
                usernameInput.value = '';
                passwordInput.value = '';
                usernameInput.focus();
            }
        }
    });
}

// Form validation
function initFormValidation() {
    const inputs = [usernameInput, passwordInput];
    
    inputs.forEach(input => {
        if (input) {
            input.addEventListener('input', () => {
                if (loginError && !loginError.classList.contains('hidden')) {
                    loginError.classList.add('hidden');
                }
                
                // Real-time validation
                validateInput(input);
            });
            
            input.addEventListener('blur', () => {
                validateInput(input);
            });
        }
    });
}

function validateInput(input) {
    if (input === usernameInput) {
        if (input.value.trim().length < 3) {
            input.classList.add('border-red-500');
            return false;
        } else {
            input.classList.remove('border-red-500');
            return true;
        }
    }
    
    if (input === passwordInput) {
        if (input.value.length < 6) {
            input.classList.add('border-red-500');
            return false;
        } else {
            input.classList.remove('border-red-500');
            return true;
        }
    }
    
    return true;
}

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    initLogin();
    initPasswordToggle();
    initKeyboardShortcuts();
    initFormValidation();
    
    // Auto focus username field
    if (usernameInput) {
        usernameInput.focus();
    }
    
    // Check if user is already logged in
    checkAuthStatus().then(isLoggedIn => {
        if (isLoggedIn && window.handleAutoLogin) {
            window.handleAutoLogin(window.currentUser);
        }
    });
});

// Export functions for use in other modules
window.loginModule = {
    login: login,
    logout: logout,
    checkAuthStatus: checkAuthStatus,
    clearAuthData: clearAuthData,
    initLogin: initLogin
};