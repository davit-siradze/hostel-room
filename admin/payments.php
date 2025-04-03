<?php
require_once '../includes/config.php';
redirect_if_not_admin();

// Process payment status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    $payment_id = (int)$_POST['payment_id'];
    $status = sanitize_input($_POST['status']);
    
    try {
        $stmt = $pdo->prepare("UPDATE payments SET status = ? WHERE id = ?");
        $stmt->execute([$status, $payment_id]);
        
        $_SESSION['success_message'] = "Payment status updated successfully";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error updating payment: " . $e->getMessage();
    }
    
    header("Location: payments.php");
    exit();
}

// Process refund requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_refund'])) {
    $payment_id = (int)$_POST['payment_id'];
    
    try {
        $pdo->beginTransaction();
        
        // 1. Update payment status to "refunded"
        $stmt = $pdo->prepare("UPDATE payments SET status = 'refunded' WHERE id = ?");
        $stmt->execute([$payment_id]);
        
        // 2. Update booking status to "cancelled"
        $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE payment_id = ?");
        $stmt->execute([$payment_id]);
        
        // 3. Log the refund
        $stmt = $pdo->prepare("INSERT INTO refunds (payment_id, amount, processed_by, processed_at) 
                              SELECT id, amount, ?, NOW() FROM payments WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $payment_id]);
        
        $pdo->commit();
        
        $_SESSION['success_message'] = "Refund processed successfully";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error processing refund: " . $e->getMessage();
    }
    
    header("Location: payments.php");
    exit();
}

// Modified query to use users table instead of guests
$query = "SELECT p.*, 
                 b.id as booking_id, b.check_in, b.check_out, b.status as booking_status,
                 u.username, u.email, /* Using users table instead of guests */
                 r.room_number, rt.name as room_type
          FROM payments p
          JOIN bookings b ON p.booking_id = b.id
          JOIN users u ON b.user_id = u.id /* Changed to user_id */
          JOIN rooms r ON b.room_id = r.id
          JOIN room_types rt ON r.room_type_id = rt.id
          ORDER BY p.payment_date DESC";
$payments = $pdo->query($query)->fetchAll();
?>


<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>გადახდების მართვა</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>

    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">გადახდების მართვა</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">ექსპორტი</button>
                        </div>
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
                
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-funnel"></i> ფილტრი
                    </div>
                    <div class="card-body">
                        <form method="get" class="row g-3">
                            <div class="col-md-3">
                                <label for="status" class="form-label">სტატუსი</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">ყველა</option>
                                    <option value="pending">მოლოდინში</option>
                                    <option value="completed">დასრულებული</option>
                                    <option value="failed">შეცდომა</option>
                                    <option value="refunded">დაბრუნებული</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="start_date" class="form-label">დაწყების თარიღი</label>
                                <input type="date" class="form-control" id="start_date" name="start_date">
                            </div>
                            <div class="col-md-3">
                                <label for="end_date" class="form-label">დასრულების თარიღი</label>
                                <input type="date" class="form-control" id="end_date" name="end_date">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">ფილტრი</button>
                                <a href="payments.php" class="btn btn-outline-secondary ms-2">გასუფთავება</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <div class="table">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>გადახდის თარიღი</th>
                                        <th>სტუმარი</th>
                                        <th>ოთახი</th>
                                        <th>ჯავშანი</th>
                                        <th>თანხა</th>
                                        <th>მეთოდი</th>
                                        <th>სტატუსი</th>
                                        <th>მოქმედება</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td><?= $payment['id'] ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($payment['payment_date'])) ?></td>
                                            <td>
                                                <?= htmlspecialchars($payment['username']) ?><br>
                                                <small class="text-muted"><?= htmlspecialchars($payment['email']) ?></small>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($payment['room_number']) ?><br>
                                                <small class="text-muted"><?= htmlspecialchars($payment['room_type']) ?></small>
                                            </td>
                                            <td>
                                                #<?= $payment['booking_id'] ?><br>
                                                <?= date('d/m/Y', strtotime($payment['check_in'])) ?> - <?= date('d/m/Y', strtotime($payment['check_out'])) ?>
                                            </td>
                                            <td><?= $payment['amount'] ?> ₾</td>
                                            <td><?= ucfirst($payment['payment_method']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= 
                                                    $payment['status'] === 'completed' ? 'success' : 
                                                    ($payment['status'] === 'pending' ? 'warning' : 
                                                    ($payment['status'] === 'refunded' ? 'info' : 'danger'))
                                                ?>">
                                                    <?= $payment['status'] ?>
                                                </span>
                                            </td>
                                            <td>
                                            <div class="dropdown" >

                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="actionMenu" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="bi bi-gear"></i>
                                                    </button>
                                                    <ul class="dropdown-menu" style="z-index: 1060;" aria-labelledby="actionMenu">
                                                        <li>
                                                            <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#statusModal<?= $payment['id'] ?>">
                                                                <i class="bi bi-pencil"></i> სტატუსის შეცვლა
                                                            </button>
                                                        </li>
                                                        <?php if ($payment['status'] === 'completed' && $payment['booking_status'] !== 'cancelled'): ?>
                                                        <li>
                                                            <button class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#refundModal<?= $payment['id'] ?>">
                                                                <i class="bi bi-arrow-counterclockwise"></i> დაბრუნება
                                                            </button>
                                                        </li>
                                                        <?php endif; ?>
                                                        <li><a class="dropdown-item" href="#"><i class="bi bi-receipt"></i> ქვითარი</a></li>
                                                    </ul>
                                                </div>
                                                
                                                <!-- Status Update Modal -->
                                                <div class="modal fade" id="statusModal<?= $payment['id'] ?>" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <form method="post">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="statusModalLabel">გადახდის სტატუსის შეცვლა</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                                                                    <div class="mb-3">
                                                                        <label for="status" class="form-label">სტატუსი</label>
                                                                        <select class="form-select" id="status" name="status" required>
                                                                            <option value="pending" <?= $payment['status'] === 'pending' ? 'selected' : '' ?>>მოლოდინში</option>
                                                                            <option value="completed" <?= $payment['status'] === 'completed' ? 'selected' : '' ?>>დასრულებული</option>
                                                                            <option value="failed" <?= $payment['status'] === 'failed' ? 'selected' : '' ?>>შეცდომა</option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">გაუქმება</button>
                                                                    <button type="submit" name="update_payment" class="btn btn-primary">შენახვა</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Refund Modal -->
                                                <div class="modal fade" id="refundModal<?= $payment['id'] ?>" tabindex="-1" aria-labelledby="refundModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <form method="post">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="refundModalLabel">გადახდის დაბრუნება</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                                                                    <p>დარწმუნებული ხართ, რომ გსურთ ამ გადახდის დაბრუნება?</p>
                                                                    <div class="alert alert-warning">
                                                                        <i class="bi bi-exclamation-triangle"></i> ეს მოქმედება ასევე გააუქმებს დაკავშირებულ ჯავშანს (#<?= $payment['booking_id'] ?>)
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">გაუქმება</button>
                                                                    <button type="submit" name="process_refund" class="btn btn-danger">დაბრუნება</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
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