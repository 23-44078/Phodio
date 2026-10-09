# Deployment notes

Read this before deploying or handing the project to another session.

## After merging to `main`, do these two things

### 1. Run the 3 migrations in Supabase (required)

Open **Supabase → SQL Editor** and run these files **in this order**:

```
phodio-main/api/db/migrations/20261009_01_admin_roles.sql
phodio-main/api/db/migrations/20261009_02_chat_messages.sql
phodio-main/api/db/migrations/20261009_03_drop_booking_title.sql
```

All three are idempotent (safe to re-run). Back up the database first if you
want to be careful — `20261009_03` drops the `bookings.title` column.

| Migrated | What happens if you skip it |
| --- | --- |
| `01` roles | The app still works; every studio account behaves as a super admin and the **Studio accounts** page shows a "run the migration" notice. |
| `02` chat | The chat panels show a setup notice. Booking, calendar and progress updates are unaffected. |
| `03` drop title | Nothing breaks, but the old `title` column stays in the database and is no longer written to. |

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

## Security follow-up (not changed, your call)

Two pre-existing debug endpoints are publicly reachable and were left as-is:

- `/admin/repair.php` — **truncates** the `admin` table and recreates a single
  account with the password `SoulprintMP` printed in the page. This is a full
  account takeover for anyone who knows the URL.
- `/admin/test_password.php` — reports whether a given username/password pair is
  valid, i.e. a login oracle.

Recommendation: delete both files, or move them behind a secret and delete them
after use. This release only makes `repair.php` recreate the account as a
**super admin** so it can still reach `/admin/team.php`.

## Deployment target

Vercel **Root Directory is `phodio-main`**, so `phodio-main/vercel.json` and
`phodio-main/api/` are what get built. The copy at the repository root
(`vercel.json`, `supabase_schema.sql`, `studio_mgmt.sql`, `README*.md`) is a
mirror for convenience only — the root `vercel.json` is orphaned and unused.

Every `.php` URL is routed through `phodio-main/api/router.php`.
