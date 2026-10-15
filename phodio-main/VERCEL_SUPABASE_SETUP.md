# Phodio — Vercel + Supabase deployment guide

The app is a PHP 8 application. On Vercel it runs through the community
`vercel-php` runtime (`vercel-php@0.9.0` = PHP 8.5) and stores its data in
Supabase PostgreSQL through `DATABASE_URL`.

- Repository: <https://github.com/23-44078/Phodio>
- Application folder: **`phodio-main/`** ← the Vercel **Root Directory**

---

# If the deployment is already broken, read this first

Two settings in the Vercel dashboard cause almost every failure. Neither is a
code problem, and both take about twenty seconds to fix.

## 1. "Root Directory" — the one that is almost certainly wrong

Vercel builds only what sits under the **Root Directory**. The PHP application
lives in `phodio-main/api/`, so:

| Root Directory | Result |
| --- | --- |
| `phodio-main` | ✅ correct — `phodio-main/vercel.json` is read and every `api/**/*.php` file becomes a function |
| `phodio-main/api` | ✅ **also works now** — `phodio-main/api/vercel.json` is read instead (it is the same config with the `api/` prefix removed) |
| repo root (`.`) | ❌ nothing works — no `vercel.json` is found at all |

> The project created from the GitHub integration reported
> `rootDirectory: "phodio-main/api"`. That is why the site showed raw PHP
> source instead of running: with that root, no `vercel.json` was found, so
> **no PHP functions were built** and every `.php` file was uploaded as a
> static text file.

**Fix:** Project → **Settings → Git → Root Directory** → set it to
`phodio-main` → **Save** → **Deployments → ⋯ → Redeploy**.

You do not have to change it if you do not want to: the `phodio-main/api`
variant of the config now works too, so a plain **Redeploy** is enough.

## 2. "Protected Deployment" / Vercel login page

If the preview URL shows a *Vercel* login screen instead of the app, the
project has **Deployment Protection** turned on.

**Fix:** Project → **Settings → Deployment Protection → Vercel
Authentication** → set to **Disabled** (or *Only Preview Deployments* if you
want previews private but the production domain public).

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

### Reading `/health.php`

`/health.php` is a diagnostic, not a page. It never includes
`config/database.php` (which exits with a bare message), so it always prints
JSON and tells you which of these went wrong:

| `database` value | Meaning | Fix |
| --- | --- | --- |
| `connected` | everything works | — |
| `not configured` | `DATABASE_URL` is empty | Settings → Environment Variables, add it to **Production** (and Preview), then **Redeploy** |
| `misconfigured` | the string is not a valid PostgreSQL URI | copy the **Session pooler** string from Supabase → Connect; URL-encode `@` as `%40` and `#` as `%23` |
| `driver missing` | `pdo_pgsql` is not loaded | `vercel.json` was not picked up — check the Root Directory |
| `failed` | the host was reached but the login or database name was rejected | wrong password, wrong project ref, or the variable was added only to Preview |

It also reports `phodio_sessions`. If that is `false`, run
`api/db/migrations/20261015_phodio_sessions.sql` in the Supabase SQL editor —
without that table the client portal cannot stay logged in.

## How the Vercel setup works

There are **two** `vercel.json` files, and Vercel reads whichever one sits at
its Root Directory:

- `phodio-main/vercel.json` — used when the Root Directory is `phodio-main`.
  Functions are `api/*.php` + `api/**/*.php`; routes map `/` → `api/index.php`,
  `/admin/dashboard.php` → `api/admin/dashboard.php`, and so on.
- `phodio-main/api/vercel.json` — used when the Root Directory is
  `phodio-main/api`. It is the same configuration with the `api/` prefix
  stripped from every path, so `/` → `/index.php`.

Both do three things:

1. Run every PHP file as a serverless function with the `vercel-php` runtime
   (`vercel-php@0.9.0` = PHP 8.5, 512 MB, 30 s).
2. Map the original URL layout onto those functions, so existing pages and
   links keep working unchanged.
3. Serve the admin stylesheet through `api/admin/assets/css.php`, because
   static files sitting next to PHP functions are not served on Vercel.

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
- `tools/`, the `*.sql` files and the session debug helper are excluded from the
  deployment by `.vercelignore` (one at the project root, one inside `api/`);
  they stay in the repository only for local work.
