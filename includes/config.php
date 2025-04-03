<?php
session_start();

// შეცდომების ჩვენება მხოლოდ დეველოპმენტის დროს
error_reporting(E_ALL);
ini_set('display_errors', 1);

// DB კავშირი
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hotel_booking1');

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch(PDOException $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
}

// უსაფრთხოების ფუნქციები
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// მომხმარებლის ავთენტიფიკაციის შემოწმება
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// გადამისამართება თუ არ არის ავტორიზებული
function redirect_if_not_logged_in() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit();
    }
}

// გადამისამართება თუ არ არის ადმინისტრატორი
function redirect_if_not_admin() {
    if (!is_admin()) {
        header("Location: index.php");
        exit();
    }
}
?>