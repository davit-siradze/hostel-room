<?php
require_once 'includes/config.php';
redirect_if_not_logged_in();

if (!isset($_GET['room_id']) || !isset($_GET['check_in']) || !isset($_GET['check_out'])) {
    header("Location: search.php");
    exit();
}

$room_id = (int)$_GET['room_id'];
$check_in = $_GET['check_in'];
$check_out = $_GET['check_out'];

// ოთახის ინფორმაციის მიღება მთავარი სურათით
$stmt = $pdo->prepare("SELECT r.*, rt.name as type_name, rt.price_per_night, rt.capacity,
                      rt.description as type_description, rt.amenities, ri.image_path as main_image
                      FROM rooms r
                      JOIN room_types rt ON r.room_type_id = rt.id
                      LEFT JOIN room_images ri ON r.main_image_id = ri.id
                      WHERE r.id = ? AND r.status = 'available'");
$stmt->execute([$room_id]);
$room = $stmt->fetch();

if (!$room) {
    $_SESSION['error_message'] = "ოთახი ვერ მოიძებნა ან არ არის ხელმისაწვდომი";
    header("Location: search.php");
    exit();
}

// დარწმუნდეთ რომ თარიღები სწორია
$today = date('Y-m-d');
if ($check_in < $today || $check_out <= $check_in) {
    $_SESSION['error_message'] = "არასწორი თარიღები";
    header("Location: search.php");
    exit();
}

// შევამოწმოთ ხელმისაწვდომობა
$stmt = $pdo->prepare("SELECT id FROM bookings 
                      WHERE room_id = ? 
                      AND status IN ('confirmed', 'pending')
                      AND ((check_in <= ? AND check_out >= ?))");
$stmt->execute([$room_id, $check_out, $check_in]);

if ($stmt->rowCount() > 0) {
    $_SESSION['error_message'] = "ოთახი ამ პერიოდში დაკავებულია";
    header("Location: search.php");
    exit();
}

// გამოვთვალოთ ღირებულება
$nights = (strtotime($check_out) - strtotime($check_in)) / (60 * 60 * 24);
$total_price = $nights * $room['price_per_night'];

// ჯავშნის დამუშავება
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $notes = isset($_POST['notes']) ? sanitize_input($_POST['notes']) : '';
    $payment_method = isset($_POST['payment_method']) ? sanitize_input($_POST['payment_method']) : '';
    
    // ვალიდაცია
    if (empty($payment_method) || !in_array($payment_method, ['payme', 'tbc', 'bog'])) {
        $error_message = "გთხოვთ აირჩიოთ გადახდის მეთოდი";
    } else {
        try {
            $pdo->beginTransaction();
            
            // შევქმნათ ჯავშანი
            $stmt = $pdo->prepare("INSERT INTO bookings 
                                  (user_id, room_id, check_in, check_out, total_price, status, notes)
                                  VALUES (?, ?, ?, ?, ?, 'pending', ?)");
            $stmt->execute([$user_id, $room_id, $check_in, $check_out, $total_price, $notes]);
            $booking_id = $pdo->lastInsertId();
            
            // გადახდის პროცესი
            $payment_status = 'pending';
            $transaction_id = generate_transaction_id();
            
            // სიმულაცია - რეალურ პროექტში აქ იქნება API გამოძახება
            if (process_payment($payment_method, $total_price, $transaction_id)) {
                $payment_status = 'completed';
            }
            
            // დავამატოთ გადახდის ინფორმაცია
            $stmt = $pdo->prepare("INSERT INTO payments 
                                  (booking_id, amount, payment_method, transaction_id, status)
                                  VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$booking_id, $total_price, $payment_method, $transaction_id, $payment_status]);
            
            // თუ გადახდა წარმატებულია, განვაახლოთ ჯავშნის სტატუსი
            if ($payment_status === 'completed') {
                $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?");
                $stmt->execute([$booking_id]);
                
                $_SESSION['success_message'] = "ჯავშანი და გადახდა წარმატებით დასრულდა!";
                header("Location: profile.php");
                exit();
            } else {
                $_SESSION['warning_message'] = "ჯავშანი დაფიქსირდა, მაგრამ გადახდა ვერ მოხერხდა. გთხოვთ სცადოთ თავიდან.";
                header("Location: booking.php?room_id=$room_id&check_in=$check_in&check_out=$check_out");
                exit();
            }
            
            $pdo->commit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "ჯავშნის დროს დაფიქსირდა შეცდომა: " . $e->getMessage();
        }
    }
}

// დამხმარე ფუნქციები
function generate_transaction_id() {
    return 'TRX-' . time() . '-' . rand(1000, 9999);
}

function process_payment($method, $amount, $transaction_id) {
    // გადახდის ფუნქცია
    return true;
}
?>


<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ჯავშნის გაკეთება - Luxury Hotels</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --light-bg: #f8f9fa;
            --dark-text: #2c3e50;
            --light-text: #7f8c8d;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--dark-text);
            background-color: #f5f7fa;
        }
        
        .booking-container {
            max-width: 1200px;
            margin: 2rem auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .booking-header {
            background: var(--primary-color);
            color: white;
            padding: 1.5rem;
            position: relative;
        }
        
        .booking-header h2 {
            margin: 0;
            font-weight: 600;
        }
        
        .room-image-container {
            height: 350px;
            overflow: hidden;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .room-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .room-image:hover {
            transform: scale(1.03);
        }
        
        .room-title {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--primary-color);
        }
        
        .room-subtitle {
            color: var(--light-text);
            margin-bottom: 1.5rem;
        }
        
        .amenities-list {
            list-style: none;
            padding: 0;
            margin: 1.5rem 0;
        }
        
        .amenities-list li {
            padding: 0.5rem 0;
            display: flex;
            align-items: center;
        }
        
        .amenities-list li i {
            color: var(--secondary-color);
            margin-right: 10px;
            font-size: 1.1rem;
        }
        
        .booking-details {
            background: var(--light-bg);
            border-radius: 8px;
            padding: 1.5rem;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 0.8rem 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: var(--light-text);
        }
        
        .detail-value {
            font-weight: 500;
        }
        
        .total-price {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--accent-color);
        }
        
        .payment-method {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 1.2rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .payment-method:hover {
            border-color: var(--secondary-color);
            background-color: rgba(52, 152, 219, 0.05);
        }
        
        .payment-method.selected {
            border-color: var(--secondary-color);
            background-color: rgba(52, 152, 219, 0.1);
        }
        
        .payment-logo {
            height: 28px;
            margin-right: 12px;
        }
        
        .btn-confirm {
            background: var(--accent-color);
            border: none;
            padding: 12px 24px;
            font-size: 1.1rem;
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        
        .btn-confirm:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }
        
        .section-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
            position: relative;
            padding-bottom: 0.5rem;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 50px;
            height: 3px;
            background: var(--secondary-color);
        }
        
        .notes-textarea {
            min-height: 120px;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
            padding: 12px;
            transition: all 0.3s ease;
        }
        
        .notes-textarea:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        @media (max-width: 768px) {
            .room-image-container {
                height: 250px;
                margin-bottom: 1.5rem;
            }
            
            .room-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="booking-container my-5">
        <div class="booking-header">
            <h2><i class="bi bi-calendar-check"></i> ჯავშნის გაკეთება</h2>
        </div>
        
        <div class="row g-0">
            <div class="col-lg-8 p-4">
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?= $error_message ?></div>
                <?php endif; ?>
                
                <div class="row mb-5">
                    <div class="col-md-6">
                        <div class="room-image-container">
                            <img src="<?= !empty($room['main_image']) ? htmlspecialchars($room['main_image']) : 'assets/images/default-room.jpg' ?>" 
                                 class="room-image" alt="<?= htmlspecialchars($room['type_name']) ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h1 class="room-title"><?= htmlspecialchars($room['type_name']) ?></h1>
                        <p class="room-subtitle">ოთახი #<?= htmlspecialchars($room['room_number']) ?></p>
                        
                        <p><?= htmlspecialchars($room['type_description']) ?></p>
                        
                        <ul class="amenities-list">
                            <?php 
                            $amenities = explode(',', $room['amenities']);
                            foreach ($amenities as $amenity): 
                                if (!empty(trim($amenity))): ?>
                                    <li><i class="bi bi-check-circle"></i> <?= htmlspecialchars(trim($amenity)) ?></li>
                                <?php endif;
                            endforeach; ?>
                            <li><i class="bi bi-people"></i> ტევადობა: <?= $room['capacity'] ?> სტუმარი</li>
                        </ul>
                    </div>
                </div>
                
                <h3 class="section-title">ჯავშნის დეტალები</h3>
                <form method="POST" id="bookingForm">
                    <div class="booking-details mb-4">
                        <div class="detail-item">
                            <span class="detail-label">ჩაფხუტის თარიღი:</span>
                            <span class="detail-value"><?= date('d F Y', strtotime($check_in)) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">გამგზავრების თარიღი:</span>
                            <span class="detail-value"><?= date('d F Y', strtotime($check_out)) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">ღამეების რაოდენობა:</span>
                            <span class="detail-value"><?= $nights ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">ფასი ღამეში:</span>
                            <span class="detail-value"><?= $room['price_per_night'] ?> ₾</span>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="notes" class="form-label">სპეციალური მოთხოვნები</label>
                        <textarea class="notes-textarea form-control" id="notes" name="notes" 
                                  placeholder="მიუთითეთ სპეციალური მოთხოვნები (მაგ. დამატებითი საწოლი, ადგილის მოწყობა და ა.შ.)"></textarea>
                    </div>
                    
                    <h3 class="section-title mt-5">გადახდის მეთოდი</h3>
                    <div class="mb-4">
                        <div class="form-check payment-method" onclick="selectPaymentMethod('payme')">
                            <input class="form-check-input" type="radio" name="payment_method" id="payme" value="payme" required>
                            <label class="form-check-label d-flex align-items-center" for="payme">
                                <img src="assets/images/payments/bog.png" alt="Payme" class="payment-logo">
                                <div>
                                    <div class="fw-bold">Payme</div>
                                    <small class="text-muted">სწრაფი და უსაფრთხო გადახდა</small>
                                </div>
                            </label>
                        </div>
                        
                        <div class="form-check payment-method" onclick="selectPaymentMethod('tbc')">
                            <input class="form-check-input" type="radio" name="payment_method" id="tbc" value="tbc">
                            <label class="form-check-label d-flex align-items-center" for="tbc">
                                <img src="assets/images/payments/tbc.png" alt="TBC" class="payment-logo">
                                <div>
                                    <div class="fw-bold">TBC Pay</div>
                                    <small class="text-muted">გადახდა ბარათით</small>
                                </div>
                            </label>
                        </div>
                        
                        <div class="form-check payment-method" onclick="selectPaymentMethod('bog')">
                            <input class="form-check-input" type="radio" name="payment_method" id="bog" value="bog">
                            <label class="form-check-label d-flex align-items-center" for="bog">
                                <img src="assets/images/payments/bog.png" alt="Bank of Georgia" class="payment-logo">
                                <div>
                                    <div class="fw-bold">Bank of Georgia</div>
                                    <small class="text-muted">ონლაინ გადახდა</small>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div>
                            <h4 class="mb-1">ჯამური ღირებულება:</h4>
                            <p class="text-muted mb-0">დღგ ჩათვლილი</p>
                        </div>
                        <div class="total-price"><?= $total_price ?> ₾</div>
                    </div>
                    
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-confirm btn-lg">
                            <i class="bi bi-credit-card"></i> ჯავშნის დადასტურება და გადახდა
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="col-lg-4 bg-light p-4">
                <h3 class="section-title">შეკვეთის შეჯამება</h3>
                
                <div class="booking-details">
                    <div class="detail-item">
                        <span class="detail-label">ოთახი:</span>
                        <span class="detail-value"><?= htmlspecialchars($room['type_name']) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">ოთახის ნომერი:</span>
                        <span class="detail-value">#<?= htmlspecialchars($room['room_number']) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">ჩაფხუტი:</span>
                        <span class="detail-value"><?= date('d F Y', strtotime($check_in)) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">გამგზავრება:</span>
                        <span class="detail-value"><?= date('d F Y', strtotime($check_out)) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">ღამეები:</span>
                        <span class="detail-value"><?= $nights ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">ფასი ღამეში:</span>
                        <span class="detail-value"><?= $room['price_per_night'] ?> ₾</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">დღგ (18%):</span>
                        <span class="detail-value"><?= number_format($total_price * 0.18, 2) ?> ₾</span>
                    </div>
                    <div class="detail-item pt-3">
                        <span class="detail-label fw-bold">ჯამი:</span>
                        <span class="detail-value total-price"><?= $total_price ?> ₾</span>
                    </div>
                </div>
                
                <div class="alert alert-success mt-4">
                    <h5><i class="bi bi-shield-check"></i> უსაფრთხო გადახდა</h5>
                    <p class="small mb-0">თქვენი გადახდის ინფორმაცია დაცულია და დაშიფრულია.</p>
                </div>
                
                <div class="alert alert-info mt-3">
                    <h5><i class="bi bi-info-circle"></i> გაუქმების პოლიტიკა</h5>
                    <p class="small mb-0">თქვენ შეგიძლიათ გააუქმოთ ჯავშანი უფასოდ 48 საათის განმავლობაში.</p>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // გადახდის მეთოდის არჩევა
        function selectPaymentMethod(method) {
            document.querySelectorAll('.payment-method').forEach(el => {
                el.classList.remove('selected');
            });
            document.querySelector(`input[value="${method}"]`).checked = true;
            document.querySelector(`input[value="${method}"]`).parentElement.classList.add('selected');
        }
        
        // ფორმის გაგზავნისას დარწმუნდეთ რომ არჩეულია გადახდის მეთოდი
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
            if (!selectedMethod) {
                e.preventDefault();
                alert('გთხოვთ აირჩიოთ გადახდის მეთოდი');
                return false;
            }
            // დამატებითი ვალიდაცია შეიძლება დაემატოს
            return true;
        });
        
        // ავტომატურად აირჩიეთ პირველი გადახდის მეთოდი
        document.addEventListener('DOMContentLoaded', function() {
            const firstMethod = document.querySelector('input[name="payment_method"]');
            if (firstMethod) {
                firstMethod.checked = true;
                firstMethod.parentElement.classList.add('selected');
            }
        });
    </script>
</body>
</html>