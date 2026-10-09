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

## Important limitation: profile uploads

The original application writes uploaded profile images to `uploads/profile/`. Vercel functions use an ephemeral filesystem, so those uploaded files are not permanent. The existing application logic was intentionally left otherwise unchanged. If persistent profile images are required later, move only that upload feature to Supabase Storage.

## Authentication/session note

The application uses PHP file sessions. On Vercel, every PHP file deployed as its own serverless function would get a separate ephemeral `/tmp`, so login state would never survive a redirect between pages. This is why all pages are served through the single `api/router.php` function: within one warm function instance the session works normally. If a request lands on a cold instance, clients may be asked to log in again — for a small school/demo deployment this is acceptable; for production authentication, migrate session/auth handling to a persistent/session-backed mechanism (e.g. store sessions in the Supabase database).
