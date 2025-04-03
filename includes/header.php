<?php
// header.php
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php">სასტუმროს ჯავშნის სისტემა</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">მთავარი</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="search.php">ოთახები</a>
                </li>
                <?php if (is_logged_in()): ?>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">ჩემი ჯავშნები</a>
                </li>
                <?php endif; ?>
                <?php if (is_admin()): ?>
                <li class="nav-item">
                    <a class="nav-link" href="admin/dashboard.php">ადმინ პანელი</a>
                </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <?php if (is_logged_in()): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <?= htmlspecialchars($_SESSION['username']) ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="profile.php">პროფილი</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">გამოსვლა</a></li>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="login.php">შესვლა</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="register.php">რეგისტრაცია</a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>