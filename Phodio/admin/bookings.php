<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../includes/booking_helpers.php';
checkLogin();

$packages = phodio_package_catalog();
$serviceTypes = phodio_service_types();
$statuses = phodio_booking_statuses();
$groupedPackages = [];
foreach ($packages as $package) {
    $groupedPackages[$package['category']][] = $package;
}
$clients = $conn->query('SELECT id, firstname, lastname, username FROM users ORDER BY firstname, lastname');
$today = date('Y-m-d');

$spTitle = 'Bookings';
$spSubtitle = 'Manage appointment requests and post service-status updates that clients can see.';
$spFullCalendar = true;
$spPageActions = '<button type="button" class="btn btn-primary px-4" id="newBookingButton">'
    . '<i class="ri-add-line me-1"></i>Schedule appointment</button>';

require __DIR__ . '/includes/header.php';
?>
<?php if (isset($_GET['status']) && $_GET['status'] === 'saved'): ?>
    <?= sp_flash('success', 'Booking details saved.', 4200) ?>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
    <?= sp_flash('danger', (string) $_GET['error']) ?>
<?php endif; ?>

<div class="sp-alert sp-alert--info mb-4 sp-enter sp-enter-2" data-sp-reveal>
    <i class="ri-flow-chart"></i>
    <div>
        <strong>Client workflow</strong>
        <span class="d-block d-sm-inline muted ms-sm-1">New requests are held as Pending; update the status as the session is confirmed, photographed, edited, and completed.</span>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-8" data-sp-reveal="left">
        <section class="surface">
            <?= sp_surface_header(
                'ri-calendar-event-line',
                'Studio schedule',
                '<span class="small muted">Select a booking to manage it</span>'
            ) ?>
            <div id="calendar"></div>
        </section>
    </div>
    <div class="col-xl-4" data-sp-reveal="right">
        <section class="surface">
            <?= sp_surface_header('ri-radar-line', 'Booking inspector') ?>
            <div class="surface-body" id="bookingInspector" aria-live="polite">
                <?= sp_empty_state(
                    'ri-cursor-line',
                    'No booking selected',
                    'Select a booking on the calendar to review its details and update progress.'
                ) ?>
            </div>
        </section>
    </div>
</div>

<div class="modal fade" id="bookingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content" id="bookingForm" action="process_booking.php" method="post">
            <div class="modal-header">
                <div>
                    <div class="sp-eyebrow sp-eyebrow--cool mb-1">Central schedule</div>
                    <h2 class="modal-title fs-5" id="modalTitle">Schedule an appointment</h2>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="booking_id" id="bookingIdInput">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="clientSelect">Client account <span class="sp-faint fw-normal text-lowercase">(optional for walk-ins)</span></label>
                        <select class="form-select" name="client_id" id="clientSelect">
                            <option value="">Walk-in / not linked</option>
                            <?php while ($client = $clients->fetch_assoc()): ?>
                                <option value="<?= (int) $client['id'] ?>">
                                    <?= sp_h(trim($client['firstname'] . ' ' . $client['lastname']) . ' · ' . $client['username']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="sessionTitle">Session title / occasion</label>
                        <input class="form-control" type="text" name="title" id="sessionTitle" maxlength="255"
                               required placeholder="e.g. Graduation portraits">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="serviceType">Session type</label>
                        <select class="form-select" name="service_type" id="serviceType" required>
                            <?php foreach ($serviceTypes as $key => $label): ?>
                                <option value="<?= sp_h($key) ?>"><?= sp_h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="attendeeCount">Number of people</label>
                        <select class="form-select" name="attendee_count" id="attendeeCount" required>
                            <option value="1">1 person</option>
                            <option value="2">2 people</option>
                            <option value="3">3 people</option>
                            <option value="4">4 people</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="packageSelect">Photography package</label>
                        <select class="form-select" name="package_key" id="packageSelect" required>
                            <?php foreach ($groupedPackages as $category => $items): ?>
                                <optgroup label="<?= sp_h($category) ?>">
                                    <?php foreach ($items as $package): ?>
                                        <option value="<?= sp_h($package['key']) ?>"
                                                data-price="<?= (int) $package['price'] ?>"
                                                data-min="<?= (int) $package['min_people'] ?>"
                                                data-max="<?= (int) $package['max_people'] ?>">
                                            <?= sp_h($package['name']) ?> — <?= sp_h(sp_money($package['price'])) ?> · <?= sp_h($package['duration']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="packagePrice">Package price</label>
                        <input class="form-control" id="packagePrice" type="text" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="motif">Theme / motif</label>
                        <input class="form-control" type="text" name="motif" id="motif" maxlength="100"
                               placeholder="e.g. Vintage, minimalist, floral">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="bookingDate">Date</label>
                        <input class="form-control" type="date" name="date" id="bookingDate" min="<?= sp_h($today) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="bookingPeriod">Period</label>
                        <select class="form-select" name="period" id="bookingPeriod" required>
                            <option value="AM">Morning · 9:00 AM</option>
                            <option value="PM">Afternoon · 1:00 PM</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="clientNotes">Client requirements / notes</label>
                        <textarea class="form-control" name="client_notes" id="clientNotes" rows="3" maxlength="1000"
                                  placeholder="Any requirements for the studio team."></textarea>
                    </div>
                </div>
                <p class="form-hint mt-3 mb-0">
                    <i class="ri-information-line me-1"></i>Only one morning and one afternoon appointment can be scheduled per date. Saving a new appointment sets it to Confirmed.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4">Save booking</button>
            </div>
        </form>
    </div>
</div>

<script>
const bookingForm = document.getElementById('bookingForm');
const bookingModal = new bootstrap.Modal(document.getElementById('bookingModal'));
const inspector = document.getElementById('bookingInspector');
const packageSelect = document.getElementById('packageSelect');
const attendeeSelect = document.getElementById('attendeeCount');
const dateInput = document.getElementById('bookingDate');
const moneyPrefix = '<?= sp_h(SP_MONEY_PREFIX) ?>';
let selectedBookingId = null;
let adminCalendar = null;

function escapeHtml(value){return String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));}
function notify(message, type = 'info'){ SoulprintUI.toast(message, type); }
function formatMoney(value){ return moneyPrefix + Number(value || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
function resetForm(date = '') {
    bookingForm.reset();
    document.getElementById('bookingIdInput').value = '';
    document.getElementById('modalTitle').textContent = 'Schedule an appointment';
    dateInput.value = date;
    dateInput.min = '<?= sp_h($today) ?>';
    document.getElementById('packagePrice').value = '';
    refreshPackagePrice();
}
function refreshPackagePrice(){
    const option = packageSelect.selectedOptions[0];
    if (!option) return;
    document.getElementById('packagePrice').value = formatMoney(option.dataset.price);
    const count = Number(attendeeSelect.value), min = Number(option.dataset.min), max = Number(option.dataset.max);
    if (count < min || count > max) attendeeSelect.value = String(min);
}
function progressMarkup(updates){
    if (!updates || !updates.length) return '<p class="small muted mb-0">No progress updates have been posted yet.</p>';
    return updates.map((update, index) => `
        <div class="sp-timeline__item ${index === updates.length - 1 ? 'sp-pop' : 'sp-timeline__item--muted'}">
            <div class="d-flex justify-content-between gap-2">
                <span class="sp-timeline__title">${escapeHtml(update.status)}</span>
                <small class="sp-timeline__meta">${escapeHtml(update.created_at)}</small>
            </div>
            <div class="sp-timeline__note">${escapeHtml(update.note || 'Progress updated by studio.')}</div>
        </div>`).join('');
}
function renderBooking(booking){
    const classes = {'Pending':'status-pending','Confirmed':'status-confirmed','In Progress':'status-progress','Editing':'status-editing','Ready for Pickup':'status-ready','Completed':'status-completed','Cancelled':'status-cancelled'};
    const statusClass = classes[booking.status] || 'status-pending';
    const time = booking.period === 'AM' ? '9:00 AM · Morning' : '1:00 PM · Afternoon';
    inspector.innerHTML = `
        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
            <div>
                <h3 class="h6 fw-bold mb-1">${escapeHtml(booking.title || booking.package_type)}</h3>
                <div class="small muted">${escapeHtml(booking.client_name || 'Walk-in / not linked')}</div>
            </div>
            <span class="status-chip ${statusClass}">${escapeHtml(booking.status)}</span>
        </div>
        <table class="table table-borderless table-sm sp-detail-table mb-3">
            <tr><td>Session type</td><td>${escapeHtml(booking.service_type || '—')}</td></tr>
            <tr><td>Package</td><td>${escapeHtml(booking.package_type || '—')}</td></tr>
            <tr><td>Price</td><td class="sp-price">${formatMoney(booking.price)}</td></tr>
            <tr><td>Theme</td><td>${escapeHtml(booking.motif || '—')}</td></tr>
            <tr><td>Group size</td><td>${Number(booking.attendee_count || 1)} ${Number(booking.attendee_count || 1) === 1 ? 'person' : 'people'}</td></tr>
            <tr><td>Schedule</td><td>${escapeHtml(booking.booking_date)} · ${time}</td></tr>
            ${booking.phone ? `<tr><td>Phone</td><td>${escapeHtml(booking.phone)}</td></tr>` : ''}
            ${booking.username ? `<tr><td>Email</td><td>${escapeHtml(booking.username)}</td></tr>` : ''}
            ${booking.client_notes ? `<tr><td>Client notes</td><td>${escapeHtml(booking.client_notes)}</td></tr>` : ''}
        </table>
        <button type="button" class="btn btn-sm btn-outline-light w-100 mb-4" id="editSelectedBooking"><i class="ri-edit-line me-1"></i>Edit appointment details</button>
        <div class="fw-bold small mb-3"><i class="ri-git-commit-line text-danger me-1"></i>Post a client-visible progress update</div>
        <form id="statusForm" action="update_booking_status.php" method="post" data-sp-ajax="1">
            <input type="hidden" name="booking_id" value="${Number(booking.id)}">
            <label class="form-label small" for="progressStatus">Service status</label>
            <select class="form-select form-select-sm mb-2" name="status" id="progressStatus" required>
                <?php foreach ($statuses as $status): ?><option value="<?= sp_h($status) ?>"><?= sp_h($status) ?></option><?php endforeach; ?>
            </select>
            <label class="form-label small" for="progressNote">Progress note</label>
            <textarea class="form-control form-control-sm progress-note mb-2" id="progressNote" name="status_note" maxlength="1500" placeholder="Share a status update or next step with the client."></textarea>
            <button class="btn btn-primary btn-sm w-100" type="submit"><i class="ri-send-plane-line me-1"></i>Save status update</button>
        </form>
        <div class="fw-bold small mt-4 mb-3"><i class="ri-history-line text-danger me-1"></i>Update history</div>
        <div class="sp-timeline">${progressMarkup(booking.updates)}</div>`;
    document.getElementById('progressStatus').value = booking.status;
    document.getElementById('editSelectedBooking').addEventListener('click', () => openEditModal(booking));
    document.getElementById('statusForm').addEventListener('submit', saveProgressUpdate);
}
async function fetchDetails(id, silent = false){
    try {
        const response = await fetch('get_booking_details.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({id:String(id)})});
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.message || 'Could not load this booking.');
        selectedBookingId = Number(data.booking.id);
        renderBooking(data.booking);
    } catch (error) { if (!silent) notify(error.message, 'danger'); }
}
function openEditModal(booking){
    resetForm(booking.booking_date);
    document.getElementById('modalTitle').textContent = 'Edit appointment details';
    document.getElementById('bookingIdInput').value = booking.id;
    document.getElementById('clientSelect').value = booking.client_id || '';
    document.getElementById('sessionTitle').value = booking.title || '';
    document.getElementById('serviceType').value = booking.service_type || 'portrait';
    attendeeSelect.value = String(booking.attendee_count || 1);
    if (booking.package_key && packageSelect.querySelector(`option[value="${CSS.escape(booking.package_key)}"]`)) packageSelect.value = booking.package_key;
    document.getElementById('motif').value = booking.motif || '';
    document.getElementById('clientNotes').value = booking.client_notes || '';
    document.getElementById('bookingPeriod').value = booking.period || 'AM';
    refreshPackagePrice();
    bookingModal.show();
}
async function saveProgressUpdate(event){
    event.preventDefault();
    const form = event.currentTarget;
    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    button.innerHTML = '<span class="sp-spinner me-2"></span>Saving update';
    try {
        const response = await fetch(form.action, {method:'POST', body:new FormData(form)});
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.message || 'Could not update this booking.');
        notify(data.message, 'success');
        adminCalendar.refetchEvents();
        await fetchDetails(selectedBookingId, true);
    } catch (error) {
        notify(error.message, 'danger');
    } finally {
        button.disabled = false;
        button.innerHTML = '<i class="ri-send-plane-line me-1"></i>Save status update';
    }
}

adminCalendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
    initialView:'dayGridMonth',
    height:'auto',
    events:'load_events.php',
    headerToolbar:{left:'prev,next today', center:'title', right:'dayGridMonth,timeGridWeek'},
    eventTimeFormat:{hour:'numeric', minute:'2-digit', meridiem:'short'},
    eventClick(info){ fetchDetails(info.event.id); },
    dateClick(info){ resetForm(info.dateStr.slice(0, 10)); bookingModal.show(); }
});
adminCalendar.render();
document.getElementById('newBookingButton').addEventListener('click', () => { resetForm(''); bookingModal.show(); });
packageSelect.addEventListener('change', refreshPackagePrice);
attendeeSelect.addEventListener('change', refreshPackagePrice);
refreshPackagePrice();
bookingForm.addEventListener('submit', event => {
    const option = packageSelect.selectedOptions[0];
    const count = Number(attendeeSelect.value);
    if (option && (count < Number(option.dataset.min) || count > Number(option.dataset.max))) {
        event.preventDefault();
        notify('Choose a package that supports the selected group size.', 'warning');
    }
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
