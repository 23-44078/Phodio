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
