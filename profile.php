<?php
require_once 'includes/config.php';
redirect_if_not_logged_in();

$user_id = $_SESSION['user_id'];

// მომხმარებლის ინფორმაცია
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// მომხმარებლის ჯავშნები
$stmt = $pdo->prepare("SELECT b.*, r.room_number, rt.name as room_type, rt.price_per_night
                      FROM bookings b
                      JOIN rooms r ON b.room_id = r.id
                      JOIN room_types rt ON r.room_type_id = rt.id
                      WHERE b.user_id = ?
                      ORDER BY b.check_in DESC");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ჩემი პროფილი</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container mt-5">
        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>პროფილის ინფორმაცია</h4>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <img src="assets/images/profile.png" class="rounded-circle" width="150" alt="Profile">
                        </div>
                        <h5 class="card-title"><?= htmlspecialchars($user['full_name']) ?></h5>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item"><strong>მომხმარებელი:</strong> <?= htmlspecialchars($user['username']) ?></li>
                            <li class="list-group-item"><strong>ელ. ფოსტა:</strong> <?= htmlspecialchars($user['email']) ?></li>
                            <li class="list-group-item"><strong>ტელეფონი:</strong> <?= htmlspecialchars($user['phone']) ?></li>
                            <li class="list-group-item"><strong>რეგისტრაციის თარიღი:</strong> <?= date('d/m/Y', strtotime($user['created_at'])) ?></li>
                        </ul>
                        <div class="mt-3">
                            <a href="edit_profile.php" class="btn btn-outline-primary">პროფილის რედაქტირება</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <h2>ჩემი ჯავშნები</h2>
                
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <?php if (empty($bookings)): ?>
                    <div class="alert alert-info">
                        თქვენ არ გაქვთ არცერთი ჯავშანი. <a href="search.php">ძებნის გვერდი</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ოთახი</th>
                                    <th>ჩაფხუტი</th>
                                    <th>გამგზავრება</th>
                                    <th>ღამეები</th>
                                    <th>ფასი</th>
                                    <th>სტატუსი</th>
                                    <th>მოქმედება</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $booking): ?>
                                    <?php
                                    $nights = (strtotime($booking['check_out']) - strtotime($booking['check_in'])) / (60 * 60 * 24);
                                    $status_class = '';
                                    switch ($booking['status']) {
                                        case 'confirmed': $status_class = 'success'; break;
                                        case 'cancelled': $status_class = 'danger'; break;
                                        case 'pending': $status_class = 'warning'; break;
                                        default: $status_class = 'secondary';
                                    }
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($booking['room_type']) ?> #<?= htmlspecialchars($booking['room_number']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($booking['check_in'])) ?></td>
                                        <td><?= date('d/m/Y', strtotime($booking['check_out'])) ?></td>
                                        <td><?= $nights ?></td>
                                        <td><?= $booking['total_price'] ?> ₾</td>
                                        <td><span class="badge bg-<?= $status_class ?>"><?= $booking['status'] ?></span></td>
                                        <td>
                                            <a href="booking_details.php?id=<?= $booking['id'] ?>" class="btn btn-sm btn-outline-primary">დეტალები</a>
                                            <?php if ($booking['status'] === 'pending' || $booking['status'] === 'confirmed'): ?>
                                                <?php if (strtotime($booking['check_in']) > time()): ?>
                                                    <a href="cancel_booking.php?id=<?= $booking['id'] ?>" class="btn btn-sm btn-outline-danger">გაუქმება</a>
                                                <?php endif; ?>
                                            <?php endif; ?>
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

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>