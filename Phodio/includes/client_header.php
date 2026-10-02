<?php
// Start session only if not started already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db/db.php';

$profile = 'default.png';
$fullname = '';
$tokenInfo = '5 shoots completed'; // Placeholder for points/tokens
if(isset($_SESSION['client'])){
    $username = $_SESSION['client'];
    $stmt = $conn->prepare("SELECT profile_image, firstname, lastname FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $profile = $result['profile_image'] ?? 'default.png';
    $fullname = $result['firstname'] ?? '';
}
?>
<nav class="navbar navbar-expand-lg px-4" style="background:#111; border-bottom:1px solid #333;">
    <div class="container-fluid d-flex justify-content-between align-items-center">
        <span class="brand fw-bold text-white">
            SOUL<span style="color:#ef4444">PRINT</span>
        </span>

        <?php if(isset($_SESSION['client'])): ?>
        <div class="d-flex align-items-center gap-3 position-relative">

            <!-- Chat Icon -->
            <a href="#" class="text-white" title="Chat">
                <i class="ri-chat-3-line" style="font-size:20px;"></i>
            </a>

            <!-- My Booking Icon -->
            <a href="client_booking.php" class="text-white" title="My Bookings">
                <i class="ri-calendar-2-line" style="font-size:20px;"></i>
            </a>

            <!-- Profile Card Dropdown -->
            <div class="position-relative">
                <img src="uploads/profile/<?= $profile ?>" 
                     id="profileToggle"
                     class="rounded-circle" style="width:40px;height:40px;object-fit:cover;cursor:pointer;">

                <div id="profileCard" class="position-absolute end-0 mt-2 p-3 bg-dark rounded shadow" 
                     style="width:220px; display:none; z-index:1000; border:1px solid #333;">
                     
                    <!-- Space for Tokens/Points -->
                    <div class="text-center mb-2" style="color:#3b82f6; font-size:14px;">
                        <?= htmlspecialchars($tokenInfo) ?>
                    </div>

                    <!-- User Name -->
                    <div class="fw-bold mb-3 text-center" style="color:white; font-size:16px;">
                        <?= htmlspecialchars($fullname) ?>
                    </div>

                    <!-- Buttons side by side -->
                    <div class="d-flex justify-content-between">
                        <a href="edit_profile.php" class="btn btn-outline-light btn-sm flex-fill me-1">Edit Profile</a>
                        <a href="logout.php" class="btn btn-danger btn-sm flex-fill ms-1">Logout</a>
                    </div>
                </div>
            </div>

        </div>
        <?php endif; ?>
    </div>
</nav>

<script>
// Toggle profile card
const profileToggle = document.getElementById('profileToggle');
const profileCard = document.getElementById('profileCard');

profileToggle.addEventListener('click', () => {
    profileCard.style.display = profileCard.style.display === 'block' ? 'none' : 'block';
});

// Hide card if clicked outside
document.addEventListener('click', function(event){
    if(!profileCard.contains(event.target) && event.target !== profileToggle){
        profileCard.style.display = 'none';
    }
});
</script>