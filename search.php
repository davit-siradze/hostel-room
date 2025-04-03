<?php
require_once 'includes/config.php';

// ფილტრაციის პარამეტრები
$room_type = isset($_GET['type']) ? (int)$_GET['type'] : null;
$check_in = isset($_GET['check_in']) ? $_GET['check_in'] : date('Y-m-d');
$check_out = isset($_GET['check_out']) ? $_GET['check_out'] : date('Y-m-d', strtotime('+1 day'));
$capacity = isset($_GET['capacity']) ? (int)$_GET['capacity'] : 2;

// ოთახების ძებნა მთავარი სურათებით
$query = "SELECT r.*, rt.name as type_name, rt.price_per_night, rt.capacity as type_capacity, 
          rt.amenities, rt.description as type_description, ri.image_path as main_image
          FROM rooms r
          JOIN room_types rt ON r.room_type_id = rt.id
          LEFT JOIN room_images ri ON r.main_image_id = ri.id
          WHERE r.status = 'available' 
          AND rt.capacity >= :capacity";

$params = [':capacity' => $capacity];

if ($room_type) {
    $query .= " AND r.room_type_id = :room_type";
    $params[':room_type'] = $room_type;
}

// შევამოწმოთ ხელმისაწვდომობა თარიღების მიხედვით
if (!empty($check_in) && !empty($check_out)) {
    $query .= " AND r.id NOT IN (
        SELECT room_id FROM bookings 
        WHERE (
            (check_in <= :check_out AND check_out >= :check_in)
            AND status IN ('confirmed', 'pending')
        )
    )";
    $params[':check_in'] = $check_in;
    $params[':check_out'] = $check_out;
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
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .room-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 20px;
            height: 100%;
        }
        .room-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .room-img {
            height: 250px;
            object-fit: cover;
            width: 100%;
        }
        .amenities-list {
            list-style-type: none;
            padding-left: 0;
        }
        .amenities-list li {
            padding: 3px 0;
        }
        .amenities-list li:before {
            content: "✓ ";
            color: #28a745;
        }
        .price-tag {
            font-size: 1.5rem;
            font-weight: bold;
            color: #28a745;
        }
        .search-form {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .filter-section {
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container mt-5 mb-5">
        <div class="filter-section">
            <h2 class="mb-4">ოთახების ძებნა</h2>
            
            <div class="search-form">
                <form method="GET" action="search.php">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="check_in" class="form-label">ჩაფხუტის თარიღი</label>
                            <input type="date" class="form-control" id="check_in" name="check_in" 
                                   value="<?= htmlspecialchars($check_in) ?>" min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3">
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
                        <div class="col-md-2">
                            <label for="capacity" class="form-label">სტუმრების რაოდენობა</label>
                            <select class="form-select" id="capacity" name="capacity">
                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                    <option value="<?= $i ?>" <?= $capacity == $i ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                                <option value="10" <?= $capacity > 6 ? 'selected' : '' ?>>6+ სტუმარი</option>
                            </select>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> ძებნა
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php if (empty($rooms)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> მოცემული კრიტერიუმებით ოთახები ვერ მოიძებნა. გთხოვთ სცადოთ სხვა პარამეტრები.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($rooms as $room): ?>
                    <div class="col">
                        <div class="card room-card">
                            <img src="<?= !empty($room['main_image']) ? htmlspecialchars($room['main_image']) : 'assets/images/default-room.jpg' ?>" 
                                 class="card-img-top room-img" alt="<?= htmlspecialchars($room['type_name']) ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="card-title mb-0"><?= htmlspecialchars($room['type_name']) ?> #<?= htmlspecialchars($room['room_number']) ?></h5>
                                    <span class="badge bg-primary">ტევადობა: <?= $room['type_capacity'] ?></span>
                                </div>
                                
                                <div class="price-tag mb-3"><?= $room['price_per_night'] ?> ₾ / ღამე</div>
                                
                                <p class="card-text"><?= htmlspecialchars($room['type_description']) ?></p>
                                
                                <h6 class="mt-3">შენიშვნები:</h6>
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
                            <div class="card-footer bg-transparent">
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
            <?php endif; ?>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // დარწმუნდეთ რომ გამგზავრების თარიღი არის ჩაფხუტის თარიღის შემდეგ
        document.getElementById('check_in').addEventListener('change', function() {
            const checkInDate = new Date(this.value);
            checkInDate.setDate(checkInDate.getDate() + 1);
            const minCheckOut = checkInDate.toISOString().split('T')[0];
            document.getElementById('check_out').min = minCheckOut;
            
            if (new Date(document.getElementById('check_out').value) < checkInDate) {
                document.getElementById('check_out').value = minCheckOut;
            }
        });

        // ინიციალიზაცია - შევამოწმოთ თარიღები გვერდის ჩატვირთვისას
        document.addEventListener('DOMContentLoaded', function() {
            const checkIn = document.getElementById('check_in');
            const checkOut = document.getElementById('check_out');
            
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