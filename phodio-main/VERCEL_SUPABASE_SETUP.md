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

## 4. Deploy

Import the repository into Vercel. Keep the **Root Directory at the repository root (`./`)** — the app (`api/` + `vercel.json`) lives at the root, and the included `vercel.json` configures the community PHP runtime and routes every PHP URL through the single front controller `api/router.php`.

After deploying:

- Public client site: `https://<project>.vercel.app/` (redirects to the login page)
- Admin panel: `https://<project>.vercel.app/admin/`
- Health check: `https://<project>.vercel.app/health.php` (returns `"ok": true` when the database connection works)

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
