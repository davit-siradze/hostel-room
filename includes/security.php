
<?php
// CSRF ტოკენის გენერაცია
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF ტოკენის ვალიდაცია
function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// მონაცემთა გაწმენდა
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// SQL ინექციისგან დაცვა (მზადაა PDO-სთვის)
function prepare_string($conn, $string) {
    return htmlspecialchars($conn->real_escape_string($string));
}
?>