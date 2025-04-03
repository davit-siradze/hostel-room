<?php 
require_once 'includes/config.php';

// მივიღოთ ყველა ოთახი მათი მთავარი სურათებით
$query = "SELECT r.*, rt.name as type_name, ri.image_path as main_image 
          FROM rooms r
          JOIN room_types rt ON r.room_type_id = rt.id
          LEFT JOIN room_images ri ON r.main_image_id = ri.id
          WHERE r.status = 'available'
          ORDER BY r.room_number";
$rooms = $pdo->query($query)->fetchAll();

// დავყოთ ოთახები 3 კატეგორიად (სტანდარტული, დელუქს, სიუტი)
$standard_rooms = array_filter($rooms, fn($room) => $room['type_name'] === 'Standard');
$deluxe_rooms = array_filter($rooms, fn($room) => $room['type_name'] === 'Deluxe');
$suite_rooms = array_filter($rooms, fn($room) => $room['type_name'] === 'Suite');
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>სასტუმროს ჯავშნის სისტემა</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .room-card {
            transition: transform 0.3s ease;
            margin-bottom: 20px;
        }
        .room-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .room-img {
            height: 200px;
            object-fit: cover;
        }
        .room-description {
            color: #666;
            margin-bottom: 15px;
            font-size: 0.9rem;
            height: 60px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container mt-5">
        <div class="hero-section text-center py-5 bg-light rounded">
            <h1 class="display-4">მოგესალმებით ჩვენს სასტუმროში</h1>
            <p class="lead">იპოვეთ სრულყოფილი ოთახი თქვენი დასვენებისთვის</p>
            <a href="search.php" class="btn btn-primary btn-lg">ყველა ოთახის ნახვა</a>
        </div>

        <!-- სტანდარტული ოთახები -->
        <div class="mt-5">
            <h2 class="mb-4">სტანდარტული ოთახები</h2>
            <div class="row">
                <?php if (empty($standard_rooms)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">სტანდარტული ოთახები არ მოიძებნა</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($standard_rooms as $room): ?>
                        <div class="col-md-4">
                            <div class="card room-card h-100">
                                <img src="<?= htmlspecialchars($room['main_image'] ?? 'assets/images/default-room.jpg') ?>" 
                                     class="card-img-top room-img" alt="<?= htmlspecialchars($room['type_name']) ?>">
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($room['type_name']) ?> #<?= htmlspecialchars($room['room_number']) ?></h5>
                                    <p class="card-text room-description">
                                        <?= !empty($room['description']) ? htmlspecialchars($room['description']) : 'ოთახის აღწერა არ არის მითითებული' ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-primary fw-bold"><?= htmlspecialchars($room['price']) ?>₾/ღამე</span>
                                        <a href="booking.php?room_id=<?= $room['id'] ?>" class="btn btn-outline-primary">დაჯავშნა</a>
                                    </div>
                                    <?php if (is_admin()): ?>
                                        <div class="mt-2">
                                            <a href="admin/edit_room.php?id=<?= $room['id'] ?>" class="btn btn-sm btn-outline-secondary">რედაქტირება</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- დელუქს ოთახები -->
        <div class="mt-5">
            <h2 class="mb-4">დელუქს ოთახები</h2>
            <div class="row">
                <?php if (empty($deluxe_rooms)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">დელუქს ოთახები არ მოიძებნა</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($deluxe_rooms as $room): ?>
                        <div class="col-md-4">
                            <div class="card room-card h-100">
                                <img src="<?= htmlspecialchars($room['main_image'] ?? 'assets/images/default-room.jpg') ?>" 
                                     class="card-img-top room-img" alt="<?= htmlspecialchars($room['type_name']) ?>">
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($room['type_name']) ?> #<?= htmlspecialchars($room['room_number']) ?></h5>
                                    <p class="card-text room-description">
                                        <?= !empty($room['description']) ? htmlspecialchars($room['description']) : 'ოთახის აღწერა არ არის მითითებული' ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-primary fw-bold"><?= htmlspecialchars($room['price']) ?>₾/ღამე</span>
                                        <a href="booking.php?room_id=<?= $room['id'] ?>" class="btn btn-outline-primary">დაჯავშნა</a>
                                    </div>
                                    <?php if (is_admin()): ?>
                                        <div class="mt-2">
                                            <a href="admin/edit_room.php?id=<?= $room['id'] ?>" class="btn btn-sm btn-outline-secondary">რედაქტირება</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- სიუტები -->
        <div class="mt-5">
            <h2 class="mb-4">სიუტები</h2>
            <div class="row">
                <?php if (empty($suite_rooms)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">სიუტები არ მოიძებნა</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($suite_rooms as $room): ?>
                        <div class="col-md-4">
                            <div class="card room-card h-100">
                                <img src="<?= htmlspecialchars($room['main_image'] ?? 'assets/images/default-room.jpg') ?>" 
                                     class="card-img-top room-img" alt="<?= htmlspecialchars($room['type_name']) ?>">
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($room['type_name']) ?> #<?= htmlspecialchars($room['room_number']) ?></h5>
                                    <p class="card-text room-description">
                                        <?= !empty($room['description']) ? htmlspecialchars($room['description']) : 'ოთახის აღწერა არ არის მითითებული' ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-primary fw-bold"><?= htmlspecialchars($room['price']) ?>₾/ღამე</span>
                                        <a href="booking.php?room_id=<?= $room['id'] ?>" class="btn btn-outline-primary">დაჯავშნა</a>
                                    </div>
                                    <?php if (is_admin()): ?>
                                        <div class="mt-2">
                                            <a href="admin/edit_room.php?id=<?= $room['id'] ?>" class="btn btn-sm btn-outline-secondary">რედაქტირება</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>