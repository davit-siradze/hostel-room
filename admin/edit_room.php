<?php
require_once '../includes/config.php';
redirect_if_not_admin();

if (!isset($_GET['id'])) {
    header("Location: rooms.php");
    exit();
}

$room_id = (int)$_GET['id'];

// ოთახის მონაცემები
$stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch();

if (!$room) {
    $_SESSION['error_message'] = "ოთახი ვერ მოიძებნა";
    header("Location: rooms.php");
    exit();
}

// ოთახის ფოტოები
$images = $pdo->prepare("SELECT * FROM room_images WHERE room_id = ? ORDER BY is_primary DESC");
$images->execute([$room_id]);
$room_images = $images->fetchAll();

// ოთახის ტიპები
$room_types = $pdo->query("SELECT * FROM room_types")->fetchAll();

// ოთახის განახლება
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_number = sanitize_input($_POST['room_number']);
    $room_type_id = (int)$_POST['room_type_id'];
    $status = sanitize_input($_POST['status']);
    $description = sanitize_input($_POST['description']);
    $price = (float)$_POST['price'];
    $primary_image = isset($_POST['primary_image']) ? (int)$_POST['primary_image'] : null;
    
    $errors = [];
    
    // ვალიდაცია
    if (empty($room_number)) $errors[] = "ოთახის ნომერი სავალდებულოა";
    if (empty($room_type_id)) $errors[] = "ოთახის ტიპი სავალდებულოა";
    if (empty($status)) $errors[] = "სტატუსი სავალდებულოა";
    if (empty($description)) $errors[] = "აღწერა სავალდებულოა";
    if (empty($price) || $price <= 0) $errors[] = "გთხოვთ მიუთითოთ სწორი ფასი";
    
    // ახალი ფოტოების ატვირთვა
    $uploaded_images = [];
    if (!empty($_FILES['images']['name'][0])) {
        $upload_dir = '../assets/images/rooms/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0775, true);
        }
        
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                $file_name = $_FILES['images']['name'][$key];
                $file_size = $_FILES['images']['size'][$key];
                $file_type = $_FILES['images']['type'][$key];
                
                $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
                $max_size = 5 * 1024 * 1024; // 5MB
                
                if (!in_array($file_type, $allowed_types)) {
                    $errors[] = "დაუშვებელი ფაილის ტიპი: $file_name";
                    continue;
                }
                
                if ($file_size > $max_size) {
                    $errors[] = "ფაილის ზომა ძალიან დიდია: $file_name (მაქსიმუმ 5MB)";
                    continue;
                }
                
                $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
                $new_name = 'room_' . uniqid() . '.' . $file_ext;
                $upload_path = $upload_dir . $new_name;
                
                if (move_uploaded_file($tmp_name, $upload_path)) {
                    $uploaded_images[] = "assets/images/rooms/$new_name";
                } else {
                    $errors[] = "ფაილის ატვირთვა ვერ მოხერხდა: $file_name";
                }
            }
        }
    }
    
    // ოთახის ნომრის უნიკალურობის შემოწმება
    $stmt = $pdo->prepare("SELECT id FROM rooms WHERE room_number = ? AND id != ?");
    $stmt->execute([$room_number, $room_id]);
    
    if ($stmt->rowCount() > 0) {
        $errors[] = "ოთახის ნომერი უკვე გამოყენებულია";
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // ოთახის განახლება
            $stmt = $pdo->prepare("UPDATE rooms SET room_number = ?, room_type_id = ?, status = ?, description = ?, price = ? WHERE id = ?");
            $stmt->execute([$room_number, $room_type_id, $status, $description, $price, $room_id]);
            
            // ახალი ფოტოების დამატება
            if (!empty($uploaded_images)) {
                foreach ($uploaded_images as $image_path) {
                    $stmt = $pdo->prepare("INSERT INTO room_images (room_id, image_path) VALUES (?, ?)");
                    $stmt->execute([$room_id, $image_path]);
                }
            }
            
            // მთავარი სურათის განახლება
            if ($primary_image) {
                // ყველა სურათისთვის ამოვიღოთ მთავარი სტატუსი
                $stmt = $pdo->prepare("UPDATE room_images SET is_primary = 0 WHERE room_id = ?");
                $stmt->execute([$room_id]);
                
                // დავაყენოთ ახალი მთავარი სურათი
                $stmt = $pdo->prepare("UPDATE room_images SET is_primary = 1 WHERE id = ? AND room_id = ?");
                $stmt->execute([$primary_image, $room_id]);
                
                // განვაახლოთ მთავარი სურათის ID ოთახის ცხრილში
                $stmt = $pdo->prepare("UPDATE rooms SET main_image_id = ? WHERE id = ?");
                $stmt->execute([$primary_image, $room_id]);
            }
            
            $pdo->commit();
            
            $_SESSION['success_message'] = "ოთახის მონაცემები წარმატებით განახლდა";
            header("Location: rooms.php");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "განახლების დროს დაფიქსირდა შეცდომა: " . $e->getMessage();
        }
    }
}

// ფოტოს წაშლა
if (isset($_GET['delete_image'])) {
    $image_id = (int)$_GET['delete_image'];
    
    // მივიღოთ სურათის მონაცემები
    $stmt = $pdo->prepare("SELECT * FROM room_images WHERE id = ? AND room_id = ?");
    $stmt->execute([$image_id, $room_id]);
    $image = $stmt->fetch();
    
    if ($image) {
        try {
            $pdo->beginTransaction();
            
            // წავშალოთ ფაილი სერვერიდან
            if (file_exists('../' . $image['image_path'])) {
                unlink('../' . $image['image_path']);
            }
            
            // წავშალოთ ჩანაწერი ბაზიდან
            $stmt = $pdo->prepare("DELETE FROM room_images WHERE id = ?");
            $stmt->execute([$image_id]);
            
            // თუ ეს იყო მთავარი სურათი, განვაახლოთ ოთახის მონაცემები
            if ($room['main_image_id'] == $image_id) {
                $stmt = $pdo->prepare("UPDATE rooms SET main_image_id = NULL WHERE id = ?");
                $stmt->execute([$room_id]);
                
                // ავირჩიოთ ახალი მთავარი სურათი
                $stmt = $pdo->prepare("SELECT id FROM room_images WHERE room_id = ? LIMIT 1");
                $stmt->execute([$room_id]);
                $new_primary = $stmt->fetch();
                
                if ($new_primary) {
                    $stmt = $pdo->prepare("UPDATE room_images SET is_primary = 1 WHERE id = ?");
                    $stmt->execute([$new_primary['id']]);
                    
                    $stmt = $pdo->prepare("UPDATE rooms SET main_image_id = ? WHERE id = ?");
                    $stmt->execute([$new_primary['id'], $room_id]);
                }
            }
            
            $pdo->commit();
            
            $_SESSION['success_message'] = "სურათი წარმატებით წაიშალა";
            header("Location: edit_room.php?id=$room_id");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "სურათის წაშლის დროს დაფიქსირდა შეცდომა: " . $e->getMessage();
            header("Location: edit_room.php?id=$room_id");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ოთახის რედაქტირება</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .image-preview {
            max-width: 150px;
            max-height: 150px;
            margin: 5px;
        }
        .description-textarea {
            min-height: 120px;
        }
        .price-input {
            max-width: 200px;
        }
        .room-image-card {
            transition: all 0.3s ease;
        }
        .room-image-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">ოთახის რედაქტირება</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="rooms.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> უკან
                        </a>
                    </div>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= $error ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="room_number" class="form-label">ოთახის ნომერი</label>
                                    <input type="text" class="form-control" id="room_number" name="room_number" 
                                           value="<?= htmlspecialchars($room['room_number']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="room_type_id" class="form-label">ოთახის ტიპი</label>
                                    <select class="form-select" id="room_type_id" name="room_type_id" required>
                                        <option value="">აირჩიეთ ტიპი</option>
                                        <?php foreach ($room_types as $type): ?>
                                            <option value="<?= $type['id'] ?>" <?= $room['room_type_id'] == $type['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($type['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="status" class="form-label">სტატუსი</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="available" <?= $room['status'] == 'available' ? 'selected' : '' ?>>ხელმისაწვდომი</option>
                                        <option value="occupied" <?= $room['status'] == 'occupied' ? 'selected' : '' ?>>დაკავებული</option>
                                        <option value="maintenance" <?= $room['status'] == 'maintenance' ? 'selected' : '' ?>>ტექნიკური მომსახურება</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="price" class="form-label">ფასი (₾)</label>
                                    <div class="input-group price-input">
                                        <input type="number" class="form-control" id="price" name="price" 
                                               value="<?= htmlspecialchars($room['price'] ?? '') ?>" min="0" step="0.01" required>
                                        <span class="input-group-text">₾/ღამე</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">ოთახის აღწერა</label>
                                <textarea class="form-control description-textarea" id="description" name="description" required><?= htmlspecialchars($room['description'] ?? '') ?></textarea>
                                <div class="form-text">მიუთითეთ ოთახის დეტალები, ფუნქციონალი და კომფორტი</div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">ოთახის ფოტოები</label>
                                
                                <?php if (!empty($room_images)): ?>
                                    <div class="row mb-3">
                                        <?php foreach ($room_images as $image): ?>
                                            <div class="col-md-3 mb-3">
                                                <div class="card room-image-card">
                                                    <img src="../<?= htmlspecialchars($image['image_path']) ?>" class="card-img-top" style="height: 150px; object-fit: cover;">
                                                    <div class="card-body p-2">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="primary_image" 
                                                                   id="primary_<?= $image['id'] ?>" value="<?= $image['id'] ?>" 
                                                                   <?= $image['is_primary'] ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="primary_<?= $image['id'] ?>">
                                                                მთავარი სურათი
                                                            </label>
                                                        </div>
                                                        <div class="mt-2">
                                                            <a href="edit_room.php?id=<?= $room_id ?>&delete_image=<?= $image['id'] ?>" 
                                                               class="btn btn-sm btn-outline-danger"
                                                               onclick="return confirm('დარწმუნებული ხართ რომ გსურთ ამ ფოტოს წაშლა?')">
                                                                <i class="bi bi-trash"></i> წაშლა
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">ფოტოები არ მოიძებნა</div>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label for="images" class="form-label">ახალი ფოტოების დამატება</label>
                                    <input type="file" class="form-control" id="images" name="images[]" multiple accept="image/*">
                                    <div class="form-text">შეგიძლიათ ატვირთოთ რამდენიმე ფოტო (მაქსიმუმ 5MB თითოეული)</div>
                                </div>
                                
                                <div id="image-preview" class="row mb-3"></div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> განახლება
                                </button>
                                <a href="rooms.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i> გაუქმება
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons@4.28.0/dist/feather.min.js"></script>
    <script>
        feather.replace();
        
        // ახალი სურათების პრევიუ
        document.getElementById('images').addEventListener('change', function(e) {
            const preview = document.getElementById('image-preview');
            preview.innerHTML = '';
            
            if (this.files) {
                for (let i = 0; i < this.files.length; i++) {
                    const file = this.files[i];
                    if (file.type.match('image.*')) {
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            const col = document.createElement('div');
                            col.className = 'col-md-3 mb-3';
                            
                            const card = document.createElement('div');
                            card.className = 'card';
                            
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.className = 'card-img-top';
                            img.style.height = '150px';
                            img.style.objectFit = 'cover';
                            
                            card.appendChild(img);
                            col.appendChild(card);
                            preview.appendChild(col);
                        }
                        
                        reader.readAsDataURL(file);
                    }
                }
            }
        });
    </script>
</body>
</html>