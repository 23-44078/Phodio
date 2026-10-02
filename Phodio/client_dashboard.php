<?php
include 'db/db.php';
session_start();
if(!isset($_SESSION['client'])){
    header("Location: client_login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Client Dashboard | SOULPRINT</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">

<style>
body{background:#0f0f0f;color:white;font-family:Arial,sans-serif;}
.dashboard-container{padding:30px;}
.card{background:#1a1a1a;border:1px solid #333;border-radius:12px;padding:20px;transition:0.3s;}
.card:hover{transform:translateY(-5px);border-color:#3b82f6;}
.btn-custom{background:#3b82f6;border:none;color:white;}
.btn-custom:hover{background:#2563eb;}
</style>
</head>
<body>

<?php include 'includes/client_header.php'; ?>

<div class="dashboard-container container">
    <h3 class="mb-4">Client Dashboard</h3>

    <div class="row g-4">

        <!-- BOOK SESSION -->
        <div class="col-md-4">
            <div class="card text-center">
                <h5>📸 Book a Session</h5>
                <p class="text-muted">Schedule your photography session.</p>
                <a href="#" class="btn btn-custom w-100">Book Now</a>
            </div>
        </div>

        <!-- VIEW BOOKINGS -->
        <div class="col-md-4">
            <div class="card text-center">
                <h5>📋 My Bookings</h5>
                <p class="text-muted">View your scheduled sessions.</p>
                <a href="#" class="btn btn-custom w-100">View</a>
            </div>
        </div>

        <!-- GALLERY -->
        <div class="col-md-4">
            <div class="card text-center">
                <h5>🖼 My Photos</h5>
                <p class="text-muted">Access your photo albums.</p>
                <a href="#" class="btn btn-custom w-100">Open Gallery</a>
            </div>
        </div>

    </div>
</div>

</body>
</html>