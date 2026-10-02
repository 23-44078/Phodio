<?php
/**
 * SOULPRINT — shared UI helpers.
 *
 * Every page includes this file (directly or through includes/head.php) so the
 * whole product shares one set of labels, formats and components. This is the
 * reason the client portal and the admin console no longer drift apart.
 */

declare(strict_types=1);

if (!defined('SP_BRAND')) {
    define('SP_BRAND', 'SOULPRINT');
    define('SP_BRAND_MARKUP', 'SOUL<span>PRINT</span>');
    define('SP_PORTAL_LABEL', 'Client Portal');
    define('SP_ADMIN_LABEL', 'Studio Management');
    define('SP_MONEY_PREFIX', "\u{20B1}");
}

/** Escape for HTML output. */
function sp_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Relative URL prefix back to the application root.
 * Pages at the root need "", pages inside admin/ need "../".
 */
function sp_prefix(): string
{
    global $spDepth;
    $depth = isset($spDepth) ? max(0, (int) $spDepth) : 0;
    return str_repeat('../', $depth);
}

/** URL for a shared asset, e.g. sp_asset('css/theme.css'). */
function sp_asset(string $path): string
{
    return sp_prefix() . 'assets/' . ltrim($path, '/');
}

/** Consistent document title: "Bookings | SOULPRINT". */
function sp_page_title(string $page): string
{
    return $page . ' | ' . SP_BRAND;
}

/** Money is always shown the same way: ₱1,234.00 */
function sp_money(float|int|string|null $amount, int $decimals = 2): string
{
    return SP_MONEY_PREFIX . number_format((float) $amount, $decimals);
}

/** Signed money for profit / gap style figures: +₱1,234.00 or −₱1,234.00 */
function sp_money_signed(float|int|string|null $amount, int $decimals = 2): string
{
    $value = (float) $amount;
    $sign = $value > 0 ? '+' : ($value < 0 ? "\u{2212}" : '');
    return $sign . sp_money(abs($value), $decimals);
}

/** Dates are always shown the same way: Mar 30, 2026 */
function sp_date(?string $value, string $format = 'M d, Y'): string
{
    $value = trim((string) $value);
    if ($value === '' || $value === '0000-00-00') {
        return '—';
    }
    $timestamp = strtotime($value);
    return $timestamp === false ? '—' : date($format, $timestamp);
}

function sp_datetime(?string $value): string
{
    return sp_date($value, 'M d, Y · g:i A');
}

/** Session label shown as "9:00 AM · Morning" everywhere. */
function sp_period_label(?string $period): string
{
    return $period === 'PM' ? '1:00 PM · Afternoon' : '9:00 AM · Morning';
}

/** Brand lock-up shared by every header and auth screen. */
function sp_brand(string $class = 'sp-brand', bool $withMark = true): string
{
    $mark = $withMark ? '<span class="sp-brand__mark"><i class="ri-camera-lens-fill"></i></span>' : '';
    return '<span class="' . sp_h($class) . '">' . $mark
        . '<span class="sp-brand__text">' . SP_BRAND_MARKUP . '</span></span>';
}

/** Status pill. Colours live in theme.css; this only maps status → class. */
function sp_status_chip(?string $status): string
{
    $status = trim((string) $status);
    $classes = [
        'Pending' => 'status-pending',
        'Confirmed' => 'status-confirmed',
        'In Progress' => 'status-progress',
        'Editing' => 'status-editing',
        'Ready for Pickup' => 'status-ready',
        'Completed' => 'status-completed',
        'Cancelled' => 'status-cancelled',
    ];
    $class = $classes[$status] ?? 'status-pending';
    $label = $status !== '' ? $status : 'Unknown';
    return '<span class="status-chip ' . $class . '">' . sp_h($label) . '</span>';
}

/** Flash / inline alert. $dismissAfter > 0 fades the banner out automatically. */
function sp_flash(string $type, string $message, int $dismissAfter = 0): string
{
    $icons = [
        'danger' => 'ri-error-warning-line',
        'success' => 'ri-checkbox-circle-line',
        'info' => 'ri-information-line',
        'warning' => 'ri-alert-line',
    ];
    $icon = $icons[$type] ?? $icons['info'];
    $attr = $dismissAfter > 0 ? ' data-sp-flash="' . $dismissAfter . '"' : '';
    return '<div class="sp-alert sp-alert--' . sp_h($type) . ' sp-flash mb-3" role="alert"' . $attr . '>'
        . '<i class="' . $icon . '"></i><div class="flex-grow-1">' . sp_h($message) . '</div>'
        . '<button type="button" class="btn-icon btn-icon--sm" data-sp-flash-close aria-label="Dismiss">'
        . '<i class="ri-close-line"></i></button></div>';
}

/** Empty-state block used by every table/list that can be empty. */
function sp_empty_state(string $icon, string $title, string $text, string $actionHtml = ''): string
{
    return '<div class="sp-empty" data-sp-reveal>'
        . '<div class="sp-empty__icon"><i class="' . sp_h($icon) . '"></i></div>'
        . '<div class="sp-empty__title">' . sp_h($title) . '</div>'
        . '<p class="sp-empty__text">' . sp_h($text) . '</p>'
        . $actionHtml
        . '</div>';
}

/** Stat tile with animated counter. */
function sp_stat_card(
    string $label,
    string $value,
    string $icon,
    string $tone = 'brand',
    string $meta = '',
    ?float $countUp = null,
    int $decimals = 0,
    string $prefix = ''
): string {
    $counterAttr = $countUp === null
        ? ''
        : ' data-sp-count="' . sp_h((string) $countUp) . '"'
            . ' data-sp-decimals="' . $decimals . '"'
            . ' data-sp-prefix="' . sp_h($prefix) . '"';

    return '<div class="sp-stat sp-stat--' . sp_h($tone) . '" data-sp-reveal>'
        . '<div class="sp-stat__icon"><i class="' . sp_h($icon) . '"></i></div>'
        . '<div class="sp-stat__label">' . sp_h($label) . '</div>'
        . '<div class="sp-stat__value"' . $counterAttr . '>' . sp_h($value) . '</div>'
        . ($meta !== '' ? '<div class="sp-stat__meta">' . sp_h($meta) . '</div>' : '')
        . '</div>';
}

/** Section header used on top of every surface/card. */
function sp_surface_header(string $icon, string $title, string $rightHtml = ''): string
{
    return '<div class="surface-header">'
        . '<span><i class="' . sp_h($icon) . ' me-2 text-danger"></i>' . sp_h($title) . '</span>'
        . $rightHtml
        . '</div>';
}
