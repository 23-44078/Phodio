# Phodio — Vercel/Supabase version

This is the existing Phodio PHP application adapted only where necessary for Vercel + Supabase PostgreSQL.

- Existing UI/pages/business logic preserved.
- MySQL/MariaDB connection files removed.
- Supabase PostgreSQL connection is read from `DATABASE_URL`.
- PostgreSQL schema/data is in `supabase_schema.sql`.
- Vercel configuration is in `vercel.json` (at the repository root — deploy with Root Directory set to the repository root, the default).
- Deployment instructions are in `VERCEL_SUPABASE_SETUP.md`.

## How it runs on Vercel

The Vercel PHP runtime (`vercel-php`) normally builds one serverless function per PHP file. Phodio instead serves **every `.php` URL through a single front controller, `api/router.php`**:

- Pages always execute — a `.php` file can never be served as a raw static download.
- PHP file sessions keep working, because all pages run inside the same function/PHP process.
- `vercel.json` routes clean URLs (`/client_login.php`, `/admin/…`) to the router; the admin stylesheet is served statically from `/admin/assets/…`.
