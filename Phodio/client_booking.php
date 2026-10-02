<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'db/db.php';

if (!isset($_SESSION['client'])) {
    header("Location: client_login.php");
    exit;
}

$username = $_SESSION['client'];

// Fetch client info
$stmt = $conn->prepare("SELECT id, firstname, lastname FROM users WHERE username=?");
$stmt->bind_param("s", $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$clientId = $user['id'];
$fullname = $user['firstname'].' '.$user['lastname'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Client Booking | SOULPRINT</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
<style>
body{background:#0f0f0f;color:white;font-family:Arial,sans-serif;}
.main-content{padding:20px;}
.card{background:#1a1a1a;border:none;color:white;}
.card-header{background:#111;border-bottom:1px solid #333;}
.form-control, .form-select{background:#222;color:#fff;border:1px solid #444;}
.form-control:focus, .form-select:focus{border-color:#3b82f6;box-shadow:none;}
</style>
</head>
<body>

<?php include 'includes/client_header.php'; ?>

<div class="main-content">
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="ri-calendar-event-line me-2"></i>Booking Calendar</span>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#bookingModal" onclick="resetModal()">
                            <i class="ri-add-circle-line me-1"></i> New Booking
                        </button>
                    </div>
                    <div id="calendar" style="min-height: 600px;"></div>
                </div>
            </div>

            <div class="col-lg-4" style="padding-top:80px;">
                <div class="card shadow mb-4">
                    <div class="card-header">Booking Details</div>
                    <div class="card-body" id="details-pane">
                        <div class="text-center py-5 text-muted">
                            <i class="ri-mouse-line ri-2x mb-3 d-block"></i>
                            <p>Select your booking to see details</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Booking Modal -->
<div class="modal fade" id="bookingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form id="bookingForm" class="modal-content" action="process_client_booking.php" method="POST">
            <div class="modal-header border-bottom border-secondary">
                <h5 id="modalTitle">New Booking</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="booking_id" id="bookingIdInput">

                <div class="mb-3">
                    <label class="form-label">Package</label>
                    <select name="package" id="packageSelect" class="form-select" required>
                        <optgroup label="📸 Self-Photography Package">
                            <option value="Solo|350|20min">Solo – ₱350 – 20 min</option>
                            <option value="Duo|550|30min">Duo – ₱550 – 30 min</option>
                            <option value="3-4 pax|750|40min">3-4 pax – ₱750 – 40 min</option>
                        </optgroup>
                        <optgroup label="🧑‍🎓 Student Promo">
                            <option value="Solo|300|20min">Solo – ₱300 – 20 min</option>
                            <option value="Duo|500|30min">Duo – ₱500 – 30 min</option>
                            <option value="3-4 pax|700|40min">3-4 pax – ₱700 – 40 min</option>
                        </optgroup>
                        <optgroup label="🎨 Creative Session Package">
                            <option value="Solo|550|30min">Solo – ₱550 – 30 min</option>
                            <option value="Duo|700|45min">Duo – ₱700 – 45 min</option>
                            <option value="3-4 pax|1500|1hr">3-4 pax – ₱1,500 – 1 hr</option>
                        </optgroup>
                        <optgroup label="🧑‍🎓 Student Promo Creative">
                            <option value="Solo|500|20min">Solo – ₱500 – 20 min</option>
                            <option value="Duo|650|30min">Duo – ₱650 – 30 min</option>
                            <option value="3-4 pax|1450|1hr">3-4 pax – ₱1,450 – 1 hr</option>
                        </optgroup>
                        <optgroup label="✨ Add-On Package">
                            <option value="Creative Session with Backdrop|1500|1hr30min">
                                Creative Session with Backdrop – ₱1,500 – 1 hr 30 min
                            </option>
                        </optgroup>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Motif / Theme</label>
                    <input type="text" name="motif" class="form-control" placeholder="e.g. Vintage Black & Wood" required>
                </div>

                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label">Date</label>
                        <input type="date" name="date" class="form-control" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Time</label>
                        <input type="time" name="time" class="form-control" required>
                    </div>
                </div>

                <div class="mb-2 text-muted small">
                    📌 Max 2 bookings per day (1 AM, 1 PM). Choose your preferred slot carefully.
                </div>
            </div>
            <div class="modal-footer border-top border-secondary">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4">Save Booking</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let selectedBookingId = null;
const bookingModal = new bootstrap.Modal(document.getElementById('bookingModal'));

document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        events: 'load_client_events.php',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek'
        },
        selectable: true,
        height: 'auto',
        
        dateClick: function(info) {
            resetModal();
            document.getElementsByName('date')[0].value = info.dateStr;
            bookingModal.show();
        },

        eventClick: function(info) {
            selectedBookingId = info.event.id;
            fetchBookingDetails(selectedBookingId);
        }
    });
    calendar.render();
});

function fetchBookingDetails(id){
    fetch('get_client_booking_details.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'id=' + id
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById('details-pane').innerHTML = `
            <table class="table table-borderless text-white mb-0">
                <tr><td><strong>Package:</strong></td><td>${data.package}</td></tr>
                <tr><td><strong>Price:</strong></td><td>₱${data.price}</td></tr>
                <tr><td><strong>Motif:</strong></td><td>${data.motif}</td></tr>
                <tr><td><strong>Schedule:</strong></td><td>${data.date} @ ${data.time}</td></tr>
            </table>
        `;
        fillModalForEdit(data);
    });
}

function fillModalForEdit(data){
    document.getElementById('bookingIdInput').value = data.id;
    document.getElementsByName('package')[0].value = data.packageValue;
    document.getElementsByName('motif')[0].value = data.motif;
    document.getElementsByName('date')[0].value = data.date;
    document.getElementsByName('time')[0].value = data.time;
}

function resetModal(){
    document.getElementById('bookingForm').reset();
    document.getElementById('bookingIdInput').value = "";
    document.getElementById('modalTitle').innerText = "New Booking";
}
</script>
</body>
</html>