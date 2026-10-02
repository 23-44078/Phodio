<?php
include 'db.php';

if($_POST) {
    $id = $_POST['booking_id'];
    $title = $_POST['title'];
    $package = $_POST['package'];
    $price = $_POST['price'];
    $motif = $_POST['motif'];
    $date = $_POST['date'];
    $time = $_POST['time'];

    $colors = ['Fashion Shoot' => '#3498db', 'Product Shoot' => '#27ae60', 'Portrait Session' => '#e67e22'];
    $color = $colors[$package] ?? '#9b59b6';

    if(!empty($id)) {
        // UPDATE EXISTING
        $sql = "UPDATE bookings SET 
                title='$title', package_type='$package', motif='$motif', 
                price='$price', booking_date='$date', start_time='$time', color_code='$color' 
                WHERE id=$id";
    } else {
        // INSERT NEW
        $sql = "INSERT INTO bookings (title, package_type, motif, price, booking_date, start_time, color_code) 
                VALUES ('$title', '$package', '$motif', '$price', '$date', '$time', '$color')";
    }
    
    if($conn->query($sql)) { header("Location: index.php"); }
}
?>