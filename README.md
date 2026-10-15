# Phodio (Soulprint studio management)

A PHP 8 + PostgreSQL booking and studio-management system:

- **Client portal** — registration, login, package recommendations, booking
  requests, appointment history and status tracking.
- **Admin panel** — dashboard with revenue/expense/profit figures, bookings
  calendar, expense tracking, liabilities and a daily performance tracker.

The application lives in [`phodio-main/`](phodio-main). It is ready to run on
Vercel with a Supabase PostgreSQL database.

[![Deploy with Vercel](https://vercel.com/button)](https://vercel.com/new/clone?repository-url=https%3A%2F%2Fgithub.com%2F23-44078%2FPhodio&root-directory=phodio-main&project-name=phodio&repository-name=phodio&env=DATABASE_URL&envDescription=Supabase%20Session%20pooler%20PostgreSQL%20URI&envLink=https%3A%2F%2Fgithub.com%2F23-44078%2FPhodio%2Fblob%2Fmain%2Fphodio-main%2FVERCEL_SUPABASE_SETUP.md)

## Deploy

1. Run [`phodio-main/supabase_schema.sql`](phodio-main/supabase_schema.sql) in
   the Supabase SQL Editor.
2. Import the repository at <https://vercel.com/new>.
3. Set the Vercel **Root Directory** to `phodio-main`.
4. Add the environment variable `DATABASE_URL` (Supabase *Session pooler* URI).
5. Deploy.

Full walkthrough, smoke tests and limitations:
[`phodio-main/VERCEL_SUPABASE_SETUP.md`](phodio-main/VERCEL_SUPABASE_SETUP.md).
