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
    // რეალურ პროექტში აქ იქნება გადახდის API-თან კავშირი
    // ახლა ვაბრუნებთ true-ს როგორც სიმულაცია
    return true;
}
?>

<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ჯავშნის გაკეთება</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .booking-card {
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .room-image {
            height: 300px;
            object-fit: cover;
            width: 100%;
        }
        .payment-method {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .payment-method:hover {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }
        .payment-method.selected {
            border-color: #0d6efd;
            background-color: #e7f1ff;
        }
        .payment-logo {
            height: 30px;
            margin-right: 10px;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .total-price {
            font-size: 1.5rem;
            font-weight: bold;
            color: #28a745;
        }
        .amenities-list {
            list-style-type: none;
            padding-left: 0;
        }
        .amenities-list li {
            padding: 5px 0;
        }
        .amenities-list li:before {
            content: "✓";
            color: #28a745;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container my-5">
        <div class="row">
            <div class="col-lg-8">
                <div class="booking-card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h3><i class="bi bi-calendar-check"></i> ჯავშნის გაკეთება</h3>
                    </div>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger m-3"><?= $error_message ?></div>
                    <?php endif; ?>
                    
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-5">
                                <img src="<?= !empty($room['main_image']) ? htmlspecialchars($room['main_image']) : 'assets/images/default-room.jpg' ?>" 
                                     class="room-image rounded" alt="<?= htmlspecialchars($room['type_name']) ?>">
                            </div>
                            <div class="col-md-7">
                                <h4><?= htmlspecialchars($room['type_name']) ?> #<?= htmlspecialchars($room['room_number']) ?></h4>
                                <p class="text-muted"><?= htmlspecialchars($room['type_description']) ?></p>
                                
                                <h5 class="mt-4">შენიშვნები:</h5>
                                <ul class="amenities-list">
                                    <?php 
                                    $amenities = explode(',', $room['amenities']);
                                    foreach ($amenities as $amenity): 
                                        if (!empty(trim($amenity))): ?>
                                            <li><?= htmlspecialchars(trim($amenity)) ?></li>
                                        <?php endif;
                                    endforeach; ?>
                                </ul>
                                
                                <div class="mt-3">
                                    <span class="badge bg-primary"><i class="bi bi-people"></i> <?= $room['capacity'] ?> სტუმარი</span>
                                    <span class="badge bg-success ms-2"><i class="bi bi-cash"></i> <?= $room['price_per_night'] ?> ₾ ღამეში</span>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <h4 class="mb-3">ჯავშნის დეტალები</h4>
                        <form method="POST" id="bookingForm">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">ჩაფხუტის თარიღი</label>
                                    <input type="text" class="form-control" value="<?= date('d/m/Y', strtotime($check_in)) ?>" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">გამგზავრების თარიღი</label>
                                    <input type="text" class="form-control" value="<?= date('d/m/Y', strtotime($check_out)) ?>" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ღამეების რაოდენობა</label>
                                    <input type="text" class="form-control" value="<?= $nights ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">დამატებითი მოთხოვნები (არასავალდებულო)</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="მიუთითეთ სპეციალური მოთხოვნები, თუ რამე გაქვთ"></textarea>
                            </div>
                            
                            <div class="alert alert-info">
                                <h5>ჯამური ღირებულება: <span class="total-price"><?= $total_price ?> ₾</span></h5>
                                <p class="mb-0">გადახდა ხდება ონლაინ რეჟიმში</p>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-credit-card"></i> გადახდის გვერდზე გადასვლა
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="booking-card">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="bi bi-credit-card"></i> გადახდის მეთოდი</h4>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <div class="form-check payment-method" onclick="selectPaymentMethod('payme')">
                                <input class="form-check-input" type="radio" name="payment_method" id="payme" value="payme" required>
                                <label class="form-check-label d-flex align-items-center" for="payme">
                                    <img src="assets/images/payments/payme.png" alt="Payme" class="payment-logo">
                                    Payme
                                </label>
                            </div>
                            
                            <div class="form-check payment-method" onclick="selectPaymentMethod('tbc')">
                                <input class="form-check-input" type="radio" name="payment_method" id="tbc" value="tbc">
                                <label class="form-check-label d-flex align-items-center" for="tbc">
                                    <img src="assets/images/payments/tbc.png" alt="TBC" class="payment-logo">
                                    TBC Pay
                                </label>
                            </div>
                            
                            <div class="form-check payment-method" onclick="selectPaymentMethod('bog')">
                                <input class="form-check-input" type="radio" name="payment_method" id="bog" value="bog">
                                <label class="form-check-label d-flex align-items-center" for="bog">
                                    <img src="assets/images/payments/bog.png" alt="Bank of Georgia" class="payment-logo">
                                    Bank of Georgia
                                </label>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <h5 class="mb-3">შეკვეთის დეტალები</h5>
                        <div class="summary-item">
                            <span>ოთახი:</span>
                            <span><?= htmlspecialchars($room['type_name']) ?> #<?= htmlspecialchars($room['room_number']) ?></span>
                        </div>
                        <div class="summary-item">
                            <span>ღამეების რაოდენობა:</span>
                            <span><?= $nights ?></span>
                        </div>
                        <div class="summary-item">
                            <span>ფასი ღამეში:</span>
                            <span><?= $room['price_per_night'] ?> ₾</span>
                        </div>
                        <div class="summary-item">
                            <span>დღგ (18%):</span>
                            <span><?= number_format($total_price * 0.18, 2) ?> ₾</span>
                        </div>
                        <div class="summary-item fw-bold">
                            <span>ჯამი:</span>
                            <span class="total-price"><?= $total_price ?> ₾</span>
                        </div>
                        
                        <div class="alert alert-warning mt-3">
                            <small><i class="bi bi-info-circle"></i> გადახდის დასრულების შემდეგ მიიღებთ დადასტურებას ელ.ფოსტაზე.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

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
            }
        });
    </script>
</body>
</html>