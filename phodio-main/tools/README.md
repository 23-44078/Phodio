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

> The previous `api/admin/repair.php`, `api/admin/test_password.php` and
> `api/session_test.php` scripts were removed from the web root: `repair.php`
> allowed anybody who opened `/admin/repair.php` to wipe the `admin` table and
> sign in with a hard-coded password, `test_password.php` printed the stored
> password hash, and `session_test.php` dumped the whole `$_SESSION` array.
> Use the CLI tools in this folder instead.

## Inspect a session (local debugging only)

`session_test.php` reads `$_SESSION`, so it has to sit next to the app to be
useful. Copy it back temporarily while debugging locally, then delete it again
- it must never be deployed:

```sh
cp tools/session_test.php api/session_test.php
php -S localhost:8000 -t api        # open http://localhost:8000/session_test.php
rm api/session_test.php
```
