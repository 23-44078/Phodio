# Deployment notes

Read this before deploying or handing the project to another session.

## Super admin removed

Studio accounts are now all equal. Every signed-in account can open the whole
panel, including **Studio accounts** (`admin/team.php`), which used to answer
"Super admin only" for anyone whose `admin.role` was not `super_admin`.

- `checkSuperAdmin()`, `phodio_is_super_admin()`, `currentAdminRoleLabel()` and
  the `PHODIO_ROLE_*` constants are gone; those pages call `checkLogin()`.
- The sidebar shows the account name and "Studio account" instead of the
  Super admin / Admin badge, and now links to **Studio accounts** (it was only
  reachable by typing the URL).
- The role picker is gone from the accounts table and the create form. New
  accounts are created with the column default (`admin`) and lose nothing.
- One guard remains: you cannot disable or remove **your own** account, so the
  studio cannot lock itself out of the panel.
- `admin.role` is still read by the login code so a database that has not run
  `20261009_01_admin_roles.sql` keeps working — it no longer gates anything.

## Admin panel: one design language

`dashboard.php` and `bookings.php` now use the same layout as the finance
pages: one page header, summary cards, dark tables with empty states.

- **Dashboard** — Income / Expenses / Liabilities / Net profit cards, a daily
  goal card with a progress bar, breakdown tables with empty states, and quick
  access to bookings, expenses, liabilities, tracker and accounts.
- **Bookings** — pending / today / upcoming counters above the calendar. The
  calendar, inspector, progress updates and client chat are unchanged.
- **Studio accounts** — accounts / active / disabled counters, and the create
  form and reset-password modal restyled to match.

## What changed in this release

No new migration is required for this release — the SQL schema is untouched.

### Security: the studio panel is now actually private

Seven admin endpoints never called `checkLogin()`. Anyone who guessed the URL
could read the studio's expenses, liabilities and daily earnings, and three of
them let a stranger **write** to those tables. All of them now require a
signed-in studio account:

| File | Was | Now |
| --- | --- | --- |
| `admin/expenses.php` | readable by anyone | `checkLogin()` |
| `admin/liabilities.php` | readable by anyone | `checkLogin()` |
| `admin/tracker.php` | readable by anyone | `checkLogin()` |
| `admin/process_expense.php` | anyone could insert | `checkLogin()` + validation |
| `admin/process_liability.php` | anyone could insert | `checkLogin()` + validation |
| `admin/process_tracker.php` | anyone could insert | `checkLogin()` + validation |
| `admin/delete_expense.php` | anyone could delete by GET | `checkLogin()` + POST only |

### Fixed: studio sign-in could bounce straight back to the login page

`admin/functions.php` called `session_start()` **before** requiring
`config/database.php`, which is the file that points `session.save_path` at
`/tmp/phodio-sessions`. Sign-in wrote the session to one directory and the
studio pages read it from another, so a successful login looked signed out on
the next request. That only worked while `api/php.ini` happened to be loaded;
the load order is now correct, so it works either way.

### Fixed: the daily tracker could not save anything

`admin/process_tracker.php` bound seven values (`"sdiidii"`) against four `?`
placeholders. PostgreSQL rejects that with `SQLSTATE[HY093]`, so every entry
failed. The `ON CONFLICT … EXCLUDED` clause re-uses the inserted values, so
four bindings are all that is needed.

### Fixed: delete buttons that went nowhere

`admin/liabilities.php` and `admin/tracker.php` linked to `delete_liability.php`
and `delete_tracker.php`, which did not exist — both delete buttons returned
404. The two endpoints have been added (guarded, POST only). `delete_expense.php`
also called `$stmt->close()`, a method the PDO compatibility layer does not
have, which was a fatal error even for a signed-in admin.

### Fixed: signed-in clients sent back to the login page

`api/index.php` read `$_SESSION['client_id']` directly. On Vercel the PHP
session file can disappear between requests, so clients who were still signed
in (their `phodio_session` cookie was valid) were redirected to `/login.php`
after every cold start. It now restores the session through
`phodio_current_client_id()`, like the rest of the app.

### Fixed: error messages shown to visitors

- `api/client_booking.php` turned `display_errors` on in production, printing
  stack traces — with database details and absolute paths — to clients.
- `api/register.php` printed the raw PostgreSQL error on failure.
- `admin/process_expense.php`, `process_liability.php` and `process_tracker.php`
  echoed `$conn->error`.

Errors are now written to the function log and the visitor gets a message they
can act on.

### New design: sign-in and registration

`api/login.php` and `api/register.php` share one look now — a dark SoulPrint
layout with a brand panel, a Client/Studio switch, icon fields, a show/hide
password control and a busy state on the submit button. Registration also
gained a password confirmation and a minimum length of 8 characters, and the
unused profile-photo upload (files do not persist on Vercel) was removed.

`/client_login.php` is still a 307 redirect to `/login.php?as=client`, so the
new design appears on `/login.php`.

### New design: the finance pages match the dashboard

`admin/expenses.php`, `admin/liabilities.php` and `admin/tracker.php` were
still plain Bootstrap cards with a light table header, while the dashboard,
bookings and team pages use the dark SoulPrint layout. They now share it:

- one page header (uppercase title, one-line description, primary action);
- three summary cards each — expenses shows this month / all time / average,
  liabilities shows outstanding / due in 30 days / next due date, the tracker
  shows logged income / clients served / goals met;
- dark tables with an empty state instead of a blank panel;
- centred modals with labelled fields;
- Inter as the shared admin font.

The shared pieces live in `admin/assets/css/style.css` as new class names
(`.page-head`, `.stat-grid`, `.table-finance`, `.empty-state`, …), so the
dashboard, bookings and team pages keep their current look.

While rewriting them, every value printed by these three pages now goes through
`admin_h()` — descriptions and creditor names are staff-typed text and were
echoed unescaped before.

### Smaller hardening

- `/login.php` throttles repeated failed attempts (10 per 10 minutes per
  browser, session based — it never locks an account out permanently).
- Successful sign-in now redirects to absolute paths (`/admin/dashboard.php`).
- Expenses, liabilities and tracker pages show a success/failure banner, so a
  rejected save is no longer a silent redirect back to an unchanged page.

## After merging to `main`, do these two things

### 1. Run the 3 migrations in Supabase (required)

Open **Supabase → SQL Editor** and run these files **in this order**:

```
phodio-main/api/db/migrations/20261009_01_admin_roles.sql
phodio-main/api/db/migrations/20261009_02_chat_messages.sql
phodio-main/api/db/migrations/20261009_03_drop_booking_title.sql
```

All three are idempotent (safe to re-run). Back up the database first —
`20261009_03` drops the `bookings.title` column.

| Migrated | What happens if you skip it |
| --- | --- |
| `01` roles | The app still works; every studio account behaves as a super admin and the **Studio accounts** page shows a "run the migration" notice. |
| `02` chat | The chat panels show a setup notice. Booking, calendar and progress updates are unaffected. |
| `03` drop title | **Required for bookings.** In the old schema `bookings.title` is `NOT NULL`, and the current code no longer writes it, so new bookings fail until this migration runs. After it runs, any deployment still on pre-PR #4 code can no longer save bookings. |

A brand-new database only needs `supabase_schema.sql` — it already contains
migrations 01 and 02.

### 2. Set `PHODIO_CHAT_KEY` in Vercel (optional)

`PHODIO_CHAT_KEY` is **optional**. Without it everything works normally.

- **Set it** → the booking chat survives a Vercel cold start that loses the PHP
  session file, because `api/chat.php` also accepts a per-booking HMAC token.
- **Leave it unset** → chat falls back to the PHP session / `phodio_session`
  cookie. A client may occasionally need to refresh to see new messages.

To enable: Vercel → Project → Settings → Environment Variables → add
`PHODIO_CHAT_KEY` for **Production and Preview**, then redeploy.

```bash
openssl rand -hex 32
```

## What changed in this release

- **Unified login** — `/login.php` serves client and studio accounts from one
  form. `/client_login.php` and `/admin/login.php` redirect to it.
- **Super admin roles** — studio accounts are now `admin` or `super_admin`.
  Only super admins can open **Studio accounts** (`/admin/team.php`) to create,
  promote, disable, reset or remove accounts. The last super admin can never be
  demoted, disabled or deleted, and you cannot edit your own account.
- **Chat** — a message thread on every booking, visible in
  `/admin/bookings.php` (inspector) and `/client_booking.php`.
- **Booking title removed** — `package_type` is now the only label for a
  booking. The free-text `title` field is gone from every form, list and
  calendar event.

## Security fix: public debug endpoints removed

`/admin/repair.php` and `/admin/test_password.php` were publicly reachable and
have been deleted:

- `repair.php` truncated the `admin` table and recreated a `soulprint` super
  admin with a hard-coded password that was printed on the page. Anyone who knew
  the URL could take over the studio.
- `test_password.php` checked the `admin` account and printed its stored password
  hash when the check failed.

Because that password was committed to this repository, **change the `soulprint`
password** and check the `admin` table for accounts you do not recognise.

To reset a password without the removed page, generate a hash on your own machine
and update the row in the Supabase SQL Editor:

```bash
php -r "echo password_hash('NEW_PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
```

```sql
UPDATE admin SET password = '<hash from the command above>' WHERE username = 'soulprint';
```

## Deployment target

Vercel **Root Directory is `phodio-main`**, so `phodio-main/vercel.json` and
`phodio-main/api/` are what get built. The copy at the repository root
(`vercel.json`, `supabase_schema.sql`, `studio_mgmt.sql`, `README*.md`) is a
mirror for convenience only — the root `vercel.json` is orphaned and unused.

Every `.php` URL is routed through `phodio-main/api/router.php`.
