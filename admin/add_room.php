<?php
require_once '../includes/config.php';
redirect_if_not_admin();

$room_types = $pdo->query("SELECT * FROM room_types")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_number = sanitize_input($_POST['room_number']);
    $room_type_id = (int)$_POST['room_type_id'];
    $status = sanitize_input($_POST['status']);
    $description = sanitize_input($_POST['description']);
    $price = (float)$_POST['price']; // ახალი ველი - ფასი
    
    $errors = [];
    
    // ვალიდაცია
    if (empty($room_number)) {
        $errors[] = "ოთახის ნომერი სავალდებულოა";
    }
    
    if (empty($room_type_id)) {
        $errors[] = "ოთახის ტიპი სავალდებულოა";
    }
    
    if (empty($status)) {
        $errors[] = "სტატუსი სავალდებულოა";
    }
    
    if (empty($description)) {
        $errors[] = "აღწერა სავალდებულოა";
    }
    
    if (empty($price) || $price <= 0) {
        $errors[] = "გთხოვთ მიუთითოთ სწორი ფასი";
    }
    
    // ... დარჩენილი კოდი უცვლელი ...
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // ოთახის დამატება აღწერით და ფასით
            $stmt = $pdo->prepare("INSERT INTO rooms (room_number, room_type_id, status, description, price) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$room_number, $room_type_id, $status, $description, $price]);
            $room_id = $pdo->lastInsertId();
            
            // ... დარჩენილი კოდი უცვლელი ...
            
            $pdo->commit();
            $_SESSION['success_message'] = "ოთახი წარმატებით დაემატა!";
            header("Location: rooms.php");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "მონაცემთა ბაზის შეცდომა: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <title>ახალი ოთახის დამატება</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .image-preview {
            max-width: 150px;
            max-height: 150px;
            margin: 5px;
        }
        textarea {
            min-height: 100px;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container mt-4">
        <h2>ახალი ოთახის დამატება</h2>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="post" enctype="multipart/form-data">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="room_number" class="form-label">ოთახის ნომერი</label>
                    <input type="text" class="form-control" id="room_number" name="room_number" required>
                </div>
                <div class="col-md-6">
                    <label for="room_type_id" class="form-label">ოთახის ტიპი</label>
                    <select class="form-select" id="room_type_id" name="room_type_id" required>
                        <option value="">აირჩიეთ ტიპი</option>
                        <?php foreach ($room_types as $type): ?>
                            <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="status" class="form-label">სტატუსი</label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="available">ხელმისაწვდომი</option>
                        <option value="occupied">დაკავებული</option>
                        <option value="maintenance">ტექნიკური მომსახურება</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="price" class="form-label">ფასი (₾)</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="price" name="price" min="0" step="0.01" required>
                        <span class="input-group-text">₾/ღამე</span>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label">ოთახის აღწერა</label>
                <textarea class="form-control" id="description" name="description" required></textarea>
                <small class="text-muted">მიუთითეთ ოთახის დეტალები, ფუნქციონალი და კომფორტი</small>
            </div>
            
            <div class="mb-3">
                <label class="form-label">ოთახის ფოტოები</label>
                <input type="file" class="form-control" name="images[]" multiple accept="image/*" required>
                <small class="text-muted">პირველი ატვირთული ფოტო გახდება მთავარი სურათი</small>
                <div id="imagePreviews" class="mt-2"></div>
            </div>
            
            <button type="submit" class="btn btn-primary">დამატება</button>
            <a href="rooms.php" class="btn btn-secondary">გაუქმება</a>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // სურათების პრევიუ
        document.querySelector('input[name="images[]"]').addEventListener('change', function(e) {
            const previews = document.getElementById('imagePreviews');
            previews.innerHTML = '';
            
            for (const file of e.target.files) {
                if (file.type.match('image.*')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'image-preview';
                        previews.appendChild(img);
                    }
                    reader.readAsDataURL(file);
                }
            }
        });
    </script>
</body>
</html>