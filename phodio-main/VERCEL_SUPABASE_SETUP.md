# Phodio — Vercel + Supabase setup

## 1. Create the Supabase database

1. Create/open your Supabase project.
2. Open **SQL Editor**.
3. Run `supabase_schema.sql` completely.

## 2. Get the database connection string

In Supabase, open **Connect** and choose **Session pooler**. Copy the PostgreSQL URI.
Use the Session pooler URI (port 5432) for this PHP application because it uses PDO prepared statements and transactions.

## 3. Add the Vercel environment variable

In Vercel: Project -> Settings -> Environment Variables, add:

`DATABASE_URL` = your Supabase Session pooler PostgreSQL connection string.

Do not put the real password in this repo or commit it to Git.

### Optional: `PHODIO_CHAT_KEY`

`PHODIO_CHAT_KEY` is **optional**. It unlocks one extra feature: it lets the
booking chat survive a Vercel cold start that drops the PHP session file.

- When it **is set**, `api/chat.php` also accepts a per-booking HMAC token
  (`X-Phodio-Chat-Token`). The token is issued by the server, is bound to one
  booking and one sender, and cannot be forged by the browser.
- When it is **not set**, chat simply uses the PHP session and the
  `phodio_session` cookie. Everything still works; a client may occasionally
  need to refresh the page to see new messages after a cold start.

To enable it, generate a long random string and add it to Vercel for **all**
environments:

```bash
openssl rand -hex 32
```

Changing the value invalidates any token already in a browser, which is
harmless: the next page load is issued a fresh one.

## 4. Database migrations

`supabase_schema.sql` is enough for a brand-new database. An existing database
needs the dated files in `api/db/migrations/` run **in order** in the Supabase
SQL Editor. They are idempotent, so re-running them is safe.

| File | What it does |
| --- | --- |
| `20261002_manuscript_alignment.sql` | Legacy MySQL alignment (old databases only) |
| `20261009_01_admin_roles.sql` | Adds `admin.role` (`admin` / `super_admin`), `full_name`, `is_active`, `last_login_at` |
| `20261009_02_chat_messages.sql` | Creates the `chat_messages` table used by the booking chat |
| `20261009_03_drop_booking_title.sql` | Drops the `bookings.title` column; `package_type` becomes the only label |

Until `20261009_01` is applied the app degrades gracefully: every studio
account behaves as a super admin. Until `20261009_02` is applied the chat
panels show a setup notice instead of an error.

## 5. Deploy

Import the repository into Vercel. Keep the **Root Directory at the repository root (`./`)** — the app (`api/` + `vercel.json`) lives at the root, and the included `vercel.json` configures the community PHP runtime and routes every PHP URL through the single front controller `api/router.php`.

After deploying:

- Unified sign-in: `https://<project>.vercel.app/login.php` (client and studio accounts use the same form)
- Public client site: `https://<project>.vercel.app/` (redirects to the sign-in page)
- Admin panel: `https://<project>.vercel.app/admin/`
- Health check: `https://<project>.vercel.app/health.php` (returns `"ok": true` when the database connection works)

`/client_login.php` and `/admin/login.php` are kept as redirects to
`/login.php`, so existing links and bookmarks keep working.

## Troubleshooting: "Database connection failed"

The app now prints the exact reason under the message, but the usual causes are:

1. **The variable was added after the deployment.** Environment variables do not apply to deployments that already exist. After adding/changing `DATABASE_URL`, open **Deployments** and click **Redeploy** on the latest one.
2. **Wrong environment scope.** Vercel env vars are scoped per environment. A `git push` of a branch (or a preview URL like `phodio-xxxxx-...vercel.app`) is a **Preview** deployment — the variable must be enabled for **Preview** as well as **Production** (ticking "All environments" is easiest).
3. **Use the Session pooler URI.** In Supabase use **Connect -> Session pooler** (hostname like `aws-0-<region>.pooler.supabase.com`, port **5432**). Do not use the direct `db.<ref>.supabase.co` host (IPv6-only, may not connect from Vercel) and do not use the transaction pooler port 6543 (it breaks PDO prepared statements).
4. **URL-encode special characters in the password.** In the connection string, `@` must be `%40`, `#` must be `%23`, `:` must be `%3A`, `/` must be `%2F`, etc. (The app decodes them correctly, but the URI itself must parse.) Supabase shows the encoded URI in the Connect dialog.
5. **The Supabase project is paused.** Free-tier projects pause after a period of inactivity — restore it from the Supabase dashboard.
6. **Schema not loaded.** Run `supabase_schema.sql` in the Supabase SQL Editor (safe to re-run). Without the `phodio_sessions` table, client logins fail at the INSERT even when the connection is fine.

## Important limitation: profile uploads

The original application writes uploaded profile images to `uploads/profile/`. Vercel functions use an ephemeral filesystem, so those uploaded files are not permanent. The existing application logic was intentionally left otherwise unchanged. If persistent profile images are required later, move only that upload feature to Supabase Storage.

## Authentication/session note

The application uses PHP file sessions. On Vercel, every PHP file deployed as its own serverless function would get a separate ephemeral `/tmp`, so login state would never survive a redirect between pages. This is why all pages are served through the single `api/router.php` function: within one warm function instance the session works normally. If a request lands on a cold instance, clients may be asked to log in again — for a small school/demo deployment this is acceptable; for production authentication, migrate session/auth handling to a persistent/session-backed mechanism (e.g. store sessions in the Supabase database).
