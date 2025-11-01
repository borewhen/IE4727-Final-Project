<?php
$target_dir = __DIR__ . "/assets/uploads/";
$target_file = $target_dir . basename($_FILES["image"]["name"]);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check that it’s really an image
    $check = getimagesize($_FILES["image"]["tmp_name"]);
    if ($check === false) {
        die("File is not an image.");
    }

    // Move file from temp directory to uploads/
    if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
        echo "File uploaded successfully: " . htmlspecialchars(basename($_FILES["image"]["name"]));
    } else {
        echo "Error uploading file.";
    }
}
?>
