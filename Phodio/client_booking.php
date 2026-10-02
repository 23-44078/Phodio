<?php
session_start();
require_once __DIR__ . '/db/db.php';
require_once __DIR__ . '/includes/booking_helpers.php';
require_once __DIR__ . '/includes/ui.php';

if (!isset($_SESSION['client_id'])) {
    header('Location: client_login.php');
    exit;
}

$packages = phodio_package_catalog();
$serviceTypes = phodio_service_types();
$today = date('Y-m-d');
$groupedPackages = [];
foreach ($packages as $package) {
    $groupedPackages[$package['category']][] = $package;
}

$spTitle = 'Book a Session';
$spDescription = 'Find a matching SOULPRINT package, request an appointment and follow your service progress.';
$spFullCalendar = true;
include __DIR__ . '/includes/page_top.php';
include __DIR__ . '/includes/client_header.php';
?>
<main class="sp-shell sp-shell--wide">
    <section class="sp-hero d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center mb-4 sp-enter">
        <div>
            <div class="sp-eyebrow mb-2"><i class="ri-aperture-line"></i><?= sp_h(SP_BRAND) ?> · <?= sp_h(SP_PORTAL_LABEL) ?></div>
            <h1 class="sp-title mb-2">Plan your next session</h1>
            <p class="sp-subtitle">Explore package recommendations, request an appointment, and follow your service progress in one place.</p>
        </div>
        <a class="btn btn-primary px-4 py-2" href="#booking-calendar"><i class="ri-calendar-check-line me-2"></i>View availability</a>
    </section>

    <section class="row g-4 mb-4" id="package-recommender">
        <div class="col-lg-5" data-sp-reveal="left">
            <div class="surface h-100">
                <?= sp_surface_header('ri-sparkling-2-line', 'Smart package finder') ?>
                <div class="surface-body">
                    <p class="muted small mb-3">Tell us what you need. The recommendation engine matches your session type, group size, budget, style, and backdrop preference to available studio packages.</p>
                    <form id="recommendationForm" data-sp-ajax="1">
                        <div class="sp-field">
                            <label class="form-label" for="recEventType">What are you planning?</label>
                            <select class="form-select" id="recEventType" name="event_type" required>
                                <?php foreach ($serviceTypes as $key => $label): ?>
                                    <option value="<?= sp_h($key) ?>"><?= sp_h($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="recPeople">Group size</label>
                                <select class="form-select" id="recPeople" name="people">
                                    <option value="1">1 person</option>
                                    <option value="2">2 people</option>
                                    <option value="3">3 people</option>
                                    <option value="4">4 people</option>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="recBudget">Budget (<?= sp_h(SP_MONEY_PREFIX) ?>)</label>
                                <input class="form-control" id="recBudget" type="number" name="budget"
                                       min="300" max="100000" step="50" value="1000" required>
                            </div>
                        </div>
                        <div class="sp-field mt-3">
                            <label class="form-label" for="recStyle">Preferred style</label>
                            <select class="form-select" id="recStyle" name="style">
                                <?php foreach (phodio_style_preferences() as $key => $label): ?>
                                    <option value="<?= sp_h($key) ?>"><?= sp_h($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sp-field">
                            <label class="form-label" for="recRequirements">Requirements or preferences <span class="sp-faint fw-normal text-lowercase">(optional)</span></label>
                            <textarea class="form-control" id="recRequirements" name="requirements" rows="2" maxlength="600"
                                      placeholder="e.g. a themed graduation portrait with a backdrop"></textarea>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="1" id="recBackdrop" name="backdrop">
                            <label class="form-check-label small" for="recBackdrop">I would like a backdrop option</label>
                        </div>
                        <button class="btn btn-primary w-100" type="submit" id="recommendButton">
                            <i class="ri-magic-line me-2"></i>Recommend packages
                        </button>
                    </form>
                    <p class="form-hint mt-3 mb-0"><i class="ri-shield-check-line me-1"></i>Recommendations use your selections only; no external AI service is contacted.</p>
                </div>
            </div>
        </div>
        <div class="col-lg-7" data-sp-reveal="right">
            <div class="surface h-100">
                <?= sp_surface_header(
                    'ri-lightbulb-flash-line',
                    'Recommended for you',
                    '<span class="badge soft-badge">Top matches</span>'
                ) ?>
                <div class="surface-body" id="recommendationResults" aria-live="polite">
                    <?= sp_empty_state(
                        'ri-camera-lens-line',
                        'Your package suggestions will appear here',
                        'Adjust the preferences and select “Recommend packages.”'
                    ) ?>
                </div>
            </div>
        </div>
    </section>

    <section class="row g-4" id="booking-calendar">
        <div class="col-xl-8" data-sp-reveal="left">
            <div class="surface">
                <div class="surface-header flex-column flex-md-row align-items-md-center">
                    <span><i class="ri-calendar-event-line me-2 text-danger"></i>Appointment calendar</span>
                    <button class="btn btn-primary btn-sm" type="button" id="newBookingButton">
                        <i class="ri-add-line me-1"></i>New booking request
                    </button>
                </div>
                <div id="calendar"></div>
            </div>
            <p class="form-hint mt-2">
                <span class="badge soft-badge me-1">Reserved</span>
                Grey entries show occupied periods without exposing another client's details. One morning and one afternoon appointment are available per date.
            </p>
        </div>
        <div class="col-xl-4" data-sp-reveal="right">
            <div class="surface">
                <?= sp_surface_header(
                    'ri-radar-line',
                    'Service progress',
                    '<span class="sp-live"><span class="sp-live__dot"></span>Live</span>'
                ) ?>
                <div class="surface-body" id="details-pane" aria-live="polite">
                    <?= sp_empty_state(
                        'ri-cursor-line',
                        'No session selected',
                        'Select one of your sessions on the calendar to see its status and update history.'
                    ) ?>
                </div>
            </div>
            <p class="form-hint mt-2"><i class="ri-time-line me-1"></i>Progress is refreshed automatically every 30 seconds.</p>
        </div>
    </section>
</main>

<div class="modal fade" id="bookingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form id="bookingForm" class="modal-content" action="process_client_booking.php" method="post" data-sp-ajax="1">
            <div class="modal-header">
                <div>
                    <div class="sp-eyebrow mb-1">Appointment request</div>
                    <h2 class="modal-title fs-5" id="modalTitle">New booking request</h2>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="booking_id" id="bookingIdInput">
                <input type="hidden" name="action" value="save">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="sessionTitle">Session title / occasion</label>
                        <input class="form-control" type="text" name="title" id="sessionTitle" maxlength="255"
                               placeholder="e.g. Graduation portraits" required>
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
                        <div class="form-hint mt-1" id="packagePriceText"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="motif">Theme / motif</label>
                        <input class="form-control" type="text" name="motif" id="motif" maxlength="100"
                               placeholder="e.g. Vintage, minimalist, floral" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="bookingDate">Preferred date</label>
                        <input class="form-control" type="date" name="date" id="bookingDate" min="<?= sp_h($today) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="bookingPeriod">Available period</label>
                        <select class="form-select" name="period" id="bookingPeriod" required>
                            <option value="AM">Morning · 9:00 AM</option>
                            <option value="PM">Afternoon · 1:00 PM</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="clientNotes">Requirements or special requests <span class="sp-faint fw-normal text-lowercase">(optional)</span></label>
                        <textarea class="form-control" name="client_notes" id="clientNotes" rows="3" maxlength="1000"
                                  placeholder="Share anything the studio should know about your session."></textarea>
                    </div>
                </div>
                <div class="sp-alert sp-alert--info mt-3 mb-0">
                    <i class="ri-information-line"></i>
                    <div>Requests begin as <strong>Pending</strong>. The selected period is held while the studio reviews and confirms your booking.</div>
                </div>
                <div class="sp-alert sp-alert--danger d-none mt-3 mb-0" id="bookingError" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Back</button>
                <button type="submit" class="btn btn-primary px-4" id="saveBookingButton">Send request</button>
            </div>
        </form>
    </div>
</div>

<script>
const bookingForm = document.getElementById('bookingForm');
const bookingModal = new bootstrap.Modal(document.getElementById('bookingModal'));
const bookingError = document.getElementById('bookingError');
const dateInput = document.getElementById('bookingDate');
const periodSelect = document.getElementById('bookingPeriod');
const packageSelect = document.getElementById('packageSelect');
const attendeeSelect = document.getElementById('attendeeCount');
let bookingCalendar = null;
let selectedBookingId = null;
let editingBookingId = null;
const queryBookingId = Number(new URLSearchParams(window.location.search).get('booking')) || null;
let openedLinkedBooking = false;
const moneyPrefix = '<?= sp_h(SP_MONEY_PREFIX) ?>';
function formatMoney(value) {
    return moneyPrefix + Number(value || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
}
function showToast(message, type = 'info') {
    SoulprintUI.toast(message, type);
}
async function parseApiResponse(response) {
    const body = await response.text();
    try {
        return JSON.parse(body);
    } catch (error) {
        if (/<(?:!doctype|html|br|b|div)\b/i.test(body.slice(0, 500))) {
            throw new Error(`The server returned an HTML error (HTTP ${response.status}) instead of JSON. Check the PHP/XAMPP error log and confirm the updated endpoint files are installed.`);
        }
        throw new Error(`The server returned an invalid API response (HTTP ${response.status}).`);
    }
}
function eventHasReservedPeriod(date, period) {
    if (!bookingCalendar) return false;
    return bookingCalendar.getEvents().some(event => event.startStr.slice(0, 10) === date && event.extendedProps.period === period && event.extendedProps.reserved && String(event.id) !== String(editingBookingId || ''));
}
function periodHasStarted(date, period) {
    if (date !== '<?= sp_h($today) ?>') return false;
    const now = new Date();
    const currentMinutes = now.getHours() * 60 + now.getMinutes();
    return currentMinutes >= (period === 'AM' ? 9 * 60 : 13 * 60);
}
function updatePeriodAvailability(preferred = null) {
    const date = dateInput.value;
    const previous = preferred || periodSelect.value;
    const options = [
        {value:'AM', label:'Morning · 9:00 AM'},
        {value:'PM', label:'Afternoon · 1:00 PM'}
    ];
    options.forEach(item => {
        const option = periodSelect.querySelector(`option[value="${item.value}"]`);
        const taken = date && eventHasReservedPeriod(date, item.value);
        const started = date && periodHasStarted(date, item.value);
        option.disabled = Boolean(taken || started);
        option.textContent = item.label + (taken ? ' · Reserved' : (started ? ' · Started' : ''));
    });
    const available = options.filter(item => !date || (!eventHasReservedPeriod(date, item.value) && !periodHasStarted(date, item.value)));
    if (available.length === 0) {
        periodSelect.value = '';
        periodSelect.setCustomValidity('Both appointment periods are already reserved for this date.');
    } else {
        periodSelect.setCustomValidity('');
        periodSelect.value = available.some(item => item.value === previous) ? previous : available[0].value;
    }
}
function refreshPackageDetails() {
    const option = packageSelect.selectedOptions[0];
    if (!option) return;
    const size = option.dataset.min === option.dataset.max
        ? option.dataset.max
        : `${option.dataset.min}–${option.dataset.max}`;
    document.getElementById('packagePriceText').textContent =
        `Package price: ${formatMoney(option.dataset.price)} · supports ${size} ${Number(option.dataset.max) === 1 ? 'person' : 'people'}.`;
    const count = Number(attendeeSelect.value);
    const min = Number(option.dataset.min);
    const max = Number(option.dataset.max);
    if (count < min || count > max) {
        attendeeSelect.value = String(min);
    }
}
function resetBookingForm(date = '') {
    bookingForm.reset();
    document.getElementById('bookingIdInput').value = '';
    editingBookingId = null;
    document.getElementById('modalTitle').textContent = 'New booking request';
    document.getElementById('saveBookingButton').textContent = 'Send request';
    bookingError.classList.add('d-none');
    dateInput.value = date;
    dateInput.min = '<?= sp_h($today) ?>';
    attendeeSelect.value = '1';
    refreshPackageDetails();
    updatePeriodAvailability('AM');
}
function buildProgressHtml(booking) {
    const statusClass = {
        'Pending':'status-pending','Confirmed':'status-confirmed','In Progress':'status-progress',
        'Editing':'status-editing','Ready for Pickup':'status-ready','Completed':'status-completed','Cancelled':'status-cancelled'
    }[booking.status] || 'status-pending';
    const time = booking.period === 'AM' ? '9:00 AM · Morning' : '1:00 PM · Afternoon';
    const updates = Array.isArray(booking.updates) ? booking.updates : [];
    const history = updates.length ? updates.map((item, index) => `
        <div class="sp-timeline__item ${index === updates.length - 1 ? 'sp-pop' : 'sp-timeline__item--muted'}">
            <div class="d-flex justify-content-between gap-2">
                <span class="sp-timeline__title">${escapeHtml(item.status)}</span>
                <small class="sp-timeline__meta">${escapeHtml(item.created_at)}</small>
            </div>
            <div class="sp-timeline__note">${escapeHtml(item.note || 'Status updated by the studio.')}</div>
        </div>`).join('') : '<p class="small muted mb-0">No progress updates have been posted yet.</p>';
    const appointmentPassed = booking.booking_date < '<?= sp_h($today) ?>' || periodHasStarted(booking.booking_date, booking.period);
    const canEdit = booking.status === 'Pending' && !appointmentPassed;
    const canCancel = ['Pending','Confirmed'].includes(booking.status) && !appointmentPassed;
    return `
        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
            <div>
                <h3 class="h6 fw-bold mb-1">${escapeHtml(booking.title)}</h3>
                <div class="small muted">${escapeHtml(booking.package_type || 'Photography service')}</div>
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
            ${booking.status_note ? `<tr><td>Latest note</td><td>${escapeHtml(booking.status_note)}</td></tr>` : ''}
        </table>
        <div class="d-flex gap-2 mb-4">
            ${canEdit ? '<button type="button" class="btn btn-sm btn-outline-light" id="editBookingButton"><i class="ri-edit-line me-1"></i>Edit request</button>' : ''}
            ${canCancel ? '<button type="button" class="btn btn-sm btn-outline-light text-danger" id="cancelBookingButton"><i class="ri-close-circle-line me-1"></i>Cancel request</button>' : ''}
        </div>
        <div class="fw-bold small mb-3"><i class="ri-git-commit-line me-1 text-danger"></i>Progress updates</div>
        <div class="sp-timeline">${history}</div>`;
}
async function loadBookingDetails(id, silent = false) {
    try {
        const response = await fetch('get_client_booking_details.php', {
            method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:new URLSearchParams({id:String(id)})
        });
        const data = await parseApiResponse(response);
        if (!response.ok || !data.ok) throw new Error(data.message || 'Could not load this booking.');
        selectedBookingId = Number(data.booking.id);
        if (!openedLinkedBooking && queryBookingId === selectedBookingId && bookingCalendar) {
            bookingCalendar.gotoDate(data.booking.booking_date);
            openedLinkedBooking = true;
        }
        const pane = document.getElementById('details-pane');
        pane.innerHTML = buildProgressHtml(data.booking);
        const editButton = document.getElementById('editBookingButton');
        if (editButton) editButton.addEventListener('click', () => editBooking(data.booking));
        const cancelButton = document.getElementById('cancelBookingButton');
        if (cancelButton) cancelButton.addEventListener('click', () => cancelBooking(data.booking.id));
    } catch (error) {
        if (!silent) showToast(error.message, 'danger');
    }
}
function editBooking(booking) {
    resetBookingForm(booking.booking_date);
    editingBookingId = Number(booking.id);
    document.getElementById('bookingIdInput').value = booking.id;
    document.getElementById('modalTitle').textContent = 'Edit pending request';
    document.getElementById('saveBookingButton').textContent = 'Update request';
    document.getElementById('sessionTitle').value = booking.title || '';
    document.getElementById('serviceType').value = booking.service_type || 'portrait';
    attendeeSelect.value = String(booking.attendee_count || 1);
    if (booking.package_key && packageSelect.querySelector(`option[value="${CSS.escape(booking.package_key)}"]`)) {
        packageSelect.value = booking.package_key;
    }
    document.getElementById('motif').value = booking.motif || '';
    document.getElementById('clientNotes').value = booking.client_notes || '';
    periodSelect.value = booking.period || 'AM';
    updatePeriodAvailability(booking.period || 'AM');
    bookingModal.show();
}
async function cancelBooking(id) {
    const confirmed = await SoulprintUI.confirm(
        'Cancel this booking request?',
        'The appointment period will be released for other clients.'
    );
    if (!confirmed) return;
    const formData = new FormData();
    formData.set('action','cancel');
    formData.set('booking_id',String(id));
    try {
        const response = await fetch('process_client_booking.php',{method:'POST',body:formData});
        const data = await parseApiResponse(response);
        if (!response.ok || !data.ok) throw new Error(data.message || 'Unable to cancel this request.');
        showToast(data.message, 'success');
        bookingCalendar.refetchEvents();
        await loadBookingDetails(id, true);
    } catch (error) { showToast(error.message, 'danger'); }
}

const calendarEl = document.getElementById('calendar');
bookingCalendar = new FullCalendar.Calendar(calendarEl, {
    initialView:'dayGridMonth',
    height:'auto',
    events:'load_client_events.php',
    headerToolbar:{left:'prev,next today',center:'title',right:'dayGridMonth,timeGridWeek'},
    selectable:false,
    eventTimeFormat:{hour:'numeric',minute:'2-digit',meridiem:'short'},
    eventClick(info) {
        if (info.event.extendedProps.isOwn) {
            loadBookingDetails(info.event.id);
        } else {
            showToast('That appointment period is reserved. Choose another period or date.', 'warning');
        }
    },
    dateClick(info) {
        const date = info.dateStr.slice(0,10);
        if (date < '<?= sp_h($today) ?>') {
            showToast('Past dates are view-only. Select a future date to make a new request.', 'warning');
            return;
        }
        const amTaken = eventHasReservedPeriod(date,'AM') || periodHasStarted(date,'AM');
        const pmTaken = eventHasReservedPeriod(date,'PM') || periodHasStarted(date,'PM');
        if (amTaken && pmTaken) {
            showToast('No appointment periods remain available for this date.', 'warning');
            return;
        }
        resetBookingForm(date);
        updatePeriodAvailability(amTaken ? 'PM' : 'AM');
        bookingModal.show();
    },
    eventsSet() {
        if (dateInput.value) updatePeriodAvailability(periodSelect.value);
    }
});
bookingCalendar.render();
if (queryBookingId) loadBookingDetails(queryBookingId);

document.getElementById('newBookingButton').addEventListener('click', () => {
    resetBookingForm('');
    bookingModal.show();
});
dateInput.addEventListener('change', () => updatePeriodAvailability());
packageSelect.addEventListener('change', refreshPackageDetails);
attendeeSelect.addEventListener('change', refreshPackageDetails);
refreshPackageDetails();

bookingForm.addEventListener('submit', async event => {
    event.preventDefault();
    bookingError.classList.add('d-none');
    if (!bookingForm.reportValidity()) return;
    const button = document.getElementById('saveBookingButton');
    button.disabled = true;
    button.innerHTML = '<span class="sp-spinner me-2"></span>Saving request';
    try {
        const response = await fetch(bookingForm.getAttribute('action'),{method:'POST',body:new FormData(bookingForm)});
        const data = await parseApiResponse(response);
        if (!response.ok || !data.ok) throw new Error(data.message || 'Could not save the booking request.');
        bookingModal.hide();
        showToast(data.message, 'success');
        bookingCalendar.refetchEvents();
        selectedBookingId = Number(data.booking_id);
        setTimeout(() => loadBookingDetails(selectedBookingId,true), 250);
    } catch (error) {
        bookingError.innerHTML = '<i class="ri-error-warning-line"></i><div></div>';
        bookingError.lastElementChild.textContent = error.message;
        bookingError.classList.remove('d-none');
        bookingError.classList.add('sp-shake');
        setTimeout(() => bookingError.classList.remove('sp-shake'), 600);
    } finally {
        button.disabled = false;
        button.textContent = document.getElementById('bookingIdInput').value ? 'Update request' : 'Send request';
    }
});

document.getElementById('recommendationForm').addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const button = document.getElementById('recommendButton');
    const results = document.getElementById('recommendationResults');
    button.disabled = true;
    button.innerHTML = '<span class="sp-spinner me-2"></span>Finding matches';
    results.innerHTML = `
        <div class="sp-skeleton sp-skeleton--title"></div>
        <div class="sp-skeleton sp-skeleton--block mb-3"></div>
        <div class="sp-skeleton sp-skeleton--text"></div>
        <div class="sp-skeleton sp-skeleton--text" style="width:80%"></div>`;
    try {
        const response = await fetch('recommend_packages.php',{method:'POST',body:new FormData(form)});
        const data = await parseApiResponse(response);
        if (!response.ok || !data.ok) throw new Error(data.message || 'Could not generate package recommendations.');
        if (!data.recommendations.length) {
            results.innerHTML = '<div class="sp-alert sp-alert--warning mb-0"><i class="ri-alert-line"></i><div>No package currently matches that group size. Try adjusting your group size.</div></div>';
            return;
        }
        results.innerHTML = `<div class="row g-3">${data.recommendations.map((item,index) => `
            <div class="col-12 ${data.recommendations.length > 1 ? 'col-md-6' : ''}">
                <article class="sp-rec-card sp-pop" style="--sp-delay:${index * 90}ms">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <div>
                            <div class="small muted">${escapeHtml(item.category)}${index === 0 ? ' · Best match' : ''}</div>
                            <h3 class="h6 fw-bold mt-1 mb-0">${escapeHtml(item.name)}</h3>
                        </div>
                        <span class="badge sp-match-score">${Number(item.score)}% match</span>
                    </div>
                    <div class="d-flex gap-3 my-3 align-items-baseline">
                        <strong class="sp-price">${formatMoney(item.price)}</strong>
                        <span class="muted small">${escapeHtml(item.duration)}</span>
                    </div>
                    <ul class="list-unstyled sp-rec-reason mb-3">${item.reasons.map(reason => `<li class="mb-1"><i class="ri-check-line text-success me-1"></i>${escapeHtml(reason)}</li>`).join('')}</ul>
                    <button type="button" class="btn btn-sm btn-outline-light w-100 use-recommendation" data-key="${escapeHtml(item.key)}" data-price="${Number(item.price)}">Use this package</button>
                </article>
            </div>`).join('')}</div>`;
        results.querySelectorAll('.use-recommendation').forEach(button => button.addEventListener('click', () => {
            const option = packageSelect.querySelector(`option[value="${CSS.escape(button.dataset.key)}"]`);
            if (!option) return;
            resetBookingForm('');
            packageSelect.value = button.dataset.key;
            document.getElementById('serviceType').value = document.getElementById('recEventType').value;
            attendeeSelect.value = document.getElementById('recPeople').value;
            document.getElementById('sessionTitle').value = document.getElementById('recEventType').selectedOptions[0].text;
            document.getElementById('motif').value = document.getElementById('recStyle').selectedOptions[0].text;
            document.getElementById('clientNotes').value = document.getElementById('recRequirements').value;
            refreshPackageDetails();
            bookingModal.show();
        }));
    } catch (error) {
        results.innerHTML = `<div class="sp-alert sp-alert--danger mb-0"><i class="ri-error-warning-line"></i><div>${escapeHtml(error.message)}</div></div>`;
    } finally {
        button.disabled = false;
        button.innerHTML = '<i class="ri-magic-line me-2"></i>Recommend packages';
    }
});

setInterval(() => {
    bookingCalendar.refetchEvents();
    if (selectedBookingId) loadBookingDetails(selectedBookingId,true);
}, 30000);
</script>
<?php include __DIR__ . '/includes/page_bottom.php'; ?>
