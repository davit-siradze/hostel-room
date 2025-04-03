<?php
// გამოიყენეთ ახალი დირექტორია
$upload_dir = '/Applications/XAMPP/xamppfiles/hotel_uploads';

// დარწმუნდით, რომ დირექტორია არსებობს
if (!file_exists($upload_dir)) {
    die("დირექტორია არ არსებობს. გთხოვთ შეამოწმოთ ბმული.");
}

// ფაილის შექმნის მცდელობა
$test_file = $upload_dir . '/test_' . time() . '.txt';
if (file_put_contents($test_file, 'ტესტის ფაილი')) {
    echo "წარმატება! ფაილი შეიქმნა: $test_file";
    
    // სცადეთ წაშლა
    if (unlink($test_file)) {
        echo "<br>წარმატება! ფაილი წარმატებით წაიშალა.";
    } else {
        echo "<br>გაფრთხილება: ფაილი ვერ წაიშალა.";
    }
} else {
    $error = error_get_last();
    echo "შეცდომა! ფაილის შექმნა ვერ მოხერხდა: " . $error['message'];
    
    // დამატებითი ინფორმაცია
    echo "<pre>";
    echo "PHP მომხმარებელი: " . exec('whoami') . "\n";
    echo "დირექტორიის უფლებები: ";
    system("ls -ld " . escapeshellarg($upload_dir));
    echo "</pre>";
}
?>