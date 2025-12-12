<?php
session_start();
// auth/login.php - Complete Login System
require_once '../config/database.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('../dashboard.php');
}

$error = '';

// Handle login form submission
if ($_POST) {
    $username_email = trim($_POST['username_email']);
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember_me']);
    
    if (empty($username_email) || empty($password)) {
        $error = "Please enter both username/email and password.";
    } else {
        $database = new Database();
        $db = $database->connect();
        
        // Find user by username or email
        $query = "SELECT * FROM users WHERE (username = :identifier OR email = :identifier) AND status != 'inactive'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':identifier', $username_email);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
            if ($user && password_verify($password, $user['password'])) {
                // Debug: Password verification successful
                error_log("Password verified successfully for user: " . $user['username']);
                
                // Successful login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                
                // Debug: Check session
                error_log("Session set - User ID: " . $_SESSION['user_id'] . ", Role: " . $_SESSION['role']);
            
            // Update last login time (using updated_at since last_login doesn't exist in schema)
            $update_login = "UPDATE users SET updated_at = NOW() WHERE id = :id";
            $update_stmt = $db->prepare($update_login);
            $update_stmt->bindParam(':id', $user['id']);
            $update_stmt->execute();
            
            // Handle remember me functionality
            if ($remember_me) {
                $remember_token = bin2hex(random_bytes(32));
                setcookie('remember_token', $remember_token, time() + (30 * 24 * 60 * 60), '/'); // 30 days
                
                // Store remember token in database (you'd need to add this column)
                // For now, we'll just set a longer session
                ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60); // 30 days
            }
            
            // Create welcome notification
            $notification_query = "INSERT INTO notifications (user_id, title, message, type) 
                                  VALUES (:user_id, 'Welcome Back!', 'You have successfully logged in.', 'success')";
            $notification_stmt = $db->prepare($notification_query);
            $notification_stmt->bindParam(':user_id', $user['id']);
            $notification_stmt->execute();
            
            // Redirect based on role or intended destination
            $redirect_url = '../dashboard.php';
            if (isset($_GET['redirect'])) {
                $redirect_url = $_GET['redirect'];
            } elseif ($user['role'] == 'admin') {
                $redirect_url = '../admin/dashboard.php';
            }
            
            showMessage("Welcome back, " . $user['first_name'] . "!", "success");
            redirect($redirect_url);
            
        } else {
            // Failed login - log the attempt
            if ($user) {
                // User exists but wrong password
                $error = "Invalid password. Please try again.";
                
                // Note: login_attempts table not in schema, so we'll skip logging for now
            } else {
                // User doesn't exist
                $error = "No account found with that username or email.";
            }
            
            // Add delay to prevent brute force attacks
            sleep(1);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sports League Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background-image: url('./bgsport.png');
            background-size: cover; 
             background-repeat: no-repeat;
     background-position: center;
    background-attachment: fixed;
           
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
            animation: slideUp 0.6s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
             background: linear-gradient(black, maroon);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .login-header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .login-form {
            padding: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #007bff;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }
        
        .form-control:hover {
            border-color: #007bff;
            background-color: white;
        }
        
        .password-toggle {
            position: relative;
        }
        
        .password-toggle-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .password-toggle-btn:hover {
            color: #007bff;
        }
        
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .remember-me input[type="checkbox"] {
            width: auto;
        }
        
        .forgot-password {
            color: #007bff;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .forgot-password:hover {
            color: #0056b3;
            text-decoration: underline;
        }
        
        .btn-login {
            width: 100%;
            padding: 0.75rem;
         background: linear-gradient(black, maroon);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-login:hover {
            background: linear-gradient(135deg, #0056b3, #004085);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .divider {
            text-align: center;
            margin: 1.5rem 0;
            position: relative;
            color: #666;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e9ecef;
        }
        
        .divider span {
            background: white;
            padding: 0 1rem;
        }
        
        .social-login {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .btn-social {
            flex: 1;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
            color: #333;
            font-weight: 600;
        }
        
        .btn-social:hover {
            border-color: #007bff;
            color: #007bff;
            transform: translateY(-2px);
        }
        
        .signup-link {
            text-align: center;
            padding-top: 1.5rem;
            border-top: 1px solid #e9ecef;
            color: #666;
        }
        
        .signup-link a {
            color: #007bff;
            text-decoration: none;
            font-weight: 600;
        }
        
        .signup-link a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .loading {
            display: none;
            text-align: center;
            margin-top: 1rem;
        }
        
        .loading-spinner {
            border: 2px solid #f3f3f3;
            border-top: 2px solid #007bff;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 0.5rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .demo-credentials {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
        }
        
        .demo-credentials h4 {
            margin-bottom: 0.5rem;
            color: #856404;
        }
        
        .demo-credentials .credential-item {
            margin-bottom: 0.25rem;
        }
        
        @media (max-width: 480px) {
            .login-container {
                margin: 10px;
            }
            
            .login-header {
                padding: 1.5rem;
            }
            
            .login-header h1 {
                font-size: 1.5rem;
            }
            
            .login-form {
                padding: 1.5rem;
            }
            
            .social-login {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Welcome Back!</h1>
            <p>Sign in to your Sports League account</p>
        </div>
        
        <div class="login-form">
            <!-- Display messages -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message_type'] ?? 'info'; ?>">
                    <?php 
                    echo $_SESSION['message'];
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <strong>Login Failed:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- Demo Credentials -->
            <div class="demo-credentials">
                <center><h4>🎯 Secured</h4></center>
               
            </div>
            
            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label for="username_email">Username or Email</label>
                    <input type="text" 
                           id="username_email" 
                           name="username_email" 
                           class="form-control" 
                           placeholder="Enter your username or email"
                           value="<?php echo htmlspecialchars($_POST['username_email'] ?? ''); ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-toggle">
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-control" 
                               placeholder="Enter your password" 
                               required>
                        <button type="button" class="password-toggle-btn" onclick="togglePassword()">
                            👁️
                        </button>
                    </div>
                </div>
                
                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember_me" value="1">
                        Remember me
                    </label>
                    <a href="forgot_password.php" class="forgot-password">Forgot password?</a>
                </div>
                
                <button type="submit" class="btn-login" id="loginBtn">
                    Sign In
                </button>
                
                <div class="loading" id="loading">
                    <div class="loading-spinner"></div>
                    Signing you in...
                </div>
            </form>
            
            <div class="divider">
                <span>or</span>
            </div>
            
            <div class="social-login">
                <a href="#" class="btn-social" onclick="showComingSoon()">
                    📧 Email Login
                </a>
                <a href="#" class="btn-social" onclick="showComingSoon()">
                    📱 SMS Login
                </a>
            </div>
            
            <div class="signup-link">
                Don't have an account? 
               <a href="../auth/register.php">Create one here</a>

            </div>
            
            <div class="signup-link" style="border-top: none; padding-top: 1rem;">
                <a href="../index.php">← Back to Home</a>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleBtn = document.querySelector('.password-toggle-btn');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleBtn.textContent = '🙈';
            } else {
                passwordField.type = 'password';
                toggleBtn.textContent = '👁️';
            }
        }
        
        function showComingSoon() {
            alert('This feature is coming soon! Please use the regular login for now.');
        }
        
        // Form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const loginBtn = document.getElementById('loginBtn');
            const loading = document.getElementById('loading');
            
            loginBtn.style.display = 'none';
            loading.style.display = 'block';
            
            // If there's an error, show the button again
            setTimeout(function() {
                if (document.querySelector('.alert-error')) {
                    loginBtn.style.display = 'block';
                    loading.style.display = 'none';
                }
            }, 1000);
        });
        
        // Auto-focus on username field
        document.getElementById('username_email').focus();
        
        // Enter key navigation
        document.getElementById('username_email').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('password').focus();
            }
        });
        
        // Quick login for demo (double-click admin)
        document.addEventListener('dblclick', function(e) {
            if (e.target.textContent.includes('Admin:')) {
                document.getElementById('username_email').value = 'admin';
                document.getElementById('password').value = 'admin123';
                document.getElementById('username_email').focus();
            }
        });
        
        // Prevent multiple form submissions
        let isSubmitting = false;
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }
            isSubmitting = true;
            
            setTimeout(function() {
                isSubmitting = false;
            }, 3000);
        });
        
        // Show password strength indicator (for future use)
        document.getElementById('password').addEventListener('input', function(e) {
            // This could show password strength in the future
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + Enter to submit
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                document.getElementById('loginForm').submit();
            }
            
            // ESC to clear form
            if (e.key === 'Escape') {
                document.getElementById('loginForm').reset();
                document.getElementById('username_email').focus();
            }
        });
    </script>
</body>
</html>