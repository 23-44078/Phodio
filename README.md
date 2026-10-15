# Phodio (Soulprint studio management)

A PHP 8 + PostgreSQL booking and studio-management system:

- **Client portal** — registration, login, package recommendations, booking
  requests, appointment history and status tracking.
- **Admin panel** — dashboard with revenue/expense/profit figures, bookings
  calendar, expense tracking, liabilities and a daily performance tracker.

The application lives in [`phodio-main/`](phodio-main). It is ready to run on
Vercel with a Supabase PostgreSQL database.

[![Deploy with Vercel](https://vercel.com/button)](https://vercel.com/new/clone?repository-url=https%3A%2F%2Fgithub.com%2F23-44078%2FPhodio&root-directory=phodio-main%2Fapi&project-name=phodio&repository-name=phodio&env=DATABASE_URL&envDescription=Supabase%20Session%20pooler%20PostgreSQL%20URI&envLink=https%3A%2F%2Fgithub.com%2F23-44078%2FPhodio%2Fblob%2Fmain%2Fphodio-main%2FVERCEL_SUPABASE_SETUP.md)

## Deploy

1. Run [`phodio-main/supabase_schema.sql`](phodio-main/supabase_schema.sql) in
   the Supabase SQL Editor.
2. Import the repository at <https://vercel.com/new>.
3. Set the Vercel **Root Directory** to `phodio-main/api` — the folder that
   contains the inner `api/` directory. This is the only value that works.
4. Add the environment variable `DATABASE_URL` (Supabase *Session pooler* URI).
5. Deploy.

Full walkthrough, smoke tests and limitations:
[`phodio-main/VERCEL_SUPABASE_SETUP.md`](phodio-main/VERCEL_SUPABASE_SETUP.md).

### If the deployment already looks broken

- **Seeing raw PHP source instead of the app?** That was the old symptom: with
  Root Directory `phodio-main/api` and no `vercel.json` at that root, no PHP
  functions were built, so every `.php` file was served as static text. The
  application now lives in `phodio-main/api/api/` with `vercel.json` next to
  it, so a plain **Redeploy** fixes it. Nothing to change.
- **Seeing a Vercel login screen?** **Settings → Deployment Protection →
  Vercel Authentication** is on. Set it to *Disabled*.

Then open `/health.php`: it prints JSON saying exactly what is wrong.
