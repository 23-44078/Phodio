# Phodio — Vercel + Supabase deployment guide

The app is a PHP 8 application. On Vercel it runs through the community
`vercel-php` runtime (`vercel-php@0.9.0` = PHP 8.5) and stores its data in
Supabase PostgreSQL through `DATABASE_URL`.

- Repository: <https://github.com/23-44078/Phodio>
- Application folder: **`phodio-main/`** ← this is the Vercel **Root Directory**

---

## Step 1 — Create the database (Supabase)

1. Create a project at <https://supabase.com/dashboard>.
2. Open **SQL Editor → New query**.
3. Paste the whole contents of `phodio-main/supabase_schema.sql` and press **Run**.
   This creates `admin`, `users`, `bookings`, `booking_updates`, `phodio_sessions`,
   `daily_tracker`, `expenses` and `liabilities`, and seeds the existing records.

   > Already ran an older copy of the schema? Run
   > `phodio-main/api/db/migrations/20261015_phodio_sessions.sql` once as well.
   > The client portal cannot stay logged in without the `phodio_sessions` table.

## Step 2 — Copy the connection string

In Supabase open **Connect** and choose **Session pooler** (port `5432`).
Copy the URI - it looks like:

```
postgresql://postgres.abcdefghijklm:YOUR-PASSWORD@aws-0-ap-southeast-1.pooler.supabase.com:5432/postgres
```

Use the **Session pooler** string, not the direct connection: the direct host
is IPv6-only, and the pooler is what survives serverless cold starts. If your
database password contains characters such as `@`, `:`, `/` or `#`, URL-encode
them (`@` becomes `%40`).

## Step 3 — Deploy

### Option A — Vercel dashboard (no local tools)

1. Go to <https://vercel.com/new> and sign in with GitHub.
2. **Import Git Repository** → pick `23-44078/Phodio`.
3. Set **Root Directory** to `phodio-main` (Edit next to Root Directory) — this
   is the step people miss. Leave **Framework Preset** on *Other*.
4. Expand **Environment Variables** and add:

   | Name | Value | Environments |
   | --- | --- | --- |
   | `DATABASE_URL` | the Supabase Session pooler URI from step 2 | Production + Preview + Development |

5. Press **Deploy**. The build takes about a minute.

### Option B — Vercel CLI

```sh
npm i -g vercel
vercel login

cd phodio-main
vercel link                 # create/link the project
vercel env add DATABASE_URL production
vercel --prod
```

### Option C — Deploy Button

[![Deploy with Vercel](https://vercel.com/button)](https://vercel.com/new/clone?repository-url=https%3A%2F%2Fgithub.com%2F23-44078%2FPhodio&root-directory=phodio-main&project-name=phodio&repository-name=phodio&env=DATABASE_URL&envDescription=Supabase%20Session%20pooler%20PostgreSQL%20URI&envLink=https%3A%2F%2Fgithub.com%2F23-44078%2FPhodio%2Fblob%2Fmain%2Fphodio-main%2FVERCEL_SUPABASE_SETUP.md)

Clones the repository into your own GitHub account, sets the Root Directory and
asks for `DATABASE_URL`. It deploys the repository's default branch, so merge
the deployment branch into `main` first (or pick the branch in the Vercel
import screen) if it is not merged yet.

## Step 4 — Check the deployment

| Check | URL | Expected |
| --- | --- | --- |
| Database link | `https://<project>.vercel.app/health.php` | `{"ok":true,"app":"Phodio","database":"connected"}` |
| Root redirect | `https://<project>.vercel.app/` | redirects to `client_login.php` |
| Client portal | `/client_login.php`, `/register.php` | login/registration pages render, new registrations are stored |
| Admin panel | `/admin/` → `/admin/login.php` | dark admin UI with working stylesheet |
| Stylesheet | `/admin/assets/css/style.css` | returns the CSS file (served by `api/admin/assets/css.php`) |

Seeded admin account: username `soulprint`, password `SoulprintMP`.
**Change it before sharing the URL** — the password is in a public GitHub
repository:

```sh
DATABASE_URL="postgresql://..." php tools/reset_admin_password.php 'A-New-Password' soulprint
```

If `/health.php` reports `database: failed`, the `DATABASE_URL` value is wrong,
the password was not URL-encoded, or the environment variable was added only to
Preview and not to Production — fix it under **Settings → Environment
Variables**, then **Deployments → ⋯ → Redeploy**.

## How the Vercel setup works

`vercel.json` does three things:

1. Runs every PHP file under `api/` (including `api/admin/**`) as a serverless
   function with the `vercel-php` runtime.
2. Maps the original URL layout onto those functions, so the existing pages and
   links keep working unchanged:
   `/` → `api/index.php`, `/client_login.php` → `api/client_login.php`,
   `/admin/dashboard.php` → `api/admin/dashboard.php`, and so on.
3. Serves the admin stylesheet through `api/admin/assets/css.php`, because
   static files next to PHP functions are not served on Vercel.

Each function bundles the whole project at `/var/task/user`, which is why the
application's `require __DIR__ . '/...'` calls keep working.

## Limitations to know about

- **PHP sessions are per-instance.** `session.save_path` is `/tmp`, so a
  cold start or another function instance forgets the admin session and you may
  have to log in again. The client portal is not affected: it authenticates
  with the `phodio_sessions` table and the `phodio_session` cookie. Ask if you
  want the admin login moved to the same database-backed scheme.
- **No persistent file uploads.** There is no upload handler in the code today;
  profile images fall back to the client's initial, which is the intended
  behaviour on Vercel. Move uploads to Supabase Storage if you need them.
- **No email or background jobs.** Everything runs inside the request
  (30s `maxDuration`, 512 MB memory).
- `tools/` and the `*.sql` files are excluded from the deployment by
  `.vercelignore`; they stay in the repository only for local work.
