<?php
// auth/register.php - User Registration with Strong Password Requirements
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ../dashboard.php');
    exit();
}

require_once '../config/database.php';

$errors = [];
$success = '';

if ($_POST) {
    // Get form data
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role']; // 'player' or 'team_owner'
    
    // Validation
    if (empty($username)) {
        $errors[] = "Username is required";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Username can only contain letters, numbers, and underscores";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Strong Password Validation
    if (empty($password)) {
        $errors[] = "Password is required";
    } else {
        // Check minimum length
        if (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters long";
        }
        
        // Check for uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        // Check for lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        // Check for symbol/special character
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/\\|`~]/', $password)) {
            $errors[] = "Password must contain at least one special character (!@#$%^&*()_+-=[]{}etc.)";
        }
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (empty($first_name)) {
        $errors[] = "First name is required";
    }
    
    if (empty($last_name)) {
        $errors[] = "Last name is required";
    }
    
    if (!in_array($role, ['player', 'team_owner'])) {
        $errors[] = "Please select a valid role";
    }
    
    // Check if username or email already exists
    if (empty($errors)) {
        try {
            $database = new Database();
            $db = $database->connect();
            
            // Check username
            $check_username = "SELECT id FROM users WHERE username = :username";
            $stmt = $db->prepare($check_username);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $errors[] = "Username already exists";
            }
            
            // Check email
            $check_email = "SELECT id FROM users WHERE email = :email";
            $stmt = $db->prepare($check_email);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $errors[] = "Email already registered";
            }
            
        } catch (Exception $e) {
            $errors[] = "Database error occurred";
        }
    }
    
    // Register user if no errors
    if (empty($errors)) {
        try {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $insert_query = "INSERT INTO users (username, email, password, first_name, last_name, phone, role, created_at) 
                            VALUES (:username, :email, :password, :first_name, :last_name, :phone, :role, NOW())";
            
            $stmt = $db->prepare($insert_query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $password_hash);
            $stmt->bindParam(':first_name', $first_name);
            $stmt->bindParam(':last_name', $last_name);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':role', $role);
            
            if ($stmt->execute()) {
                $success = "Registration successful! You can now login.";
                // Clear form data
                $username = $email = $first_name = $last_name = $phone = '';
            } else {
                $errors[] = "Registration failed. Please try again.";
            }
            
        } catch (Exception $e) {
            $errors[] = "Registration failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register - Sports League Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body { 
            font-family: 'Arial', sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .register-container { 
            background: white; 
            border-radius: 10px; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
        }
        
        .register-header { 
            background: linear-gradient(black, maroon);
            color: white; 
            padding: 30px;
            text-align: center;
        }
        
        .register-header h2 { 
            font-size: 28px; 
            margin-bottom: 10px;
        }
        
        .register-header p { 
            opacity: 0.9; 
            font-size: 16px;
        }
        
        .register-form { 
            padding: 30px;
        }
        
        .form-group { 
            margin-bottom: 20px;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: bold;
            color: #333;
        }
        
        input, select { 
            width: 100%; 
            padding: 12px 15px; 
            border: 2px solid #e1e5e9; 
            border-radius: 8px; 
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        input:focus, select:focus { 
            outline: none; 
            border-color: #007bff;
        }
        
        .btn { 
            width: 100%; 
            background: linear-gradient(black, maroon);
            color: white; 
            padding: 15px; 
            border: none; 
            border-radius: 8px; 
            font-size: 18px; 
            font-weight: bold;
            cursor: pointer; 
            transition: transform 0.2s ease;
            margin-top: 10px;
        }
        
        .btn:hover { 
            transform: translateY(-2px);
        }
        
        .error-list { 
            background: #f8d7da; 
            color: #721c24; 
            padding: 15px; 
            border-radius: 8px; 
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
        
        .success-message { 
            background: #d4edda; 
            color: #155724; 
            padding: 15px; 
            border-radius: 8px; 
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        
        .login-link { 
            text-align: center; 
            margin-top: 20px; 
            padding-top: 20px; 
            border-top: 1px solid #e1e5e9;
        }
        
        .login-link a { 
            color: #007bff; 
            text-decoration: none; 
            font-weight: bold;
        }
        
        .login-link a:hover { 
            text-decoration: underline;
        }
        
        .role-description {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        .password-strength {
            font-size: 12px;
            margin-top: 5px;
            color: #666;
        }
        
        .password-requirements {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 6px;
            padding: 12px;
            margin-top: 8px;
            font-size: 13px;
        }
        
        .password-requirements h4 {
            color: #0056b3;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .requirement {
            padding: 4px 0;
            color: #666;
        }
        
        .requirement.met {
            color: #28a745;
        }
        
        .requirement.met::before {
            content: '✓ ';
            font-weight: bold;
        }
        
        .requirement.unmet::before {
            content: '✗ ';
            color: #dc3545;
            font-weight: bold;
        }
        
        .password-input-wrapper {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: #666;
        }
        
        .toggle-password:hover {
            color: #007bff;
        }
        
        @media (max-width: 600px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .register-container {
                margin: 10px;
            }
            
            .register-header, .register-form {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h2>Join Our League</h2>
            <p>Create your account to get started</p>
        </div>
        
        <div class="register-form">
            <?php if (!empty($errors)): ?>
                <div class="error-list">
                    <strong>Please fix the following errors:</strong>
                    <ul style="margin-top: 10px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message">
                    <?php echo htmlspecialchars($success); ?>
                    <br><a href="login.php" style="color: #155724; text-decoration: underline;">Click here to login</a>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="registerForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" 
                               id="first_name" 
                               name="first_name" 
                               value="<?php echo htmlspecialchars($first_name ?? ''); ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" 
                               id="last_name" 
                               name="last_name" 
                               value="<?php echo htmlspecialchars($last_name ?? ''); ?>" 
                               required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="username">Username *</label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           value="<?php echo htmlspecialchars($username ?? ''); ?>" 
                           required>
                    <div class="password-strength">3+ characters, letters/numbers/underscore only</div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           value="<?php echo htmlspecialchars($email ?? ''); ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" 
                           id="phone" 
                           name="phone" 
                           value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <div class="password-input-wrapper">
                            <input type="password" 
                                   id="password" 
                                   name="password"
                                   style="padding-right: 45px;"
                                   required>
                            <button type="button" class="toggle-password" onclick="togglePassword('password')">👁️</button>
                        </div>
                        <div class="password-requirements">
                            <h4>Password Requirements:</h4>
                            <div class="requirement unmet" id="req-length">At least 6 characters</div>
                            <div class="requirement unmet" id="req-upper">One uppercase letter (A-Z)</div>
                            <div class="requirement unmet" id="req-lower">One lowercase letter (a-z)</div>
                            <div class="requirement unmet" id="req-symbol">One special character (!@#$%^&*...)</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <div class="password-input-wrapper">
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password"
                                   style="padding-right: 45px;"
                                   required>
                            <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">👁️</button>
                        </div>
                        <div class="password-strength" id="match-status"></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="role">I want to register as *</label>
                    <select id="role" name="role" required>
                        <option value="">Select your role...</option>
                        <option value="player" <?php echo (isset($role) && $role == 'player') ? 'selected' : ''; ?>>
                            Player
                        </option>
                        <option value="team_owner" <?php echo (isset($role) && $role == 'team_owner') ? 'selected' : ''; ?>>
                            Team Owner
                        </option>
                    </select>
                    <div class="role-description">
                        <strong>Player:</strong> Join existing teams and participate in leagues<br>
                        <strong>Team Owner:</strong> Create and manage teams, recruit players
                    </div>
                </div>
                
                <button type="submit" class="btn">Create Account</button>
            </form>
            
            <div class="login-link">
                Already have an account? <a href="login.php">Login here</a>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            
            if (field.type === 'password') {
                field.type = 'text';
                button.textContent = '🙈';
            } else {
                field.type = 'password';
                button.textContent = '👁️';
            }
        }
        
        // Real-time password strength validation
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            
            // Check length
            const lengthReq = document.getElementById('req-length');
            if (password.length >= 6) {
                lengthReq.className = 'requirement met';
            } else {
                lengthReq.className = 'requirement unmet';
            }
            
            // Check uppercase
            const upperReq = document.getElementById('req-upper');
            if (/[A-Z]/.test(password)) {
                upperReq.className = 'requirement met';
            } else {
                upperReq.className = 'requirement unmet';
            }
            
            // Check lowercase
            const lowerReq = document.getElementById('req-lower');
            if (/[a-z]/.test(password)) {
                lowerReq.className = 'requirement met';
            } else {
                lowerReq.className = 'requirement unmet';
            }
            
            // Check symbol
            const symbolReq = document.getElementById('req-symbol');
            if (/[!@#$%^&*()_+\-=\[\]{};:'",.<>?\/\\|`~]/.test(password)) {
                symbolReq.className = 'requirement met';
            } else {
                symbolReq.className = 'requirement unmet';
            }
            
            // Check if all requirements are met
            const allMet = password.length >= 6 && 
                          /[A-Z]/.test(password) && 
                          /[a-z]/.test(password) && 
                          /[!@#$%^&*()_+\-=\[\]{};:'",.<>?\/\\|`~]/.test(password);
            
            if (allMet) {
                this.style.borderColor = '#28a745';
            } else if (password.length > 0) {
                this.style.borderColor = '#ffc107';
            } else {
                this.style.borderColor = '#e1e5e9';
            }
            
            checkPasswordMatch();
        });
        
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);
        
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchStatus = document.getElementById('match-status');
            
            if (confirmPassword.length === 0) {
                matchStatus.textContent = '';
                document.getElementById('confirm_password').style.borderColor = '#e1e5e9';
                return;
            }
            
            if (password === confirmPassword) {
                matchStatus.textContent = '✓ Passwords match';
                matchStatus.style.color = '#28a745';
                document.getElementById('confirm_password').style.borderColor = '#28a745';
                document.getElementById('confirm_password').setCustomValidity('');
            } else {
                matchStatus.textContent = '✗ Passwords do not match';
                matchStatus.style.color = '#dc3545';
                document.getElementById('confirm_password').style.borderColor = '#dc3545';
                document.getElementById('confirm_password').setCustomValidity('Passwords do not match');
            }
        }
        
        // Username validation
        document.getElementById('username').addEventListener('input', function() {
            const username = this.value;
            const pattern = /^[a-zA-Z0-9_]+$/;
            
            if (!pattern.test(username) && username.length > 0) {
                this.setCustomValidity('Username can only contain letters, numbers, and underscores');
                this.style.borderColor = '#dc3545';
            } else if (username.length < 3 && username.length > 0) {
                this.setCustomValidity('Username must be at least 3 characters');
                this.style.borderColor = '#ffc107';
            } else {
                this.setCustomValidity('');
                this.style.borderColor = username.length > 0 ? '#28a745' : '#e1e5e9';
            }
        });
        
        // Form submission validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Final validation before submission
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            // Check all password requirements
            if (password.length < 6 || 
                !/[A-Z]/.test(password) || 
                !/[a-z]/.test(password) || 
                !/[!@#$%^&*()_+\-=\[\]{};:'",.<>?\/\\|`~]/.test(password)) {
                e.preventDefault();
                alert('Password does not meet all requirements!');
                return false;
            }
        });
    </script>
</body>
</html>