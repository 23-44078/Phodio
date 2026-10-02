<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Studio Management | Dark Edition</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">
    
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-content">
<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="ri-calendar-event-line me-2"></i>Booking Schedule</span>
                    <button class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#addModal" onclick="resetModal()">
                        <i class="ri-add-circle-line me-1"></i> New Booking
                    </button>
                </div>
                <div id="calendar" style="min-height: 600px;"></div>
            </div>
        </div>

        <div class="col-lg-4"style="padding-top: 80px !important;">
            <div class="card shadow mb-4">
                <div class="card-header d-flex justify-content-between align-items-center" style="border-bottom: 1px solid #007bff !important;">
                    <span><i class="ri-information-line me-2"></i>Active Inspector</span>
                    <div id="actionButtons" style="display:none;">
                        <button class="btn btn-sm btn-outline-light border-0" onclick="openEditModal()"><i class="ri-edit-line"></i></button>
                        <button class="btn btn-sm btn-outline-danger border-0" onclick="deleteBooking()"><i class="ri-delete-bin-line"></i></button>
                    </div>
                </div>
                <div class="card-body" id="details-pane">
                    <div class="text-center py-5">
                        <i class="ri-mouse-line ri-2x text-muted mb-3 d-block"></i>
                        <p class="text-muted small">Select an event to view details</p>
                    </div>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-header">Financial Breakdown</div>
                <div class="card-body p-0">
                    <table class="table table-dark table-hover mb-0" style="font-size: 0.9rem;">
                        <tbody>
                            <tr><td class="ps-3 text-muted">Awaiting selection...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form id="bookingForm" class="modal-content" action="process_booking.php" method="POST">
            <div class="modal-header border-bottom border-secondary">
                <h5 id="modalTitle">Create New Booking</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="booking_id" id="bookingIdInput">
                
                <div class="mb-3">
                    <label class="form-label">Client / Title</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. John Doe Portrait" required>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label">Package</label>
                        <select name="package" class="form-select">
                            <option value="Fashion Shoot">Fashion Shoot</option>
                            <option value="Product Shoot">Product Shoot</option>
                            <option value="Portrait Session">Portrait Session</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Price (₱)</label>
                        <input type="number" name="price" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Motif / Theme</label>
                    <input type="text" name="motif" class="form-control" placeholder="e.g. Vintage Black and Wood">
                </div>
                <div class="row">
                    <div class="col-6">
                        <label class="form-label">Date</label>
                        <input type="date" name="date" class="form-control" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Time</label>
                        <input type="time" name="time" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top border-secondary">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4">Save Booking</button>
            </div>
        </form>
    </div>
</div>
 </div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
let selectedBookingId = null;
// Initialize modal variable globally
const bookingModal = new bootstrap.Modal(document.getElementById('addModal'));

document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        events: 'load_events.php',
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
            document.getElementById('modalTitle').innerText = "Create New Booking";
            bookingModal.show();
        },

        eventClick: function(info) {
            selectedBookingId = info.event.id;
            fetchDetails(selectedBookingId);
        }
    });
    calendar.render();
});

function fetchDetails(id) {
    fetch('get_booking_details.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('actionButtons').style.display = 'block';
        document.getElementById('details-pane').innerHTML = `
            <table class="table table-borderless sm mb-0">
                <tr><td><strong>Title:</strong></td><td>${data.title}</td></tr>
                <tr><td><strong>Package:</strong></td><td><span class="badge bg-danger">${data.package_type}</span></td></tr>
                <tr><td><strong>Price:</strong></td><td class="text-success fw-bold">₱${new Intl.NumberFormat().format(data.price)}</td></tr>
                <tr><td><strong>Motif:</strong></td><td><em>${data.motif}</em></td></tr>
                <tr><td><strong>Schedule:</strong></td><td>${data.booking_date} @ ${data.start_time}</td></tr>
            </table>
        `;
        fillModalForEdit(data);
    });
}

function fillModalForEdit(data) {
    document.getElementById('bookingIdInput').value = data.id;
    document.getElementsByName('title')[0].value = data.title;
    document.getElementsByName('package')[0].value = data.package_type;
    document.getElementsByName('price')[0].value = data.price;
    document.getElementsByName('motif')[0].value = data.motif;
    document.getElementsByName('date')[0].value = data.booking_date;
    document.getElementsByName('time')[0].value = data.start_time;
}

function resetModal() {
    document.getElementById('bookingForm').reset();
    document.getElementById('bookingIdInput').value = "";
    document.getElementById('modalTitle').innerText = "Create New Booking";
}

function openEditModal() {
    document.getElementById('modalTitle').innerText = "Edit Booking";
    bookingModal.show();
}

function deleteBooking() {
    if(confirm("Delete this booking permanently?")) {
        window.location.href = `delete_booking.php?id=${selectedBookingId}`;
    }
}
</script>
</body>
</html>