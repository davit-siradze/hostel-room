<?php
require_once '../includes/config.php';
redirect_if_not_admin();

// სტატისტიკა
$total_bookings = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_rooms = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$revenue = $pdo->query("SELECT SUM(total_price) FROM bookings WHERE status = 'completed'")->fetchColumn();

// უახლესი ჯავშნები
$recent_bookings = $pdo->query("SELECT b.*, u.username, r.room_number, rt.name as room_type
                               FROM bookings b
                               JOIN users u ON b.user_id = u.id
                               JOIN rooms r ON b.room_id = r.id
                               JOIN room_types rt ON r.room_type_id = rt.id
                               ORDER BY b.created_at DESC
                               LIMIT 5")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ადმინისტრირების პანელი</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>

    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">ადმინისტრირების პანელი</h1>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <h5 class="card-title">ჯავშნები</h5>
                                <p class="card-text display-6"><?= $total_bookings ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h5 class="card-title">მომხმარებლები</h5>
                                <p class="card-text display-6"><?= $total_users ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <h5 class="card-title">ოთახები</h5>
                                <p class="card-text display-6"><?= $total_rooms ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <h5 class="card-title">შემოსავალი</h5>
                                <p class="card-text display-6"><?= $revenue ? number_format($revenue, 2) : '0' ?> ₾</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>უახლესი ჯავშნები</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_bookings)): ?>
                            <div class="alert alert-info">ჯავშნები არ მოიძებნა</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>მომხმარებელი</th>
                                            <th>ოთახი</th>
                                            <th>თარიღები</th>
                                            <th>ფასი</th>
                                            <th>სტატუსი</th>
                                            <th>მოქმედება</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_bookings as $booking): ?>
                                            <tr>
                                                <td><?= $booking['id'] ?></td>
                                                <td><?= htmlspecialchars($booking['username']) ?></td>
                                                <td><?= htmlspecialchars($booking['room_type']) ?> #<?= htmlspecialchars($booking['room_number']) ?></td>
                                                <td><?= date('d/m/Y', strtotime($booking['check_in'])) ?> - <?= date('d/m/Y', strtotime($booking['check_out'])) ?></td>
                                                <td><?= $booking['total_price'] ?> ₾</td>
                                                <td><span class="badge bg-<?= 
                                                    $booking['status'] === 'confirmed' ? 'success' : 
                                                    ($booking['status'] === 'cancelled' ? 'danger' : 'warning') 
                                                ?>"><?= $booking['status'] ?></span></td>
                                                <td>
                                                    <a href="booking_details.php?id=<?= $booking['id'] ?>" class="btn btn-sm btn-outline-primary">ნახვა</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>