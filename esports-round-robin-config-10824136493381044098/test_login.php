<?php
// test_login.php - Debug Login Issues
session_start();

// Include database
require_once 'config/database.php';

echo "<h2>Login Debug Test</h2>";

// Test 1: Database Connection
echo "<h3>1. Testing Database Connection:</h3>";
try {
    $database = new Database();
    $db = $database->connect();
    if ($db) {
        echo "✅ Database connected successfully!<br>";
    } else {
        echo "❌ Database connection failed!<br>";
        exit;
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
    exit;
}

// Test 2: Check if users table exists
echo "<h3>2. Testing Users Table:</h3>";
try {
    $query = "SELECT COUNT(*) FROM users";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $count = $stmt->fetchColumn();
    echo "✅ Users table exists with $count users<br>";
} catch (Exception $e) {
    echo "❌ Users table error: " . $e->getMessage() . "<br>";
    exit;
}

// Test 3: Check admin user
echo "<h3>3. Testing Admin User:</h3>";
try {
    $query = "SELECT * FROM users WHERE username = 'admin'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "✅ Admin user found:<br>";
        echo "- ID: " . $admin['id'] . "<br>";
        echo "- Username: " . $admin['username'] . "<br>";
        echo "- Email: " . $admin['email'] . "<br>";
        echo "- Role: " . $admin['role'] . "<br>";
        echo "- Password Hash: " . substr($admin['password'], 0, 20) . "...<br>";
    } else {
        echo "❌ Admin user NOT found! Let's create one:<br>";
        
        // Create admin user
        $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $insert_query = "INSERT INTO users (username, email, password, first_name, last_name, role) 
                        VALUES ('admin', 'admin@sportsleague.com', :password, 'System', 'Administrator', 'admin')";
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(':password', $password_hash);
        
        if ($insert_stmt->execute()) {
            echo "✅ Admin user created successfully!<br>";
        } else {
            echo "❌ Failed to create admin user!<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Admin user check error: " . $e->getMessage() . "<br>";
}

// Test 4: Password verification
echo "<h3>4. Testing Password Verification:</h3>";
if (isset($admin)) {
    $test_password = 'admin123';
    if (password_verify($test_password, $admin['password'])) {
        echo "✅ Password verification works!<br>";
    } else {
        echo "❌ Password verification failed!<br>";
        echo "Trying to fix password hash...<br>";
        
        // Fix password hash
        $new_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $update_query = "UPDATE users SET password = :password WHERE username = 'admin'";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':password', $new_hash);
        
        if ($update_stmt->execute()) {
            echo "✅ Password hash fixed!<br>";
        } else {
            echo "❌ Failed to fix password hash!<br>";
        }
    }
}

// Test 5: Session test
echo "<h3>5. Testing Sessions:</h3>";
$_SESSION['test'] = 'working';
if (isset($_SESSION['test'])) {
    echo "✅ Sessions are working!<br>";
    unset($_SESSION['test']);
} else {
    echo "❌ Sessions not working!<br>";
}

// Test 6: File structure
echo "<h3>6. Testing File Structure:</h3>";
$files_to_check = [
    'config/database.php',
    'dashboard.php',
    'auth/login.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists<br>";
    } else {
        echo "❌ $file missing!<br>";
    }
}

// Manual login test
echo "<h3>7. Manual Login Test:</h3>";
if (isset($_GET['test_login'])) {
    // Simulate login
    $_SESSION['user_id'] = $admin['id'] ?? 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['role'] = 'admin';
    $_SESSION['first_name'] = 'System';
    $_SESSION['last_name'] = 'Administrator';
    
    echo "✅ Session variables set:<br>";
    echo "- User ID: " . $_SESSION['user_id'] . "<br>";
    echo "- Username: " . $_SESSION['username'] . "<br>";
    echo "- Role: " . $_SESSION['role'] . "<br>";
    
    echo "<br><a href='dashboard.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Dashboard</a>";
} else {
    echo "<a href='?test_login=1' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Manual Login</a>";
}

echo "<br><br><a href='auth/login.php' style='background: #ffc107; color: black; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Back to Login Page</a>";
?>