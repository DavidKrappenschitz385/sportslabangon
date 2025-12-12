<?php
// auth/logout.php - Complete Logout System
require_once '../config/database.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$was_logged_in = isLoggedIn();
$user_name = '';

if ($was_logged_in) {
    // Get user name for goodbye message
    $user = getCurrentUser();
    if ($user) {
        $user_name = $user['first_name'];
        
        // Optional: Log logout activity in database
        try {
            $database = new Database();
            $db = $database->connect();
            
            // Create logout notification
            $notification_query = "INSERT INTO notifications (user_id, title, message, type) 
                                  VALUES (:user_id, 'Logged Out', 'You have successfully logged out.', 'info')";
            $notification_stmt = $db->prepare($notification_query);
            $notification_stmt->bindParam(':user_id', $user['id']);
            $notification_stmt->execute();
            
            // Optional: Update last activity timestamp
            $update_activity = "UPDATE users SET updated_at = NOW() WHERE id = :id";
            $update_stmt = $db->prepare($update_activity);
            $update_stmt->bindParam(':id', $user['id']);
            $update_stmt->execute();
            
        } catch (Exception $e) {
            // Continue with logout even if database operations fail
            error_log("Logout database operation failed: " . $e->getMessage());
        }
    }
}

// Clear all session variables
$_SESSION = array();

// Delete the session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Clear remember me cookie if it exists
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
    unset($_COOKIE['remember_token']);
}

// Destroy the session
session_destroy();

// Start new session for logout message
session_start();

// Set goodbye message
if ($was_logged_in) {
    if ($user_name) {
        $_SESSION['message'] = "Goodbye " . htmlspecialchars($user_name) . "! You have been logged out successfully.";
    } else {
        $_SESSION['message'] = "You have been logged out successfully.";
    }
    $_SESSION['message_type'] = "success";
} else {
    $_SESSION['message'] = "You are already logged out.";
    $_SESSION['message_type'] = "info";
}

// Redirect to index page
header("Location: ../index.php");
exit();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out - Sports League Management</title>
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
        
        .logout-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            text-align: center;
            max-width: 400px;
            width: 100%;
            animation: fadeIn 0.6s ease-out;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .logout-icon {
            font-size: 4rem;
            color: #007bff;
            margin-bottom: 1rem;
            animation: wave 2s ease-in-out infinite;
        }
        
        @keyframes wave {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(-10deg); }
            75% { transform: rotate(10deg); }
        }
        
        h1 {
            color: #333;
            margin-bottom: 1rem;
            font-size: 1.8rem;
        }
        
        p {
            color: #666;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #007bff;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0.5rem;
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #0056b3, #004085);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #5a6268, #495057);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
        }
        
        @media (max-width: 480px) {
            .logout-container {
                padding: 2rem;
                margin: 1rem;
            }
            
            h1 {
                font-size: 1.5rem;
            }
            
            .logout-icon {
                font-size: 3rem;
            }
        }
    </style>
    <script>
        // Auto redirect to index page after 3 seconds
        setTimeout(function() {
            window.location.href = '../index.php';
        }, 3000);
        
        // Prevent back button after logout
        history.pushState(null, null, location.href);
        window.onpopstate = function () {
            history.go(1);
        };
        
        // Clear any cached data
        if ('caches' in window) {
            caches.keys().then(function(names) {
                names.forEach(function(name) {
                    caches.delete(name);
                });
            });
        }
        
        // Clear local storage (if any was used)
        if (typeof(Storage) !== "undefined") {
            localStorage.clear();
            sessionStorage.clear();
        }
    </script>
</head>
<body>
    <div class="logout-container">
        <div class="logout-icon">👋</div>
        <h1>Logging You Out...</h1>
        <p>Thank you for using Sports League Management System. You are being safely logged out.</p>
        
        <div class="loading-spinner"></div>
        
        <p><small>You will be redirected to the home page automatically in a few seconds.</small></p>
        
        <div style="margin-top: 2rem;">
            <a href="../index.php" class="btn">Go to Home Page</a>
            <a href="login.php" class="btn btn-secondary">Login Again</a>
        </div>
    </div>
    
    <!-- Fallback redirect in case JavaScript is disabled -->
    <noscript>
        <meta http-equiv="refresh" content="2;url=../index.php">
    </noscript>
</body>
</html>