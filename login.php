<?php
require_once 'includes/config.php';

if (is_logged_in()) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];
    
    $errors = [];
    
    if (empty($username) || empty($password)) {
        $errors[] = "გთხოვთ შეავსოთ ყველა ველი";
    }
    
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // ავტორიზაცია წარმატებულია
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            
            header("Location: index.php");
            exit();
        } else {
            $errors[] = "არასწორი მომხმარებლის სახელი ან პაროლი";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>შესვლა</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>შესვლა</h4>
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

                        <?php if (isset($_SESSION['success_message'])): ?>
                            <div class="alert alert-success">
                                <?= $_SESSION['success_message'] ?>
                            </div>
                            <?php unset($_SESSION['success_message']); ?>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">მომხმარებლის სახელი ან ელ. ფოსტა</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">პაროლი</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">დამიმახსოვრე</label>
                            </div>
                            <button type="submit" class="btn btn-primary">შესვლა</button>
                        </form>
                        <div class="mt-3">
                            არ გაქვთ ანგარიში? <a href="register.php">რეგისტრაცია</a>
                        </div>
                        <div class="mt-2">
                            <a href="forgot_password.php">დაგავიწყდათ პაროლი?</a>
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