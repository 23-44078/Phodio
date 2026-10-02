# Phodio — SOULPRINT Studio Management

A PHP + MySQL studio management system with two sides that share one design
system:

- **Client portal** (`client_login.php`, `client_dashboard.php`, `client_booking.php`)
  — package finder, appointment requests, live service progress.
- **Studio console** (`admin/`) — dashboard, bookings, expenses, liabilities and
  the daily performance tracker.

## Requirements

- PHP 8.1+ with `mysqli`
- MySQL / MariaDB 5.7+
- XAMPP, MAMP or any local web server with PHP

## Setup

1. Copy the `Phodio/` folder into your web root (for XAMPP: `C:/xampp/htdocs/Phodio`).
2. Create the database and import the schema:
   ```sql
   CREATE DATABASE studio_mgmt CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
   ```
   then import `Phodio/studio_mgmt.sql` in phpMyAdmin.
3. Apply the follow-up migration `Phodio/db/migrations/20261002_manuscript_alignment.sql`.
4. Database credentials live in `Phodio/db/db.php` (default: `root` / empty password).
5. Make `Phodio/uploads/profile/` writable so client profile photos can be saved.
6. Open `http://localhost/Phodio/` for the client portal, or
   `http://localhost/Phodio/admin/` for the studio console.

## Design system

Everything visual comes from one place so the two sides of the product cannot
drift apart again:

| File | Purpose |
| --- | --- |
| `Phodio/assets/css/theme.css` | Design tokens (colour, type, spacing, radius, elevation) and every shared component: surfaces, stat cards, buttons, forms, tables, chips, timeline, empty states, alerts, modals, toasts, navbar, sidebar, FullCalendar skin. |
| `Phodio/assets/css/motion.css` | All animation primitives — scroll reveal, count-up, ripples, skeletons, shimmer, page enter, hover affordances — plus a `prefers-reduced-motion` kill switch. |
| `Phodio/assets/js/ui.js` | Shared behaviour: reveal-on-scroll, animated counters, meter fills, button ripples, scroll progress, toasts, the themed confirm dialog, password toggles, sidebar, flash auto-dismiss and form submit states. |
| `Phodio/includes/ui.php` | PHP helpers: `sp_h()`, `sp_money()`, `sp_date()`, `sp_period_label()`, `sp_status_chip()`, `sp_flash()`, `sp_empty_state()`, `sp_stat_card()`, `sp_surface_header()`, `sp_brand()`. |
| `Phodio/includes/page_top.php` | The single `<head>` — fonts, Bootstrap, RemixIcon, the two stylesheets and the scripts, in a fixed order. |
| `Phodio/includes/page_bottom.php` | Closes the document. |
| `Phodio/includes/client_header.php` | Client portal navigation bar. |
| `Phodio/admin/includes/header.php` | Studio console chrome (auth check, sidebar, page head). |
| `Phodio/admin/includes/footer.php` | Closes the console shell. |
| `Phodio/admin/assets/css/style.css` | Layout only — sidebar, top bar and content shell for the console. |

### Rules that keep it consistent

- **Do not add a `<style>` block to a page.** New styling belongs in
  `theme.css` (shared) or `admin/assets/css/style.css` (console layout only).
- **Do not hard-code colours, radii or shadows.** Use the `--sp-*` tokens.
- **Every page opens with `page_top.php`** (directly, or through
  `admin/includes/header.php`) and closes with `page_bottom.php`.
- **Money is always `₱1,234.00`** (`sp_money()`), **dates are always `Mar 30, 2026`**
  (`sp_date()`), and appointment slots are always `9:00 AM · Morning`
  (`sp_period_label()`).
- **The brand is one word: `SOULPRINT`.** Page titles follow `<Page> | SOULPRINT`.
- **Confirmations go through `SoulprintUI.confirm()`**, never `window.confirm()`.
- **Toasts go through `SoulprintUI.toast(message, type)`.**
- Mark something as reveal-on-scroll with `data-sp-reveal`, and animate a number
  with `data-sp-count="1234.5" data-sp-decimals="2" data-sp-prefix="₱"`.
- All motion is disabled automatically when the visitor has
  `prefers-reduced-motion: reduce`.

### Tokens at a glance

```
--sp-page      #0b0d12   page background
--sp-surface   #151922   panels and cards
--sp-line      #2b3242   borders
--sp-brand     #ef4444   primary accent (SOULPRINT red)
--sp-accent    #3b82f6   secondary accent
--sp-success   #22c55e   --sp-warning #f59e0b   --sp-info #38bdf8
--sp-text      #f8fafc   --sp-muted #a6afbf
--sp-font      Inter, system-ui, …
```

## Application flow

- A client submits a request → status `Pending`, the period is held.
- The studio updates the status (`Confirmed` → `In Progress` → `Editing` →
  `Ready for Pickup` → `Completed`); every change is written to
  `booking_updates` and appears in the client's progress timeline.
- Only one morning (9:00 AM) and one afternoon (1:00 PM) appointment exist per
  date; bookings are never hard-deleted so the history stays intact.
