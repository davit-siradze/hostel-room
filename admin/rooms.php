<?php
require_once '../includes/config.php';
redirect_if_not_admin();

if (isset($_GET['delete'])) {
    $room_id = (int)$_GET['delete'];
    
    // შევამოწმოთ არის თუ არა ოთახზე აქტიური ჯავშნები
    $stmt = $pdo->prepare("SELECT id FROM bookings WHERE room_id = ? AND status IN ('pending', 'confirmed')");
    $stmt->execute([$room_id]);
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['error_message'] = "ამ ოთახზე არსებობს აქტიური ჯავშნები. წაშლა შეუძლებელია.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // მივიღოთ ოთახის ყველა სურათი
            $stmt = $pdo->prepare("SELECT image_path FROM room_images WHERE room_id = ?");
            $stmt->execute([$room_id]);
            $images = $stmt->fetchAll();
            
            // წავშალოთ ფაილები
            foreach ($images as $image) {
                if (file_exists('../' . $image['image_path'])) {
                    unlink('../' . $image['image_path']);
                }
            }
            
            // წავშალოთ ოთახი
            $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ?");
            $stmt->execute([$room_id]);
            
            $pdo->commit();
            
            $_SESSION['success_message'] = "ოთახი წარმატებით წაიშალა";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "წაშლის დროს დაფიქსირდა შეცდომა: " . $e->getMessage();
        }
    }
    
    header("Location: rooms.php");
    exit();
}

// ოთახების სია მთავარი სურათებით
$query = "SELECT r.*, rt.name as type_name, rt.price_per_night, rt.capacity, 
          ri.image_path as main_image
          FROM rooms r
          JOIN room_types rt ON r.room_type_id = rt.id
          LEFT JOIN room_images ri ON r.main_image_id = ri.id
          ORDER BY r.room_number";
$rooms = $pdo->query($query)->fetchAll();
?>

<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ოთახების მართვა</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">ოთახების მართვა</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="add_room.php" class="btn btn-sm btn-primary">
                            <i class="bi bi-plus-lg"></i> ახალი ოთახი
                        </a>
                    </div>
                </div>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger"><?= $_SESSION['error_message'] ?></div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>ფოტო</th>
                                        <th>ნომერი</th>
                                        <th>ტიპი</th>
                                        <th>ფასი</th>
                                        <th>ტევადობა</th>
                                        <th>სტატუსი</th>
                                        <th>მოქმედება</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rooms as $room): ?>
                                        <tr>
                                            <td><?= $room['id'] ?></td>
                                            <td>
                                                <?php if ($room['main_image']): ?>
                                                    <img src="../<?= htmlspecialchars($room['main_image']) ?>" 
                                                         style="width: 80px; height: 60px; object-fit: cover;" 
                                                         class="rounded" alt="Room Image">
                                                <?php else: ?>
                                                    <span class="text-muted">ფოტო არაა</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($room['room_number']) ?></td>
                                            <td><?= htmlspecialchars($room['type_name']) ?></td>
                                            <td><?= $room['price'] ?> ₾</td>
                                            <td><?= $room['capacity'] ?> სტუმარი</td>
                                            <td>
                                                <span class="badge bg-<?= 
                                                    $room['status'] === 'available' ? 'success' : 
                                                    ($room['status'] === 'occupied' ? 'danger' : 'warning')
                                                ?>">
                                                    <?= $room['status'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="edit_room.php?id=<?= $room['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="rooms.php?delete=<?= $room['id'] ?>" class="btn btn-sm btn-outline-danger" 
                                                   onclick="return confirm('დარწმუნებული ხართ რომ გსურთ ამ ოთახის წაშლა?')">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons@4.28.0/dist/feather.min.js"></script>
    <script>
        feather.replace();
    </script>
</body>
</html>