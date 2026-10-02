<?php
/**
 * SOULPRINT — shared document head (opens <body> as well).
 *
 * Every screen renders through this partial, which is what keeps the fonts,
 * colours, icons, stylesheets and script order identical across the client
 * portal and the admin console. Set these before including it:
 *
 *   $spTitle        — page name, e.g. 'Bookings' (becomes "Bookings | SOULPRINT")
 *   $spDepth        — 0 for root pages, 1 for pages inside admin/
 *   $spDescription  — meta description (optional)
 *   $spFullCalendar — true to also load the FullCalendar assets
 *   $spExtraHead    — extra markup for <head> (optional)
 */

require_once __DIR__ . '/ui.php';

$spTitle = isset($spTitle) && $spTitle !== '' ? (string) $spTitle : SP_BRAND;
$spDescription = isset($spDescription) && $spDescription !== ''
    ? (string) $spDescription
    : 'SOULPRINT studio management — booking, service progress, expenses and daily performance in one place.';
$spFullCalendar = !empty($spFullCalendar);
$spExtraHead = $spExtraHead ?? '';
?>
<!DOCTYPE html>
<html lang="en" class="sp-no-js">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= sp_h($spDescription) ?>">
    <meta name="theme-color" content="#0b0d12">
    <title><?= sp_h(sp_page_title($spTitle)) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
<?php if ($spFullCalendar): ?>
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
<?php endif; ?>
    <link rel="stylesheet" href="<?= sp_h(sp_asset('css/theme.css')) ?>">
    <link rel="stylesheet" href="<?= sp_h(sp_asset('css/motion.css')) ?>">
<?= $spExtraHead ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($spFullCalendar): ?>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<?php endif; ?>
    <script src="<?= sp_h(sp_asset('js/ui.js')) ?>"></script>
</head>
<body>
