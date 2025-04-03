<?php
require_once 'includes/config.php';

// ფილტრაციის პარამეტრები
$room_type = isset($_GET['type']) ? (int)$_GET['type'] : null;
$check_in = isset($_GET['check_in']) && $_GET['check_in'] >= date('Y-m-d') ? $_GET['check_in'] : date('Y-m-d');
$check_out = isset($_GET['check_out']) && $_GET['check_out'] > $check_in ? $_GET['check_out'] : date('Y-m-d', strtotime('+1 day'));

// ოთახების ძებნა მთავარი სურათებით
$query = "SELECT r.*, rt.name as type_name, rt.price_per_night, rt.capacity as type_capacity, 
          rt.amenities, rt.description as type_description, ri.image_path as main_image
          FROM rooms r
          JOIN room_types rt ON r.room_type_id = rt.id
          LEFT JOIN room_images ri ON r.main_image_id = ri.id
          WHERE r.status = 'available'";

$params = [];

if ($room_type) {
    $query .= " AND r.room_type_id = :room_type";
    $params[':room_type'] = $room_type;
}

// შევამოწმოთ ხელმისაწვდომობა თარიღების მიხედვით
if (!empty($check_in) && !empty($check_out) && $check_in < $check_out) {
    $query .= " AND r.id NOT IN (
        SELECT room_id FROM bookings 
        WHERE (
            (check_in <= :check_out AND check_out >= :check_in)
            AND status IN ('confirmed', 'pending')
        )
    )";
    $params[':check_in'] = $check_in;
    $params[':check_out'] = $check_out;
} else {
    // თუ თარიღები არ არის მითითებული ან არასწორია, ვაჩვენებთ ყველა ხელმისაწვდომ ოთახს
    $query .= " AND r.id NOT IN (
        SELECT room_id FROM bookings 
        WHERE status IN ('confirmed', 'pending')
        AND check_out >= CURDATE()
    )";
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rooms = $stmt->fetchAll();

// ოთახების ტიპები დროფდაუნისთვის
$room_types = $pdo->query("SELECT * FROM room_types")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ოთახების ძებნა</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .room-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 20px;
            height: 100%;
            border: none;
            border-radius: 10px;
            overflow: hidden;
        }
        .room-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
        }
        .room-img {
            height: 250px;
            object-fit: cover;
            width: 100%;
            transition: transform 0.5s ease;
        }
        .room-card:hover .room-img {
            transform: scale(1.05);
        }
        .amenities-list {
            list-style-type: none;
            padding-left: 0;
            margin-bottom: 1rem;
        }
        .amenities-list li {
            padding: 5px 0;
            display: flex;
            align-items: center;
        }
        .amenities-list li:before {
            content: "✓";
            color: #28a745;
            margin-right: 10px;
            font-weight: bold;
        }
        .price-tag {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
            margin: 10px 0;
        }
        .search-form {
            background-color: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        .filter-section {
            margin-bottom: 30px;
        }
        .capacity-badge {
            background-color: #3498db;
            font-size: 0.9rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .no-rooms-message {
            padding: 30px;
            text-align: center;
            background-color: #f8f9fa;
            border-radius: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container mt-4 mb-5">
        <div class="filter-section">
            <h2 class="mb-4 text-center">ოთახების ძებნა</h2>
            
            <div class="search-form">
                <form method="GET" action="search.php">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="check_in" class="form-label">ჩაფხუტის თარიღი</label>
                            <input type="date" class="form-control" id="check_in" name="check_in" 
                                   value="<?= htmlspecialchars($check_in) ?>" min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="check_out" class="form-label">გამგზავრების თარიღი</label>
                            <input type="date" class="form-control" id="check_out" name="check_out" 
                                   value="<?= htmlspecialchars($check_out) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="type" class="form-label">ოთახის ტიპი</label>
                            <select class="form-select" id="type" name="type">
                                <option value="">ყველა ტიპი</option>
                                <?php foreach ($room_types as $type): ?>
                                    <option value="<?= $type['id'] ?>" <?= $room_type == $type['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($type['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100 py-2">
                                <i class="bi bi-search"></i> ძებნა
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($rooms)): ?>
            <div class="no-rooms-message">
                <h4><i class="bi bi-info-circle"></i> ოთახები ვერ მოიძებნა</h4>
                <p class="mb-0">მოცემული კრიტერიუმებით ოთახები ვერ მოიძებნა. გთხოვთ სცადოთ სხვა პარამეტრები.</p>
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($rooms as $room): ?>
                    <div class="col">
                        <div class="card room-card h-100">
                            <img src="<?= !empty($room['main_image']) ? htmlspecialchars($room['main_image']) : 'assets/images/default-room.jpg' ?>" 
                                 class="card-img-top room-img" alt="<?= htmlspecialchars($room['type_name']) ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title mb-0"><?= htmlspecialchars($room['type_name']) ?></h5>
                                    <span class="capacity-badge text-white"><?= $room['type_capacity'] ?> სტუმარი</span>
                                </div>
                                <p class="text-muted mb-2">ოთახი #<?= htmlspecialchars($room['room_number']) ?></p>
                                
                                <div class="price-tag"><?= $room['price_per_night'] ?> ₾ / ღამე</div>
                                
                                <p class="card-text"><?= htmlspecialchars($room['type_description']) ?></p>
                                
                                <h6 class="mt-3 mb-2">შენიშვნები:</h6>
                                <ul class="amenities-list">
                                    <?php 
                                    $amenities = explode(',', $room['amenities']);
                                    foreach ($amenities as $amenity): 
                                        if (!empty(trim($amenity))): ?>
                                            <li><?= htmlspecialchars(trim($amenity)) ?></li>
                                        <?php endif;
                                    endforeach; ?>
                                </ul>
                            </div>
                            <div class="card-footer bg-transparent border-top-0">
                                <a href="booking.php?room_id=<?= $room['id'] ?>&check_in=<?= $check_in ?>&check_out=<?= $check_out ?>" 
                                   class="btn btn-primary w-100 <?= !is_logged_in() ? 'disabled' : '' ?>">
                                    <i class="bi bi-calendar-check"></i> ჯავშნის გაკეთება
                                </a>
                                <?php if (!is_logged_in()): ?>
                                    <p class="text-muted mt-2 text-center small">ჯავშნის გასაკეთებლად გთხოვთ შეხვიდეთ სისტემაში</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // დარწმუნდეთ რომ გამგზავრების თარიღი არის ჩაფხუტის თარიღის შემდეგ
        document.getElementById('check_in').addEventListener('change', function() {
            const checkInDate = new Date(this.value);
            const today = new Date();
            
            // თუ არჩეული თარიღი არის დღევანდელზე ადრე
            if (checkInDate < today) {
                this.value = today.toISOString().split('T')[0];
                checkInDate.setDate(today.getDate());
            }
            
            checkInDate.setDate(checkInDate.getDate() + 1);
            const minCheckOut = checkInDate.toISOString().split('T')[0];
            const checkOutEl = document.getElementById('check_out');
            
            checkOutEl.min = minCheckOut;
            
            if (new Date(checkOutEl.value) < checkInDate) {
                checkOutEl.value = minCheckOut;
            }
        });

        // ინიციალიზაცია - შევამოწმოთ თარიღები გვერდის ჩატვირთვისას
        document.addEventListener('DOMContentLoaded', function() {
            const checkIn = document.getElementById('check_in');
            const checkOut = document.getElementById('check_out');
            const today = new Date().toISOString().split('T')[0];
            
            // თუ check_in არის დღევანდელზე ადრე
            if (checkIn.value < today) {
                checkIn.value = today;
            }
            
            // თუ check_out არის check_in-ზე ადრე ან ტოლი
            if (new Date(checkOut.value) <= new Date(checkIn.value)) {
                const newDate = new Date(checkIn.value);
                newDate.setDate(newDate.getDate() + 1);
                checkOut.value = newDate.toISOString().split('T')[0];
            }
            
            checkOut.min = new Date(checkIn.value);
            checkOut.min.setDate(checkOut.min.getDate() + 1);
        });
    </script>
</body>
</html>