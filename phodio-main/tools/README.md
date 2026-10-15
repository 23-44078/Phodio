# tools

Command line helpers. Nothing in this folder is part of the deployed
application - it lives outside `api/`, so Vercel never runs or serves it.

## Reset an admin password

```sh
DATABASE_URL="postgresql://postgres.<ref>:<password>@aws-0-<region>.pooler.supabase.com:5432/postgres" \
  php tools/reset_admin_password.php 'YourNewPassword' soulprint
```

The password is hashed with bcrypt before it is stored, exactly like the
admin login page expects.

> The previous `api/admin/repair.php` and `api/admin/test_password.php` scripts
> were removed from the web root: `repair.php` allowed anybody who opened
> `/admin/repair.php` to wipe the `admin` table and sign in with a
> hard-coded password, and `test_password.php` printed the stored password
> hash to any visitor. Use this CLI tool instead.
