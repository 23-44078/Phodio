<?php
session_start();
require_once __DIR__ . '/db/db.php';
require_once __DIR__ . '/includes/booking_helpers.php';
require_once __DIR__ . '/includes/ui.php';

if (!isset($_SESSION['client_id'])) {
    header('Location: client_login.php');
    exit;
}

$clientId = (int) $_SESSION['client_id'];
$summaryStmt = $conn->prepare("SELECT
        SUM(status = 'Pending') AS pending_count,
        SUM(status IN ('Confirmed','In Progress','Editing','Ready for Pickup')) AS active_count,
        SUM(status = 'Completed') AS completed_count
    FROM bookings WHERE client_id = ?");
$summaryStmt->bind_param('i', $clientId);
$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc() ?: [];

$bookingsStmt = $conn->prepare("SELECT id, title, service_type, package_type, price, booking_date, start_time, slot_period, status, status_note
                                FROM bookings WHERE client_id = ?
                                ORDER BY booking_date DESC, start_time DESC LIMIT 8");
$bookingsStmt->bind_param('i', $clientId);
$bookingsStmt->execute();
$bookings = $bookingsStmt->get_result();

$pendingCount = (int) ($summary['pending_count'] ?? 0);
$activeCount = (int) ($summary['active_count'] ?? 0);
$completedCount = (int) ($summary['completed_count'] ?? 0);

$spTitle = 'Client Dashboard';
$spDescription = 'Your SOULPRINT session requests, service progress and upcoming appointments.';
include __DIR__ . '/includes/page_top.php';
include __DIR__ . '/includes/client_header.php';
?>
<main class="sp-shell">
    <section class="sp-hero d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 sp-enter">
        <div>
            <div class="sp-eyebrow mb-2"><i class="ri-aperture-line"></i><?= sp_h(SP_PORTAL_LABEL) ?></div>
            <h1 class="sp-title mb-2">Welcome, <?= sp_h((string) ($_SESSION['client_name'] ?? 'Client')) ?></h1>
            <p class="sp-subtitle">Manage appointment requests and keep up with your photography service progress.</p>
        </div>
        <a href="client_booking.php" class="btn btn-primary px-4 py-2">
            <i class="ri-calendar-check-line me-2"></i>Book or track a session
        </a>
    </section>

    <section class="row g-3 mb-4">
        <div class="col-6 col-lg-4">
            <?= sp_stat_card('Awaiting confirmation', (string) $pendingCount, 'ri-time-line', 'warning', 'Requests waiting for the studio', (float) $pendingCount) ?>
        </div>
        <div class="col-6 col-lg-4">
            <?= sp_stat_card('Active services', (string) $activeCount, 'ri-camera-lens-line', 'accent', 'Confirmed or in production', (float) $activeCount) ?>
        </div>
        <div class="col-12 col-lg-4">
            <?= sp_stat_card('Completed services', (string) $completedCount, 'ri-checkbox-circle-line', 'success', 'Finished and released', (float) $completedCount) ?>
        </div>
    </section>

    <section class="surface" data-sp-reveal>
        <?= sp_surface_header(
            'ri-camera-lens-line',
            'Your photography requests',
            '<a href="client_booking.php" class="btn btn-outline-light btn-sm">View progress</a>'
        ) ?>
        <div class="surface-body px-0 py-0">
            <?php if ($bookings->num_rows === 0): ?>
                <?= sp_empty_state(
                    'ri-calendar-todo-line',
                    'No booking requests yet',
                    'Use the package finder to get a suggestion and request your first session.',
                    '<a href="client_booking.php#package-recommender" class="btn btn-primary"><i class="ri-magic-line me-2"></i>Find a package</a>'
                ) ?>
            <?php else: ?>
                <?php while ($booking = $bookings->fetch_assoc()): ?>
                    <?php $period = $booking['slot_period'] ?: phodio_period_from_time((string) $booking['start_time']); ?>
                    <a href="client_booking.php?booking=<?= (int) $booking['id'] ?>"
                       class="sp-booking-row d-block text-decoration-none">
                        <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                            <div>
                                <div class="fw-bold text-white"><?= sp_h((string) ($booking['title'] ?: $booking['package_type'])) ?></div>
                                <div class="small muted mt-1">
                                    <?= sp_h((string) ($booking['package_type'] ?? 'Photography service')) ?>
                                    <span class="sp-dot"></span><?= sp_h(sp_date($booking['booking_date'])) ?>
                                    <span class="sp-dot"></span><?= sp_h(sp_period_label($period)) ?>
                                </div>
                                <?php if (!empty($booking['status_note'])): ?>
                                    <div class="small muted mt-2">
                                        <i class="ri-chat-3-line me-1"></i><?= sp_h((string) $booking['status_note']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <span class="sp-price"><?= sp_h(sp_money($booking['price'])) ?></span>
                                <?= sp_status_chip((string) $booking['status']) ?>
                                <i class="ri-arrow-right-s-line sp-row-arrow" aria-hidden="true"></i>
                            </div>
                        </div>
                    </a>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </section>

    <p class="muted small mt-3 mb-0" data-sp-reveal>
        <i class="ri-refresh-line me-1"></i>Current progress and status history are available in your booking calendar and refresh automatically there.
    </p>
</main>
<?php include __DIR__ . '/includes/page_bottom.php'; ?>
