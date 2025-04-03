<div class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active text-white" href="dashboard.php">
                    <i class="bi bi-speedometer2 me-2"></i> დაფა
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="bookings.php">
                    <i class="bi bi-calendar-check me-2"></i> ჯავშნები
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="rooms.php">
                    <i class="bi bi-door-closed me-2"></i> ოთახები
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="room_types.php">
                    <i class="bi bi-building me-2"></i> ოთახის ტიპები
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="users.php">
                    <i class="bi bi-people me-2"></i> მომხმარებლები
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="payments.php">
                    <i class="bi bi-credit-card me-2"></i> გადახდები
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="settings.php">
                    <i class="bi bi-gear me-2"></i> პარამეტრები
                </a>
            </li>
        </ul>
        
        <hr class="text-white">
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link text-white" href="../profile.php">
                    <i class="bi bi-person me-2"></i> ჩემი პროფილი
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="../logout.php">
                    <i class="bi bi-box-arrow-right me-2"></i> გამოსვლა
                </a>
            </li>
        </ul>
    </div>
</div>

<style>
    .sidebar {
        min-height: 100vh;
        height: 100%;
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        z-index: 100;
        padding-top: 56px; /* header-ის სიმაღლე */
        overflow-y: auto;
    }
    
    .main-content {
        margin-left: 250px; /* საიდბარის სიგანე */
        padding: 20px;
    }
    
    body {
        overflow-x: hidden;
    }
    
    @media (max-width: 767.98px) {
        .sidebar {
            width: 100%;
            padding-top: 56px;
        }
        
        .main-content {
            margin-left: 0;
        }
        
        .sidebar.collapse:not(.show) {
            display: none;
        }
    }
</style>