# Phodio — Vercel / Supabase version

The original PHP application adapted for Vercel serverless functions and
Supabase PostgreSQL. The UI, pages and business logic are unchanged.

[![Deploy with Vercel](https://vercel.com/button)](https://vercel.com/new/clone?repository-url=https%3A%2F%2Fgithub.com%2F23-44078%2FPhodio&root-directory=phodio-main%2Fapi&project-name=phodio&repository-name=phodio&env=DATABASE_URL&envDescription=Supabase%20Session%20pooler%20PostgreSQL%20URI&envLink=https%3A%2F%2Fgithub.com%2F23-44078%2FPhodio%2Fblob%2Fmain%2Fphodio-main%2FVERCEL_SUPABASE_SETUP.md)

**Read [VERCEL_SUPABASE_SETUP.md](VERCEL_SUPABASE_SETUP.md) before deploying.**

| File | Purpose |
| --- | --- |
| `api/vercel.json` | Declares **one** function (`api/router.php`) and routes everything to it. **Root Directory must be `phodio-main/api`**, and the function count must stay at 1 — the Hobby plan allows only 12 |
| `api/api/router.php` | Front controller: maps the requested URL to the matching PHP script. This is what keeps the deployment to a single function |
| `supabase_schema.sql` | Full PostgreSQL schema and seed data for Supabase |
| `api/api/db/migrations/` | Extra SQL to run against a database created earlier |
| `api/api/config/database.php` | PDO/PostgreSQL connection read from `DATABASE_URL`, with a `mysqli`-compatible shim so the existing queries keep working |
| `api/php.ini` | PHP settings used inside every Vercel function (`session.save_path=/tmp`, errors off) |
| `.vercelignore` | Keeps SQL dumps, docs and `tools/` out of the deployment |
| `tools/` | Local CLI helpers, never deployed or reachable over HTTP |

**If the deployment looks broken, check two dashboard settings first:**
**Root Directory** (Settings → Git — must be `phodio-main/api`) and **Deployment
Protection** (Settings) — the setup guide opens with both.

The only PHP version with the required `pdo_pgsql` extension is the
`vercel-php` community runtime; see the setup guide for the deployment steps.
