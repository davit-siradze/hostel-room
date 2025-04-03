<?php
require_once 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $full_name = sanitize_input($_POST['full_name']);
    $phone = sanitize_input($_POST['phone']);
    
    // ვალიდაცია
    $errors = [];
    
    if (empty($username)) {
        $errors[] = "მომხმარებლის სახელი სავალდებულოა";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "გთხოვთ მიუთითოთ სწორი ელ. ფოსტა";
    }
    
    if (strlen($password) < 8) {
        $errors[] = "პაროლი უნდა შედგებოდეს მინიმუმ 8 სიმბოლოსგან";
    }
    
    if ($password !== $password_confirm) {
        $errors[] = "პაროლები არ ემთხვევა";
    }
    
    if (empty($errors)) {
        // შევამოწმოთ არის თუ არა მომხმარებელი უკვე რეგისტრირებული
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->rowCount() > 0) {
            $errors[] = "მომხმარებლის სახელი ან ელ. ფოსტა უკვე გამოყენებულია";
        } else {
            // დარეგისტრირება
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, phone) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$username, $email, $hashed_password, $full_name, $phone])) {
                $_SESSION['success_message'] = "რეგისტრაცია წარმატებით დასრულდა. გთხოვთ შეხვიდეთ სისტემაში.";
                header("Location: login.php");
                exit();
            } else {
                $errors[] = "დაფიქსირდა შეცდომა. გთხოვთ სცადოთ თავიდან.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>რეგისტრაცია</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>რეგისტრაცია</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul>
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= $error ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">მომხმარებლის სახელი</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">ელ. ფოსტა</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">პაროლი</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="mb-3">
                                <label for="password_confirm" class="form-label">გაიმეორეთ პაროლი</label>
                                <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                            </div>
                            <div class="mb-3">
                                <label for="full_name" class="form-label">სრული სახელი</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">ტელეფონი</label>
                                <input type="tel" class="form-control" id="phone" name="phone" required>
                            </div>
                            <button type="submit" class="btn btn-primary">რეგისტრაცია</button>
                        </form>
                        <div class="mt-3">
                            უკვე გაქვთ ანგარიში? <a href="login.php">შესვლა</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>