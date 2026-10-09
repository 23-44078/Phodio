# Phodio

Photography studio management system (client booking + admin panel), built in plain PHP and adapted for **Vercel + Supabase PostgreSQL**.

## Deploy to Vercel

1. Create a Supabase project and run `supabase_schema.sql` in its SQL Editor.
2. In Vercel, import this Git repository.
   - **Leave the Root Directory setting at the repository root (`./`)** — the app lives at the root of this repo (`api/` + `vercel.json`), so no other setting is needed.
3. Add the environment variable `DATABASE_URL` with your Supabase **Session pooler** PostgreSQL URI (see `VERCEL_SUPABASE_SETUP.md`).
4. Deploy.

- Public site: `https://<your-project>.vercel.app/`
- Admin panel: `https://<your-project>.vercel.app/admin/`

## Layout

- `api/` — the PHP application (pages, admin panel, includes, config).
- `api/router.php` — front controller; every `.php` URL is served through this single Vercel function (keeps PHP sessions working and prevents raw PHP source downloads).
- `vercel.json` — Vercel configuration (PHP runtime + routes).
- `supabase_schema.sql` — PostgreSQL schema for Supabase.
- `studio_mgmt.sql` — original MySQL/MariaDB schema (reference only).

More details in `README_VERCEL.md` and `VERCEL_SUPABASE_SETUP.md`.
