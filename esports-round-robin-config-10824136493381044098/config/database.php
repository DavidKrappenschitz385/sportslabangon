<?php
// config/database.php - Database Configuration

class Database {
    private $host = 'localhost';
    private $db_name = 'leagues';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function connect() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                'mysql:host=' . $this->host . ';dbname=' . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $e) {
            echo 'Connection Error: ' . $e->getMessage();
        }
        
        return $this->conn;
    }
}

// Session configuration
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Helper functions

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    
    $database = new Database();
    $db = $database->connect();
    
    $query = "SELECT * FROM users WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $_SESSION['user_id']);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: auth/login.php');
        exit();
    }
}

function requireRole($role) {
    requireLogin();
    $user = getCurrentUser();
    if ($user['role'] !== $role && $user['role'] !== 'admin') {
        header('Location: auth/dashboard.php');
        exit();
    }
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function showMessage($message, $type = 'info') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
}

function displayMessage() {
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        $type = $_SESSION['message_type'] ?? 'info';
        echo "<div class='alert alert-$type'>$message</div>";
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
}
?>